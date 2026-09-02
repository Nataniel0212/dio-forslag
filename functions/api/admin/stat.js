/* GET /api/admin/stat — siffrorna och de senaste anmälningarna. */

import { json, forSedan } from '../../../src/svar.js';
import { kravInloggning } from '../../../src/auth.js';

export async function onRequestGet(context) {
  const { request, env } = context;

  const stopp = await kravInloggning(request, env);
  if (stopp) return stopp;

  const idag = new Date();
  idag.setUTCHours(0, 0, 0, 0);

  /* Fyra räkningar och en lista. D1 kör dem i ett anrop med batch, vilket
     är både snabbare och snällare mot dygnskvoten än fem separata. */
  const [aktiva, avanmalda, iDag, veckan, senaste] = await env.DB.batch([
    env.DB.prepare("SELECT COUNT(*) AS antal FROM prenumeranter WHERE status = 'aktiv'"),
    env.DB.prepare("SELECT COUNT(*) AS antal FROM prenumeranter WHERE status = 'avanmald'"),
    env.DB.prepare("SELECT COUNT(*) AS antal FROM prenumeranter WHERE status = 'aktiv' AND skapad >= ?")
      .bind(idag.toISOString()),
    env.DB.prepare("SELECT COUNT(*) AS antal FROM prenumeranter WHERE status = 'aktiv' AND skapad >= ?")
      .bind(forSedan(7 * 86400)),
    env.DB.prepare('SELECT epost, status, skapad FROM prenumeranter ORDER BY skapad DESC, id DESC LIMIT 50'),
  ]);

  return json({
    ok: true,
    aktiva: aktiva.results[0].antal,
    avanmalda: avanmalda.results[0].antal,
    idag: iDag.results[0].antal,
    veckan: veckan.results[0].antal,
    senaste: senaste.results,
  });
}
