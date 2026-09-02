<?php
declare(strict_types=1);

/* ============================================================
   Öppningsmejlet.

   Skickas i omgångar om 20 åt gången. Två skäl: webbhotell stryper
   antalet mejl per minut, och ett PHP-anrop som försöker skicka
   hundratals brev hinner slå i tidsgränsen och dö halvvägs. Varje
   skickat brev loggas, så ett avbrutet utskick går att fortsätta utan
   att någon får brevet två gånger.
   ============================================================ */

require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/session.php';
kraev_inloggning();

const PER_OMGANG = 20;

$pdo = db();
$atgard = (string) ($_POST['atgard'] ?? $_GET['atgard'] ?? '');

/* ---------- Skicka en omgång (anropas av sidans skript) ---------- */
if ($atgard === 'omgang') {
    $utskick_id = (int) ($_POST['utskick'] ?? 0);
    $utskick = hamta_utskick($pdo, $utskick_id);
    if ($utskick === null) {
        svara_json(404, ['ok' => false, 'meddelande' => 'Utskicket finns inte.']);
    }

    $mottagare = $pdo->prepare(
        "SELECT p.id, p.epost, p.token
           FROM prenumeranter p
           LEFT JOIN utskick_logg l
                  ON l.prenumerant_id = p.id AND l.utskick_id = ?
          WHERE p.status = 'aktiv' AND l.id IS NULL
          ORDER BY p.id
          LIMIT " . PER_OMGANG
    );
    $mottagare->execute([$utskick_id]);

    $skickade = 0;
    $misslyckade = 0;
    foreach ($mottagare->fetchAll() as $rad) {
        $lank = avanmal_lank($rad['token']);
        $ok = skicka_mail(
            $rad['epost'],
            $utskick['amne'],
            $utskick['text'] . "\n\n--\nVill du inte ha fler mejl från oss? " . $lank,
            $lank
        );

        $pdo->prepare(
            'INSERT INTO utskick_logg (utskick_id, prenumerant_id, ok, skickad)
             VALUES (?, ?, ?, ?)'
        )->execute([$utskick_id, $rad['id'], $ok ? 1 : 0, nu()]);

        if ($ok) {
            $skickade++;
        } else {
            $misslyckade++;
        }
    }

    $kvar = kvar_att_skicka($pdo, $utskick_id);
    if ($kvar === 0) {
        $pdo->prepare('UPDATE utskick SET klart = ? WHERE id = ? AND klart IS NULL')
            ->execute([nu(), $utskick_id]);
    }

    svara_json(200, [
        'ok' => true,
        'skickade' => $skickade,
        'misslyckade' => $misslyckade,
        'kvar' => $kvar,
    ]);
}

$amne = trim((string) ($_POST['amne'] ?? 'Nu öppnar DIO Södermalm'));
$text = trim((string) ($_POST['text'] ?? standardtext()));
$meddelande = '';
$fel = '';
$pagaende = null;

/* ---------- Testmejl ---------- */
if ($atgard === 'test') {
    $till = trim((string) ($_POST['testadress'] ?? ''));
    if (!filter_var($till, FILTER_VALIDATE_EMAIL)) {
        $fel = 'Skriv en giltig adress att testa mot.';
    } elseif (skicka_mail($till, $amne, $text)) {
        $meddelande = 'Testmejl skickat till ' . $till
                    . '. Kolla att det kom fram och ser rätt ut.';
    } else {
        $fel = 'Servern kunde inte skicka mejlet. Se avsnittet om mejl i README.';
    }
}

/* ---------- Starta utskicket ---------- */
if ($atgard === 'starta') {
    if ($amne === '' || $text === '') {
        $fel = 'Ämne och text måste vara ifyllda.';
    } elseif (($_POST['bekraftat'] ?? '') !== 'ja') {
        $fel = 'Kryssa i rutan först — ett utskick går inte att ta tillbaka.';
    } else {
        $pdo->prepare('INSERT INTO utskick (amne, text, skapad) VALUES (?, ?, ?)')
            ->execute([$amne, $text, nu()]);
        $pagaende = (int) $pdo->lastInsertId();
    }
}

/* Ett utskick som avbrutits mitt i plockas upp igen nästa gång sidan
   öppnas — därför är den här kontrollen viktigare än den ser ut. */
if ($pagaende === null) {
    $rad = $pdo->query('SELECT id FROM utskick WHERE klart IS NULL ORDER BY id DESC LIMIT 1')->fetch();
    if ($rad !== false && kvar_att_skicka($pdo, (int) $rad['id']) > 0) {
        $pagaende = (int) $rad['id'];
    }
}

$antal_aktiva = (int) $pdo->query("SELECT COUNT(*) FROM prenumeranter WHERE status = 'aktiv'")->fetchColumn();

function hamta_utskick(PDO $pdo, int $id): ?array
{
    $sats = $pdo->prepare('SELECT * FROM utskick WHERE id = ?');
    $sats->execute([$id]);
    $rad = $sats->fetch();
    return $rad === false ? null : $rad;
}

function kvar_att_skicka(PDO $pdo, int $utskick_id): int
{
    $sats = $pdo->prepare(
        "SELECT COUNT(*)
           FROM prenumeranter p
           LEFT JOIN utskick_logg l ON l.prenumerant_id = p.id AND l.utskick_id = ?
          WHERE p.status = 'aktiv' AND l.id IS NULL"
    );
    $sats->execute([$utskick_id]);
    return (int) $sats->fetchColumn();
}

function standardtext(): string
{
    return "Hej!\n\n"
         . "Du skrev upp dig på vår lista för att få veta när vi öppnar. Nu är det dags.\n\n"
         . "DIO Södermalm slår upp dörrarna på Medborgarplatsen den DATUM. "
         . "Välkommen in på en pasta, ett glas vin eller bara en kaffe.\n\n"
         . "Vi ses!\n"
         . "Alexander med team";
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Öppningsmejl | DIO admin</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="admin.css">
</head>
<body>

<header class="topp">
  <h1>Öppningsmejl</h1>
  <nav>
    <a href="index.php">Tillbaka</a>
    <a href="index.php?ut=1">Logga ut</a>
  </nav>
</header>

<main>
  <?php if ($fel !== ''): ?><p class="fel"><?= h($fel) ?></p><?php endif; ?>
  <?php if ($meddelande !== ''): ?><p class="ok"><?= h($meddelande) ?></p><?php endif; ?>

  <?php if ($pagaende !== null): ?>

    <p>Utskicket är igång. Låt fliken stå öppen tills det står klart.</p>
    <p id="status" class="status">Förbereder …</p>

    <script>
      /* Sidan ber servern om en omgång i taget. Ett kort anrop per omgång
         är hela poängen: då hinner inget anrop slå i tidsgränsen. */
      (function () {
        var status = document.getElementById('status');
        var skickade = 0;
        var trasiga = 0;

        function omgang() {
          var data = new FormData();
          data.append('atgard', 'omgang');
          data.append('utskick', '<?= $pagaende ?>');

          fetch('utskick.php', {
            method: 'POST',
            body: data,
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
          })
            .then(function (svar) { return svar.json(); })
            .then(function (svar) {
              if (!svar.ok) { throw new Error(svar.meddelande || 'okänt fel'); }
              skickade += svar.skickade;
              trasiga += svar.misslyckade;
              status.textContent = 'Skickat ' + skickade + ' mejl. ' + svar.kvar + ' kvar.';
              if (svar.kvar > 0) {
                setTimeout(omgang, 1500);   // andrum mellan omgångarna
              } else {
                status.textContent = 'Klart. ' + skickade + ' mejl skickade'
                  + (trasiga ? ', ' + trasiga + ' misslyckades.' : '.');
              }
            })
            .catch(function (e) {
              status.textContent = 'Utskicket stannade: ' + e.message
                + '. Ladda om sidan så fortsätter det där det slutade.';
            });
        }

        omgang();
      })();
    </script>

  <?php else: ?>

    <p class="hjalp">
      Går till <strong><?= $antal_aktiva ?></strong> adresser. Skicka alltid ett
      testmejl till dig själv först — ett utskick går inte att ångra.
    </p>

    <form method="post" class="utskick">
      <label for="amne">Ämne</label>
      <input id="amne" name="amne" type="text" value="<?= h($amne) ?>" required>

      <label for="text">Text</label>
      <textarea id="text" name="text" rows="14" required><?= h($text) ?></textarea>
      <p class="hjalp">Avanmälningslänken läggs till automatiskt sist i brevet.</p>

      <div class="rad">
        <input name="testadress" type="email" placeholder="din@mejl.se"
               value="<?= h((string) ($_POST['testadress'] ?? '')) ?>">
        <button type="submit" name="atgard" value="test" class="andrahand">Skicka testmejl</button>
      </div>

      <label class="kryss">
        <input type="checkbox" name="bekraftat" value="ja">
        Jag har läst igenom brevet och vill skicka det till alla <?= $antal_aktiva ?>.
      </label>

      <button type="submit" name="atgard" value="starta">Skicka till alla</button>
    </form>

  <?php endif; ?>
</main>

</body>
</html>
