<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Noto‘g‘ri so‘rov.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

$errors = [];

if ($username === '') {
    $errors[] = 'Foydalanuvchi nomini kiriting.';
} elseif (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
    $errors[] = 'Foydalanuvchi nomi 3–50 belgidan iborat bo‘lishi kerak.';
} elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    $errors[] = 'Foydalanuvchi nomida faqat harflar, raqamlar va _ ishlatilishi mumkin.';
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email manzili noto‘g‘ri.';
}

if (strlen($password) < 6) {
    $errors[] = 'Parol kamida 6 belgidan iborat bo‘lishi kerak.';
}

if ($password !== $passwordConfirm) {
    $errors[] = 'Parollar mos kelmadi.';
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'errors' => $errors
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$usernameSafe = mysqli_real_escape_string($conn, $username);

if ($email === '') {
    $emailValue = 'NULL';
} else {
    $emailSafe = mysqli_real_escape_string($conn, $email);
    $emailValue = "'" . $emailSafe . "'";
}

$check = mysqli_query(
    $conn,
    "SELECT id
     FROM users
     WHERE username = '{$usernameSafe}'
        OR (email IS NOT NULL AND email = {$emailValue})
     LIMIT 1"
);

if (!$check) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Server xatosi.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (mysqli_num_rows($check) > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Bu foydalanuvchi nomi yoki email allaqachon ishlatilgan.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$passwordHashSafe = mysqli_real_escape_string($conn, $passwordHash);

$query = "
    INSERT INTO users (
        username,
        email,
        password_hash
    )
    VALUES (
        '{$usernameSafe}',
        {$emailValue},
        '{$passwordHashSafe}'
    )
";

if (!mysqli_query($conn, $query)) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Ro‘yxatdan o‘tishda xatolik yuz berdi.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Ro‘yxatdan o‘tish muvaffaqiyatli yakunlandi.'
], JSON_UNESCAPED_UNICODE);