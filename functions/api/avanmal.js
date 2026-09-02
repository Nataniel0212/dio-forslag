/* ============================================================
   /api/avanmal — avanmälan via länken i mejlen.

   Adressen ligger kvar i databasen men markeras som avanmäld. Raderar vi
   raden kan samma adress hamna på listan igen vid nästa import, och då är
   avanmälan värdelös.
   ============================================================ */

import { json, nu, h } from '../../src/svar.js';

async function avanmal(env, token) {
  if (!/^[a-f0-9]{32}$/.test(token)) return false;

  await env.DB.prepare(
    "UPDATE prenumeranter SET status = 'avanmald', avanmald = ? WHERE token = ? AND status = 'aktiv'"
  )
    .bind(nu(), token)
    .run();

  /* Redan avanmäld räknas också som lyckat: den som klickar två gånger ska
     se samma lugna besked, inte ett fel. */
  const rad = await env.DB.prepare('SELECT id FROM prenumeranter WHERE token = ?')
    .bind(token)
    .first();

  return rad !== null;
}

/** Gmails ettklicksavanmälan postar hit i bakgrunden och visar aldrig
 *  någon sida — den vill bara ha ett kvitto. */
export async function onRequestPost(context) {
  const { request, env } = context;
  const url = new URL(request.url);

  let token = url.searchParams.get('token') || '';
  if (!token) {
    try {
      const data = await request.formData();
      token = String(data.get('token') || '');
    } catch {
      /* Gmail postar utan kropp — token står då i adressen. */
    }
  }

  const ok = await avanmal(env, token);
  return json({ ok }, ok ? 200 : 404);
}

export async function onRequestGet(context) {
  const { request, env } = context;
  const token = new URL(request.url).searchParams.get('token') || '';
  const ok = await avanmal(env, token);

  const innehall = ok
    ? `<h2>Du är avanmäld</h2>
       <p class="sub">Vi hör inte av oss mer. Ångrar du dig är du välkommen
          tillbaka till <a href="/">startsidan</a>.</p>`
    : `<h2>Länken gäller inte</h2>
       <p class="sub">Länken är gammal eller ofullständig. Mejla
          <a href="mailto:${h(context.env.AVSANDARE_EPOST)}">${h(context.env.AVSANDARE_EPOST)}</a>
          så tar vi bort adressen för hand.</p>`;

  return new Response(sida(innehall), {
    status: ok ? 200 : 404,
    headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' },
  });
}

/* Sidan lånar startsidans stilmall. Sektionen får klassen is-in direkt —
   .section startar med opacity: 0 och tonas normalt in av skriptet på
   startsidan, och något sådant skript finns inte här. */
function sida(innehall) {
  return `<!DOCTYPE html>
<html lang="sv">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Avanmälan | DIO Södermalm</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400&family=Jost:wght@200;300;400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <main>
    <section class="section signup-section is-in" style="min-height:100svh;display:grid;place-content:center">
      <div class="signup-wrap">${innehall}</div>
    </section>
  </main>
</body>
</html>`;
}
