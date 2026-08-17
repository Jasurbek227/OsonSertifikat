<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/admin_auth.php';

requireAdmin();

$pageTitle = 'Yangi blok';

$error = '';


/*
|--------------------------------------------------------------------------
| Create block
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim(
        (string) ($_POST['name'] ?? '')
    );

    $generation = (int) (
        $_POST['generation'] ?? 1
    );

    $description = trim(
        (string) ($_POST['description'] ?? '')
    );

    $selectedQuestions = isset(
        $_POST['question_ids']
    ) && is_array(
        $_POST['question_ids']
    )
        ? $_POST['question_ids']
        : array();


    $questionIds = array();

    foreach ($selectedQuestions as $questionId) {

        $questionId = (int) $questionId;

        if ($questionId > 0) {
            $questionIds[] = $questionId;
        }
    }

    $questionIds = array_values(
        array_unique($questionIds)
    );


    if ($name === '') {

        $error = 'Blok nomini kiriting.';
    } elseif ($generation <= 0) {

        $error = 'Generation noto‘g‘ri.';
    } elseif (count($questionIds) === 0) {

        $error = 'Blok uchun kamida 1 ta savol tanlang.';
    }


    if ($error === '') {

        $safeName = mysqli_real_escape_string(
            $conn,
            $name
        );

        $safeDescription = mysqli_real_escape_string(
            $conn,
            $description
        );


        mysqli_begin_transaction($conn);


        try {

            $insertBlock = "
                INSERT INTO blocks (
                    name,
                    generation,
                    description,
                    is_active
                )
                VALUES (
                    '$safeName',
                    $generation,
                    " .
                (
                    $description !== ''
                    ? "'$safeDescription'"
                    : "NULL"
                ) .
                ",
                    1
                )
            ";


            if (!mysqli_query($conn, $insertBlock)) {
                throw new Exception(
                    mysqli_error($conn)
                );
            }


            $blockId = (int) mysqli_insert_id(
                $conn
            );


            foreach ($questionIds as $questionId) {

                $checkQuery = "
                    SELECT id
                    FROM questions
                    WHERE id = $questionId
                      AND is_active = 1
                    LIMIT 1
                ";

                $checkResult = mysqli_query(
                    $conn,
                    $checkQuery
                );

                if (
                    !$checkResult ||
                    mysqli_num_rows($checkResult) === 0
                ) {
                    throw new Exception(
                        'Tanlangan savollardan biri topilmadi.'
                    );
                }


                $insertQuestion = "
                    INSERT INTO block_questions (
                        block_id,
                        question_id
                    )
                    VALUES (
                        $blockId,
                        $questionId
                    )
                ";


                if (!mysqli_query(
                    $conn,
                    $insertQuestion
                )) {
                    throw new Exception(
                        mysqli_error($conn)
                    );
                }
            }


            mysqli_commit($conn);


            header(
                'Location: block_edit.php?id=' .
                    $blockId .
                    '&created=1'
            );

            exit;
        } catch (Throwable $exception) {

            mysqli_rollback($conn);

            $error =
                'Blok yaratishda xatolik: ' .
                $exception->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Question search
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) ($_GET['search'] ?? '')
);

$topicId = (int) (
    $_GET['topic_id'] ?? 0
);


$questionConditions = array(
    'q.is_active = 1'
);

if ($search !== '') {

    $safeSearch = mysqli_real_escape_string(
        $conn,
        $search
    );

    $questionConditions[] = "
        (
            q.text LIKE '%$safeSearch%'
            OR q.id LIKE '%$safeSearch%'
        )
    ";
}

if ($topicId > 0) {
    $questionConditions[] =
        "q.topic_id = $topicId";
}

$questionWhere = implode(
    ' AND ',
    $questionConditions
);


/*
|--------------------------------------------------------------------------
| Topics
|--------------------------------------------------------------------------
*/

$topics = array();

$topicsResult = mysqli_query(
    $conn,
    "
    SELECT id, name
    FROM topics
    WHERE is_active = 1
    ORDER BY id ASC
    "
);

if ($topicsResult) {

    while ($topic = mysqli_fetch_assoc(
        $topicsResult
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

$questionsResult = mysqli_query(
    $conn,
    "
    SELECT
        q.id,
        q.topic_id,
        q.question_type,
        q.text,
        t.name AS topic_name
    FROM questions q
    INNER JOIN topics t
        ON t.id = q.topic_id
    WHERE $questionWhere
    ORDER BY q.id DESC
    LIMIT 300
    "
);

if ($questionsResult) {

    while ($question = mysqli_fetch_assoc(
        $questionsResult
    )) {

        $questions[] = $question;
    }
}

?>

<link rel="stylesheet" href="../assets/css/admin.css">


<section class="admin-page admin-content-page">

    <a href="blocks.php" class="page-back">
        <span class="page-back-icon">←</span>
        <span>Bloklar</span>
    </a>


    <div class="admin-page-header">

        <div>

            <span class="admin-eyebrow">
                BLOCK MANAGEMENT
            </span>

            <h1 class="admin-page-title">
                Yangi blok
            </h1>

            <p class="admin-page-description">
                Savollar blokini yarating
            </p>

        </div>

    </div>


    <?php if ($error !== ''): ?>

    <div class="admin-message admin-message-error">

        <?php
            echo htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

    </div>

    <?php endif; ?>


    <form method="POST" class="admin-form" id="blockCreateForm">

        <div class="admin-form-card">

            <div class="admin-form-grid">

                <div class="admin-form-field">

                    <label>
                        Blok nomi
                    </label>

                    <input type="text" name="name" value="<?php
                                                            echo htmlspecialchars(
                                                                (string)
                                                                ($_POST['name'] ?? ''),
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            );
                                                            ?>" placeholder="Masalan: 1-blok" required>

                </div>


                <div class="admin-form-field">

                    <label>
                        Generation
                    </label>

                    <input type="number" name="generation" min="1" value="<?php
                                                                            echo (int) (
                                                                                $_POST['generation'] ?? 1
                                                                            );
                                                                            ?>" required>

                </div>

            </div>


            <div class="admin-form-field">

                <label>
                    Tavsif
                </label>

                <textarea name="description" rows="4" placeholder="Ixtiyoriy tavsif"><?php
                                                                                        echo htmlspecialchars(
                                                                                            (string)
                                                                                            ($_POST['description'] ?? ''),
                                                                                            ENT_QUOTES,
                                                                                            'UTF-8'
                                                                                        );
                                                                                        ?></textarea>

            </div>

        </div>


        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <div>

                    <h2>
                        Savollar
                    </h2>

                    <p>
                        Savollarni tanlang. Tavsiya etilgan savollar soni 20 ta.
                    </p>

                </div>


                <div class="admin-selection-count" id="selectionCount">
                    0 ta
                </div>
                <div id="blockQuestionWarning" class="admin-block-warning" hidden>
                    <i data-lucide="triangle-alert"></i>

                    <span>
                        Bu blokda 20 tadan kam savol bor.
                        20 ta savol tavsiya etiladi, lekin blokni yaratish mumkin.
                    </span>
                </div>

            </div>


            <div class="admin-filter-card admin-filter-card-inner">

                <div class="admin-filter-grid">

                    <div class="admin-form-field">

                        <label>
                            Qidirish
                        </label>

                        <input type="text" name="search" value="<?php
                                                                echo htmlspecialchars(
                                                                    $search,
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                );
                                                                ?>" placeholder="Savol yoki ID">

                    </div>


                    <div class="admin-form-field">

                        <label>
                            Mavzu
                        </label>

                        <select name="topic_id">

                            <option value="0">
                                Barchasi
                            </option>

                            <?php foreach (
                                $topics
                                as $topic
                            ): ?>

                            <option value="<?php
                                                echo (int)
                                                $topic['id'];
                                                ?>" <?php
                                                    echo $topicId ===
                                                        (int)
                                                        $topic['id']
                                                        ? 'selected'
                                                        : '';
                                                    ?>>
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


                    <div class="admin-filter-button">

                        <button type="submit" formmethod="GET" class="btn btn-outline-light">
                            <i data-lucide="search"></i>
                            Qidirish
                        </button>

                    </div>

                </div>

            </div>


            <div class="admin-question-picker">

                <?php if (
                    count($questions) > 0
                ): ?>

                <?php foreach (
                        $questions
                        as $question
                    ): ?>

                <?php

                        $oldSelection =
                            isset(
                                $_POST['question_ids']
                            ) &&
                            is_array(
                                $_POST['question_ids']
                            )
                            ? array_map(
                                'intval',
                                $_POST['question_ids']
                            )
                            : array();

                        $isChecked = in_array(
                            (int) $question['id'],
                            $oldSelection,
                            true
                        );

                        ?>

                <label class="
                                admin-question-picker-row
                                <?php
                                echo $isChecked
                                    ? 'is-selected'
                                    : '';
                                ?>
                            ">

                    <input type="checkbox" name="question_ids[]" value="<?php
                                                                                echo (int)
                                                                                $question['id'];
                                                                                ?>" class="block-question-checkbox" <?php
                                                                                                                    echo $isChecked
                                                                                                                        ? 'checked'
                                                                                                                        : '';
                                                                                                                    ?>>


                    <div class="admin-question-picker-content">

                        <div class="admin-list-meta">

                            <span>
                                #
                                <?php
                                        echo (int)
                                        $question['id'];
                                        ?>
                            </span>

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
                                        echo htmlspecialchars(
                                            $question['question_type'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                            </span>

                        </div>


                        <div class="admin-question-picker-text">

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

                    </div>


                    <div class="admin-picker-check">

                        <i data-lucide="check"></i>

                    </div>

                </label>

                <?php endforeach; ?>

                <?php else: ?>

                <div class="admin-empty">

                    <div class="admin-empty-icon">
                        <i data-lucide="circle-help"></i>
                    </div>

                    <h3>
                        Savollar topilmadi
                    </h3>

                    <p>
                        Avval admin paneldan savollar yarating.
                    </p>

                </div>

                <?php endif; ?>

            </div>

        </div>


        <div class="admin-form-actions">

            <a href="blocks.php" class="btn btn-outline-light">
                Bekor qilish
            </a>

            <button type="submit" class="btn btn-primary" id="createBlockButton">
                <i data-lucide="plus"></i>
                Blok yaratish
            </button>

        </div>

    </form>

</section>


<script src="https://unpkg.com/lucide@latest"></script>

<script>
document.addEventListener(
    'DOMContentLoaded',
    function() {

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }


        const checkboxes =
            document.querySelectorAll(
                '.block-question-checkbox'
            );

        const countElement =
            document.getElementById(
                'selectionCount'
            );

        const createButton =
            document.getElementById(
                'createBlockButton'
            );


        function updateSelection() {

            let count = 0;

            checkboxes.forEach(
                function(checkbox) {

                    const row =
                        checkbox.closest(
                            '.admin-question-picker-row'
                        );

                    if (checkbox.checked) {

                        count++;

                        row.classList.add(
                            'is-selected'
                        );

                    } else {

                        row.classList.remove(
                            'is-selected'
                        );
                    }

                }
            );


            countElement.textContent =
                count + ' ta';


            const warning =
                document.getElementById(
                    'blockQuestionWarning'
                );


            if (warning) {

                warning.hidden = !(count > 0 && count < 20);
            }


            /*
             * There is no 20-question maximum anymore.
             */

            checkboxes.forEach(
                function(checkbox) {

                    checkbox.disabled = false;

                }
            );


            /*
             * At least one question is required.
             */

            createButton.disabled =
                count === 0;

        }


        checkboxes.forEach(
            function(checkbox) {

                checkbox.addEventListener(
                    'change',
                    updateSelection
                );

            }
        );


        updateSelection();

    }
);
</script>
<script src="../assets/js/admin.js"></script>