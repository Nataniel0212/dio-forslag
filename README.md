# DIO Södermalm — startsida

"Öppnar snart"-sida för DIO Södermalm med en egen liten backend för
mejllistan. Ren HTML/CSS/JS i frontend, PHP och MySQL i botten, inga
ramverk och inga beroenden.
Typsnitt hämtas från Google Fonts (Cormorant Garamond + Jost).

    index.html          hela sidan
    css/style.css       stilmallen
    robots.txt          blockerar sökmotorer så länge sidan är en förhandsvisning

    inc/                serverkod som aldrig nås direkt (skyddad av .htaccess)
      config.php        databaslösenord och admin-hash — skapas på servern
      config.example.php  mallen att utgå från
      bootstrap.php     gemensam start: fel, config, hjälpfunktioner
      db.php            anslutning och tabeller
      mail.php          allt utgående mejl går genom den här filen

    api/                det sidan pratar med
      anmal.php         tar emot en adress från formuläret
      avanmal.php       avanmälan via länken i mejlen

    admin/              Alexanders vy
      index.php         inloggning, antal anmälda, senaste 50
      utskick.php       skriv och skicka öppningsmejlet
      export.php        ladda ner listan som CSV
      losenord.php      engångsverktyg som sätter admin-lösenordet

    arkiv/              de två förslag som inte valdes (publiceras inte)

## Tillfällig förhandsvisning

<https://nataniel0212.github.io/dio-forslag/>

GitHub Pages kör bara statiska filer, så **formuläret fungerar inte där** —
det behöver PHP. Förhandsvisningen visar designen, inget annat. Skriver man
in en adress svarar sidan att servern inte går att nå.

Repot är publikt eftersom GitHub Pages kräver det på gratiskontot, men
sidan har `noindex` och `robots.txt` blockerar allt — sökmotorer ska
alltså inte plocka upp den.

## Så testar du lokalt

Backenden behöver PHP och MySQL. Enklast med Docker:

    docker network create dio-test

    docker run -d --name dio-mysql --network dio-test \
      -e MYSQL_ROOT_PASSWORD=rot-test -e MYSQL_DATABASE=dio \
      -e MYSQL_USER=dio -e MYSQL_PASSWORD=dio-test mysql:8

    docker build -t dio-php .          # PHP med pdo_mysql, se Dockerfile
    docker run -d --name dio-web --network dio-test -p 8080:8080 \
      -v "$PWD:/app" -w /app dio-php php -S 0.0.0.0:8080

Kopiera sedan `inc/config.example.php` till `inc/config.php` och peka den
mot `dio-mysql`. Sidan ligger på <http://127.0.0.1:8080/>.

Vill du bara titta på designen räcker `python -m http.server 8123`.

## Lägga upp på one.com

1. **Ladda upp filerna** till `public_html` via one.coms filhanterare eller
   FTP: `index.html`, `css/`, `robots.txt`, `inc/`, `api/` och `admin/`.
   `arkiv/` ska inte upp.
2. **Skapa en MySQL-databas** i one.coms kontrollpanel. Skriv upp värdnamn,
   databasnamn, användare och lösenord.
3. **Kopiera `inc/config.example.php` till `inc/config.php`** och fyll i
   databasuppgifterna, avsändaradressen och `SAJT_URL`. Tabellerna skapar
   sig själva första gången någon besöker sidan.
4. **Sätt admin-lösenordet:** öppna `/admin/losenord.php`, skriv ett
   lösenord, klistra in raden du får i `inc/config.php` och **radera sedan
   filen från servern**. Sidan stänger sig själv så fort lösenordet är satt,
   men den ska ändå inte ligga kvar.
5. **Kontrollera att `inc/` är skyddad:** gå till
   `https://diosodermalm.se/inc/config.php`. Du ska få 403. Får du en tom
   sida i stället är det också säkert — PHP kör filen och den avbryter
   direkt — men då fungerar inte `.htaccess`, och det vill vi veta om.
6. **Testa formuläret** på riktigt och kontrollera att adressen dyker upp i
   `/admin/`.
7. **Skicka ett testmejl** från `/admin/utskick.php` till dig själv innan
   listan används skarpt.

### Innan sidan går live på riktigt

- Ta bort `<meta name="robots" content="noindex, nofollow">` högst upp i
  `index.html`.
- Byt ut `robots.txt` mot en som släpper in sökmotorer men håller admin
  utanför:

      User-agent: *
      Disallow: /admin/
      Disallow: /api/

- Stäng av GitHub Pages för repot. Annars ligger samma sida på två adresser,
  och Google får två kopior att välja mellan.

## Mejl

Allt utgående mejl går genom `skicka_mail()` i `inc/mail.php` och använder
webbhotellets `mail()`. Det räcker gott för en lista i den här storleken.

Avsändaradressen **måste** ligga på diosodermalm.se — annars ser
mottagarens server att mejlet inte kommer från den domän det utger sig för
(SPF) och lägger det i skräpposten. Kontrollera i one.coms kontrollpanel att
SPF och DKIM är påslagna för domänen.

Öppningsmejlet skickas i omgångar om 20. Varje skickat brev loggas i
`utskick_logg`, så ett utskick som avbryts går att fortsätta utan att någon
får brevet två gånger — ladda bara om `/admin/utskick.php`.

Säger adminsidan att mejlet inte kunde skickas är `mail()` avstängd eller
strypt hos one.com. Då byter vi till SMTP mot deras mejlserver, och det är
bara `skicka_mail()` som behöver skrivas om.

## Personuppgifter

Vi sparar mejladress, tidpunkt, IP och webbläsarsträng, plus texten
personen faktiskt godkände. IP och tidpunkt är där för att kunna visa när
och hur samtycket lämnades om någon frågar.

Varje utskick innehåller en avanmälningslänk med en egen nyckel per adress,
och `List-Unsubscribe` i mejlhuvudet så att Gmail och Outlook visar sin egen
knapp. Avanmälda ligger kvar i databasen som `avanmald` — raderar vi raden
kan samma adress hamna på listan igen vid nästa import, och då är avanmälan
värdelös.

Alexander ska kunna svara på tre frågor: vad vi sparar, varför, och hur man
tas bort. Det står ovan.

## Designen

Alexander valde "Editorial"-förslaget. Efter hans genomgång:

- Nedräkningen är borttagen.
- Paletten är samplad direkt ur hans logotypbild: bakgrund `#000E02`
  (mörkgrön, nästan svart) och linjer/text `#EFEDE3` (varm cream). Guldet
  från skissen är borta — varumärket är två färger, och djupet kommer från
  opacitet i stället för fler kulörer.
- Loggan är omritad som ren SVG med geometrin uppmätt ur hans bild:
  versalhöjd 213, linjebredd 9, D-bredd 200, O-bredd 218 (liggande ellips),
  mellanrum 85 och 84, SÖDERMALM 61 % av märkets bredd. Vektor, alltså
  skarp i alla storlekar och oberoende av typsnittsladdning.
- Raden "Vill du jobba med oss? Kontakta …" ligger längst ner, ovanför
  sidfoten.
- Sektionerna "Om oss" och "Hitta hit" är borttagna på Alexanders begäran.
  Kvar är hero, mejlanmälan, jobba-hos-oss-raden och sidfoten. Stilarna för
  de borttagna delarna (`.cols`, `.marks`, `.cards`/`.card`, `.section.alt`)
  står kvar i `style.css` — de används inte nu, men är ramen att bygga
  vidare i när meny och bilder kommer.

## Vad som inte är på plats än

- **Texterna** är platshållare skrivna utifrån Instagram-bion. Alexander bör
  skriva om dem. Sök på `TODO` i filerna.
- **Bilder.** Sidan klarar sig utan, men en interiörbild skulle lyfta sidan.
- **Datumet i öppningsmejlet.** Mallen i `admin/utskick.php` säger DATUM.

## Kontaktuppgifter som används på sidan

- Mejl: alexander@diosodermalm.se
- Instagram: @dio.sodermalm
