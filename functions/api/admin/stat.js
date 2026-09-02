/* GET /api/admin/stat — siffrorna och de senaste anmälningarna. */

import { json, forSedan } from '../../../src/svar.js';
import { kravInloggning } from '../../../src/auth.js';

export async function onRequestGet(context) {
  const { request, env } = context;

  const stopp = await kravInloggning(request, env);
  if (stopp) return stopp;

  /* Dygnet ska brytas vid svensk midnatt, inte vid UTC-midnatt. Med
     setUTCHours(0) hamnade allt som kom in mellan midnatt och klockan två
     på sommaren under gårdagen, eftersom Stockholm då ligger två timmar
     före UTC. Vi frågar Intl vilket datum det är i Stockholm just nu och
     räknar ut när det dygnet började i UTC. */
  const idag = svenskMidnattIUtc();

  /* Fyra räkningar och en lista. D1 kör dem i ett anrop med batch, vilket
     är både snabbare och snällare mot dygnskvoten än fem separata. */
  const [aktiva, avanmalda, iDag, veckan, senaste] = await env.DB.batch([
    env.DB.prepare("SELECT COUNT(*) AS antal FROM prenumeranter WHERE status = 'aktiv'"),
    env.DB.prepare("SELECT COUNT(*) AS antal FROM prenumeranter WHERE status = 'avanmald'"),
    env.DB.prepare("SELECT COUNT(*) AS antal FROM prenumeranter WHERE status = 'aktiv' AND skapad >= ?")
      .bind(idag),
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

/** Tidpunkten för senaste midnatt i Stockholm, uttryckt i UTC och samma
 *  format som databasen lagrar. */
function svenskMidnattIUtc() {
  const nu = new Date();

  // sv-SE ger "2026-09-03 01:15:00" i Stockholmstid. Bara klockslaget
  // behövs — datumet är bara med för att formatet ska bli det förväntade.
  const delar = new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'Europe/Stockholm',
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
    hour12: false,
  }).format(nu);

  const [t, m, sek] = delar.split(' ')[1].split(':').map(Number);

  // Hur lång tid som gått sedan svensk midnatt, bakåträknat från nu.
  const sedanMidnatt = ((t * 60 + m) * 60 + sek) * 1000 + nu.getMilliseconds();
  return new Date(nu.getTime() - sedanMidnatt).toISOString();
}
