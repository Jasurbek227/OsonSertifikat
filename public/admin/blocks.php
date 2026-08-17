<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/admin_auth.php';

requireAdmin();

$pageTitle = 'Bloklar';
$message = '';
$messageType = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'toggle'
) {
    $blockId = (int) ($_POST['block_id'] ?? 0);

    if ($blockId > 0) {
        $query = "
            UPDATE blocks
            SET is_active = IF(is_active = 1, 0, 1)
            WHERE id = $blockId
            LIMIT 1
        ";

        if (mysqli_query($conn, $query)) {
            $message = 'Blok holati o‘zgartirildi.';
            $messageType = 'success';
        } else {
            $message = 'Blok holatini o‘zgartirishda xatolik.';
            $messageType = 'error';
        }
    }
}

$search = trim((string) ($_GET['search'] ?? ''));
$activeFilter = trim((string) ($_GET['active'] ?? ''));

$conditions = ['1 = 1'];

if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $search);

    $conditions[] = "
        (
            b.name LIKE '%$safeSearch%'
            OR b.description LIKE '%$safeSearch%'
            OR CAST(b.id AS CHAR) LIKE '%$safeSearch%'
        )
    ";
}

if ($activeFilter === 'active') {
    $conditions[] = 'b.is_active = 1';
}

if ($activeFilter === 'inactive') {
    $conditions[] = 'b.is_active = 0';
}

$where = implode(' AND ', $conditions);

$blocks = [];

$query = "
    SELECT
        b.id,
        b.name,
        b.generation,
        b.description,
        b.is_active,
        b.created_at,
        COUNT(bq.question_id) AS question_count
    FROM blocks b
    LEFT JOIN block_questions bq
        ON bq.block_id = b.id
    WHERE $where
    GROUP BY
        b.id,
        b.name,
        b.generation,
        b.description,
        b.is_active,
        b.created_at
    ORDER BY b.id DESC
";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $blocks[] = $row;
    }
}
?>

<link rel="stylesheet" href="../assets/css/admin.css">

<section class="admin-page admin-content-page">

    <div class="admin-page-header">

        <div>
            <span class="admin-eyebrow">ADMIN PANEL</span>

            <h1 class="admin-page-title">
                Bloklar
            </h1>
        </div>

        <div class="admin-header-actions">
            <a
                href="block_create.php"
                class="btn btn-primary admin-action-button"
            >
                <i data-lucide="plus"></i>
                Yangi blok
            </a>
        </div>

    </div>

    <?php if ($message !== ''): ?>

        <div class="admin-message admin-message-<?php echo $messageType; ?>">
            <?php
            echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </div>

    <?php endif; ?>

    <form
        method="GET"
        class="admin-filter-card admin-block-filter-card"
    >

        <div class="admin-filter-grid admin-block-filter-grid">

            <div class="admin-form-field">
                <label>Qidirish</label>

                <input
                    type="text"
                    name="search"
                    value="<?php
                    echo htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>"
                    placeholder="Blok nomi yoki ID"
                >
            </div>

            <div class="admin-form-field">
                <label>Holat</label>

                <select name="active">

                    <option value="">
                        Barchasi
                    </option>

                    <option
                        value="active"
                        <?php
                        echo $activeFilter === 'active'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Faol
                    </option>

                    <option
                        value="inactive"
                        <?php
                        echo $activeFilter === 'inactive'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Nofaol
                    </option>

                </select>
            </div>

            <div class="admin-filter-button">
                <button
                    type="submit"
                    class="btn btn-outline-light"
                >
                    <i data-lucide="filter"></i>
                    Filtrlash
                </button>
            </div>

        </div>

    </form>

    <div class="admin-list">

        <?php if (count($blocks) > 0): ?>

            <?php foreach ($blocks as $block): ?>

                <div class="admin-list-row">

                    <div class="admin-list-leading">

                        <div class="admin-list-icon">
                            <i data-lucide="layers"></i>
                        </div>

                        <div class="admin-list-main">

                            <div class="admin-list-title-row">

                                <h3 class="admin-list-title">
                                    <?php
                                    echo htmlspecialchars(
                                        $block['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </h3>

                                <?php if (
                                    (int) $block['is_active'] === 1
                                ): ?>

                                    <span class="admin-status admin-status-success">
                                        Faol
                                    </span>

                                <?php else: ?>

                                    <span class="admin-status admin-status-muted">
                                        Nofaol
                                    </span>

                                <?php endif; ?>

                            </div>

                            <p class="admin-list-description">

                                <?php if (
                                    !empty($block['description'])
                                ): ?>

                                    <?php
                                    echo htmlspecialchars(
                                        $block['description'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                <?php else: ?>

                                    Tavsif berilmagan.

                                <?php endif; ?>

                            </p>

                            <div class="admin-list-meta">

                                <span>
                                    <i data-lucide="hash"></i>
                                    #<?php echo (int) $block['id']; ?>
                                </span>

                                <span>
                                    <i data-lucide="circle-help"></i>
                                    <?php
                                    echo (int) $block['question_count'];
                                    ?>
                                    ta savol
                                </span>

                            </div>

                        </div>

                    </div>

                    <div class="admin-list-actions">

                        <a
                            href="block_edit.php?id=<?php echo (int) $block['id']; ?>"
                            class="btn btn-outline-light admin-small-button"
                        >
                            <i data-lucide="pencil"></i>
                            Tahrirlash
                        </a>

                        <a
                            href="block_edit.php?id=<?php echo (int) $block['id']; ?>&tab=questions"
                            class="btn btn-outline-light admin-small-button"
                        >
                            <i data-lucide="list-plus"></i>
                            Savollar
                        </a>

                        <form
                            method="POST"
                            class="admin-inline-form"
                            onsubmit="return confirm('Blok holatini o‘zgartirmoqchimisiz?');"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="toggle"
                            >

                            <input
                                type="hidden"
                                name="block_id"
                                value="<?php echo (int) $block['id']; ?>"
                            >

                            <?php if (
                                (int) $block['is_active'] === 1
                            ): ?>

                                <button
                                    type="submit"
                                    class="btn btn-danger admin-small-button"
                                >
                                    <i data-lucide="power"></i>
                                    O‘chirish
                                </button>

                            <?php else: ?>

                                <button
                                    type="submit"
                                    class="btn btn-outline-light admin-small-button"
                                >
                                    <i data-lucide="power"></i>
                                    Faollashtirish
                                </button>

                            <?php endif; ?>

                        </form>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="admin-empty">

                <div class="admin-empty-icon">
                    <i data-lucide="layers"></i>
                </div>

                <h3>
                    Bloklar topilmadi
                </h3>

                <p>
                    Yangi blok yarating.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>

<script src="https://unpkg.com/lucide@latest"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>

<?php
require_once __DIR__ . '/../../layout/footer.php';
?>
