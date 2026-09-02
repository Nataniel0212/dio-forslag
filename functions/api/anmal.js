/* ============================================================
   POST /api/anmal — tar emot en adress från formuläret på startsidan.

   Svarar med JSON till sidans JavaScript, och med en omdirigering tillbaka
   till startsidan om formuläret postats utan JavaScript. Båda vägarna
   fungerar; formuläret har action och method satta i HTML.
   ============================================================ */

import {
  json, nu, klientIp, lasFalt, logga, antalHandelser, giltigEpost, nyToken,
} from '../../src/svar.js';

const SAMTYCKESTEXT = 'Meddela mig när ni öppnar (startsidan, dio-anmälan)';
const MAX_PER_TIMME = 5;

export async function onRequestPost(context) {
  const { request, env } = context;
  const ip = klientIp(request);

  // Sidans fetch ber uttryckligen om JSON. Ett vanligt formulär utan
  // JavaScript gör det inte och ska ha en riktig sida tillbaka.
  const villHaJson = (request.headers.get('Accept') || '').includes('application/json');
  const svar = (status, ok, meddelande) => bygg(villHaJson, status, ok, meddelande);

  const falt = await lasFalt(request);

  /* Honungsfällan: fältet är dolt för människor men fylls i av enkla
     spamrobotar. Vi svarar som vanligt så att roboten inte lär sig något,
     men sparar ingenting. */
  if (String(falt.webbplats || '').trim() !== '') {
    await logga(env, ip, 'spam');
    return svar(200, true, 'Tack! Vi hör av oss när vi öppnar.');
  }

  const epost = String(falt.email || '').trim().toLowerCase();
  if (!giltigEpost(epost)) {
    return svar(422, false, 'Kontrollera mejladressen.');
  }

  /* Spärr mot översvämning: samma IP får lämna ett fåtal adresser i timmen.
     Räcker gott för en riktig besökare, stoppar den som skriptar. */
  if (await antalHandelser(env, ip, 'anmalan', 3600) >= MAX_PER_TIMME) {
    await logga(env, ip, 'sparrad', epost);
    return svar(429, false, 'För många försök just nu. Prova igen om en stund.');
  }

  try {
    const fanns = await env.DB.prepare('SELECT id, status FROM prenumeranter WHERE epost = ?')
      .bind(epost)
      .first();

    if (!fanns) {
      await env.DB.prepare(
        `INSERT INTO prenumeranter
           (epost, status, token, kalla, samtyckestext, ip, webblasare, skapad)
         VALUES (?, 'aktiv', ?, 'startsida', ?, ?, ?, ?)`
      )
        .bind(
          epost,
          nyToken(),
          SAMTYCKESTEXT,
          ip,
          (request.headers.get('User-Agent') || '').slice(0, 255),
          nu()
        )
        .run();
    } else if (fanns.status !== 'aktiv') {
      /* Någon som avanmält sig tidigare och skriver upp sig igen ska så
         klart hamna på listan på nytt. */
      await env.DB.prepare(
        "UPDATE prenumeranter SET status = 'aktiv', avanmald = NULL, skapad = ? WHERE id = ?"
      )
        .bind(nu(), fanns.id)
        .run();
    }
    /* Fanns adressen redan som aktiv gör vi ingenting — men svaret är
       detsamma. Formuläret ska inte gå att använda för att lista ut vilka
       adresser som står på listan. */

    await logga(env, ip, 'anmalan', epost);
  } catch (fel) {
    console.error('anmalan:', fel && fel.message);
    return svar(500, false, 'Något gick fel hos oss. Försök igen om en stund.');
  }

  return svar(200, true, 'Tack! Vi hör av oss när vi öppnar.');
}

/** Allt annat än POST hör inte hemma här. */
export function onRequest() {
  return json({ ok: false, meddelande: 'Fel anropsmetod.' }, 405);
}

function bygg(villHaJson, status, ok, meddelande) {
  if (villHaJson) return json({ ok, meddelande }, status);

  /* Utan JavaScript får besökaren en riktig sida tillbaka. Alltid 303 och
     aldrig en felkod här: felkoden skulle visa en felsida i stället för
     vår. Utfallet står i adressen. */
  return new Response(null, {
    status: 303,
    headers: {
      Location: `/?anmalan=${ok ? 'ok' : 'fel'}#anmalan`,
      'Cache-Control': 'no-store',
    },
  });
}
