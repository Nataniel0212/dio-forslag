<?php
declare(strict_types=1);

/* Laddar ner listan som CSV — att öppna i Excel eller att klistra in i
   ett utskicksverktyg om ni byter till ett sådant längre fram. */

require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/session.php';
kraev_inloggning();

$rader = db()->query(
    "SELECT epost, skapad FROM prenumeranter WHERE status = 'aktiv' ORDER BY skapad"
)->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="dio-anmalningar-' . date('Y-m-d') . '.csv"');

$ut = fopen('php://output', 'w');
/* Byteordningsmärket får Excel att förstå att filen är UTF-8. Utan det
   blir å, ä och ö obegripliga när Alexander öppnar den. */
fwrite($ut, "\xEF\xBB\xBF");
fputcsv($ut, ['mejladress', 'anmald'], ';');
foreach ($rader as $rad) {
    fputcsv($ut, [$rad['epost'], $rad['skapad']], ';');
}
fclose($ut);
