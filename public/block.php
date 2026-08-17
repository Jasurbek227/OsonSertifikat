<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

require_once __DIR__ . '/../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$blockId = (int) ($_GET['id'] ?? 0);

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

if ($blockId <= 0) {
    header('Location: blocks.php');
    exit;
}

function jsonResponse(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| AJAX answer
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'answer'
) {
    $sessionId = (int) ($_POST['session_id'] ?? 0);
    $questionId = (int) ($_POST['question_id'] ?? 0);
    $answer = trim((string) ($_POST['answer'] ?? ''));
    $answerA = trim((string) ($_POST['answer_a'] ?? ''));
    $answerB = trim((string) ($_POST['answer_b'] ?? ''));

    if ($sessionId <= 0 || $questionId <= 0) {
        jsonResponse([
            'success' => false,
            'message' => 'Noto‘g‘ri so‘rov.'
        ]);
    }

    $sessionQuery = "
        SELECT id, block_id, status, total_questions
        FROM block_sessions
        WHERE id = $sessionId
          AND user_id = $userId
          AND block_id = $blockId
        LIMIT 1
    ";
    $sessionResult = mysqli_query($conn, $sessionQuery);

    if (!$sessionResult || mysqli_num_rows($sessionResult) === 0) {
        jsonResponse([
            'success' => false,
            'message' => 'Sessiya topilmadi.'
        ]);
    }

    $session = mysqli_fetch_assoc($sessionResult);

    if ($session['status'] !== 'active') {
        jsonResponse([
            'success' => false,
            'message' => 'Ushbu blok sessiyasi faol emas.'
        ]);
    }

    $questionQuery = "
        SELECT
            q.id,
            q.question_type,
            q.correct_answer,
            q.part_a_text,
            q.part_a_correct_answer,
            q.part_b_text,
            q.part_b_correct_answer
        FROM block_questions bq
        INNER JOIN questions q
            ON q.id = bq.question_id
        WHERE bq.block_id = $blockId
          AND q.id = $questionId
          AND q.is_active = 1
        LIMIT 1
    ";
    $questionResult = mysqli_query($conn, $questionQuery);

    if (!$questionResult || mysqli_num_rows($questionResult) === 0) {
        jsonResponse([
            'success' => false,
            'message' => 'Savol topilmadi.'
        ]);
    }

    $question = mysqli_fetch_assoc($questionResult);
    $questionType = (string) $question['question_type'];

    $existingQuery = "
        SELECT id, answer, is_correct
        FROM attempts
        WHERE user_id = $userId
          AND question_id = $questionId
          AND block_session_id = $sessionId
        LIMIT 1
    ";
    $existingResult = mysqli_query($conn, $existingQuery);

    if ($existingResult && mysqli_num_rows($existingResult) > 0) {
        $existing = mysqli_fetch_assoc($existingResult);

        $correctAnswer = $questionType === 'written'
            ? ''
            : strtoupper(trim((string) $question['correct_answer']));

        jsonResponse([
            'success' => true,
            'already_answered' => true,
            'is_correct' => (bool) $existing['is_correct'],
            'correct_answer' => $correctAnswer
        ]);
    }

    $isCorrect = false;
    $storedAnswer = '';
    $correctAnswerForClient = '';

    if ($questionType === 'multiple_choice' || $questionType === 'six_option') {
        if ($answer === '') {
            jsonResponse([
                'success' => false,
                'message' => 'Variantni tanlang.'
            ]);
        }

        $correctAnswerForClient = strtoupper(
            trim((string) $question['correct_answer'])
        );

        $isCorrect = strcasecmp(
            $answer,
            $correctAnswerForClient
        ) === 0;

        $storedAnswer = $answer;
    } elseif ($questionType === 'written') {
        $hasPartA = trim((string) $question['part_a_text']) !== '';
        $hasPartB = trim((string) $question['part_b_text']) !== '';

        if (!$hasPartA && !$hasPartB) {
            jsonResponse([
                'success' => false,
                'message' => 'Yozma savol noto‘g‘ri sozlangan.'
            ]);
        }

        $partAIsCorrect = true;
        $partBIsCorrect = true;

        if ($hasPartA) {
            if ($answerA === '') {
                jsonResponse([
                    'success' => false,
                    'message' => 'A qism javobini kiriting.'
                ]);
            }

            $partAIsCorrect = strcasecmp(
                $answerA,
                trim((string) $question['part_a_correct_answer'])
            ) === 0;
        }

        if ($hasPartB) {
            if ($answerB === '') {
                jsonResponse([
                    'success' => false,
                    'message' => 'B qism javobini kiriting.'
                ]);
            }

            $partBIsCorrect = strcasecmp(
                $answerB,
                trim((string) $question['part_b_correct_answer'])
            ) === 0;
        }

        $isCorrect = $partAIsCorrect && $partBIsCorrect;

        $storedAnswer = json_encode([
            'a' => $answerA,
            'b' => $answerB
        ], JSON_UNESCAPED_UNICODE);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Noma’lum savol turi.'
        ]);
    }

    $safeAnswer = mysqli_real_escape_string($conn, $storedAnswer);

    mysqli_begin_transaction($conn);

    try {
        $attemptQuery = "
            INSERT INTO attempts (
                user_id,
                question_id,
                block_session_id,
                answer,
                is_correct
            )
            VALUES (
                $userId,
                $questionId,
                $sessionId,
                '$safeAnswer',
                " . ($isCorrect ? '1' : '0') . "
            )
        ";

        if (!mysqli_query($conn, $attemptQuery)) {
            throw new RuntimeException(mysqli_error($conn));
        }

        if ($isCorrect) {
            $mistakeQuery = "
                DELETE FROM mistake_queue
                WHERE user_id = $userId
                  AND question_id = $questionId
            ";
        } else {
            $mistakeQuery = "
                INSERT IGNORE INTO mistake_queue (
                    user_id,
                    question_id
                )
                VALUES (
                    $userId,
                    $questionId
                )
            ";
        }

        if (!mysqli_query($conn, $mistakeQuery)) {
            throw new RuntimeException(mysqli_error($conn));
        }

        $progressQuery = "
            SELECT
                COUNT(DISTINCT question_id) AS answered_count,
                COALESCE(
                    SUM(
                        CASE
                            WHEN is_correct = 1 THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS correct_count
            FROM attempts
            WHERE user_id = $userId
              AND block_session_id = $sessionId
        ";
        $progressResult = mysqli_query($conn, $progressQuery);

        $answeredCount = 0;
        $correctCount = 0;

        if ($progressResult) {
            $progress = mysqli_fetch_assoc($progressResult);
            $answeredCount = (int) $progress['answered_count'];
            $correctCount = (int) $progress['correct_count'];
        }

        $totalQuestions = (int) $session['total_questions'];
        $finished = $answeredCount >= $totalQuestions;

        if ($finished) {
            $finishQuery = "
                UPDATE block_sessions
                SET
                    status = 'completed',
                    finished_at = NOW(),
                    score = $correctCount
                WHERE id = $sessionId
                  AND user_id = $userId
                  AND status = 'active'
            ";

            if (!mysqli_query($conn, $finishQuery)) {
                throw new RuntimeException(mysqli_error($conn));
            }
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);

        jsonResponse([
            'success' => false,
            'message' => 'Javobni saqlashda xatolik.'
        ]);
    }

    jsonResponse([
        'success' => true,
        'already_answered' => false,
        'is_correct' => $isCorrect,
        'correct_answer' => $correctAnswerForClient,
        'answered_count' => $answeredCount,
        'correct_count' => $correctCount,
        'total_questions' => $totalQuestions,
        'finished' => $finished
    ]);
}

/*
|--------------------------------------------------------------------------
| Load block
|--------------------------------------------------------------------------
*/
$blockQuery = "
    SELECT id, name, description, generation
    FROM blocks
    WHERE id = $blockId
      AND is_active = 1
    LIMIT 1
";
$blockResult = mysqli_query($conn, $blockQuery);

if (!$blockResult || mysqli_num_rows($blockResult) === 0) {
    header('Location: blocks.php');
    exit;
}

$block = mysqli_fetch_assoc($blockResult);

/*
|--------------------------------------------------------------------------
| Load active questions
|--------------------------------------------------------------------------
*/
$questions = [];

$questionsQuery = "
    SELECT
        q.id,
        q.question_type,
        q.text,
        q.part_a_text,
        q.part_b_text
    FROM block_questions bq
    INNER JOIN questions q
        ON q.id = bq.question_id
    WHERE bq.block_id = $blockId
      AND q.is_active = 1
    ORDER BY bq.id ASC
";
$questionsResult = mysqli_query($conn, $questionsQuery);

if ($questionsResult) {
    while ($row = mysqli_fetch_assoc($questionsResult)) {
        $questionId = (int) $row['id'];
        $row['options'] = [];
        $row['images'] = [];

        $optionsResult = mysqli_query(
            $conn,
            "
            SELECT option_key, option_text
            FROM question_options
            WHERE question_id = $questionId
            ORDER BY option_key ASC
            "
        );

        if ($optionsResult) {
            while ($option = mysqli_fetch_assoc($optionsResult)) {
                $row['options'][] = $option;
            }
        }

        $imagesResult = mysqli_query(
            $conn,
            "
            SELECT file_path
            FROM question_images
            WHERE question_id = $questionId
            ORDER BY id ASC
            "
        );

        if ($imagesResult) {
            while ($image = mysqli_fetch_assoc($imagesResult)) {
                $row['images'][] = $image['file_path'];
            }
        }

        $questions[] = $row;
    }
}

$totalQuestions = count($questions);

if ($totalQuestions === 0) {
    require_once __DIR__ . '/../layout/header.php';
    ?>
    <link rel="stylesheet" href="assets/css/style.css">

    <section class="page-section">
        <a href="blocks.php" class="page-back">← Orqaga</a>

        <div class="blocks-content">
            <div class="blocks-empty">
                <div class="blocks-empty-icon">
                    <i data-lucide="clipboard-x"></i>
                </div>
                <h3>Ushbu blokda faol savollar mavjud emas.</h3>
            </div>
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
    require_once __DIR__ . '/../layout/footer.php';
    exit;
}

/*
|--------------------------------------------------------------------------
| Find/create active session
|--------------------------------------------------------------------------
*/
$sessionId = 0;

$activeSessionQuery = "
    SELECT id
    FROM block_sessions
    WHERE user_id = $userId
      AND block_id = $blockId
      AND status = 'active'
    ORDER BY id DESC
    LIMIT 1
";
$activeSessionResult = mysqli_query($conn, $activeSessionQuery);

if (
    $activeSessionResult &&
    mysqli_num_rows($activeSessionResult) > 0
) {
    $activeSession = mysqli_fetch_assoc($activeSessionResult);
    $sessionId = (int) $activeSession['id'];
} else {
    $createSessionQuery = "
        INSERT INTO block_sessions (
            user_id,
            block_id,
            total_questions
        )
        VALUES (
            $userId,
            $blockId,
            $totalQuestions
        )
    ";

    if (!mysqli_query($conn, $createSessionQuery)) {
        die(
            'Block session yaratishda xatolik: ' .
            mysqli_error($conn)
        );
    }

    $sessionId = (int) mysqli_insert_id($conn);
}

/*
|--------------------------------------------------------------------------
| Load attempts
|--------------------------------------------------------------------------
*/
$selectedAnswers = [];

$selectedQuery = "
    SELECT
        question_id,
        answer,
        is_correct
    FROM attempts
    WHERE user_id = $userId
      AND block_session_id = $sessionId
";

$selectedResult = mysqli_query($conn, $selectedQuery);

if ($selectedResult) {
    while ($row = mysqli_fetch_assoc($selectedResult)) {
        $selectedAnswers[(int) $row['question_id']] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Current position
|--------------------------------------------------------------------------
*/
$positionKey = 'block_position_' . $sessionId;

if (!isset($_SESSION[$positionKey])) {
    $_SESSION[$positionKey] = 0;
}

$currentPosition = max(
    0,
    min(
        (int) $_SESSION[$positionKey],
        $totalQuestions - 1
    )
);

$firstUnanswered = $totalQuestions;

foreach ($questions as $index => $question) {
    if (!isset($selectedAnswers[(int) $question['id']])) {
        $firstUnanswered = $index;
        break;
    }
}

if (
    $firstUnanswered < $totalQuestions &&
    $currentPosition > $firstUnanswered
) {
    $currentPosition = $firstUnanswered;
}

$_SESSION[$positionKey] = $currentPosition;

$currentQuestion = $questions[$currentPosition];
$currentQuestionId = (int) $currentQuestion['id'];
$currentAttempt = $selectedAnswers[$currentQuestionId] ?? null;

$pageTitle = (string) $block['name'];

require_once __DIR__ . '/../layout/header.php';
?>

<link rel="stylesheet" href="assets/css/style.css">

<section
    class="page-section block-solving-page"
    data-block-id="<?php echo $blockId; ?>"
    data-session-id="<?php echo $sessionId; ?>"
    data-total-questions="<?php echo $totalQuestions; ?>"
    data-questions='<?php
        echo json_encode(
            $questions,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        );
    ?>'
    data-answers='<?php
        echo json_encode(
            $selectedAnswers,
            JSON_UNESCAPED_UNICODE |
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        );
    ?>'
>

    <div class="block-solving-header">

        <a href="blocks.php" class="page-back">
            <span class="page-back-icon">←</span>
            <span>Orqaga</span>
        </a>

        <div class="block-solving-title">

            <h1 class="page-title">
                <?php echo h((string) $block['name']); ?>
            </h1>

            <?php if (!empty($block['description'])): ?>
                <p class="page-description">
                    <?php echo h((string) $block['description']); ?>
                </p>
            <?php endif; ?>

        </div>

    </div>


    <div class="block-progress">

        <div class="block-progress-top">
            <span>Savol</span>

            <strong id="questionCounter">
                <?php echo $currentPosition + 1; ?>
                /
                <?php echo $totalQuestions; ?>
            </strong>
        </div>

        <div class="block-progress-bar">
            <div
                class="block-progress-fill"
                id="blockProgressFill"
                style="width: <?php
                    echo (
                        (
                            $currentPosition + 1
                        ) /
                        $totalQuestions *
                        100
                    );
                ?>%;"
            ></div>
        </div>

    </div>


    <div class="question-navigation-top" id="questionNavigator">
        <?php foreach ($questions as $index => $question): ?>
            <?php
            $navQuestionId = (int) $question['id'];
            $answered = isset($selectedAnswers[$navQuestionId]);
            ?>
            <button
                type="button"
                class="question-nav-number <?php
                    echo $index === $currentPosition
                        ? 'is-current'
                        : '';
                    echo $answered
                        ? ' is-answered'
                        : '';
                ?>"
                data-position="<?php echo $index; ?>"
                data-question-id="<?php echo $navQuestionId; ?>"
            >
                <?php echo $index + 1; ?>
            </button>
        <?php endforeach; ?>
    </div>


    <div
        class="question-feedback"
        id="questionFeedback"
        hidden
    ></div>


    <div
        class="question-card"
        id="questionCard"
        data-question-id="<?php echo $currentQuestionId; ?>"
    >

        <div class="question-number">
            <?php echo $currentPosition + 1; ?>
        </div>


        <div class="question-text">
            <?php
            echo nl2br(
                h((string) $currentQuestion['text'])
            );
            ?>
        </div>


        <?php if (!empty($currentQuestion['images'])): ?>

            <div class="question-images">

                <?php foreach ($currentQuestion['images'] as $image): ?>

                    <img
                        src="<?php echo h((string) $image); ?>"
                        alt=""
                        class="question-image"
                    >

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <?php if (
            $currentQuestion['question_type'] === 'multiple_choice' ||
            $currentQuestion['question_type'] === 'six_option'
        ): ?>

            <div class="question-options">

                <?php foreach ($currentQuestion['options'] as $option): ?>

                    <?php
                    $optionKey = (string) $option['option_key'];
                    $selected = (
                        $currentAttempt &&
                        (string) $currentAttempt['answer'] === $optionKey
                    );
                    ?>

                    <button
                        type="button"
                        class="question-option <?php
                            echo $selected
                                ? 'question-option-selected'
                                : '';
                        ?>"
                        data-answer="<?php echo h($optionKey); ?>"
                        <?php echo $currentAttempt ? 'disabled' : ''; ?>
                    >

                        <span class="question-option-key">
                            <?php echo h($optionKey); ?>
                        </span>

                        <span class="question-option-text">
                            <?php
                            echo nl2br(
                                h((string) $option['option_text'])
                            );
                            ?>
                        </span>

                    </button>

                <?php endforeach; ?>

            </div>


        <?php elseif (
            $currentQuestion['question_type'] === 'written'
        ): ?>

            <?php
            $savedWritten = [
                'a' => '',
                'b' => ''
            ];

            if (
                $currentAttempt &&
                !empty($currentAttempt['answer'])
            ) {
                $decoded = json_decode(
                    (string) $currentAttempt['answer'],
                    true
                );

                if (is_array($decoded)) {
                    $savedWritten['a'] = (string) ($decoded['a'] ?? '');
                    $savedWritten['b'] = (string) ($decoded['b'] ?? '');
                }
            }
            ?>

            <div class="question-written">

                <?php if (
                    trim((string) $currentQuestion['part_a_text']) !== ''
                ): ?>

                    <div class="question-written-part">

                        <div class="question-written-part-title">
                            A
                        </div>

                        <div class="question-written-part-text">
                            <?php
                            echo nl2br(
                                h((string) $currentQuestion['part_a_text'])
                            );
                            ?>
                        </div>

                        <input
                            type="text"
                            class="question-answer-input"
                            id="writtenAnswerA"
                            placeholder="A qism javobi"
                            value="<?php echo h($savedWritten['a']); ?>"
                            <?php echo $currentAttempt ? 'disabled' : ''; ?>
                        >

                    </div>

                <?php endif; ?>


                <?php if (
                    trim((string) $currentQuestion['part_b_text']) !== ''
                ): ?>

                    <div class="question-written-part">

                        <div class="question-written-part-title">
                            B
                        </div>

                        <div class="question-written-part-text">
                            <?php
                            echo nl2br(
                                h((string) $currentQuestion['part_b_text'])
                            );
                            ?>
                        </div>

                        <input
                            type="text"
                            class="question-answer-input"
                            id="writtenAnswerB"
                            placeholder="B qism javobi"
                            value="<?php echo h($savedWritten['b']); ?>"
                            <?php echo $currentAttempt ? 'disabled' : ''; ?>
                        >

                    </div>

                <?php endif; ?>


                <?php if (!$currentAttempt): ?>

                    <button
                        type="button"
                        class="question-answer-submit"
                        id="writtenAnswerSubmit"
                    >
                        Javobni yuborish
                    </button>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>


    <div class="question-navigation">

        <button
            type="button"
            class="question-navigation-button"
            id="previousQuestion"
            <?php echo $currentPosition <= 0 ? 'disabled' : ''; ?>
        >
            ← Oldingi
        </button>

        <button
            type="button"
            class="question-navigation-button"
            id="nextQuestion"
            <?php echo $currentAttempt ? '' : 'disabled'; ?>
        >
            <?php echo (
                $currentPosition >= $totalQuestions - 1
            ) ? 'Tugatish' : 'Keyingi →'; ?>
        </button>

    </div>


    <div
        class="block-result"
        id="blockResult"
        hidden
    >

        <div class="block-result-content">

            <div class="block-result-icon">
                <i data-lucide="circle-check"></i>
            </div>

            <h2>
                Blok yakunlandi
            </h2>

            <p id="blockResultText"></p>

            <a
                href="blocks.php"
                class="block-result-button"
            >
                Bloklar ro‘yxatiga qaytish
            </a>

        </div>

    </div>

</section>


<script src="https://unpkg.com/lucide@latest"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    const page = document.querySelector('.block-solving-page');

    if (!page) {
        return;
    }

    const blockId = Number(page.dataset.blockId);
    const sessionId = Number(page.dataset.sessionId);
    const totalQuestions = Number(page.dataset.totalQuestions);

    const questions = JSON.parse(page.dataset.questions || '[]');
    const answerState = JSON.parse(page.dataset.answers || '{}');

    let currentPosition = <?php echo $currentPosition; ?>;
    let feedbackTimer = null;
    let isSubmitting = false;


    function questionIdAt(position) {
        return Number(questions[position].id);
    }


    function isAnswered(questionId) {
        return Object.prototype.hasOwnProperty.call(
            answerState,
            String(questionId)
        );
    }


    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }


    function escapeAttribute(value) {
        return escapeHtml(value)
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }


    function getWrittenAnswer(raw) {

        if (!raw) {
            return {a: '', b: ''};
        }

        try {
            const parsed = JSON.parse(raw);

            return {
                a: parsed.a || '',
                b: parsed.b || ''
            };
        } catch (error) {
            return {a: '', b: ''};
        }
    }


    function clearFeedback() {

        const feedback =
            document.getElementById('questionFeedback');

        feedback.hidden = true;
        feedback.className = 'question-feedback';
        feedback.textContent = '';
    }


    function showFeedback(isCorrect) {

        const feedback =
            document.getElementById('questionFeedback');

        feedback.hidden = false;
        feedback.className =
            'question-feedback ' +
            (isCorrect
                ? 'is-correct'
                : 'is-wrong');

        feedback.textContent =
            isCorrect
                ? 'To‘g‘ri javob!'
                : 'Noto‘g‘ri javob. To‘g‘ri javob yashil bilan ko‘rsatildi.';
    }


    function updateProgress() {

        document.getElementById(
            'questionCounter'
        ).textContent =
            (currentPosition + 1) +
            ' / ' +
            totalQuestions;

        document.getElementById(
            'blockProgressFill'
        ).style.width =
            (
                (
                    currentPosition + 1
                ) /
                totalQuestions *
                100
            ) + '%';
    }


    function updateTopNavigator() {

        const buttons =
            document.querySelectorAll(
                '.question-nav-number'
            );

        buttons.forEach(function (button) {

            const position =
                Number(button.dataset.position);

            const questionId =
                Number(button.dataset.questionId);

            button.classList.toggle(
                'is-current',
                position === currentPosition
            );

            button.classList.toggle(
                'is-answered',
                isAnswered(questionId)
            );

            /*
             * Future questions cannot be opened.
             * Previous and current questions are allowed.
             */
            button.disabled =
                position > currentPosition;

        });
    }


    function updateNavigation() {

        const previous =
            document.getElementById(
                'previousQuestion'
            );

        const next =
            document.getElementById(
                'nextQuestion'
            );

        const currentId =
            questionIdAt(currentPosition);

        previous.disabled =
            currentPosition <= 0;

        next.disabled =
            !isAnswered(currentId);

        next.textContent =
            currentPosition >= totalQuestions - 1
                ? 'Tugatish'
                : 'Keyingi →';

        updateTopNavigator();
    }


    function setFeedbackColors(questionId, selectedAnswer, correctAnswer) {

        const options =
            document.querySelectorAll(
                '.question-option'
            );

        options.forEach(function (option) {

            const value =
                String(option.dataset.answer || '');

            option.classList.remove(
                'question-option-correct',
                'question-option-wrong'
            );

            if (
                correctAnswer &&
                value.toUpperCase() ===
                correctAnswer.toUpperCase()
            ) {

                option.classList.add(
                    'question-option-correct'
                );
            }

            if (
                selectedAnswer &&
                value.toUpperCase() ===
                selectedAnswer.toUpperCase() &&
                value.toUpperCase() !==
                correctAnswer.toUpperCase()
            ) {

                option.classList.add(
                    'question-option-wrong'
                );
            }

            option.disabled = true;
        });
    }


    function renderQuestion(position) {

        if (
            position < 0 ||
            position >= questions.length
        ) {
            return;
        }

        currentPosition = position;

        const question =
            questions[position];

        const card =
            document.getElementById(
                'questionCard'
            );

        const questionId =
            Number(question.id);

        const stored =
            answerState[String(questionId)] || null;

        clearFeedback();

        let html = '';

        html += `
            <div class="question-number">
                ${position + 1}
            </div>
        `;

        html += `
            <div class="question-text">
                ${escapeHtml(
                    question.text
                ).replace(/\n/g, '<br>')}
            </div>
        `;

        if (
            question.images &&
            question.images.length
        ) {

            html += `
                <div class="question-images">
            `;

            question.images.forEach(function (image) {

                html += `
                    <img
                        src="${escapeAttribute(image)}"
                        alt=""
                        class="question-image"
                    >
                `;
            });

            html += `
                </div>
            `;
        }


        if (
            question.type === 'multiple_choice' ||
            question.type === 'six_option'
        ) {

            html += `
                <div class="question-options">
            `;

            const answered =
                Boolean(stored);

            question.options.forEach(function (option) {

                const key =
                    String(option.option_key);

                const selected =
                    answered &&
                    String(stored.answer) === key;

                html += `
                    <button
                        type="button"
                        class="question-option ${
                            selected
                                ? 'question-option-selected'
                                : ''
                        }"
                        data-answer="${escapeAttribute(key)}"
                        ${answered ? 'disabled' : ''}
                    >

                        <span class="question-option-key">
                            ${escapeHtml(key)}
                        </span>

                        <span class="question-option-text">
                            ${escapeHtml(
                                option.option_text
                            ).replace(/\n/g, '<br>')}
                        </span>

                    </button>
                `;
            });

            html += `
                </div>
            `;

        } else {

            const answered =
                Boolean(stored);

            const saved =
                answered
                    ? getWrittenAnswer(stored.answer)
                    : {a: '', b: ''};

            html += `
                <div class="question-written">
            `;

            if (question.part_a_text) {

                html += `
                    <div class="question-written-part">

                        <div class="question-written-part-title">
                            A
                        </div>

                        <div class="question-written-part-text">
                            ${escapeHtml(
                                question.part_a_text
                            ).replace(/\n/g, '<br>')}
                        </div>

                        <input
                            type="text"
                            class="question-answer-input"
                            id="writtenAnswerA"
                            placeholder="A qism javobi"
                            value="${escapeAttribute(saved.a)}"
                            ${answered ? 'disabled' : ''}
                        >

                    </div>
                `;
            }

            if (question.part_b_text) {

                html += `
                    <div class="question-written-part">

                        <div class="question-written-part-title">
                            B
                        </div>

                        <div class="question-written-part-text">
                            ${escapeHtml(
                                question.part_b_text
                            ).replace(/\n/g, '<br>')}
                        </div>

                        <input
                            type="text"
                            class="question-answer-input"
                            id="writtenAnswerB"
                            placeholder="B qism javobi"
                            value="${escapeAttribute(saved.b)}"
                            ${answered ? 'disabled' : ''}
                        >

                    </div>
                `;
            }

            if (!answered) {

                html += `
                    <button
                        type="button"
                        class="question-answer-submit"
                        id="writtenAnswerSubmit"
                    >
                        Javobni yuborish
                    </button>
                `;
            }

            html += `
                </div>
            `;
        }


        card.innerHTML = html;
        card.dataset.questionId = String(question.id);

        updateProgress();
        updateNavigation();
        bindQuestionEvents();

        if (stored) {

            /*
             * On revisiting an answered multiple-choice question,
             * show its result again.
             */
            if (
                question.type === 'multiple_choice' ||
                question.type === 'six_option'
            ) {

                const storedAnswer =
                    String(stored.answer || '');

                /*
                 * We need the correct answer for revisit.
                 * It is fetched only when the user answers,
                 * so just mark the selected option here.
                 */
                const selected =
                    card.querySelector(
                        '.question-option-selected'
                    );

                if (selected) {
                    selected.disabled = true;
                }
            }

            if (
                question.type === 'written'
            ) {

                /*
                 * Written feedback is shown after the
                 * first submission; revisits stay locked.
                 */
            }
        }

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }


    function bindQuestionEvents() {

        document.querySelectorAll(
            '.question-option'
        ).forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    const questionId =
                        Number(
                            document.getElementById(
                                'questionCard'
                            ).dataset.questionId
                        );

                    submitAnswer(
                        questionId,
                        String(button.dataset.answer),
                        button,
                        '',
                        ''
                    );

                }
            );

        });


        const writtenSubmit =
            document.getElementById(
                'writtenAnswerSubmit'
            );

        if (writtenSubmit) {

            writtenSubmit.addEventListener(
                'click',
                function () {

                    const questionId =
                        Number(
                            document.getElementById(
                                'questionCard'
                            ).dataset.questionId
                        );

                    const inputA =
                        document.getElementById(
                            'writtenAnswerA'
                        );

                    const inputB =
                        document.getElementById(
                            'writtenAnswerB'
                        );

                    submitAnswer(
                        questionId,
                        '',
                        writtenSubmit,
                        inputA
                            ? inputA.value.trim()
                            : '',
                        inputB
                            ? inputB.value.trim()
                            : ''
                    );

                }
            );
        }
    }


    function submitAnswer(
        questionId,
        answer,
        button,
        answerA,
        answerB
    ) {

        if (
            isSubmitting ||
            isAnswered(questionId)
        ) {
            return;
        }

        if (
            !answer &&
            !answerA &&
            !answerB
        ) {
            return;
        }

        isSubmitting = true;

        if (button) {
            button.disabled = true;
        }

        const formData =
            new FormData();

        formData.append('action', 'answer');
        formData.append('session_id', String(sessionId));
        formData.append('question_id', String(questionId));
        formData.append('answer', answer);
        formData.append('answer_a', answerA);
        formData.append('answer_b', answerB);


        fetch(
            'block.php?id=' +
            encodeURIComponent(blockId),
            {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }
        )
        .then(function (response) {

            return response.json();

        })
        .then(function (data) {

            if (!data.success) {

                if (button) {
                    button.disabled = false;
                }

                isSubmitting = false;

                alert(
                    data.message ||
                    'Xatolik yuz berdi.'
                );

                return;
            }


            answerState[String(questionId)] = {

                answer:
                    answer ||
                    JSON.stringify({
                        a: answerA,
                        b: answerB
                    }),

                is_correct:
                    data.is_correct
            };


            /*
             * Show immediate visual feedback.
             */
            if (
                questions[currentPosition].type ===
                    'multiple_choice' ||
                questions[currentPosition].type ===
                    'six_option'
            ) {

                setFeedbackColors(
                    questionId,
                    answer,
                    data.correct_answer
                );

            } else {

                const inputs =
                    document.querySelectorAll(
                        '.question-answer-input'
                    );

                inputs.forEach(function (input) {
                    input.disabled = true;
                });
            }


            showFeedback(
                Boolean(data.is_correct)
            );


            updateNavigation();


            /*
             * Automatic next is universal.
             *
             * Feedback stays visible for 900 ms,
             * then the next question opens.
             */
            clearTimeout(feedbackTimer);

            feedbackTimer =
                setTimeout(
                    function () {

                        isSubmitting = false;

                        if (data.finished) {

                            showResult(
                                Number(data.correct_count),
                                Number(data.total_questions)
                            );

                            return;
                        }


                        if (
                            currentPosition <
                            totalQuestions - 1
                        ) {

                            renderQuestion(
                                currentPosition + 1
                            );

                        } else {

                            showResult(
                                Number(data.correct_count),
                                Number(data.total_questions)
                            );
                        }

                    },
                    900
                );

        })
        .catch(function () {

            if (button) {
                button.disabled = false;
            }

            isSubmitting = false;

            alert(
                'Server bilan bog‘lanishda xatolik.'
            );

        });
    }


    function showResult(
        correct,
        total
    ) {

        document.getElementById(
            'questionCard'
        ).hidden = true;

        document.getElementById(
            'questionNavigator'
        ).hidden = true;

        document.querySelector(
            '.question-navigation'
        ).hidden = true;

        document.getElementById(
            'blockResultText'
        ).textContent =
            correct +
            ' / ' +
            total +
            ' ta savol to‘g‘ri.';

        document.getElementById(
            'blockResult'
        ).hidden = false;

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }


    document.getElementById(
        'previousQuestion'
    ).addEventListener(
        'click',
        function () {

            if (currentPosition > 0) {
                renderQuestion(
                    currentPosition - 1
                );
            }
        }
    );


    document.getElementById(
        'nextQuestion'
    ).addEventListener(
        'click',
        function () {

            const currentId =
                questionIdAt(currentPosition);

            if (!isAnswered(currentId)) {
                return;
            }

            if (
                currentPosition <
                totalQuestions - 1
            ) {

                renderQuestion(
                    currentPosition + 1
                );

            } else {

                const correct =
                    Object.values(answerState)
                        .filter(function (item) {
                            return Number(item.is_correct) === 1;
                        })
                        .length;

                showResult(
                    correct,
                    totalQuestions
                );
            }
        }
    );


    document.querySelectorAll(
        '.question-nav-number'
    ).forEach(function (button) {

        button.addEventListener(
            'click',
            function () {

                const position =
                    Number(
                        button.dataset.position
                    );

                if (
                    position <= currentPosition
                ) {

                    renderQuestion(position);
                }
            }
        );
    });


    bindQuestionEvents();
    updateNavigation();

});
</script>

<?php

require_once __DIR__ . '/../layout/footer.php';
?>
