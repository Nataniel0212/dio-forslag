<?php
declare(strict_types=1);

/* ============================================================
   Tar emot en adress från formuläret på startsidan.

   Svarar med JSON till sidans JavaScript, och med en vanlig omdirigering
   tillbaka till sidan om formuläret postats utan JavaScript. Båda vägarna
   fungerar — formuläret har action och method satta i HTML.
   ============================================================ */

require __DIR__ . '/../inc/bootstrap.php';

const SAMTYCKESTEXT = 'Meddela mig när ni öppnar (startsidan, dio-anmälan)';
const MAX_PER_TIMME = 5;

/* Vill anroparen ha JSON? Sidans fetch säger det uttryckligen; ett vanligt
   formulär utan JavaScript gör det inte och ska ha en sida tillbaka. */
$vill_ha_json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    svar(405, false, 'Fel anropsmetod.', $vill_ha_json);
}

/* Formuläret kan skicka antingen vanliga fält eller JSON. */
$in = $_POST;
if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $rakost = json_decode((string) file_get_contents('php://input'), true);
    $in = is_array($rakost) ? $rakost : [];
    $vill_ha_json = true;
}

/* Honungsfällan: fältet är dolt för människor men fylls i av enkla
   spamrobotar. Vi svarar som vanligt så att roboten inte lär sig något,
   men sparar ingenting. */
if (trim((string) ($in['webbplats'] ?? '')) !== '') {
    logga('spam');
    svar(200, true, 'Tack! Vi hör av oss när vi öppnar.', $vill_ha_json);
}

$epost = strtolower(trim((string) ($in['email'] ?? '')));

if ($epost === '' || mb_strlen($epost) > 191 || !filter_var($epost, FILTER_VALIDATE_EMAIL)) {
    svar(422, false, 'Kontrollera mejladressen.', $vill_ha_json);
}

/* Spärr mot översvämning: samma IP får lämna ett fåtal adresser i timmen.
   Räcker gott för en riktig besökare, stoppar den som skriptar. */
if (antal_handelser('anmalan', 3600) >= MAX_PER_TIMME) {
    logga('spärrad', $epost);
    svar(429, false, 'För många försök just nu. Prova igen om en stund.', $vill_ha_json);
}

try {
    $pdo = db();

    $sats = $pdo->prepare('SELECT id, status FROM prenumeranter WHERE epost = ?');
    $sats->execute([$epost]);
    $rad = $sats->fetch();

    if ($rad === false) {
        $pdo->prepare(
            'INSERT INTO prenumeranter
                (epost, status, token, kalla, samtyckestext, ip, webblasare, skapad)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $epost,
            'aktiv',
            ny_token(),
            'startsida',
            SAMTYCKESTEXT,
            klient_ip(),
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            nu(),
        ]);
    } elseif ($rad['status'] !== 'aktiv') {
        /* Någon som avanmält sig tidigare och skriver upp sig igen ska
           så klart hamna på listan på nytt. */
        $pdo->prepare(
            'UPDATE prenumeranter SET status = ?, avanmald = NULL, skapad = ? WHERE id = ?'
        )->execute(['aktiv', nu(), $rad['id']]);
    }
    /* Fanns adressen redan som aktiv gör vi ingenting — men svaret är
       detsamma. Vem som helst ska inte kunna använda formuläret för att
       lista ut vilka adresser som står på listan. */

    logga('anmalan', $epost);
} catch (Throwable $fel) {
    error_log('DIO anmälan: ' . $fel->getMessage());
    svar(500, false, 'Något gick fel hos oss. Försök igen om en stund.', $vill_ha_json);
}

svar(200, true, 'Tack! Vi hör av oss när vi öppnar.', $vill_ha_json);

/** Svarar antingen i JSON eller genom att skicka besökaren tillbaka. */
function svar(int $status, bool $ok, string $meddelande, bool $json): void
{
    if ($json) {
        svara_json($status, ['ok' => $ok, 'meddelande' => $meddelande]);
    }

    /* Utan JavaScript får besökaren en riktig sida tillbaka. Alltid 303
       och aldrig en felkod här: felkoden skulle visa webbhotellets egen
       felsida i stället för vår. Utfallet står i adressen. */
    http_response_code(303);
    header('Location: ../index.html?anmalan=' . ($ok ? 'ok' : 'fel') . '#anmalan');
    exit;
}
