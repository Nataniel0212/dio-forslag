# DIO Södermalm — startsida

"Öppnar snart"-sida för DIO Södermalm med en egen backend för mejllistan.
Ren HTML/CSS/JS i frontend, Cloudflare Pages Functions och D1 i botten.
Inga ramverk. Typsnitt från Google Fonts (Cormorant Garamond + Jost).

    public/                 det som besökaren laddar
      index.html            hela sidan
      css/style.css         stilmallen
      robots.txt            håller sökmotorer borta från /api/
      admin/index.html      Alexanders vy: antal anmälda, CSV, utskick
      admin/admin.css       adminvyns egen stilmall
      verktyg/losenord.html räknar fram ADMIN_HASH, körs i webbläsaren
      verktyg/hash.js       PBKDF2 — delas med servern, se kommentaren i filen

    functions/              API:t, en fil per adress
      api/anmal.js          POST /api/anmal — tar emot en adress
      api/avanmal.js        /api/avanmal — avanmälan via länken i mejlen
      api/admin/login.js    inloggning
      api/admin/logout.js   utloggning
      api/admin/stat.js     siffrorna och senaste 50
      api/admin/export.js   listan som CSV
      api/admin/utskick.js  öppningsmejlet, i omgångar om 20

    src/                    delad kod som functions importerar
      svar.js               svarsformat, tid, IP, spärrar, validering
      auth.js               sessioner och inloggningskontroll
      mail.js               allt utgående mejl går genom den här filen

    schema.sql              databasen
    wrangler.toml           Cloudflare-konfigurationen
    arkiv/                  de två förslag som inte valdes (publiceras inte)

## Varför Cloudflare och inte ett vanligt webbhotell

Domänen ligger hos one.com men paketet där är ett e-postpaket — ingen PHP,
ingen databas, ingenstans att lägga filerna. Cloudflare Pages kör sidan
gratis, D1 håller listan och Resend skickar mejlen. Allt ryms med marginal
i gratisnivåerna: Workers klarar 100 000 anrop per dygn, D1 5 GB och
100 000 skrivna rader per dygn, Resend 3 000 mejl i månaden.

Backenden fanns tidigare i PHP och MySQL. Den koden ligger kvar i
git-historiken (commit `9ce1e6f`) om ni någon gång flyttar till ett vanligt
webbhotell igen.

## Så testar du lokalt

Wrangler kräver **Node 22 eller senare**. Har du en äldre Node går det att
köra i en container med `node:22`, men enklast är att uppgradera.

    npm install
    npx wrangler d1 create dio          # skriv in id:t i wrangler.toml
    npm run schema                      # lägger upp tabellerna lokalt
    npm run dev                         # http://localhost:8788

Hemligheterna läses lokalt ur `.dev.vars`, som ligger i `.gitignore`:

    ADMIN_HASH = "pbkdf2$..."              # från /verktyg/losenord.html
    RESEND_API_KEY = "re_..."
    RESEND_BAS = "http://127.0.0.1:9999"   # valfritt, se nedan

`RESEND_BAS` pekar om mejlutskicket till en annan adress än Resends. Det är
till för att kunna provköra hela utskicket mot en attrapp utan att skicka
riktiga brev. Lämnas den osatt gäller Resend.

Wrangler läser `.dev.vars` vid start — ändrar du filen måste servern
startas om.

## Lägga upp på Cloudflare

1. **Skapa ett Cloudflare-konto** och lägg till domänen `diosodermalm.se`
   under Websites. Cloudflare läser in befintliga DNS-poster automatiskt.
   **Kontrollera MX-posterna innan namnservrarna byts** — Alexanders mejl
   ligger hos one.com, och tappas MX-posterna slutar hans mejl fungera.
   Jämför listan Cloudflare visar med den hos one.com, post för post.
2. **Byt namnservrar** hos one.com till de två Cloudflare anger. Tar
   normalt några timmar att slå igenom.
3. **Skapa databasen:** `npx wrangler d1 create dio`, klistra in id:t i
   `wrangler.toml`, kör sedan `npm run schema:skarp`.
4. **Koppla repot** i Cloudflare Pages (Workers & Pages → Create → Pages →
   Connect to Git). Build command lämnas tom, output directory är `public`.
   Då bygger Cloudflare om sidan automatiskt vid varje push till `main`.
5. **Lägg in hemligheterna** under Settings → Variables and Secrets, som
   *Secret* (krypterad), inte som vanlig variabel:
   - `ADMIN_HASH` — från `/verktyg/losenord.html`
   - `RESEND_API_KEY` — från Resend
6. **Verifiera domänen hos Resend** och lägg in DNS-posterna de ger dig.
   Utan det vägrar de skicka från `@diosodermalm.se`.
7. **Peka domänen** på Pages-projektet under Custom domains.
8. **Testa:** skriv in en adress i formuläret, kontrollera att den dyker
   upp i `/admin/`, och skicka ett testmejl därifrån till dig själv.

## Mejl

Allt utgående mejl går genom `src/mail.js` och Resends API. Byter ni
leverantör är det bara den filen som skrivs om.

Öppningsmejlet skickas i omgångar om 20. Varje omgång loggas i
`utskick_logg`, så ett utskick som avbryts går att fortsätta utan att någon
får brevet två gånger — ladda bara om adminsidan, den plockar upp ett
påbörjat utskick av sig själv.

Går en omgång fel — Resend nere, nyckeln utgången — markeras mottagarna med
`ok = 0` och felet sparas i loggen. De räknas som avklarade med flit:
annars hade ett trasigt brev fått hela utskicket att köra i cirkel. Vilka
det gällde syns i `utskick_logg`.

Avsändaradressen måste ligga på en domän som är verifierad hos Resend,
annars stoppas mejlen som förfalskade.

## Personuppgifter

Vi sparar mejladress, tidpunkt, IP och webbläsarsträng, plus texten personen
faktiskt godkände. IP och tidpunkt finns där för att kunna visa när och hur
samtycket lämnades om någon frågar.

Varje utskick innehåller en avanmälningslänk med en egen nyckel per adress,
och `List-Unsubscribe` i mejlhuvudet så att Gmail och Outlook visar sin egen
knapp. Avanmälda ligger kvar i databasen som `avanmald` — raderar vi raden
kan samma adress hamna på listan igen vid nästa import, och då är avanmälan
värdelös.

Alexander ska kunna svara på tre frågor: vad vi sparar, varför, och hur man
tas bort. Det står ovan.

## Säkerheten i korthet

- Adminlösenordet finns bara som PBKDF2-hash i en krypterad miljövariabel.
- Sessionen är en slumpad nyckel i en HttpOnly-kaka; databasen sparar bara
  en hash av den.
- Fem misslyckade inloggningar per kvart och IP, sedan paus.
- Fem anmälningar per timme och IP, plus en honungsfälla i formuläret.
- Alla databasanrop är parametriserade.
- `src/` ligger utanför `public/` och serveras aldrig.

## Designen

Alexander valde "Editorial"-förslaget. Efter hans genomgång:

- Nedräkningen är borttagen.
- Paletten är samplad direkt ur hans logotypbild: bakgrund `#000E02`
  (mörkgrön, nästan svart) och linjer/text `#EFEDE3` (varm cream). Guldet
  från skissen är borta — varumärket är två färger.
- Allt sken är borttaget: radialgradienterna bakom loggan, skuggorna på
  märket och den ljusare panelfärgen. Sidan är en enda platt `#000E02` rakt
  igenom och märket står som ren linjeteckning. Det var dimman runt de
  tunna linjerna som fick heron att se plottrig ut.
- Loggan är ren SVG med geometrin uppmätt ur hans bild: versalhöjd 213,
  linjebredd 9, D-bredd 200, O-bredd 218 (liggande ellips), mellanrum 85
  och 84, SÖDERMALM 61 % av märkets bredd. Skarp i alla storlekar och
  oberoende av typsnittsladdning.
- Sektionerna "Om oss" och "Hitta hit" är borttagna på Alexanders begäran.
  Kvar är hero, mejlanmälan, jobba-hos-oss-raden och sidfoten. Stilarna för
  de borttagna delarna (`.cols`, `.marks`, `.cards`) står kvar i
  `style.css` som ram att bygga vidare i när meny och bilder kommer.

## Vad som inte är på plats än

- **Texterna** är platshållare skrivna utifrån Instagram-bion. Alexander bör
  skriva om dem. Sök på `TODO` i filerna.
- **Bilder.** Sidan klarar sig utan, men en interiörbild skulle lyfta den.
- **Datumet i öppningsmejlet.** Mallen i adminvyn säger DATUM.

## Kontaktuppgifter som används på sidan

- Mejl: alexander@diosodermalm.se
- Instagram: @dio.sodermalm
