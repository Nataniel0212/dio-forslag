<?php
declare(strict_types=1);
defined('DIO') || exit;

/* ============================================================
   Utgående mejl.
   All mejlsändning går genom den här filen. Byter vi längre fram från
   webbhotellets mail() till en tjänst som Brevo eller Postmark är det
   bara den här funktionen som skrivs om.
   ============================================================ */

/**
 * Skickar ett brev. Returnerar true om servern tog emot det för leverans
 * (vilket inte är samma sak som att det kom fram — studsar syns först i
 * avsändarens inkorg).
 *
 * @param string $till         mottagarens adress
 * @param string $amne         ämnesrad
 * @param string $text         brödtext, ren text
 * @param string $avanmal_lank full länk för avanmälan, tom om brevet inte
 *                             är ett listutskick
 */
function skicka_mail(string $till, string $amne, string $text, string $avanmal_lank = ''): bool
{
    if (!filter_var($till, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $avsandare = sprintf('%s <%s>', kodad_rubrik(AVSANDARE_NAMN), AVSANDARE_EPOST);

    $rubriker = [
        'From: ' . $avsandare,
        'Reply-To: ' . AVSANDARE_EPOST,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    /* List-Unsubscribe gör att Gmail och Outlook visar sin egen
       avanmälningsknapp. Det är halva skillnaden mellan att hamna i
       inkorgen och i skräpposten när man mejlar en lista. */
    if ($avanmal_lank !== '') {
        $rubriker[] = 'List-Unsubscribe: <' . $avanmal_lank . '>';
        $rubriker[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
    }

    /* Radbrytningar ska vara \r\n i mejl, och rader längre än ~990 tecken
       är otillåtna — wordwrap håller oss innanför. */
    $brodtext = wordwrap(str_replace(["\r\n", "\r"], "\n", $text), 78, "\r\n");

    // Femte argumentet sätter kuvertavsändaren, så att studsar går
    // tillbaka till oss i stället för till webbhotellets systemkonto.
    return @mail(
        $till,
        kodad_rubrik($amne),
        $brodtext,
        implode("\r\n", $rubriker),
        '-f' . AVSANDARE_EPOST
    );
}

/** Ämnesrader och namn får bara innehålla ASCII i mejlhuvudet. Å, Ä och Ö
 *  måste därför kodas, annars kommer de fram som frågetecken. */
function kodad_rubrik(string $text): string
{
    $text = str_replace(["\r", "\n"], '', $text); // aldrig injicerade rubriker
    if (preg_match('/^[\x20-\x7E]*$/', $text) === 1) {
        return $text;
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

/** Full länk som avanmäler en enskild adress. */
function avanmal_lank(string $token): string
{
    return rtrim(SAJT_URL, '/') . '/api/avanmal.php?token=' . urlencode($token);
}
