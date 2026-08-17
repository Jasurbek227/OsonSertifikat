<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Hardcoded admin credentials
|--------------------------------------------------------------------------
*/

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');


/*
|--------------------------------------------------------------------------
| Start session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Check admin login
|--------------------------------------------------------------------------
*/

function requireAdmin(): void
{
    if (
        !isset($_SESSION['is_admin']) ||
        $_SESSION['is_admin'] !== true
    ) {

        header('Location: ../login.php');

        exit;
    }
}