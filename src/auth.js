/* ============================================================
   Inloggningen till adminvyn.

   Lösenordet finns aldrig på servern — bara en PBKDF2-hash i miljövariabeln
   ADMIN_HASH. Workers har ingen bcrypt, men WebCrypto har PBKDF2, och med
   tillräckligt många varv räcker det gott för en enda adminanvändare.

   Själva hashningen ligger i public/verktyg/hash.js, eftersom
   verktygssidan i webbläsaren måste köra exakt samma kod. Här sköts
   sessionerna.
   ============================================================ */

import { nu } from './svar.js';
import { stammerLosenord } from '../public/verktyg/hash.js';

const SESSION_TIMMAR = 12;
const KAKA = 'dio_admin';

export { stammerLosenord };

/** SHA-256 i hex. Används för att lagra sessioner utan att lagra kakan. */
async function sha256(text) {
  const bitar = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
  return [...new Uint8Array(bitar)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/** Loggar in: skapar en session och returnerar kakan att sätta. */
export async function skapaSession(env) {
  const bytes = crypto.getRandomValues(new Uint8Array(32));
  const token = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
  const giltigTill = new Date(Date.now() + SESSION_TIMMAR * 3600 * 1000).toISOString();

  /* Databasen får en hash av kakan, aldrig kakan själv. Läcker tabellen
     ska ingen kunna logga in med innehållet. */
  await env.DB.prepare(
    'INSERT INTO sessioner (token_hash, skapad, giltig_till) VALUES (?, ?, ?)'
  )
    .bind(await sha256(token), nu(), giltigTill)
    .run();

  // Utgångna sessioner städas bort i samma veva. Det finns inget
  // schemalagt jobb att hänga upp det på, och tabellen får inte växa fritt.
  await env.DB.prepare('DELETE FROM sessioner WHERE giltig_till < ?').bind(nu()).run();

  return [
    `${KAKA}=${token}`,
    'Path=/',
    'HttpOnly',                        // ingen JavaScript-åtkomst till kakan
    'Secure',                          // bara över https
    'SameSite=Lax',                    // följer inte med anrop från andra sajter
    `Max-Age=${SESSION_TIMMAR * 3600}`,
  ].join('; ');
}

/** Läser kakan ur anropet. */
function kakvarde(request) {
  const rad = request.headers.get('Cookie') || '';
  for (const bit of rad.split(';')) {
    const [namn, ...resten] = bit.trim().split('=');
    if (namn === KAKA) return resten.join('=');
  }
  return '';
}

/** Är anroparen inloggad? */
export async function arInloggad(request, env) {
  const token = kakvarde(request);
  if (!/^[a-f0-9]{64}$/.test(token)) return false;

  const rad = await env.DB.prepare(
    'SELECT token_hash FROM sessioner WHERE token_hash = ? AND giltig_till > ?'
  )
    .bind(await sha256(token), nu())
    .first();

  return rad !== null;
}

/** Loggar ut och returnerar kakan som raderar sessionen i webbläsaren. */
export async function avslutaSession(request, env) {
  const token = kakvarde(request);
  if (/^[a-f0-9]{64}$/.test(token)) {
    await env.DB.prepare('DELETE FROM sessioner WHERE token_hash = ?')
      .bind(await sha256(token))
      .run();
  }
  return `${KAKA}=; Path=/; HttpOnly; Secure; SameSite=Lax; Max-Age=0`;
}

/** Vakt för endpoints som kräver inloggning. Returnerar ett färdigt
 *  401-svar när ingen är inloggad, annars null. */
export async function kravInloggning(request, env) {
  if (await arInloggad(request, env)) return null;

  return new Response(JSON.stringify({ ok: false, meddelande: 'Inte inloggad.' }), {
    status: 401,
    headers: { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' },
  });
}
