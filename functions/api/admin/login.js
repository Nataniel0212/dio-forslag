/* ============================================================
   POST /api/admin/login — logga in i adminvyn.
   GET  /api/admin/login — svarar om man redan är inloggad, så att
                           adminsidan vet vad den ska visa vid laddning.
   ============================================================ */

import { json, klientIp, lasFalt, logga, antalHandelser } from '../../../src/svar.js';
import { stammerLosenord, skapaSession, arInloggad } from '../../../src/auth.js';

const MAX_FORSOK = 5;
const FONSTER_SEKUNDER = 900;   // en kvart

export async function onRequestGet(context) {
  return json({ ok: true, inloggad: await arInloggad(context.request, context.env) });
}

export async function onRequestPost(context) {
  const { request, env } = context;
  const ip = klientIp(request);

  // Utan spärr går ett lösenord att gissa sig till med tillräckligt många
  // försök. Fem misslyckade i kvarten, sedan paus.
  if (await antalHandelser(env, ip, 'inloggning_fel', FONSTER_SEKUNDER) >= MAX_FORSOK) {
    return json({ ok: false, meddelande: 'För många försök. Vänta en kvart och prova igen.' }, 429);
  }

  if (!env.ADMIN_HASH) {
    return json({ ok: false, meddelande: 'Inget lösenord är satt än. Se /verktyg/losenord.html.' }, 500);
  }

  const falt = await lasFalt(request);
  const losenord = String(falt.losenord || '');

  if (!(await stammerLosenord(losenord, env.ADMIN_HASH))) {
    await logga(env, ip, 'inloggning_fel');
    return json({ ok: false, meddelande: 'Fel lösenord.' }, 401);
  }

  const kaka = await skapaSession(env);
  return json({ ok: true }, 200, { 'Set-Cookie': kaka });
}
