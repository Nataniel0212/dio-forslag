<?php
declare(strict_types=1);

/* ============================================================
   Adminsidan: logga in, se hur många som anmält sig, exportera listan
   och skicka öppningsmejlet.
   ============================================================ */

require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/session.php';

if (isset($_GET['ut'])) {
    logga_ut();
}

$fel = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['losenord'])) {
    // Fem misslyckade försök i kvarten, sedan paus. Utan spärr går ett
    // lösenord att gissa sig till med tillräckligt många försök.
    if (antal_handelser('inloggning_fel', 900) >= 5) {
        $fel = 'För många försök. Vänta en kvart och prova igen.';
    } elseif (ADMIN_HASH === '') {
        $fel = 'Inget lösenord är satt än. Se admin/losenord.php.';
    } elseif (password_verify((string) $_POST['losenord'], ADMIN_HASH)) {
        logga_in();
        header('Location: index.php');
        exit;
    } else {
        logga('inloggning_fel');
        $fel = 'Fel lösenord.';
    }
}

$inloggad = ar_inloggad();

if ($inloggad) {
    $pdo = db();
    $antal = static function (string $villkor, array $varden = []) use ($pdo): int {
        $sats = $pdo->prepare('SELECT COUNT(*) FROM prenumeranter WHERE ' . $villkor);
        $sats->execute($varden);
        return (int) $sats->fetchColumn();
    };

    $aktiva     = $antal("status = 'aktiv'");
    $avanmalda  = $antal("status = 'avanmald'");
    $idag       = $antal("status = 'aktiv' AND skapad >= ?", [date('Y-m-d 00:00:00')]);
    $veckan     = $antal("status = 'aktiv' AND skapad >= ?", [date('Y-m-d H:i:s', time() - 7 * 86400)]);

    $senaste = $pdo->query(
        "SELECT epost, status, skapad FROM prenumeranter ORDER BY skapad DESC, id DESC LIMIT 50"
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Anmälningar | DIO Södermalm</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="admin.css">
</head>
<body>

<?php if (!$inloggad): ?>

  <main class="ruta">
    <h1>DIO — admin</h1>
    <?php if ($fel !== ''): ?><p class="fel"><?= h($fel) ?></p><?php endif; ?>
    <form method="post">
      <label for="losenord">Lösenord</label>
      <input id="losenord" name="losenord" type="password" autocomplete="current-password" autofocus required>
      <button type="submit">Logga in</button>
    </form>
  </main>

<?php else: ?>

  <header class="topp">
    <h1>Anmälningar</h1>
    <nav>
      <a href="utskick.php">Skicka öppningsmejl</a>
      <a href="export.php">Ladda ner CSV</a>
      <a href="?ut=1">Logga ut</a>
    </nav>
  </header>

  <main>
    <section class="tal">
      <div class="tal-kort">
        <span class="siffra"><?= $aktiva ?></span>
        <span class="etikett">på listan</span>
      </div>
      <div class="tal-kort">
        <span class="siffra"><?= $veckan ?></span>
        <span class="etikett">senaste 7 dagarna</span>
      </div>
      <div class="tal-kort">
        <span class="siffra"><?= $idag ?></span>
        <span class="etikett">i dag</span>
      </div>
      <div class="tal-kort">
        <span class="siffra"><?= $avanmalda ?></span>
        <span class="etikett">avanmälda</span>
      </div>
    </section>

    <h2>Senaste 50</h2>
    <?php if ($senaste === []): ?>
      <p class="tom">Ingen har anmält sig än.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Mejladress</th><th>Status</th><th>Anmäld</th></tr></thead>
        <tbody>
          <?php foreach ($senaste as $rad): ?>
            <tr>
              <td><?= h($rad['epost']) ?></td>
              <td><?= $rad['status'] === 'aktiv' ? 'Aktiv' : 'Avanmäld' ?></td>
              <td><?= h($rad['skapad']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </main>

<?php endif; ?>

</body>
</html>
