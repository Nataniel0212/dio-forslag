<?php
declare(strict_types=1);
defined('DIO') || exit;

/* ============================================================
   Databasen. Tre tabeller:
     prenumeranter  — adresserna och deras status
     utskick        — ett mejl som gått (eller ska gå) till listan
     handelser      — enkel logg, används också till spärrarna
   ============================================================ */

/** Öppnar anslutningen en gång per anrop och återanvänder den. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO(DB_DSN, DB_USER, DB_LOSEN, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Riktiga prepared statements, inte emulerade: det är det som gör
        // att inskickad text aldrig kan bli SQL.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    skapa_tabeller($pdo);
    return $pdo;
}

/** Lägger upp tabellerna om de saknas.
 *
 *  Körs vid varje anslutning. Det kostar en handfull snabba DDL-anrop som
 *  inte gör något när tabellerna redan finns, och i utbyte blir
 *  installationen ett enda steg: ladda upp, fyll i config, klart. På en
 *  sida med den här trafiken är det ett bra byte. */
function skapa_tabeller(PDO $pdo): void
{
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

    // Nyckel respektive tidsstämpel skrivs olika i SQLite och MySQL.
    // SQLite används bara när sidan körs lokalt för test.
    $id    = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    $slut  = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    /* epost är 191 tecken och inte 254 som standarden tillåter: ett unikt
       index på utf8mb4 får inte vara bredare än 767 byte i äldre MySQL,
       och 191 × 4 ryms precis. Längre adresser finns i praktiken inte. */
    $pdo->exec("CREATE TABLE IF NOT EXISTS prenumeranter (
        id $id,
        epost VARCHAR(191) NOT NULL UNIQUE,
        status VARCHAR(20) NOT NULL DEFAULT 'aktiv',
        token VARCHAR(32) NOT NULL,
        kalla VARCHAR(50) NOT NULL DEFAULT 'startsida',
        samtyckestext VARCHAR(255) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        webblasare VARCHAR(255) NOT NULL DEFAULT '',
        skapad DATETIME NOT NULL,
        avanmald DATETIME NULL
    )$slut");

    $pdo->exec("CREATE TABLE IF NOT EXISTS utskick (
        id $id,
        amne VARCHAR(255) NOT NULL,
        text TEXT NOT NULL,
        skapad DATETIME NOT NULL,
        klart DATETIME NULL
    )$slut");

    /* Loggen gör utskicken återupptagbara: dör anropet mitt i en omgång
       vet vi ändå exakt vilka som redan fått mejlet, så ingen får det två
       gånger när vi kör vidare. */
    $index_logg = $sqlite ? '' : ',
        UNIQUE KEY ix_utskick_mottagare (utskick_id, prenumerant_id)';
    $pdo->exec("CREATE TABLE IF NOT EXISTS utskick_logg (
        id $id,
        utskick_id INT UNSIGNED NOT NULL,
        prenumerant_id INT UNSIGNED NOT NULL,
        ok TINYINT NOT NULL DEFAULT 1,
        skickad DATETIME NOT NULL$index_logg
    )$slut");

    $index_handelser = $sqlite ? '' : ',
        KEY ix_handelser_ip (ip, typ, skapad)';
    $pdo->exec("CREATE TABLE IF NOT EXISTS handelser (
        id $id,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        typ VARCHAR(30) NOT NULL,
        detalj VARCHAR(255) NOT NULL DEFAULT '',
        skapad DATETIME NOT NULL$index_handelser
    )$slut");

    /* SQLite kan inte deklarera index inne i CREATE TABLE — de läggs
       till efteråt. MySQL har redan fått sina ovan. */
    if ($sqlite) {
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS ix_utskick_mottagare
                    ON utskick_logg (utskick_id, prenumerant_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS ix_handelser_ip
                    ON handelser (ip, typ, skapad)');
    }
}

/** Slumpad nyckel till avanmälningslänken. Måste vara omöjlig att gissa —
 *  annars kan vem som helst avanmäla andras adresser. */
function ny_token(): string
{
    return bin2hex(random_bytes(16));
}
