/* ============================================================
   Utgående mejl via Resend.

   Workers kan inte skicka mejl själva — det finns ingen SMTP-klient i
   körmiljön. Allt går därför genom Resends HTTP-API, och all kod som rör
   mejl ligger i den här filen. Byter vi leverantör är det bara här det
   ändras.
   ============================================================ */

/* Bas-URL:en är utbytbar för att utskicket ska gå att provköra mot en
   attrapp i stället för mot riktiga mottagare. I drift är RESEND_BAS
   osatt och då gäller Resends riktiga adress. */
const bas = (env) => (env.RESEND_BAS || 'https://api.resend.com') + '/emails';

/** Avsändare på formen "DIO Södermalm <alexander@diosodermalm.se>".
 *  Domänen måste vara verifierad hos Resend, annars vägrar de skicka. */
function avsandare(env) {
  return `${env.AVSANDARE_NAMN || 'DIO Södermalm'} <${env.AVSANDARE_EPOST}>`;
}

/** Rubrikerna som gör ett listutskick väluppfostrat: mottagarens e-postklient
 *  får en egen avanmälningsknapp. Det är halva skillnaden mellan inkorgen
 *  och skräpposten när man mejlar en lista. */
function avanmalRubriker(lank) {
  if (!lank) return undefined;
  return {
    'List-Unsubscribe': `<${lank}>`,
    'List-Unsubscribe-Post': 'List-Unsubscribe=One-Click',
  };
}

/** Ett enskilt mejl, till exempel testutskicket från adminvyn. */
export async function skickaMail(env, till, amne, text, avanmal = '') {
  return await anropa(bas(env), env, {
    from: avsandare(env),
    to: [till],
    subject: amne,
    text,
    headers: avanmalRubriker(avanmal),
  });
}

/**
 * En omgång av listutskicket. Resend tar upp till 100 brev per anrop, och
 * varje brev får sin egen text och sina egna rubriker — det är precis vad
 * vi behöver, eftersom avanmälningslänken är unik per mottagare.
 *
 * @param {Array<{epost: string, text: string, avanmal: string}>} brev
 * @returns {Promise<{ok: boolean, fel?: string}>}
 */
export async function skickaOmgang(env, amne, brev) {
  if (brev.length === 0) return { ok: true };

  return await anropa(
    `${bas(env)}/batch`,
    env,
    brev.map((b) => ({
      from: avsandare(env),
      to: [b.epost],
      subject: amne,
      text: b.text,
      headers: avanmalRubriker(b.avanmal),
    }))
  );
}

/** Själva anropet. Både nekade svar och nätverksfel kommer tillbaka som
 *  { ok: false, fel } — ett kastat undantag här hade tagit ner endpointen
 *  mitt i ett utskick, och då hade adminvyn inte kunnat säga vad som hände. */
async function anropa(url, env, kropp) {
  let svar;

  try {
    svar = await fetch(url, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${env.RESEND_API_KEY}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(kropp),
    });
  } catch (fel) {
    return { ok: false, fel: `Nådde inte mejltjänsten: ${fel && fel.message}` };
  }

  if (svar.ok) return { ok: true };

  const text = await svar.text().catch(() => '');
  return { ok: false, fel: `${svar.status}: ${text.slice(0, 200)}` };
}
