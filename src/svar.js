/* ============================================================
   Små hjälpare som alla endpoints delar: svarsformat, tid, IP,
   inläsning av inskickade fält och de enkla spärrarna.
   ============================================================ */

/** JSON-svar. Sidans formulär och adminvyn pratar bara JSON med servern. */
export function json(data, status = 200, extraRubriker = {}) {
  return new Response(JSON.stringify(data), {
    status,
    headers: {
      'Content-Type': 'application/json; charset=utf-8',
      // Svaren är alltid personliga eller färska — de ska aldrig hamna i
      // Cloudflares cache mellan två besökare.
      'Cache-Control': 'no-store',
      ...extraRubriker,
    },
  });
}

export function nu() {
  return new Date().toISOString();
}

/** Tidpunkt så många sekunder tillbaka, i samma format som databasen. */
export function forSedan(sekunder) {
  return new Date(Date.now() - sekunder * 1000).toISOString();
}

/** Besökarens IP. Cloudflare sätter CF-Connecting-IP och den går inte att
 *  förfalska utifrån — till skillnad från X-Forwarded-For, som vem som
 *  helst kan skicka med och som därför är värdelös till spärrlistor. */
export function klientIp(request) {
  return (request.headers.get('CF-Connecting-IP') || '').slice(0, 45);
}

/** Läser fälten ur ett anrop oavsett om de kommer som formulärdata eller
 *  JSON. Formuläret på startsidan ska fungera även utan JavaScript. */
export async function lasFalt(request) {
  const typ = request.headers.get('Content-Type') || '';

  if (typ.includes('application/json')) {
    try {
      const kropp = await request.json();
      return kropp && typeof kropp === 'object' ? kropp : {};
    } catch {
      return {};
    }
  }

  try {
    const data = await request.formData();
    return Object.fromEntries(data.entries());
  } catch {
    return {};
  }
}

/* Loggen ska inte växa för evigt. Raderna bär både IP och mejladress, och
   de behövs bara så länge spärrarna räknar på dem — det längsta fönstret
   är en timme. En månad räcker gott för att kunna titta bakåt på en
   spamvåg, och sedan finns uppgifterna inte kvar. */
const LOGG_DAGAR = 30;

/** Skriver en rad i händelseloggen och städar bort de gamla. */
export async function logga(env, ip, typ, detalj = '') {
  await env.DB.batch([
    env.DB.prepare('INSERT INTO handelser (ip, typ, detalj, skapad) VALUES (?, ?, ?, ?)')
      .bind(ip, typ, String(detalj).slice(0, 255), nu()),
    // Ingen schemaläggare finns att hänga städningen på, så den får åka
    // med här. Skrivningarna är få: en per anmälan, inte per besök.
    env.DB.prepare('DELETE FROM handelser WHERE skapad < ?')
      .bind(forSedan(LOGG_DAGAR * 86400)),
  ]);
}

/** Hur många händelser av en typ som kommit från samma IP den senaste
 *  tiden. Grunden för spärren i formuläret och vid inloggning. */
export async function antalHandelser(env, ip, typ, sekunder) {
  const rad = await env.DB.prepare(
    'SELECT COUNT(*) AS antal FROM handelser WHERE ip = ? AND typ = ? AND skapad > ?'
  )
    .bind(ip, typ, forSedan(sekunder))
    .first();

  return rad ? Number(rad.antal) : 0;
}

/** Enkel adresskontroll. Servern gör den igen även om sidan redan gjort
 *  den — klientvalidering är bekvämlighet, aldrig skydd. */
export function giltigEpost(epost) {
  return typeof epost === 'string'
    && epost.length <= 191
    && /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(epost);
}

/** Full länk som avanmäler en enskild adress. */
export function avanmalLank(env, token) {
  return `${(env.SAJT_URL || '').replace(/\/$/, '')}/api/avanmal?token=${encodeURIComponent(token)}`;
}

/** Slumpad nyckel till avanmälningslänken — måste vara omöjlig att gissa,
 *  annars kan vem som helst avanmäla andras adresser. */
export function nyToken() {
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  return [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/** Skydd mot inklistrad HTML i utskrifter. */
export function h(text) {
  return String(text ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
