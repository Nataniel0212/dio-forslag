<?php
declare(strict_types=1);

/* ============================================================
   Gemensam start för allt som körs på servern.
   Varje PHP-fil under /api och /admin börjar med att inkludera den här.
   ============================================================ */

/* Filerna i inc/ ska bara nås via include. .htaccess blockerar katalogen,
   och konstanten är hängslet till det bältet: skulle någon peka
   webbservern rakt på en fil här avslutas den direkt. */
define('DIO', true);

/* Fel loggas, visas aldrig. Ett PHP-fel i klartext avslöjar sökvägar och
   i värsta fall databaslösenordet. */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('Europe/Stockholm');

$konfig = __DIR__ . '/config.php';
if (!is_file($konfig)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Sajten är inte konfigurerad än: inc/config.php saknas.\n"
       . "Kopiera inc/config.example.php till inc/config.php och fyll i uppgifterna.");
}
require $konfig;
require __DIR__ . '/db.php';
require __DIR__ . '/mail.php';

/** Tidsstämpel i det format databasen lagrar. */
function nu(): string
{
    return date('Y-m-d H:i:s');
}

/** Besökarens IP. Vi litar bara på REMOTE_ADDR — X-Forwarded-For går att
 *  sätta till vad som helst av den som skickar anropet, och används den
 *  till spärrlistor blir spärren meningslös. */
function klient_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? substr($ip, 0, 45) : '';
}

/** Skriver en rad i händelseloggen. Loggen används både för att spärra
 *  översvämning av formuläret och för att kunna se vad som hänt. */
function logga(string $typ, string $detalj = ''): void
{
    $sats = db()->prepare(
        'INSERT INTO handelser (ip, typ, detalj, skapad) VALUES (?, ?, ?, ?)'
    );
    $sats->execute([klient_ip(), $typ, mb_substr($detalj, 0, 255), nu()]);
}

/** Hur många händelser av en typ som kommit från samma IP den senaste
 *  tiden. Grunden för spärrarna i formuläret och admininloggningen. */
function antal_handelser(string $typ, int $sekunder): int
{
    $sats = db()->prepare(
        'SELECT COUNT(*) FROM handelser WHERE ip = ? AND typ = ? AND skapad > ?'
    );
    $sats->execute([klient_ip(), $typ, date('Y-m-d H:i:s', time() - $sekunder)]);
    return (int) $sats->fetchColumn();
}

/** Svar i JSON. Formuläret på startsidan pratar JSON med servern. */
function svara_json(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Skydd mot inklistrad HTML i utskrifter. */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
