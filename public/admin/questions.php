<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_auth.php';
requireAdmin();

require_once __DIR__ . '/../../includes/db.php';

$pageTitle = 'Savollar';

$message = '';
$messageType = '';

if (isset($_GET['delete'])) {
    if ($_GET['delete'] === 'success') {
        $message = 'Savol butunlay o‘chirildi.';
        $messageType = 'success';
    } elseif ($_GET['delete'] === 'error') {
        $message = 'Savolni o‘chirishda xatolik yuz berdi.';
        $messageType = 'error';
    } elseif ($_GET['delete'] === 'invalid') {
        $message = 'Noto‘g‘ri savol ID.';
        $messageType = 'error';
    }
}

/*
|--------------------------------------------------------------------------
| Activate / deactivate
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'toggle'
) {
    $questionId = (int) ($_POST['question_id'] ?? 0);

    if ($questionId > 0) {
        $query = "
            UPDATE questions
            SET is_active = IF(is_active = 1, 0, 1)
            WHERE id = $questionId
            LIMIT 1
        ";

        if (mysqli_query($conn, $query)) {
            $message = 'Savol holati o‘zgartirildi.';
            $messageType = 'success';
        } else {
            $message = 'Savol holatini o‘zgartirishda xatolik.';
            $messageType = 'error';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim((string) ($_GET['search'] ?? ''));
$topicId = (int) ($_GET['topic_id'] ?? 0);
$type = trim((string) ($_GET['type'] ?? ''));
$activeFilter = trim((string) ($_GET['active'] ?? ''));

$conditions = ['1 = 1'];

if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $search);

    $conditions[] = "
        (
            q.text LIKE '%$safeSearch%'
            OR q.id LIKE '%$safeSearch%'
        )
    ";
}

if ($topicId > 0) {
    $conditions[] = "q.topic_id = $topicId";
}

if (
    in_array(
        $type,
        ['multiple_choice', 'six_option', 'written'],
        true
    )
) {
    $safeType = mysqli_real_escape_string($conn, $type);
    $conditions[] = "q.question_type = '$safeType'";
}

if ($activeFilter === 'active') {
    $conditions[] = 'q.is_active = 1';
}

if ($activeFilter === 'inactive') {
    $conditions[] = 'q.is_active = 0';
}

$where = implode(' AND ', $conditions);

/*
|--------------------------------------------------------------------------
| Topics
|--------------------------------------------------------------------------
*/
$topics = [];

$topicResult = mysqli_query(
    $conn,
    "
    SELECT id, name
    FROM topics
    ORDER BY id ASC
    "
);

if ($topicResult) {
    while ($topic = mysqli_fetch_assoc($topicResult)) {
        $topics[] = $topic;
    }
}

/*
|--------------------------------------------------------------------------
| Questions
|--------------------------------------------------------------------------
*/
$questions = [];

$query = "
    SELECT
        q.id,
        q.topic_id,
        q.question_type,
        q.text,
        q.is_new,
        q.is_active,
        t.name AS topic_name,

        (
            SELECT COUNT(*)
            FROM question_options qo
            WHERE qo.question_id = q.id
        ) AS option_count,

        (
            SELECT COUNT(*)
            FROM question_images qi
            WHERE qi.question_id = q.id
        ) AS image_count

    FROM questions q

    INNER JOIN topics t
        ON t.id = q.topic_id

    WHERE $where

    ORDER BY q.id DESC
";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $questions[] = $row;
    }
}

?>

<link rel="stylesheet" href="../assets/css/admin.css">

<section class="page-section admin-page">

    <a href="index.php" class="admin-page-back">
        <i data-lucide="arrow-left"></i>
        Dashboard
    </a>

    <div class="page-heading admin-compact-heading">

        <h1 class="page-title">
            Savollar
        </h1>

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


    <div class="admin-toolbar">

        <a
            href="question_create.php"
            class="admin-primary-button"
        >
            <i data-lucide="plus"></i>
            Yangi savol
        </a>

    </div>


    <form
        method="GET"
        class="admin-filter-form admin-question-filter-form"
    >

        <div class="admin-filter-field">

            <label>
                Qidirish
            </label>

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
                placeholder="Savol matni yoki ID"
            >

        </div>


        <div class="admin-filter-field">

            <label>
                Mavzu
            </label>

            <select name="topic_id">

                <option value="0">
                    Barchasi
                </option>

                <?php foreach ($topics as $topic): ?>

                    <option
                        value="<?php
                        echo (int) $topic['id'];
                        ?>"
                        <?php
                        echo $topicId ===
                            (int) $topic['id']
                            ? 'selected'
                            : '';
                        ?>
                    >
                        <?php
                        echo htmlspecialchars(
                            $topic['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="admin-filter-field">

            <label>
                Turi
            </label>

            <select name="type">

                <option value="">
                    Barchasi
                </option>

                <option
                    value="multiple_choice"
                    <?php
                    echo $type === 'multiple_choice'
                        ? 'selected'
                        : '';
                    ?>
                >
                    Variantli
                </option>

                <option
                    value="six_option"
                    <?php
                    echo $type === 'six_option'
                        ? 'selected'
                        : '';
                    ?>
                >
                    6 variantli
                </option>

                <option
                    value="written"
                    <?php
                    echo $type === 'written'
                        ? 'selected'
                        : '';
                    ?>
                >
                    Yozma
                </option>

            </select>

        </div>


        <div class="admin-filter-field">

            <label>
                Holat
            </label>

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


        <button
            type="submit"
            class="admin-secondary-button admin-filter-submit"
            title="Filtrlash"
        >
            <i data-lucide="filter"></i>
        </button>

    </form>


    <div class="admin-question-list">

        <?php if (count($questions) > 0): ?>

            <?php foreach ($questions as $question): ?>

                <?php
                $questionId =
                    (int) $question['id'];

                $isActive =
                    (int) $question['is_active'] === 1;
                ?>

                <div class="admin-question-row">

                    <div class="admin-question-id">
                        #<?php echo $questionId; ?>
                    </div>


                    <div class="admin-question-main">

                        <div class="admin-question-meta">

                            <span>
                                <?php
                                echo htmlspecialchars(
                                    $question['topic_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </span>


                            <span>

                                <?php
                                switch ($question['question_type']) {
                                    case 'multiple_choice':
                                        echo 'Variantli';
                                        break;

                                    case 'six_option':
                                        echo '6 variantli';
                                        break;

                                    case 'written':
                                        echo 'Yozma';
                                        break;
                                }
                                ?>

                            </span>


                            <?php if (
                                (int) $question['is_new'] === 1
                            ): ?>

                                <span>
                                    Yangi
                                </span>

                            <?php endif; ?>


                            <?php if (
                                (int) $question['option_count'] > 0
                            ): ?>

                                <span>
                                    <?php
                                    echo (int)
                                        $question['option_count'];
                                    ?>
                                    variant
                                </span>

                            <?php endif; ?>


                            <?php if (
                                (int) $question['image_count'] > 0
                            ): ?>

                                <span>
                                    Rasm
                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="admin-question-text">

                            <?php
                            echo nl2br(
                                htmlspecialchars(
                                    $question['text'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            );
                            ?>

                        </div>


                        <div class="admin-question-status">

                            <?php if ($isActive): ?>

                                <span class="admin-status admin-status-success">
                                    Faol
                                </span>

                            <?php else: ?>

                                <span class="admin-status admin-status-muted">
                                    Nofaol
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="admin-question-actions">

                        <!-- Edit -->
                        <a
                            href="question_edit.php?id=<?php echo $questionId; ?>"
                            class="admin-question-icon-button admin-question-edit-button"
                            title="Tahrirlash"
                            aria-label="Tahrirlash"
                        >
                            <i data-lucide="pencil"></i>
                        </a>


                        <!-- Permanent delete -->
                        <form
                            method="POST"
                            action="question_delete.php"
                            class="admin-question-inline-form"
                            onsubmit="
                                return confirm(
                                    'DIQQAT!\n\nBu savol butunlay o‘chiriladi.\nSavolga tegishli variantlar, blok biriktirmalari, urinishlar, xatolar va boshqa bog‘langan ma’lumotlar ham o‘chiriladi.\n\nDavom etasizmi?'
                                );
                            "
                        >

                            <input
                                type="hidden"
                                name="question_id"
                                value="<?php echo $questionId; ?>"
                            >

                            <button
                                type="submit"
                                class="admin-question-icon-button admin-question-delete-button"
                                title="Butunlay o‘chirish"
                                aria-label="Butunlay o‘chirish"
                            >
                                <i data-lucide="trash-2"></i>
                            </button>

                        </form>


                        <!-- Activate / deactivate -->
                        <form
                            method="POST"
                            class="admin-question-switch-form"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="toggle"
                            >

                            <input
                                type="hidden"
                                name="question_id"
                                value="<?php echo $questionId; ?>"
                            >


                            <label
                                class="admin-question-switch"
                                title="<?php
                                    echo $isActive
                                        ? 'Faol — o‘chirish'
                                        : 'Nofaol — faollashtirish';
                                ?>"
                            >

                                <input
                                    type="checkbox"
                                    <?php
                                    echo $isActive
                                        ? 'checked'
                                        : '';
                                    ?>
                                    aria-label="<?php
                                    echo $isActive
                                        ? 'Savol faol'
                                        : 'Savol nofaol';
                                    ?>"
                                >

                                <span
                                    class="admin-question-switch-track"
                                ></span>

                            </label>

                        </form>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="admin-empty">

                <div class="admin-empty-icon">
                    <i data-lucide="file-question"></i>
                </div>

                <h3>
                    Savollar topilmadi
                </h3>

                <p>
                    Filtrlarni o‘zgartiring yoki yangi savol yarating.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>


<script src="https://unpkg.com/lucide@latest"></script>

<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {

        document
            .querySelectorAll(
                '.admin-question-switch-form input[type="checkbox"]'
            )
            .forEach(
                function (checkbox) {

                    checkbox.addEventListener(
                        'change',
                        function () {

                            checkbox
                                .closest(
                                    'form'
                                )
                                .submit();

                        }
                    );

                }
            );


        if (
            typeof lucide !== 'undefined'
        ) {
            lucide.createIcons();
        }

    }
);
</script>


<?php
require_once __DIR__ . '/../../layout/footer.php';
?>
