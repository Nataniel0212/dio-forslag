<?php
declare(strict_types=1);
defined('DIO') || exit;

/* Inloggningen till adminsidan. Egen fil eftersom både översikten och
   utskickssidan behöver den. */

function starta_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,               // ingen JavaScript-åtkomst till kakan
        'samesite' => 'Lax',              // följer inte med anrop från andra sajter
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_name('dio_admin');
    session_start();
}

function ar_inloggad(): bool
{
    starta_session();
    return ($_SESSION['admin'] ?? false) === true;
}

function logga_in(): void
{
    starta_session();
    // Nytt sessions-id vid inloggning, annars går en session som satts
    // före inloggningen att återanvända efteråt.
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
}

function logga_ut(): void
{
    starta_session();
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

/** Sidor som kräver inloggning börjar med det här anropet. */
function kraev_inloggning(): void
{
    if (!ar_inloggad()) {
        header('Location: index.php');
        exit;
    }
}
