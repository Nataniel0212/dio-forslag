# DIO Södermalm — startsida

"Öppnar snart"-sida för DIO Södermalm. Ren HTML/CSS/JS, inga beroenden.
Typsnitt hämtas från Google Fonts (Cormorant Garamond + Jost).

    index.html      hela sidan
    css/style.css   stilmallen
    robots.txt      blockerar sökmotorer så länge sidan är en förhandsvisning
    arkiv/          de två förslag som inte valdes (publiceras inte)

## Tillfällig förhandsvisning

<https://nataniel0212.github.io/dio-forslag/>

Repot är publikt eftersom GitHub Pages kräver det på gratiskontot, men
sidan har `noindex` och `robots.txt` blockerar allt — sökmotorer ska
alltså inte plocka upp den.

## Så testar du lokalt

    python -m http.server 8123

Sedan <http://127.0.0.1:8123/>. (Att dubbelklicka på `index.html` går också.)

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

## Vad som inte är på plats än

- **Mejlformuläret sparar ingenting.** Det validerar adressen och visar ett
  kvitto, men skickar inte iväg något. Vi behöver välja var adresserna ska
  landa innan det kopplas på — t.ex. Mailchimp/Brevo/Buttondown, eller en
  egen liten endpoint. Sätt `action`/`method` på `<form class="signup">`
  när valet är gjort.
- **`noindex` måste bort** när sidan går live på riktig domän. Ligger som
  kommentar högst upp i `index.html`.
- **Texterna** är platshållare skrivna utifrån Instagram-bion. Alexander bör
  skriva om dem. Sök på `TODO` i filerna.
- **Adress.** Bara "Medborgarplatsen" är känt, inte gatuadressen.
- **Öppettider** meddelas senare.
- **Bilder.** Sidan klarar sig utan, men en interiörbild skulle lyfta
  "Om oss".

## Kontaktuppgifter som används på sidan

- Mejl: alexander@diosodermalm.se
- Instagram: @dio.sodermalm

## Riv förhandsvisningen när sidan flyttar till riktig domän

    gh repo delete Nataniel0212/dio-forslag --yes
