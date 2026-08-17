<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAuth();

require_once __DIR__ . '/../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Current user
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Block ID
|--------------------------------------------------------------------------
*/

$blockId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($blockId <= 0) {
    header('Location: blocks.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| AJAX answer
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'answer'
) {

    header('Content-Type: application/json; charset=utf-8');

    $sessionId = isset($_POST['session_id'])
        ? (int) $_POST['session_id']
        : 0;

    $questionId = isset($_POST['question_id'])
        ? (int) $_POST['question_id']
        : 0;

    $answer = isset($_POST['answer'])
        ? trim((string) $_POST['answer'])
        : '';

    if (
        $sessionId <= 0 ||
        $questionId <= 0 ||
        $answer === ''
    ) {
        echo json_encode([
            'success' => false,
            'message' => 'Noto‘g‘ri so‘rov.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify active session belongs to current user and block
    |--------------------------------------------------------------------------
    */

    $sessionQuery = "
        SELECT
            id,
            block_id,
            status,
            total_questions
        FROM block_sessions
        WHERE id = $sessionId
          AND user_id = $userId
          AND block_id = $blockId
        LIMIT 1
    ";

    $sessionResult = mysqli_query($conn, $sessionQuery);

    if (!$sessionResult || mysqli_num_rows($sessionResult) === 0) {

        echo json_encode([
            'success' => false,
            'message' => 'Sessiya topilmadi.'
        ]);

        exit;
    }

    $session = mysqli_fetch_assoc($sessionResult);

    if ($session['status'] !== 'active') {

        echo json_encode([
            'success' => false,
            'message' => 'Ushbu blok sessiyasi faol emas.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify question belongs to this block
    |--------------------------------------------------------------------------
    */

    $questionQuery = "
        SELECT
            q.id,
            q.question_type,
            q.correct_answer,
            q.part_a_correct_answer,
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

    if (
        !$questionResult ||
        mysqli_num_rows($questionResult) === 0
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'Savol topilmadi.'
        ]);

        exit;
    }

    $question = mysqli_fetch_assoc($questionResult);


    /*
    |--------------------------------------------------------------------------
    | Check whether this question was already answered
    |--------------------------------------------------------------------------
    */

    $answerCheckQuery = "
        SELECT
            id,
            is_correct
        FROM attempts
        WHERE user_id = $userId
          AND question_id = $questionId
          AND block_session_id = $sessionId
        LIMIT 1
    ";

    $answerCheckResult = mysqli_query(
        $conn,
        $answerCheckQuery
    );

    if (
        $answerCheckResult &&
        mysqli_num_rows($answerCheckResult) > 0
    ) {

        $existingAttempt = mysqli_fetch_assoc(
            $answerCheckResult
        );

        echo json_encode([
            'success' => true,
            'already_answered' => true,
            'is_correct' => (bool) $existingAttempt['is_correct']
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Check answer
    |--------------------------------------------------------------------------
    */

    $isCorrect = false;

    if ($question['question_type'] === 'multiple_choice') {

        $correctAnswer = trim(
            (string) $question['correct_answer']
        );

        $isCorrect = (
            strcasecmp(
                $answer,
                $correctAnswer
            ) === 0
        );
    } elseif ($question['question_type'] === 'six_option') {

        $correctAnswer = trim(
            (string) $question['correct_answer']
        );

        $isCorrect = (
            strcasecmp(
                $answer,
                $correctAnswer
            ) === 0
        );
    } elseif ($question['question_type'] === 'written') {

        /*
         * Written answers are compared as text.
         * More advanced numeric tolerance can be added later.
         */

        $correctAnswer = trim(
            (string) $question['correct_answer']
        );

        $isCorrect = (
            strcasecmp(
                $answer,
                $correctAnswer
            ) === 0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Escape answer
    |--------------------------------------------------------------------------
    */

    $safeAnswer = mysqli_real_escape_string(
        $conn,
        $answer
    );


    /*
    |--------------------------------------------------------------------------
    | Save attempt
    |--------------------------------------------------------------------------
    */

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

    $attemptResult = mysqli_query(
        $conn,
        $attemptQuery
    );

    if (!$attemptResult) {

        echo json_encode([
            'success' => false,
            'message' => 'Javobni saqlashda xatolik.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Wrong answer -> mistake queue
    |--------------------------------------------------------------------------
    */

    if (!$isCorrect) {

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

        mysqli_query(
            $conn,
            $mistakeQuery
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Count answered questions
    |--------------------------------------------------------------------------
    */

    $answeredQuery = "
        SELECT COUNT(DISTINCT question_id) AS answered_count
        FROM attempts
        WHERE user_id = $userId
          AND block_session_id = $sessionId
    ";

    $answeredResult = mysqli_query(
        $conn,
        $answeredQuery
    );

    $answeredCount = 0;

    if ($answeredResult) {

        $answeredRow = mysqli_fetch_assoc(
            $answeredResult
        );

        $answeredCount = (int) $answeredRow['answered_count'];
    }


    /*
    |--------------------------------------------------------------------------
    | Count correct answers
    |--------------------------------------------------------------------------
    */

    $correctQuery = "
        SELECT COUNT(DISTINCT question_id) AS correct_count
        FROM attempts
        WHERE user_id = $userId
          AND block_session_id = $sessionId
          AND is_correct = 1
    ";

    $correctResult = mysqli_query(
        $conn,
        $correctQuery
    );

    $correctCount = 0;

    if ($correctResult) {

        $correctRow = mysqli_fetch_assoc(
            $correctResult
        );

        $correctCount = (int) $correctRow['correct_count'];
    }


    /*
    |--------------------------------------------------------------------------
    | Finish automatically after all questions answered
    |--------------------------------------------------------------------------
    */

    $totalQuestions = (int) $session['total_questions'];

    $finished = (
        $answeredCount >= $totalQuestions
    );

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

        mysqli_query(
            $conn,
            $finishQuery
        );
    }


    echo json_encode([
        'success' => true,
        'already_answered' => false,
        'is_correct' => $isCorrect,
        'answered_count' => $answeredCount,
        'correct_count' => $correctCount,
        'total_questions' => $totalQuestions,
        'finished' => $finished
    ]);

    exit;
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
        description,
        generation
    FROM blocks
    WHERE id = $blockId
      AND is_active = 1
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

$block = mysqli_fetch_assoc($blockResult);


/*
|--------------------------------------------------------------------------
| Load block questions
|--------------------------------------------------------------------------
*/

$questions = array();

$questionsQuery = "
    SELECT
        q.id,
        q.question_type,
        q.text,
        q.correct_answer,
        bq.id AS block_question_id
    FROM block_questions bq
    INNER JOIN questions q
        ON q.id = bq.question_id
    WHERE bq.block_id = $blockId
      AND q.is_active = 1
    ORDER BY bq.id ASC
";

$questionsResult = mysqli_query(
    $conn,
    $questionsQuery
);

if ($questionsResult) {

    while ($row = mysqli_fetch_assoc($questionsResult)) {

        $questionId = (int) $row['id'];

        /*
         * Load options.
         */

        $row['options'] = array();

        $optionsQuery = "
            SELECT
                option_key,
                option_text
            FROM question_options
            WHERE question_id = $questionId
            ORDER BY option_key ASC
        ";

        $optionsResult = mysqli_query(
            $conn,
            $optionsQuery
        );

        if ($optionsResult) {

            while ($option = mysqli_fetch_assoc(
                $optionsResult
            )) {

                $row['options'][] = $option;
            }
        }


        /*
         * Load optional images.
         */

        $row['images'] = array();

        $imagesQuery = "
            SELECT
                file_path
            FROM question_images
            WHERE question_id = $questionId
            ORDER BY id ASC
        ";

        $imagesResult = mysqli_query(
            $conn,
            $imagesQuery
        );

        if ($imagesResult) {

            while ($image = mysqli_fetch_assoc(
                $imagesResult
            )) {

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

    <section class="page-section">

        <a href="blocks.php" class="page-back">
            <span class="page-back-icon">←</span>
            <span>Orqaga</span>
        </a>

        <div class="blocks-content">

            <div>

                <h3>
                    Ushbu blokda savollar mavjud emas.
                </h3>

            </div>

        </div>

    </section>

<?php

    require_once __DIR__ . '/../layout/footer.php';

    exit;
}


/*
|--------------------------------------------------------------------------
| Find active block session
|--------------------------------------------------------------------------
*/

$sessionId = 0;

$activeSessionQuery = "
    SELECT
        id,
        status
    FROM block_sessions
    WHERE user_id = $userId
      AND block_id = $blockId
      AND status = 'active'
    ORDER BY id DESC
    LIMIT 1
";

$activeSessionResult = mysqli_query(
    $conn,
    $activeSessionQuery
);

if (
    $activeSessionResult &&
    mysqli_num_rows($activeSessionResult) > 0
) {

    $activeSession = mysqli_fetch_assoc(
        $activeSessionResult
    );

    $sessionId = (int) $activeSession['id'];
} else {

    /*
     * Create a new block session.
     */

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

    $createSessionResult = mysqli_query(
        $conn,
        $createSessionQuery
    );

    if (!$createSessionResult) {

        die('Block session yaratishda xatolik: ' .
            mysqli_error($conn));
    }

    $sessionId = (int) mysqli_insert_id($conn);
}


/*
|--------------------------------------------------------------------------
| Determine current question
|--------------------------------------------------------------------------
|
| The user can return to already answered questions.
| They cannot skip an unanswered question.
|
*/

$answeredIds = array();

$answeredQuery = "
    SELECT DISTINCT question_id
    FROM attempts
    WHERE user_id = $userId
      AND block_session_id = $sessionId
";

$answeredResult = mysqli_query(
    $conn,
    $answeredQuery
);

if ($answeredResult) {

    while ($answeredRow = mysqli_fetch_assoc(
        $answeredResult
    )) {

        $answeredIds[] = (int) $answeredRow['question_id'];
    }
}


/*
 * Current position is stored in the PHP session.
 */

$positionKey = 'block_position_' . $sessionId;

if (!isset($_SESSION[$positionKey])) {
    $_SESSION[$positionKey] = 0;
}

$currentPosition = (int) $_SESSION[$positionKey];

if ($currentPosition < 0) {
    $currentPosition = 0;
}

if ($currentPosition >= $totalQuestions) {
    $currentPosition = $totalQuestions - 1;
}


/*
|--------------------------------------------------------------------------
| Find first unanswered question
|--------------------------------------------------------------------------
*/

$firstUnanswered = 0;

foreach ($questions as $index => $question) {

    if (
        !in_array(
            (int) $question['id'],
            $answeredIds,
            true
        )
    ) {

        $firstUnanswered = $index;
        break;
    }
}


/*
 * If current position is ahead of the first unanswered
 * question, move it back.
 */

if (
    $firstUnanswered > 0 &&
    $currentPosition > $firstUnanswered
) {
    $currentPosition = $firstUnanswered;
}


/*
|--------------------------------------------------------------------------
| Selected answers
|--------------------------------------------------------------------------
*/

$selectedAnswers = array();

$selectedQuery = "
    SELECT
        question_id,
        answer,
        is_correct
    FROM attempts
    WHERE user_id = $userId
      AND block_session_id = $sessionId
";

$selectedResult = mysqli_query(
    $conn,
    $selectedQuery
);

if ($selectedResult) {

    while ($selectedRow = mysqli_fetch_assoc(
        $selectedResult
    )) {

        $selectedAnswers[(int) $selectedRow['question_id']] = $selectedRow;
    }
}


$_SESSION[$positionKey] = $currentPosition;

$currentQuestion = $questions[$currentPosition];

$pageTitle = $block['name'];

require_once __DIR__ . '/../layout/header.php';

?>
<link rel="stylesheet" href="assets/css/style.css">

<section class="page-section block-solving-page" data-block-id="<?php echo $blockId; ?>"
    data-session-id="<?php echo $sessionId; ?>" data-total-questions="<?php echo $totalQuestions; ?>">


    <!-- Header -->

    <div class="block-solving-header">

        <a href="blocks.php" class="page-back">

            <span class="page-back-icon">
                ←
            </span>

            <span>
                Orqaga
            </span>

        </a>


        <div class="block-solving-title">

            <h1 class="page-title">

                <?php
                echo htmlspecialchars(
                    $block['name'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </h1>

            <?php if (!empty($block['description'])): ?>

                <p class="page-description">

                    <?php
                    echo htmlspecialchars(
                        $block['description'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </p>

            <?php endif; ?>

        </div>

    </div>


    <!-- Progress -->

    <div class="block-progress">

        <div class="block-progress-top">

            <span>
                Savol
            </span>

            <strong id="questionCounter">
                <?php echo $currentPosition + 1; ?>
                /
                <?php echo $totalQuestions; ?>
            </strong>

        </div>


        <div class="block-progress-bar">

            <div class="block-progress-fill" id="blockProgressFill" style="width:
                    <?php
                    echo (
                        (($currentPosition + 1) /
                            $totalQuestions) * 100
                    );
                    ?>%;"></div>

        </div>

    </div>


    <!-- Question -->

    <div class="question-card" id="questionCard" data-question-id="<?php echo (int) $currentQuestion['id']; ?>">

        <div class="question-number">

            <?php echo $currentPosition + 1; ?>

        </div>


        <div class="question-text">

            <?php
            echo nl2br(
                htmlspecialchars(
                    $currentQuestion['text'],
                    ENT_QUOTES,
                    'UTF-8'
                )
            );
            ?>

        </div>


        <!-- Images -->

        <?php if (count($currentQuestion['images']) > 0): ?>

            <div class="question-images">

                <?php foreach (
                    $currentQuestion['images']
                    as $imagePath
                ): ?>

                    <img src="<?php
                                echo htmlspecialchars(
                                    $imagePath,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>" alt="" class="question-image">

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <!-- Options -->

        <?php if (
            $currentQuestion['question_type'] ===
            'multiple_choice' ||
            $currentQuestion['question_type'] ===
            'six_option'
        ): ?>

            <div class="question-options">

                <?php foreach (
                    $currentQuestion['options']
                    as $option
                ): ?>

                    <?php

                    $optionKey = $option['option_key'];

                    $selected = (
                        isset(
                            $selectedAnswers[(int) $currentQuestion['id']]
                        ) &&
                        $selectedAnswers[(int) $currentQuestion['id']]['answer'] === $optionKey
                    );

                    ?>

                    <button type="button" class="question-option
                        <?php
                        echo $selected
                            ? ' question-option-selected'
                            : '';
                        ?>" data-answer="<?php
                                            echo htmlspecialchars(
                                                $optionKey,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>" <?php
                                                echo $selected
                                                    ? 'disabled'
                                                    : '';
                                                ?>>

                        <span class="question-option-key">

                            <?php
                            echo htmlspecialchars(
                                $optionKey,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </span>


                        <span class="question-option-text">

                            <?php
                            echo nl2br(
                                htmlspecialchars(
                                    $option['option_text'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            );
                            ?>

                        </span>

                    </button>

                <?php endforeach; ?>

            </div>


        <?php elseif (
            $currentQuestion['question_type'] ===
            'written'
        ): ?>


            <div class="question-written">

                <?php

                $existingAnswer = '';

                if (
                    isset(
                        $selectedAnswers[(int) $currentQuestion['id']]
                    )
                ) {

                    $existingAnswer =
                        $selectedAnswers[(int) $currentQuestion['id']]['answer'];
                }

                ?>


                <input type="text" class="question-answer-input" id="writtenAnswer" placeholder="Javobingizni kiriting"
                    value="<?php
                            echo htmlspecialchars(
                                $existingAnswer,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>" <?php
                                echo $existingAnswer !== ''
                                    ? 'disabled'
                                    : '';
                                ?>>


                <?php if ($existingAnswer === ''): ?>

                    <button type="button" class="question-answer-submit" id="writtenAnswerSubmit">
                        Javobni yuborish
                    </button>

                <?php endif; ?>

            </div>

        <?php endif; ?>


    </div>


    <!-- Navigation -->

    <div class="question-navigation">

        <button type="button" class="question-navigation-button" id="previousQuestion" <?php
                                                                                        echo $currentPosition <= 0
                                                                                            ? 'disabled'
                                                                                            : '';
                                                                                        ?>>

            ← Oldingi

        </button>


        <button type="button" class="question-navigation-button" id="nextQuestion" <?php

                                                                                    $currentAnswered = in_array(
                                                                                        (int) $currentQuestion['id'],
                                                                                        $answeredIds,
                                                                                        true
                                                                                    );

                                                                                    echo !$currentAnswered
                                                                                        ? 'disabled'
                                                                                        : '';

                                                                                    ?>>

            Keyingi →

        </button>

    </div>


    <!-- Result -->

    <div class="block-result" id="blockResult" style="display: none;">

        <div class="block-result-content">

            <div class="block-result-icon">
                <i data-lucide="circle-check"></i>
            </div>

            <h2>
                Blok yakunlandi
            </h2>

            <p id="blockResultText"></p>

            <a href="blocks.php" class="block-result-button">
                Bloklar ro‘yxatiga qaytish
            </a>

        </div>

    </div>

</section>


<script src="https://unpkg.com/lucide@latest"></script>

<script>
    document.addEventListener(
        'DOMContentLoaded',
        function() {

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }


            const page =
                document.querySelector(
                    '.block-solving-page'
                );

            if (!page) {
                return;
            }


            const blockId =
                parseInt(
                    page.dataset.blockId,
                    10
                );

            const sessionId =
                parseInt(
                    page.dataset.sessionId,
                    10
                );

            const totalQuestions =
                parseInt(
                    page.dataset.totalQuestions,
                    10
                );


            let currentPosition =
                <?php echo $currentPosition; ?>;


            const questionIds = <?php

                                echo json_encode(
                                    array_map(
                                        function ($question) {
                                            return (int) $question['id'];
                                        },
                                        $questions
                                    )
                                );

                                ?>;


            const answeredQuestions = <?php

                                        echo json_encode(
                                            array_values($answeredIds)
                                        );

                                        ?>;


            const questionData = <?php

                                    echo json_encode(
                                        array_map(
                                            function ($question) {

                                                return [
                                                    'id' =>
                                                    (int) $question['id'],

                                                    'type' =>
                                                    $question['question_type'],

                                                    'text' =>
                                                    $question['text'],

                                                    'options' =>
                                                    $question['options'],

                                                    'images' =>
                                                    $question['images']
                                                ];
                                            },
                                            $questions
                                        )
                                    );

                                    ?>;


            function isAnswered(questionId) {

                return answeredQuestions.indexOf(
                    parseInt(questionId, 10)
                ) !== -1;

            }


            function renderQuestion(position) {

                if (
                    position < 0 ||
                    position >= questionData.length
                ) {
                    return;
                }


                const question =
                    questionData[position];

                const questionCard =
                    document.getElementById(
                        'questionCard'
                    );

                const questionCounter =
                    document.getElementById(
                        'questionCounter'
                    );

                const progressFill =
                    document.getElementById(
                        'blockProgressFill'
                    );


                currentPosition = position;


                questionCard.dataset.questionId =
                    question.id;


                let html = '';


                html += `
                <div class="question-number">
                    ${position + 1}
                </div>
            `;


                html += `
                <div class="question-text">
                    ${escapeHtml(question.text)
                        .replace(/\n/g, '<br>')}
                </div>
            `;


                if (
                    question.images &&
                    question.images.length > 0
                ) {

                    html += `
                    <div class="question-images">
                `;

                    question.images.forEach(
                        function(image) {

                            html += `
                            <img
                                src="${escapeAttribute(image)}"
                                alt=""
                                class="question-image"
                            >
                        `;

                        }
                    );

                    html += `
                    </div>
                `;
                }


                if (
                    question.type ===
                    'multiple_choice' ||
                    question.type ===
                    'six_option'
                ) {

                    html += `
                    <div class="question-options">
                `;


                    question.options.forEach(
                        function(option) {

                            const selected =
                                getSelectedAnswer(
                                    question.id
                                ) ===
                                option.option_key;


                            html += `
                            <button
                                type="button"
                                class="question-option
                                ${
                                    selected
                                        ? 'question-option-selected'
                                        : ''
                                }"
                                data-answer="${
                                    escapeAttribute(
                                        option.option_key
                                    )
                                }"
                                ${
                                    selected
                                        ? 'disabled'
                                        : ''
                                }
                            >

                                <span
                                    class="question-option-key"
                                >
                                    ${
                                        escapeHtml(
                                            option.option_key
                                        )
                                    }
                                </span>

                                <span
                                    class="question-option-text"
                                >
                                    ${
                                        escapeHtml(
                                            option.option_text
                                        ).replace(
                                            /\n/g,
                                            '<br>'
                                        )
                                    }
                                </span>

                            </button>
                        `;

                        }
                    );


                    html += `
                    </div>
                `;

                } else {

                    html += `
                    <div class="question-written">

                        <input
                            type="text"
                            class="question-answer-input"
                            id="writtenAnswer"
                            placeholder="Javobingizni kiriting"
                            ${
                                isAnswered(question.id)
                                    ? 'disabled'
                                    : ''
                            }
                        >

                        ${
                            !isAnswered(question.id)
                                ? `
                                    <button
                                        type="button"
                                        class="question-answer-submit"
                                        id="writtenAnswerSubmit"
                                    >
                                        Javobni yuborish
                                    </button>
                                `
                                : ''
                        }

                    </div>
                `;
                }


                questionCard.innerHTML = html;


                questionCounter.textContent =
                    (position + 1) +
                    ' / ' +
                    totalQuestions;


                progressFill.style.width =
                    (
                        ((position + 1) /
                            totalQuestions) * 100
                    ) + '%';


                updateNavigation();


                bindQuestionEvents();

            }


            function updateNavigation() {

                const previousButton =
                    document.getElementById(
                        'previousQuestion'
                    );

                const nextButton =
                    document.getElementById(
                        'nextQuestion'
                    );


                previousButton.disabled =
                    currentPosition <= 0;


                const questionId =
                    questionIds[
                        currentPosition
                    ];


                nextButton.disabled = !isAnswered(questionId);


                if (
                    currentPosition >=
                    totalQuestions - 1
                ) {

                    nextButton.textContent =
                        'Tugatish';

                } else {

                    nextButton.textContent =
                        'Keyingi →';

                }

            }


            function getSelectedAnswer(
                questionId
            ) {

                const stored =
                    sessionStorage.getItem(
                        'block_' +
                        sessionId +
                        '_question_' +
                        questionId
                    );

                return stored || '';

            }


            function storeSelectedAnswer(
                questionId,
                answer
            ) {

                sessionStorage.setItem(
                    'block_' +
                    sessionId +
                    '_question_' +
                    questionId,
                    answer
                );

            }


            function submitAnswer(
                questionId,
                answer,
                button
            ) {

                if (
                    !answer ||
                    isAnswered(questionId)
                ) {
                    return;
                }


                button.disabled = true;


                const formData =
                    new FormData();

                formData.append(
                    'action',
                    'answer'
                );

                formData.append(
                    'session_id',
                    sessionId
                );

                formData.append(
                    'question_id',
                    questionId
                );

                formData.append(
                    'answer',
                    answer
                );


                fetch(
                        'block.php?id=' + blockId, {
                            method: 'POST',
                            body: formData
                        }
                    )
                    .then(
                        function(response) {
                            return response.json();
                        }
                    )
                    .then(
                        function(data) {

                            if (!data.success) {

                                button.disabled =
                                    false;

                                alert(
                                    data.message ||
                                    'Xatolik yuz berdi.'
                                );

                                return;
                            }


                            storeSelectedAnswer(
                                questionId,
                                answer
                            );


                            if (
                                answeredQuestions.indexOf(
                                    questionId
                                ) === -1
                            ) {

                                answeredQuestions.push(
                                    questionId
                                );
                            }


                            button.classList.add(
                                'question-option-selected'
                            );


                            updateNavigation();


                            if (data.finished) {

                                showResult(
                                    data.correct_count,
                                    data.total_questions
                                );

                                return;
                            }


                            /*
                             * Automatically move forward.
                             */

                            setTimeout(
                                function() {

                                    if (
                                        currentPosition <
                                        totalQuestions - 1
                                    ) {

                                        renderQuestion(
                                            currentPosition + 1
                                        );

                                    }

                                },
                                180
                            );

                        }
                    )
                    .catch(
                        function() {

                            button.disabled =
                                false;

                            alert(
                                'Server bilan bog‘lanishda xatolik.'
                            );

                        }
                    );

            }


            function bindQuestionEvents() {

                const optionButtons =
                    document.querySelectorAll(
                        '.question-option'
                    );


                optionButtons.forEach(
                    function(button) {

                        button.addEventListener(
                            'click',
                            function() {

                                const questionId =
                                    parseInt(
                                        document
                                        .getElementById(
                                            'questionCard'
                                        )
                                        .dataset
                                        .questionId,
                                        10
                                    );

                                const answer =
                                    button.dataset.answer;


                                submitAnswer(
                                    questionId,
                                    answer,
                                    button
                                );

                            }
                        );

                    }
                );


                const writtenButton =
                    document.getElementById(
                        'writtenAnswerSubmit'
                    );


                if (writtenButton) {

                    writtenButton.addEventListener(
                        'click',
                        function() {

                            const input =
                                document.getElementById(
                                    'writtenAnswer'
                                );

                            const answer =
                                input.value.trim();


                            if (!answer) {
                                return;
                            }


                            submitAnswer(
                                questionIds[
                                    currentPosition
                                ],
                                answer,
                                writtenButton
                            );

                        }
                    );

                }

            }


            function showResult(
                correct,
                total
            ) {

                const result =
                    document.getElementById(
                        'blockResult'
                    );

                const resultText =
                    document.getElementById(
                        'blockResultText'
                    );


                resultText.textContent =
                    correct +
                    ' / ' +
                    total +
                    ' ta savol to‘g‘ri.';


                document
                    .getElementById(
                        'questionCard'
                    )
                    .style.display =
                    'none';


                document
                    .querySelector(
                        '.question-navigation'
                    )
                    .style.display =
                    'none';


                result.style.display =
                    'block';


                if (
                    typeof lucide !==
                    'undefined'
                ) {
                    lucide.createIcons();
                }

            }


            document
                .getElementById(
                    'previousQuestion'
                )
                .addEventListener(
                    'click',
                    function() {

                        if (
                            currentPosition > 0
                        ) {

                            renderQuestion(
                                currentPosition - 1
                            );

                        }

                    }
                );


            document
                .getElementById(
                    'nextQuestion'
                )
                .addEventListener(
                    'click',
                    function() {

                        const currentQuestionId =
                            questionIds[
                                currentPosition
                            ];


                        if (
                            !isAnswered(
                                currentQuestionId
                            )
                        ) {
                            return;
                        }


                        if (
                            currentPosition <
                            totalQuestions - 1
                        ) {

                            renderQuestion(
                                currentPosition + 1
                            );

                        }

                    }
                );


            function escapeHtml(value) {

                const div =
                    document.createElement(
                        'div'
                    );

                div.textContent =
                    value == null ?
                    '' :
                    value;

                return div.innerHTML;

            }


            function escapeAttribute(value) {

                return escapeHtml(value)
                    .replace(
                        /"/g,
                        '&quot;'
                    )
                    .replace(
                        /'/g,
                        '&#039;'
                    );

            }


            bindQuestionEvents();

        });
</script>


<?php

require_once __DIR__ . '/../layout/footer.php';

?>