<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/admin_auth.php';

requireAdmin();

$pageTitle = 'Admin Panel';

function getTableCount(
    mysqli $conn,
    string $table
): int {
    $allowedTables = [
        'users',
        'questions',
        'blocks'
    ];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $result = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM `$table`"
    );

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);

    return (int) ($row['total'] ?? 0);
}

$userCount = getTableCount($conn, 'users');
$questionCount = getTableCount($conn, 'questions');
$blockCount = getTableCount($conn, 'blocks');
?>

<link rel="stylesheet" href="../assets/css/admin.css">

<main class="admin-page">

    <section class="admin-dashboard">

        <div class="admin-page-header">

            <div>

                <span class="admin-eyebrow">
                    ADMIN PANEL
                </span>

                <h1 class="admin-page-title">
                    Dashboard
                </h1>

                <p class="admin-page-description">
                    Oson Sertifikat boshqaruv paneli
                </p>

            </div>

            <div class="admin-header-actions">

                <a
                    href="../dashboard.php"
                    class="btn btn-outline-light"
                >
                    <i data-lucide="external-link"></i>
                    Saytga o'tish
                </a>

            </div>

        </div>

        <section class="admin-stats-grid">

            <div class="admin-stat-card">
                <div class="admin-stat-icon admin-stat-icon-blue">
                    <i data-lucide="users"></i>
                </div>

                <div class="admin-stat-content">
                    <span class="admin-stat-label">
                        Foydalanuvchilar
                    </span>
                    <strong class="admin-stat-value">
                        <?= $userCount ?>
                    </strong>
                </div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-icon admin-stat-icon-blue">
                    <i data-lucide="file-question"></i>
                </div>

                <div class="admin-stat-content">
                    <span class="admin-stat-label">
                        Savollar
                    </span>
                    <strong class="admin-stat-value">
                        <?= $questionCount ?>
                    </strong>
                </div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-icon admin-stat-icon-blue">
                    <i data-lucide="layers"></i>
                </div>

                <div class="admin-stat-content">
                    <span class="admin-stat-label">
                        Bloklar
                    </span>
                    <strong class="admin-stat-value">
                        <?= $blockCount ?>
                    </strong>
                </div>
            </div>

        </section>

        <section class="admin-section">

            <div class="admin-section-header">

                <div>
                    <h2 class="admin-section-title">
                        Boshqaruv
                    </h2>

                    <p class="admin-section-description">
                        Tizim bo‘limlarini boshqarish
                    </p>
                </div>

            </div>

            <div class="admin-management-grid admin-management-grid-fixed">

                <a
                    href="questions.php"
                    class="admin-management-card"
                >
                    <div class="admin-management-icon">
                        <i data-lucide="file-question"></i>
                    </div>

                    <div class="admin-management-content">
                        <h3>Savollar</h3>

                        <p>
                            Savollarni ko‘rish, qo‘shish va tahrirlash
                        </p>
                    </div>

                    <i
                        data-lucide="arrow-up-right"
                        class="admin-card-arrow"
                    ></i>
                </a>

                <a
                    href="blocks.php"
                    class="admin-management-card"
                >
                    <div class="admin-management-icon">
                        <i data-lucide="layers"></i>
                    </div>

                    <div class="admin-management-content">
                        <h3>Bloklar</h3>

                        <p>
                            Savol bloklarini boshqarish
                        </p>
                    </div>

                    <i
                        data-lucide="arrow-up-right"
                        class="admin-card-arrow"
                    ></i>
                </a>

                <a
                    href="users.php"
                    class="admin-management-card"
                >
                    <div class="admin-management-icon">
                        <i data-lucide="users"></i>
                    </div>

                    <div class="admin-management-content">
                        <h3>Foydalanuvchilar</h3>

                        <p>
                            Foydalanuvchilarni ko‘rish va boshqarish
                        </p>
                    </div>

                    <i
                        data-lucide="arrow-up-right"
                        class="admin-card-arrow"
                    ></i>
                </a>

                <a
                    href="images.php"
                    class="admin-management-card"
                >
                    <div class="admin-management-icon">
                        <i data-lucide="images"></i>
                    </div>

                    <div class="admin-management-content">
                        <h3>Rasmlar</h3>

                        <p>
                            Rasmlarni yuklang va boshqaring
                        </p>
                    </div>

                    <i
                        data-lucide="arrow-up-right"
                        class="admin-card-arrow"
                    ></i>
                </a>

            </div>

            <a
                href="question_create.php"
                class="admin-create-question-wide"
            >

                <span class="admin-wide-action-icon">
                    <i data-lucide="plus"></i>
                </span>

                <span class="admin-wide-action-content">

                    <strong>
                        Savol yaratish
                    </strong>

                    <small>
                        Yangi test yoki yozma savol qo‘shing
                    </small>

                </span>

                <i
                    data-lucide="arrow-up-right"
                    class="admin-wide-action-arrow"
                ></i>

            </a>

        </section>

    </section>

</main>

<script src="https://unpkg.com/lucide@latest"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>
<script src="../assets/js/admin.js"></script>
<?php
require_once __DIR__ . '/../../layout/footer.php';
?>
