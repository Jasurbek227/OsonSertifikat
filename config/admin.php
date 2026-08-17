<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Hardcoded admin credentials
|--------------------------------------------------------------------------
|
| Change these whenever you want.
|
*/

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', '1234');


/*
|--------------------------------------------------------------------------
| Admin session
|--------------------------------------------------------------------------
*/

function isAdmin(): bool
{
    return isset($_SESSION['is_admin']) &&
           $_SESSION['is_admin'] === true;
}


function requireAdmin(): void
{
    if (!isAdmin()) {
        header('Location: ../login.php');
        exit;
    }
}