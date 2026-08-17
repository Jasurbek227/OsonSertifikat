<?php

declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/admin_auth.php';
requireAdmin();
$blockId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;
if ($blockId <= 0) {
    header('Location: blocks.php');
    exit;
}
$pageTitle = 'Blokni tahrirlash';
$error = '';
$message = '';
$activeTab = (
    isset($_GET['tab']) &&
    $_GET['tab'] === 'questions'
)
    ? 'questions'
    : 'details';
/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    $action = isset($_POST['action'])
        ? (string) $_POST['action']
        : '';
    if ($action === 'update_details') {
        $name = trim(
            (string) ($_POST['name'] ?? '')
        );
        $generation = (int) (
            $_POST['generation'] ?? 1
        );
        $description = trim(
            (string) ($_POST['description'] ?? '')
        );
        if ($name === '') {
            $error = 'Blok nomini kiriting.';
        } elseif ($generation <= 0) {
            $error = 'Generation noto‘g‘ri.';
        } else {
            $safeName =
                mysqli_real_escape_string(
                    $conn,
                    $name
                );
            $safeDescription =
                mysqli_real_escape_string(
                    $conn,
                    $description
                );
            $query = "
                UPDATE blocks
                SET
                    name = '$safeName',
                    generation = $generation,
                    description = " .
                (
                    $description !== ''
                    ? "'$safeDescription'"
                    : "NULL"
                ) .
                "
                WHERE id = $blockId
                LIMIT 1
            ";
            if (mysqli_query($conn, $query)) {
                $message =
                    'Blok ma’lumotlari saqlandi.';
            } else {
                $error =
                    'Saqlashda xatolik: ' .
                    mysqli_error($conn);
            }
        }
        $activeTab = 'details';
    }
    if ($action === 'update_questions') {
        $selectedQuestions =
            isset($_POST['question_ids']) &&
            is_array(
                $_POST['question_ids']
            )
            ? $_POST['question_ids']
            : array();
        $questionIds = array();
        foreach (
            $selectedQuestions
            as $questionId
        ) {
            $questionId = (int) $questionId;
            if ($questionId > 0) {
                $questionIds[] = $questionId;
            }
        }
        $questionIds = array_values(
            array_unique(
                $questionIds
            )
        );
        if (count($questionIds) === 0) {
            $error =
                'Blok uchun kamida 1 ta savol tanlang.';
        } else {
            mysqli_begin_transaction($conn);
            try {
                $deleteQuery = "
                    DELETE FROM block_questions
                    WHERE block_id = $blockId
                ";
                if (
                    !mysqli_query(
                        $conn,
                        $deleteQuery
                    )
                ) {
                    throw new Exception(
                        mysqli_error($conn)
                    );
                }
                foreach (
                    $questionIds
                    as $questionId
                ) {
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
                        mysqli_num_rows(
                            $checkResult
                        ) === 0
                    ) {
                        throw new Exception(
                            'Tanlangan savollardan biri topilmadi.'
                        );
                    }
                    $insertQuery = "
                        INSERT INTO block_questions (
                            block_id,
                            question_id
                        )
                        VALUES (
                            $blockId,
                            $questionId
                        )
                    ";
                    if (
                        !mysqli_query(
                            $conn,
                            $insertQuery
                        )
                    ) {
                        throw new Exception(
                            mysqli_error($conn)
                        );
                    }
                }
                mysqli_commit($conn);
                $message =
                    'Blok savollari yangilandi.';
            } catch (Throwable $exception) {
                mysqli_rollback($conn);
                $error =
                    'Savollarni saqlashda xatolik: ' .
                    $exception->getMessage();
            }
        }
        $activeTab = 'questions';
    }
}
/*
|--------------------------------------------------------------------------
| Load block
|--------------------------------------------------------------------------
*/
$blockQuery = "
    SELECT
        id,
        name,
        generation,
        description,
        is_active,
        created_at
    FROM blocks
    WHERE id = $blockId
    LIMIT 1
";
$blockResult = mysqli_query(
    $conn,
    $blockQuery
);
if (
    !$blockResult ||
    mysqli_num_rows($blockResult) === 0
) {
    header('Location: blocks.php');
    exit;
}
$block = mysqli_fetch_assoc(
    $blockResult
);
/*
|--------------------------------------------------------------------------
| Current block questions
|--------------------------------------------------------------------------
*/
$currentQuestionIds = array();
$currentQuestionQuery = "
    SELECT
        question_id
    FROM block_questions
    WHERE block_id = $blockId
    ORDER BY id ASC
";
$currentQuestionResult = mysqli_query(
    $conn,
    $currentQuestionQuery
);
if ($currentQuestionResult) {
    while (
        $row = mysqli_fetch_assoc(
            $currentQuestionResult
        )
    ) {
        $currentQuestionIds[] =
            (int) $row['question_id'];
    }
}
/*
|--------------------------------------------------------------------------
| Search/filter for question picker
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
$topicResult = mysqli_query(
    $conn,
    "
    SELECT
        id,
        name
    FROM topics
    WHERE is_active = 1
    ORDER BY id ASC
    "
);
if ($topicResult) {
    while (
        $topic = mysqli_fetch_assoc(
            $topicResult
        )
    ) {
        $topics[] = $topic;
    }
}
/*
|--------------------------------------------------------------------------
| All selectable questions
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
    while (
        $question = mysqli_fetch_assoc(
            $questionsResult
        )
    ) {
        $questions[] = $question;
    }
}
/*
|--------------------------------------------------------------------------
| Progress
|--------------------------------------------------------------------------
*/
$currentCount = count(
    $currentQuestionIds
);
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
                <?php
                echo htmlspecialchars(
                    $block['name'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </h1>
            <p class="admin-page-description">
                Blokni boshqaring
            </p>
        </div>
        <div class="admin-header-actions">
            <?php if (
                (int) $block['is_active'] === 1
            ): ?>
            <span class="
                        admin-status
                        admin-status-success
                        admin-status-large
                    ">
                Faol
            </span>
            <?php else: ?>
            <span class="
                        admin-status
                        admin-status-muted
                        admin-status-large
                    ">
                Nofaol
            </span>
            <?php endif; ?>
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
    <?php if ($message !== ''): ?>
    <div class="admin-message admin-message-success">
        <?php
            echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
    </div>
    <?php endif; ?>
    <div class="admin-tabs">
        <a href="block_edit.php?id=<?php
        echo $blockId;
        ?>&tab=details" class="
                admin-tab
                <?php
                echo $activeTab === 'details'
                    ? 'is-active'
                    : '';
                ?>
            ">
            <i data-lucide="settings-2"></i>
            Asosiy
        </a>
        <a href="block_edit.php?id=<?php
        echo $blockId;
        ?>&tab=questions" class="
                admin-tab
                <?php
                echo $activeTab === 'questions'
                    ? 'is-active'
                    : '';
                ?>
            ">
            <i data-lucide="list-checks"></i>
            Savollar
            <span>
                <?php echo $currentCount; ?> ta
            </span>
        </a>
    </div>
    <?php if (
        $activeTab === 'details'
    ): ?>
    <form method="POST" class="admin-form">
        <input type="hidden" name="action" value="update_details">
        <div class="admin-form-card">
            <div class="admin-form-grid">
                <div class="admin-form-field">
                    <label>
                        Blok nomi
                    </label>
                    <input type="text" name="name" value="<?php
                        echo htmlspecialchars(
                            $block['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>" required>
                </div>
                <div class="admin-form-field">
                    <label>
                        Generation
                    </label>
                    <input type="number" name="generation" min="1" value="<?php
                        echo (int) 
                            $block['generation'];
                        ?>" required>
                </div>
            </div>
            <div class="admin-form-field">
                <label>
                    Tavsif
                </label>
                <textarea name="description" rows="5"><?php
                    echo htmlspecialchars(
                        (string) 
                        $block['description'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?></textarea>
            </div>
            <div class="admin-detail-grid">
                <div>
                    <span>
                        Block ID
                    </span>
                    <strong>
                        #<?php
                            echo $blockId;
                            ?>
                    </strong>
                </div>
                <div>
                    <span>
                        Generation
                    </span>
                    <strong>
                        <?php
                            echo (int) 
                                $block['generation'];
                            ?>
                    </strong>
                </div>
                <div>
                    <span>
                        Savollar
                    </span>
                    <strong>
                        <?php
                            echo $currentCount;
                            ?> ta
                    </strong>
                </div>
            </div>
        </div>
        <div class="admin-form-actions">
            <a href="blocks.php" class="btn btn-outline-light">
                Bekor qilish
            </a>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save"></i>
                Saqlash
            </button>
        </div>
    </form>
    <?php else: ?>
    <form method="GET" class="admin-filter-card">
        <input type="hidden" name="id" value="<?php echo $blockId; ?>">
        <input type="hidden" name="tab" value="questions">
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
                <button type="submit" class="btn btn-outline-light">
                    <i data-lucide="search"></i>
                    Qidirish
                </button>
            </div>
        </div>
    </form>
    <form method="POST" class="admin-form">
        <input type="hidden" name="action" value="update_questions">
        <div class="admin-form-card">
            <div class="admin-form-card-header">
                <div>
                    <h2>
                        Blok savollari
                    </h2>
                    <p>
                        Savollarni tanlang. Tavsiya etilgan savollar soni 20 ta!
                    </p>
                </div>
                <div class="
                            admin-selection-count
                            <?php
                            echo $currentCount === 20
                                ? 'is-complete'
                                : '';
                            ?>" id="selectionCount">
                    <?php
                        echo $currentCount;
                        ?> ta
                </div>
            </div>
            <div id="blockQuestionWarning" class="admin-block-warning" <?php
                echo (
                    $currentCount > 0 &&
                    $currentCount < 20
                )
                    ? ''
                    : 'hidden';
                ?>>
                <i data-lucide="triangle-alert"></i>

                <span>
                    Tavsiya etilgan savollar soni 20 ta!
                </span>
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
                            $isChecked =
                                in_array(
                                    (int) 
                                    $question['id'],
                                    $currentQuestionIds,
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
                                ?>" class="
                                        block-question-checkbox
                                    " <?php
                                    echo $isChecked
                                        ? 'checked'
                                        : '';
                                    ?>>
                    <div class="
                                        admin-question-picker-content
                                    ">
                        <div class="
                                            admin-list-meta
                                        ">
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
                        <div class="
                                            admin-question-picker-text
                                        ">
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
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="admin-form-actions">
            <a href="blocks.php" class="btn btn-outline-light">
                Bekor qilish
            </a>
            <button type="submit" class="btn btn-primary" id="saveQuestionsButton">
                <i data-lucide="save"></i>
                Savollarni saqlash
            </button>
        </div>
    </form>
    <?php endif; ?>
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
        const saveButton =
            document.getElementById(
                'saveQuestionsButton'
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


            if (countElement) {

                countElement.textContent =
                    count + ' ta';

                countElement.classList.toggle(
                    'is-complete',
                    count >= 20
                );
            }


            const warning =
                document.getElementById(
                    'blockQuestionWarning'
                );


            if (warning) {

                warning.hidden = !(count > 0 && count < 20);
            }


            checkboxes.forEach(
                function(checkbox) {

                    checkbox.disabled = false;

                }
            );


            if (saveButton) {

                saveButton.disabled =
                    count === 0;

            }

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