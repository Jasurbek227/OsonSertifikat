<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Oson Sertifikat';

?>

<!DOCTYPE html>
<html lang="uz">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($pageTitle) ?> — Oson Sertifikat
    </title>

    <!--<link
        rel="stylesheet"
        href="assets/css/style.css"
    > -->

</head>

<body>

<div class="app">

    <?php require __DIR__ . '/navigation.php'; ?>

    <main class="main-content">