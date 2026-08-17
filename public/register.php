<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireGuest();
?>

<!DOCTYPE html>
<html lang="uz">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Ro‘yxatdan o‘tish — Oson Sertifikat</title>
    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<main class="auth-page">

    <section class="auth-container">

        <div class="auth-header">

            <a
                href="index.php"
                class="auth-logo"
            >
                Oson Sertifikat
            </a>

            <h1 class="auth-title">
                Ro‘yxatdan o‘tish
            </h1>

            <p class="auth-subtitle">
                Oson Sertifikat hisobingizni yarating.
            </p>

        </div>


        <form
            id="registerForm"
            class="auth-form"
            novalidate
        >

            <div class="form-group">

                <label
                    for="username"
                    class="form-label"
                >
                    Foydalanuvchi nomi
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-input"
                    autocomplete="username"
                    minlength="3"
                    maxlength="50"
                    required
                >

            </div>


            <div class="form-group">

                <label
                    for="email"
                    class="form-label"
                >
                    Email

                    <span class="form-optional">
                        (ixtiyoriy)
                    </span>

                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    autocomplete="email"
                >

            </div>


            <div class="form-group">

                <label
                    for="password"
                    class="form-label"
                >
                    Parol
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-input"
                    autocomplete="new-password"
                    minlength="6"
                    required
                >

            </div>


            <div class="form-group">

                <label
                    for="passwordConfirm"
                    class="form-label"
                >
                    Parolni tasdiqlang
                </label>

                <input
                    type="password"
                    id="passwordConfirm"
                    name="password_confirm"
                    class="form-input"
                    autocomplete="new-password"
                    minlength="6"
                    required
                >

            </div>


            <div
                id="registerMessage"
                class="form-message"
                role="alert"
            ></div>


            <button
                type="submit"
                class="auth-button"
                id="registerButton"
            >
                Ro‘yxatdan o‘tish
            </button>

        </form>


        <div class="auth-footer">

            <span>
                Hisobingiz bormi?
            </span>

            <a
                href="login.php"
                class="auth-link"
            >
                Kirish
            </a>

        </div>

    </section>

</main>


<script src="assets/js/auth.js"></script>

<script>
    initRegisterForm();
</script>

</body>

</html>