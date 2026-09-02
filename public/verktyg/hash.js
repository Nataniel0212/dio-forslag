/* ============================================================
   Lösenordshashen — PBKDF2 över WebCrypto.

   Filen ligger under public/ av ett enda skäl: verktygssidan
   /verktyg/losenord.html körs i webbläsaren och måste använda exakt samma
   kod som servern, annars kan en hash som verktyget skapar vara omöjlig
   att verifiera. Servern importerar samma fil via src/auth.js.

   Det finns inget hemligt här. Koden räknar fram en hash av ett lösenord
   någon redan känner till — den avslöjar ingenting om det riktiga
   lösenordet, som bara finns som hash i miljövariabeln ADMIN_HASH.

   Format:  pbkdf2$<varv>$<salt i base64>$<hash i base64>
   ============================================================ */

const VARV = 210000;   // samma storleksordning som OWASP rekommenderar

async function pbkdf2(losenord, salt, varv) {
  const nyckel = await crypto.subtle.importKey(
    'raw',
    new TextEncoder().encode(losenord),
    'PBKDF2',
    false,
    ['deriveBits']
  );

  const bitar = await crypto.subtle.deriveBits(
    { name: 'PBKDF2', hash: 'SHA-256', salt, iterations: varv },
    nyckel,
    256
  );

  return new Uint8Array(bitar);
}

const tillBas64 = (bytes) => btoa(String.fromCharCode(...bytes));
const franBas64 = (text) => Uint8Array.from(atob(text), (c) => c.charCodeAt(0));

/** Skapar strängen som ska stå i ADMIN_HASH. */
export async function skapaHash(losenord) {
  const salt = crypto.getRandomValues(new Uint8Array(16));
  const hash = await pbkdf2(losenord, salt, VARV);
  return `pbkdf2$${VARV}$${tillBas64(salt)}$${tillBas64(hash)}`;
}

/** Jämför ett inskrivet lösenord med den sparade hashen. Varvtalet läses
 *  ur hashen, så en gammal hash fortsätter fungera om VARV höjs. */
export async function stammerLosenord(losenord, sparad) {
  if (typeof sparad !== 'string') return false;

  const delar = sparad.split('$');
  if (delar.length !== 4 || delar[0] !== 'pbkdf2') return false;

  const varv = Number(delar[1]);
  if (!Number.isFinite(varv) || varv < 1000) return false;

  let salt;
  let vantad;
  try {
    salt = franBas64(delar[2]);
    vantad = franBas64(delar[3]);
  } catch {
    return false;
  }

  return likaLanga(await pbkdf2(losenord, salt, varv), vantad);
}

/** Jämför två byte-följder utan att avbryta vid första skillnaden. En
 *  vanlig jämförelse tar olika lång tid beroende på hur många tecken som
 *  stämmer, och den skillnaden går att mäta sig fram till. */
function likaLanga(a, b) {
  if (a.length !== b.length) return false;
  let skillnad = 0;
  for (let i = 0; i < a.length; i++) skillnad |= a[i] ^ b[i];
  return skillnad === 0;
}
