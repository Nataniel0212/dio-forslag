/* ============================================================
   POST /api/admin/utskick — öppningsmejlet.

   Fyra åtgärder:
     status   vad som är på gång och hur många som står på tur
     test     ett enda brev till en adress man anger
     starta   lägger upp utskicket och returnerar dess id
     omgang   skickar nästa 20 brev

   Utskicket går i omgångar av två skäl: ett Worker-anrop har en tidsgräns,
   och varje skickat brev ska hinna loggas innan nästa går. Loggen gör
   utskicket återupptagbart — dör anropet mitt i fortsätter det där det
   slutade, och ingen får brevet två gånger.
   ============================================================ */

import { json, nu, lasFalt, giltigEpost, avanmalLank } from '../../../src/svar.js';
import { kravInloggning } from '../../../src/auth.js';
import { skickaMail, skickaOmgang } from '../../../src/mail.js';

const PER_OMGANG = 20;

export async function onRequestPost(context) {
  const { request, env } = context;

  const stopp = await kravInloggning(request, env);
  if (stopp) return stopp;

  const falt = await lasFalt(request);
  const atgard = String(falt.atgard || '');

  if (atgard === 'status') return status(env);
  if (atgard === 'test') return test(env, falt);
  if (atgard === 'starta') return starta(env, falt);
  if (atgard === 'omgang') return omgang(env, falt);

  return json({ ok: false, meddelande: 'Okänd åtgärd.' }, 400);
}

/** Antal på listan, plus ett eventuellt utskick som inte hunnit bli klart. */
async function status(env) {
  const aktiva = await env.DB.prepare(
    "SELECT COUNT(*) AS antal FROM prenumeranter WHERE status = 'aktiv'"
  ).first();

  const pagaende = await env.DB.prepare(
    'SELECT id, amne FROM utskick WHERE klart IS NULL ORDER BY id DESC LIMIT 1'
  ).first();

  let kvar = 0;
  if (pagaende) kvar = await kvarAttSkicka(env, pagaende.id);

  return json({
    ok: true,
    aktiva: aktiva.antal,
    pagaende: pagaende && kvar > 0 ? { id: pagaende.id, amne: pagaende.amne, kvar } : null,
  });
}

async function test(env, falt) {
  const till = String(falt.testadress || '').trim();
  const amne = String(falt.amne || '').trim();
  const text = String(falt.text || '').trim();

  if (!giltigEpost(till)) {
    return json({ ok: false, meddelande: 'Skriv en giltig adress att testa mot.' }, 422);
  }
  if (!amne || !text) {
    return json({ ok: false, meddelande: 'Ämne och text måste vara ifyllda.' }, 422);
  }

  const svar = await skickaMail(env, till, amne, text);
  if (!svar.ok) {
    return json({ ok: false, meddelande: `Mejlet gick inte att skicka. ${svar.fel}` }, 502);
  }

  return json({
    ok: true,
    meddelande: `Testmejl skickat till ${till}. Kolla att det kom fram och ser rätt ut.`,
  });
}

async function starta(env, falt) {
  const amne = String(falt.amne || '').trim();
  const text = String(falt.text || '').trim();

  if (!amne || !text) {
    return json({ ok: false, meddelande: 'Ämne och text måste vara ifyllda.' }, 422);
  }
  if (String(falt.bekraftat || '') !== 'ja') {
    return json({ ok: false, meddelande: 'Kryssa i rutan först — ett utskick går inte att ta tillbaka.' }, 422);
  }

  const resultat = await env.DB.prepare(
    'INSERT INTO utskick (amne, text, skapad) VALUES (?, ?, ?)'
  )
    .bind(amne, text, nu())
    .run();

  const id = resultat.meta.last_row_id;
  return json({ ok: true, utskick: id, kvar: await kvarAttSkicka(env, id) });
}

async function omgang(env, falt) {
  const id = Number(falt.utskick || 0);

  const utskicket = await env.DB.prepare('SELECT * FROM utskick WHERE id = ?').bind(id).first();
  if (!utskicket) {
    return json({ ok: false, meddelande: 'Utskicket finns inte.' }, 404);
  }

  const { results: mottagare } = await env.DB.prepare(
    `SELECT p.id, p.epost, p.token
       FROM prenumeranter p
       LEFT JOIN utskick_logg l ON l.prenumerant_id = p.id AND l.utskick_id = ?
      WHERE p.status = 'aktiv' AND l.id IS NULL
      ORDER BY p.id
      LIMIT ?`
  )
    .bind(id, PER_OMGANG)
    .all();

  if (mottagare.length === 0) {
    await env.DB.prepare('UPDATE utskick SET klart = ? WHERE id = ? AND klart IS NULL')
      .bind(nu(), id)
      .run();
    return json({ ok: true, skickade: 0, misslyckade: 0, kvar: 0 });
  }

  const brev = mottagare.map((m) => {
    const lank = avanmalLank(env, m.token);
    return {
      epost: m.epost,
      avanmal: lank,
      text: `${utskicket.text}\n\n--\nVill du inte ha fler mejl från oss? ${lank}`,
    };
  });

  const svar = await skickaOmgang(env, utskicket.amne, brev);

  /* Loggen skrivs oavsett utfall. Gick omgången fel markeras mottagarna med
     ok = 0 — de räknas som avklarade så att ett trasigt brev inte gör att
     hela utskicket fastnar och kör i cirkel. Vilka det gällde syns i
     loggen efteråt. */
  await env.DB.batch(
    mottagare.map((m) =>
      env.DB.prepare(
        'INSERT OR IGNORE INTO utskick_logg (utskick_id, prenumerant_id, ok, fel, skickad) VALUES (?, ?, ?, ?, ?)'
      ).bind(id, m.id, svar.ok ? 1 : 0, svar.ok ? null : String(svar.fel).slice(0, 255), nu())
    )
  );

  const kvar = await kvarAttSkicka(env, id);
  if (kvar === 0) {
    await env.DB.prepare('UPDATE utskick SET klart = ? WHERE id = ? AND klart IS NULL')
      .bind(nu(), id)
      .run();
  }

  return json({
    ok: true,
    skickade: svar.ok ? mottagare.length : 0,
    misslyckade: svar.ok ? 0 : mottagare.length,
    fel: svar.ok ? undefined : svar.fel,
    kvar,
  });
}

async function kvarAttSkicka(env, utskickId) {
  const rad = await env.DB.prepare(
    `SELECT COUNT(*) AS antal
       FROM prenumeranter p
       LEFT JOIN utskick_logg l ON l.prenumerant_id = p.id AND l.utskick_id = ?
      WHERE p.status = 'aktiv' AND l.id IS NULL`
  )
    .bind(utskickId)
    .first();

  return rad ? Number(rad.antal) : 0;
}
