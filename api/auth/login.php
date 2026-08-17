<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');


/*
|--------------------------------------------------------------------------
| Admin credentials
|--------------------------------------------------------------------------
*/

$adminUsername = 'admin';
$adminPassword = '1234';


/*
|--------------------------------------------------------------------------
| Request method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Noto‘g‘ri so‘rov.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get login data
|--------------------------------------------------------------------------
*/

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';


if ($login === '' || $password === '') {

    echo json_encode([
        'success' => false,
        'message' => 'Login va parolni kiriting.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| ADMIN LOGIN
|--------------------------------------------------------------------------
*/

if (
    $login === $adminUsername &&
    $password === $adminPassword
) {

    session_regenerate_id(true);

    $_SESSION['is_admin'] = true;
    $_SESSION['admin_username'] = $adminUsername;

    /*
    | Admin does not need a normal user ID.
    */

    unset($_SESSION['user_id']);
    unset($_SESSION['username']);


    echo json_encode([
        'success' => true,
        'message' => 'Admin sifatida kirildi.',
        'redirect' => 'admin/index.php'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| NORMAL USER LOGIN
|--------------------------------------------------------------------------
*/

$loginSafe = mysqli_real_escape_string(
    $conn,
    $login
);


$result = mysqli_query(
    $conn,
    "SELECT
        id,
        username,
        password_hash
     FROM users
     WHERE username = '{$loginSafe}'
        OR email = '{$loginSafe}'
     LIMIT 1"
);


if (!$result) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Server xatosi.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


$user = mysqli_fetch_assoc($result);


if (
    !$user ||
    !password_verify(
        $password,
        $user['password_hash']
    )
) {

    echo json_encode([
        'success' => false,
        'message' => 'Login yoki parol noto‘g‘ri.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| User session
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);

$_SESSION['user_id'] =
    (int) $user['id'];

$_SESSION['username'] =
    $user['username'];

$_SESSION['is_admin'] = false;


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true,
    'message' => 'Kirish muvaffaqiyatli.',
    'redirect' => 'dashboard.php'
], JSON_UNESCAPED_UNICODE);

exit;