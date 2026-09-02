/* POST /api/admin/logout — avslutar sessionen både i databasen och i
   webbläsaren. */

import { json } from '../../../src/svar.js';
import { avslutaSession } from '../../../src/auth.js';

export async function onRequestPost(context) {
  const kaka = await avslutaSession(context.request, context.env);
  return json({ ok: true }, 200, { 'Set-Cookie': kaka });
}
