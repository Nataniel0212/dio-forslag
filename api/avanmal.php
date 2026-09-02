<?php
declare(strict_types=1);

/* ============================================================
   Avanmälan via länken i mejlen. Adressen ligger kvar i databasen men
   markeras som avanmäld — raderar vi raden skulle samma adress kunna
   hamna på listan igen vid nästa utskick, och då är avanmälan värdelös.
   ============================================================ */

require __DIR__ . '/../inc/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$avanmald = false;

if (preg_match('/^[a-f0-9]{32}$/', $token) === 1) {
    $sats = db()->prepare(
        'UPDATE prenumeranter SET status = ?, avanmald = ? WHERE token = ? AND status = ?'
    );
    $sats->execute(['avanmald', nu(), $token, 'aktiv']);

    /* Redan avanmäld räknas också som lyckat: den som klickar två gånger
       ska se samma lugna besked, inte ett fel. */
    $koll = db()->prepare('SELECT COUNT(*) FROM prenumeranter WHERE token = ?');
    $koll->execute([$token]);
    $avanmald = ((int) $koll->fetchColumn()) === 1;
}

/* Gmails ettklicksavanmälan postar hit i bakgrunden och visar aldrig
   någon sida — den vill bara ha ett kvitto. */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    svara_json($avanmald ? 200 : 404, ['ok' => $avanmald]);
}

http_response_code($avanmald ? 200 : 404);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="sv">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Avanmälan | DIO Södermalm</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400&family=Jost:wght@200;300;400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <main id="top">
    <section class="section signup-section is-in" style="min-height:100svh;display:grid;place-content:center">
      <div class="signup-wrap">
        <?php if ($avanmald): ?>
          <h2>Du är avanmäld</h2>
          <p class="sub">Vi hör inte av oss mer. Ångrar du dig är du välkommen
             tillbaka till <a href="../index.html">startsidan</a>.</p>
        <?php else: ?>
          <h2>Länken gäller inte</h2>
          <p class="sub">Länken är gammal eller ofullständig. Mejla
             <a href="mailto:<?= h(AVSANDARE_EPOST) ?>"><?= h(AVSANDARE_EPOST) ?></a>
             så tar vi bort adressen för hand.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>
