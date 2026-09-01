# DIO Södermalm — startsida

Tre frontend-skisser på en "öppnar snart"-sida. Öppna `index.html` i roten
för att se alla tre bredvid varandra.

## Tillfällig förhandsvisning (att skicka till Alexander)

- Valsida: <https://nataniel0212.github.io/dio-forslag/>
- Förslag 1: <https://nataniel0212.github.io/dio-forslag/forslag-1/>
- Förslag 2: <https://nataniel0212.github.io/dio-forslag/forslag-2/>
- Förslag 3: <https://nataniel0212.github.io/dio-forslag/forslag-3/>

Repot är publikt eftersom GitHub Pages kräver det på gratiskontot, men
varje sida har `noindex` och `robots.txt` blockerar allt — sökmotorer ska
alltså inte plocka upp det. Ta bort `<meta name="robots">` när sidan går
live på den riktiga domänen.

Riv förhandsvisningen när ni valt:

    gh repo delete Nataniel0212/dio-forslag --yes

    index.html          valsida (intern, publiceras inte)
    forslag-1/          "Ridån"      — helskärm, centrerat, om/kontakt under vecket
    forslag-2/          "Delad"      — två halvor, flikar i stället för scroll
    forslag-3/          "Editorial"  — hero med nedräkning + sektioner

Ren HTML/CSS/JS, inga beroenden. Typsnitt hämtas från Google Fonts
(Cormorant Garamond + Jost).

## Så testar du lokalt

    python -m http.server 8123

Sedan http://127.0.0.1:8123/ i webbläsaren. (Att bara dubbelklicka på
`index.html` fungerar också.)

## Vad som inte är på plats än

- **Mejlformuläret sparar ingenting.** Det validerar adressen och visar ett
  kvitto, men skickar inte iväg något. Vi behöver välja var adresserna ska
  landa innan det kopplas på — t.ex. Mailchimp/Brevo/Buttondown, eller en
  egen liten endpoint. Sätt `action`/`method` på `<form class="signup">`
  när valet är gjort.
- **Bilder.** Förslag 2 har en platshållare där interiörbilden ska ligga
  (`.stage` i CSS:en). Förslag 1 och 3 klarar sig utan bild.
- **Loggan** är byggd i text (Cormorant Garamond med spärrning, I:et som en
  tunn stapel). Ligger nära originalet, men om det finns en SVG eller
  vektorfil från formgivaren är den bättre — den byts in direkt i `.logo`.
- **Texterna** är platshållare skrivna utifrån Instagram-bion. Alexander bör
  skriva om dem. Sök på `TODO` i filerna.
- **Premiärdatum.** Nedräkningen i förslag 3 står på ett gissat datum —
  ändra `data-open` på `<ul class="countdown">`.
- **Adress.** Bara "Medborgarplatsen" är känt, inte gatuadressen.

## Kontaktuppgifter som används i skisserna

- Mejl: alexander@diosodermalm.se
- Instagram: @dio.sodermalm
