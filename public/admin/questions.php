<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_auth.php';
requireAdmin();

require_once __DIR__ . '/../../includes/db.php';

$pageTitle = 'Savollar';

$message = '';
$messageType = '';


/*
|--------------------------------------------------------------------------
| Delete / deactivate question
|--------------------------------------------------------------------------
|
| We do not physically delete questions because existing blocks,
| attempts, mistake queues and other relations may reference them.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action'])
) {

    $action = $_POST['action'];

    if ($action === 'toggle') {

        $questionId = isset($_POST['question_id'])
            ? (int) $_POST['question_id']
            : 0;

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
}


/*
|--------------------------------------------------------------------------
| Search / filter
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim((string) $_GET['search'])
    : '';

$topicId = isset($_GET['topic_id'])
    ? (int) $_GET['topic_id']
    : 0;

$type = isset($_GET['type'])
    ? trim((string) $_GET['type'])
    : '';

$activeFilter = isset($_GET['active'])
    ? trim((string) $_GET['active'])
    : '';


$conditions = array();

$conditions[] = '1 = 1';


if ($search !== '') {

    $safeSearch = mysqli_real_escape_string(
        $conn,
        $search
    );

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
    $type === 'multiple_choice' ||
    $type === 'six_option' ||
    $type === 'written'
) {
    $safeType = mysqli_real_escape_string(
        $conn,
        $type
    );

    $conditions[] = "q.question_type = '$safeType'";
}


if ($activeFilter === 'active') {
    $conditions[] = 'q.is_active = 1';
}

if ($activeFilter === 'inactive') {
    $conditions[] = 'q.is_active = 0';
}


$where = implode(
    ' AND ',
    $conditions
);


/*
|--------------------------------------------------------------------------
| Topics
|--------------------------------------------------------------------------
*/

$topics = array();

$topicQuery = "
    SELECT
        id,
        name
    FROM topics
    ORDER BY id ASC
";

$topicResult = mysqli_query(
    $conn,
    $topicQuery
);

if ($topicResult) {

    while ($topic = mysqli_fetch_assoc(
        $topicResult
    )) {

        $topics[] = $topic;
    }
}


/*
|--------------------------------------------------------------------------
| Questions
|--------------------------------------------------------------------------
*/

$questions = array();

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

$result = mysqli_query(
    $conn,
    $query
);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {
        $questions[] = $row;
    }
}


?>

<link rel="stylesheet" href="../assets/css/admin.css">

<section class="page-section admin-page">

    <a
        href="index.php"
        class="page-back"
    >
        <span class="page-back-icon">←</span>
        <span>Admin panel</span>
    </a>


    <div class="page-heading">

        <h1 class="page-title">
            Savollar
        </h1>

        <p class="page-description">
            Savollarni yaratish va boshqarish
        </p>

    </div>


    <?php if ($message !== ''): ?>

        <div class="admin-message admin-message-<?php
            echo $messageType;
        ?>">
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
            + Yangi savol
        </a>

    </div>


    <form
        method="GET"
        class="admin-filter-form"
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
                    echo $type ===
                        'multiple_choice'
                        ? 'selected'
                        : '';
                    ?>
                >
                    Variantli
                </option>

                <option
                    value="six_option"
                    <?php
                    echo $type ===
                        'six_option'
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
                    echo $activeFilter ===
                        'active'
                        ? 'selected'
                        : '';
                    ?>
                >
                    Faol
                </option>

                <option
                    value="inactive"
                    <?php
                    echo $activeFilter ===
                        'inactive'
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
            class="admin-secondary-button"
        >
            Filtrlash
        </button>

    </form>


    <div class="admin-question-list">

        <?php if (count($questions) > 0): ?>

            <?php foreach ($questions as $question): ?>

                <div class="admin-question-row">

                    <div class="admin-question-id">
                        #
                        <?php
                        echo (int) $question['id'];
                        ?>
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

                                if ($question['question_type'] == 'multiple_choice') {
                                    echo htmlspecialchars(
                                        'Variantli',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                } else if ($question['question_type'] == 'six_option') {
                                    echo htmlspecialchars(
                                        '6 Variantli',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                } else if ($question['question_type'] == 'written') {
                                    echo htmlspecialchars(
                                        'Yozma',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
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

                            <span>
                                <?php
                                echo (int) $question[
                                    'option_count'
                                ];
                                ?>
                                variant
                            </span>

                            <?php if (
                                (int) $question[
                                    'image_count'
                                ] > 0
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

                            <?php if (
                                (int) $question['is_active'] === 1
                            ): ?>

                                <span>
                                    Faol
                                </span>

                            <?php else: ?>

                                <span>
                                    Nofaol
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="admin-question-actions">

                        <a
                            href="question_edit.php?id=<?php
                            echo (int) $question['id'];
                            ?>"
                            class="admin-action-button"
                        >
                            Tahrirlash
                        </a>


                        <form
                            method="POST"
                            onsubmit="
                                return confirm(
                                    'Savol holatini o‘zgartirasizmi?'
                                );
                            "
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="toggle"
                            >

                            <input
                                type="hidden"
                                name="question_id"
                                value="<?php
                                echo (int) $question['id'];
                                ?>"
                            >

                            <button
                                type="submit"
                                class="admin-action-button"
                            >
                                <?php
                                echo (
                                    (int) $question[
                                        'is_active'
                                    ] === 1
                                )
                                    ? 'O‘chirish'
                                    : 'Faollashtirish';
                                ?>
                            </button>

                        </form>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="admin-empty">

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


<?php

require_once __DIR__ . '/../../layout/footer.php';

?>