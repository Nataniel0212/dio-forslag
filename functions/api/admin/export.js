/* GET /api/admin/export — listan som CSV, att öppna i Excel eller ta med
   till ett utskicksverktyg om ni byter till ett sådant längre fram. */

import { kravInloggning } from '../../../src/auth.js';

export async function onRequestGet(context) {
  const { request, env } = context;

  const stopp = await kravInloggning(request, env);
  if (stopp) return stopp;

  const { results } = await env.DB.prepare(
    "SELECT epost, skapad FROM prenumeranter WHERE status = 'aktiv' ORDER BY skapad"
  ).all();

  const rader = [['mejladress', 'anmald'], ...results.map((r) => [r.epost, r.skapad])];
  const csv = rader.map((rad) => rad.map(falt).join(';')).join('\r\n');

  const datum = new Date().toISOString().slice(0, 10);

  return new Response(
    // Byteordningsmärket får Excel att förstå att filen är UTF-8. Utan det
    // blir å, ä och ö obegripliga när Alexander öppnar den.
    '﻿' + csv,
    {
      headers: {
        'Content-Type': 'text/csv; charset=utf-8',
        'Content-Disposition': `attachment; filename="dio-anmalningar-${datum}.csv"`,
        'Cache-Control': 'no-store',
      },
    }
  );
}

/** Citerar ett fält om det innehåller något som annars bryter formatet. */
function falt(varde) {
  const text = String(varde ?? '');
  return /[";\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}
