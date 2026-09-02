<?php
/* ============================================================
   MALL — kopiera till inc/config.php och fyll i.
   config.php ligger i .gitignore: den innehåller lösenord och ska
   aldrig hamna i repot.
   ============================================================ */

defined('DIO') || exit;

/* ---------- Databas ----------
   Uppgifterna står i one.coms kontrollpanel under MySQL/Databas.
   Värdnamnet är oftast något i stil med xxxxx.mysql.service.one.com. */
const DB_DSN   = 'mysql:host=VARDNAMN;dbname=DATABASNAMN;charset=utf8mb4';
const DB_USER  = 'ANVANDARNAMN';
const DB_LOSEN = 'LOSENORD';

/* ---------- Adminsidan ----------
   Här ligger en hash, inte lösenordet. Öppna /admin/losenord.php en gång,
   skriv ditt lösenord, klistra in raden du får och radera sedan filen.
   Så länge ADMIN_HASH är tom går det inte att logga in. */
const ADMIN_HASH = '';

/* ---------- Avsändare för utskicken ----------
   Adressen måste ligga på samma domän som sajten, annars stoppas mejlen
   som förfalskade (SPF). */
const AVSANDARE_NAMN  = 'DIO Södermalm';
const AVSANDARE_EPOST = 'alexander@diosodermalm.se';

/* Full adress till sajten, utan avslutande snedstreck. Används för att
   bygga avanmälningslänkar i mejlen. */
const SAJT_URL = 'https://diosodermalm.se';
