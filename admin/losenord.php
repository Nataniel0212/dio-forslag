<?php
declare(strict_types=1);

/* ============================================================
   Engångsverktyg: gör om ett lösenord till den hash som ska stå i
   inc/config.php.

   Sidan stänger sig själv så fort ADMIN_HASH är ifylld — annars hade den
   legat kvar som en öppen dörr på sajten. Radera filen när du är klar.
   ============================================================ */

require __DIR__ . '/../inc/bootstrap.php';

if (ADMIN_HASH !== '') {
    http_response_code(410);
    exit('Lösenordet är redan satt. Radera den här filen från servern.');
}

$hash = '';
$fel  = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $losen = (string) ($_POST['losenord'] ?? '');
    if (mb_strlen($losen) < 12) {
        $fel = 'För kort — ta minst 12 tecken.';
    } else {
        $hash = password_hash($losen, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sätt lösenord | DIO admin</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <main class="ruta">
    <h1>Sätt admin-lösenord</h1>
    <p class="hjalp">Välj ett lösenord, klistra in raden du får i
       <code>inc/config.php</code> och radera sedan den här filen.</p>
    <?php if ($fel !== ''): ?><p class="fel"><?= h($fel) ?></p><?php endif; ?>
    <form method="post">
      <label for="losenord">Lösenord (minst 12 tecken)</label>
      <input id="losenord" name="losenord" type="password" autofocus required>
      <button type="submit">Skapa hash</button>
    </form>
    <?php if ($hash !== ''): ?>
      <p class="hjalp">Rad att klistra in:</p>
      <pre class="hash">const ADMIN_HASH = '<?= h($hash) ?>';</pre>
    <?php endif; ?>
  </main>
</body>
</html>
