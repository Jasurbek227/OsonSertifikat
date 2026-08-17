<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_auth.php';

requireGuest();
?>

<!DOCTYPE html>
<html lang="uz">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kirish — Oson Sertifikat</title>
    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

    <main class="auth-page">

        <section class="auth-container">

            <div class="auth-header">

                <a href="index.php" class="auth-logo">
                    Oson Sertifikat
                </a>

                <h1 class="auth-title">
                    Kirish
                </h1>

                <p class="auth-subtitle">
                    Hisobingizga kiring va tayyorgarlikni davom ettiring.
                </p>

            </div>


            <form id="loginForm" class="auth-form" novalidate>

                <div class="form-group">

                    <label for="login" class="form-label">
                        Login yoki email
                    </label>

                    <input type="text" id="login" name="login" class="form-input" autocomplete="username" required>

                </div>


                <div class="form-group">

                    <label for="password" class="form-label">
                        Parol
                    </label>

                    <input type="password" id="password" name="password" class="form-input"
                        autocomplete="current-password" required>

                </div>


                <div id="loginMessage" class="form-message" role="alert"></div>


                <button type="submit" class="auth-button" id="loginButton">
                    Kirish
                </button>

            </form>


            <div class="auth-footer">

                <span>
                    Hisobingiz yo‘qmi?
                </span>

                <a href="register.php" class="auth-link">
                    Ro‘yxatdan o‘tish
                </a>

            </div>

        </section>

    </main>


    <script src="assets/js/auth.js"></script>

    <script>
        initLoginForm();
    </script>

</body>

</html>