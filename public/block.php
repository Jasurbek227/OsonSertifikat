<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

require_once __DIR__ . '/../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


$userId = isset($_SESSION['user_id'])
    ? (int) $_SESSION['user_id']
    : 0;

$blockId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($userId <= 0) {
    header('Location: login.php');
    exit;
}


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

    header(
        'Content-Type: application/json; charset=utf-8'
    );


    $sessionId = isset($_POST['session_id'])
        ? (int) $_POST['session_id']
        : 0;

    $questionId = isset($_POST['question_id'])
        ? (int) $_POST['question_id']
        : 0;

    $answer = isset($_POST['answer'])
        ? trim((string) $_POST['answer'])
        : '';

    $answerA = isset($_POST['answer_a'])
        ? trim((string) $_POST['answer_a'])
        : '';

    $answerB = isset($_POST['answer_b'])
        ? trim((string) $_POST['answer_b'])
        : '';


    if (
        $sessionId <= 0 ||
        $questionId <= 0
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'Noto‘g‘ri so‘rov.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify session
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

    $sessionResult =
        mysqli_query(
            $conn,
            $sessionQuery
        );


    if (
        !$sessionResult ||
        mysqli_num_rows($sessionResult) === 0
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'Sessiya topilmadi.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    $session =
        mysqli_fetch_assoc(
            $sessionResult
        );


    if (
        $session['status'] !== 'active'
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'Ushbu blok sessiyasi faol emas.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify question belongs to block
    |--------------------------------------------------------------------------
    */

    $questionQuery = "
        SELECT
            q.id,
            q.question_type,
            q.correct_answer,
            q.part_a_correct_answer,
            q.part_b_correct_answer,
            q.part_a_text,
            q.part_b_text

        FROM block_questions bq

        INNER JOIN questions q
            ON q.id = bq.question_id

        WHERE bq.block_id = $blockId
          AND q.id = $questionId
          AND q.is_active = 1

        LIMIT 1
    ";


    $questionResult =
        mysqli_query(
            $conn,
            $questionQuery
        );


    if (
        !$questionResult ||
        mysqli_num_rows($questionResult) === 0
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'Savol topilmadi.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    $question =
        mysqli_fetch_assoc(
            $questionResult
        );


    /*
    |--------------------------------------------------------------------------
    | Prevent second answer
    |--------------------------------------------------------------------------
    */

    $existingQuery = "
        SELECT
            id,
            is_correct
        FROM attempts
        WHERE user_id = $userId
          AND question_id = $questionId
          AND block_session_id = $sessionId
        LIMIT 1
    ";


    $existingResult =
        mysqli_query(
            $conn,
            $existingQuery
        );


    if (
        $existingResult &&
        mysqli_num_rows($existingResult) > 0
    ) {

        $existing =
            mysqli_fetch_assoc(
                $existingResult
            );


        echo json_encode([
            'success' => true,
            'already_answered' => true,
            'is_correct' =>
                (bool) $existing['is_correct']
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Check answer
    |--------------------------------------------------------------------------
    */

    $isCorrect = false;

    $storedAnswer = '';


    if (
        $question['question_type'] === 'multiple_choice' ||
        $question['question_type'] === 'six_option'
    ) {

        if ($answer === '') {

            echo json_encode([
                'success' => false,
                'message' => 'Variantni tanlang.'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }


        $correctAnswer =
            trim(
                (string) 
                $question['correct_answer']
            );


        $isCorrect =
            strcasecmp(
                $answer,
                $correctAnswer
            ) === 0;


        $storedAnswer =
            $answer;

    } elseif (
        $question['question_type'] === 'written'
    ) {

        $hasPartA =
            trim(
                (string) 
                $question['part_a_text']
            ) !== '';

        $hasPartB =
            trim(
                (string) 
                $question['part_b_text']
            ) !== '';


        if (
            !$hasPartA &&
            !$hasPartB
        ) {

            echo json_encode([
                'success' => false,
                'message' => 'Yozma savol noto‘g‘ri sozlangan.'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }


        $partAIsCorrect = true;
        $partBIsCorrect = true;


        if ($hasPartA) {

            if ($answerA === '') {

                echo json_encode([
                    'success' => false,
                    'message' => 'A qism javobini kiriting.'
                ], JSON_UNESCAPED_UNICODE);

                exit;
            }


            $correctA =
                trim(
                    (string) 
                    $question[
                        'part_a_correct_answer'
                    ]
                );


            $partAIsCorrect =
                strcasecmp(
                    $answerA,
                    $correctA
                ) === 0;
        }


        if ($hasPartB) {

            if ($answerB === '') {

                echo json_encode([
                    'success' => false,
                    'message' => 'B qism javobini kiriting.'
                ], JSON_UNESCAPED_UNICODE);

                exit;
            }


            $correctB =
                trim(
                    (string) 
                    $question[
                        'part_b_correct_answer'
                    ]
                );


            $partBIsCorrect =
                strcasecmp(
                    $answerB,
                    $correctB
                ) === 0;
        }


        $isCorrect =
            $partAIsCorrect &&
            $partBIsCorrect;


        $storedAnswer =
            json_encode(
                [
                    'a' => $answerA,
                    'b' => $answerB
                ],
                JSON_UNESCAPED_UNICODE
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Save attempt + mistake queue atomically
    |--------------------------------------------------------------------------
    */

    $safeAnswer =
        mysqli_real_escape_string(
            $conn,
            $storedAnswer
        );


    mysqli_begin_transaction(
        $conn
    );


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
                " .
            (
                $isCorrect
                ? '1'
                : '0'
            ) .
            "
            )
        ";


        if (
            !mysqli_query(
                $conn,
                $attemptQuery
            )
        ) {

            throw new RuntimeException(
                mysqli_error($conn)
            );
        }


        if ($isCorrect) {

            $removeMistakeQuery = "
                DELETE FROM mistake_queue
                WHERE user_id = $userId
                  AND question_id = $questionId
            ";


            if (
                !mysqli_query(
                    $conn,
                    $removeMistakeQuery
                )
            ) {

                throw new RuntimeException(
                    mysqli_error($conn)
                );
            }

        } else {

            $addMistakeQuery = "
                INSERT IGNORE INTO mistake_queue (
                    user_id,
                    question_id
                )
                VALUES (
                    $userId,
                    $questionId
                )
            ";


            if (
                !mysqli_query(
                    $conn,
                    $addMistakeQuery
                )
            ) {

                throw new RuntimeException(
                    mysqli_error($conn)
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Count progress
        |--------------------------------------------------------------------------
        */

        $progressQuery = "
            SELECT
                COUNT(DISTINCT question_id) AS answered_count,
                COALESCE(
                    SUM(
                        CASE
                            WHEN is_correct = 1
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS correct_count
            FROM attempts
            WHERE user_id = $userId
              AND block_session_id = $sessionId
        ";


        $progressResult =
            mysqli_query(
                $conn,
                $progressQuery
            );


        $answeredCount = 0;
        $correctCount = 0;


        if ($progressResult) {

            $progress =
                mysqli_fetch_assoc(
                    $progressResult
                );


            $answeredCount =
                (int) 
                $progress['answered_count'];


            $correctCount =
                (int) 
                $progress['correct_count'];
        }


        $totalQuestions =
            (int) 
            $session['total_questions'];


        $finished =
            $answeredCount >=
            $totalQuestions;


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


            if (
                !mysqli_query(
                    $conn,
                    $finishQuery
                )
            ) {

                throw new RuntimeException(
                    mysqli_error($conn)
                );
            }
        }


        mysqli_commit(
            $conn
        );


    } catch (
        Throwable $exception
    ) {

        mysqli_rollback(
            $conn
        );


        echo json_encode([
            'success' => false,
            'message' =>
                'Javobni saqlashda xatolik.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    echo json_encode([
        'success' => true,
        'already_answered' => false,
        'is_correct' => $isCorrect,
        'answered_count' => $answeredCount,
        'correct_count' => $correctCount,
        'total_questions' => $totalQuestions,
        'finished' => $finished
    ], JSON_UNESCAPED_UNICODE);

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


$blockResult =
    mysqli_query(
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


$block =
    mysqli_fetch_assoc(
        $blockResult
    );


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


$questionsResult =
    mysqli_query(
        $conn,
        $questionsQuery
    );


if ($questionsResult) {

    while (
        $row =
        mysqli_fetch_assoc(
            $questionsResult
        )
    ) {

        $questionId =
            (int) $row['id'];


        /*
        |--------------------------------------------------------------------------
        | Options
        |--------------------------------------------------------------------------
        */

        $row['options'] = [];


        $optionsResult =
            mysqli_query(
                $conn,
                "
                SELECT
                    option_key,
                    option_text
                FROM question_options
                WHERE question_id = $questionId
                ORDER BY option_key ASC
                "
            );


        if ($optionsResult) {

            while (
                $option =
                mysqli_fetch_assoc(
                    $optionsResult
                )
            ) {

                $row['options'][] =
                    $option;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Images
        |--------------------------------------------------------------------------
        */

        $row['images'] = [];


        $imagesResult =
            mysqli_query(
                $conn,
                "
                SELECT file_path
                FROM question_images
                WHERE question_id = $questionId
                ORDER BY id ASC
                "
            );


        if ($imagesResult) {

            while (
                $image =
                mysqli_fetch_assoc(
                    $imagesResult
                )
            ) {

                $row['images'][] =
                    $image['file_path'];
            }
        }


        $questions[] =
            $row;
    }
}


$totalQuestions =
    count($questions);


if ($totalQuestions === 0) {

    require_once __DIR__ . '/../layout/header.php';

    ?>

    <link rel="stylesheet" href="assets/css/style.css">

    <section class="page-section">

        <a href="blocks.php" class="page-back">
            ← Orqaga
        </a>

        <div class="blocks-content">

            <div class="blocks-empty">

                <div class="blocks-empty-icon">
                    <i data-lucide="clipboard-x"></i>
                </div>

                <h3>
                    Ushbu blokda faol savollar mavjud emas.
                </h3>

            </div>

        </div>

    </section>

    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function () {

                if (
                    typeof lucide !== 'undefined'
                ) {
                    lucide.createIcons();
                }

            }
        );
    </script>

    <?php

    require_once __DIR__ . '/../layout/footer.php';

    exit;
}


/*
|--------------------------------------------------------------------------
| Active block session
|--------------------------------------------------------------------------
*/

$sessionId = 0;


$activeSessionQuery = "
    SELECT
        id,
        total_questions
    FROM block_sessions
    WHERE user_id = $userId
      AND block_id = $blockId
      AND status = 'active'
    ORDER BY id DESC
    LIMIT 1
";


$activeSessionResult =
    mysqli_query(
        $conn,
        $activeSessionQuery
    );


if (
    $activeSessionResult &&
    mysqli_num_rows(
        $activeSessionResult
    ) > 0
) {

    $activeSession =
        mysqli_fetch_assoc(
            $activeSessionResult
        );


    $sessionId =
        (int) 
        $activeSession['id'];

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


    if (
        !mysqli_query(
            $conn,
            $createSessionQuery
        )
    ) {

        die(
            'Block sessiya yaratishda xatolik: ' .
            mysqli_error($conn)
        );
    }


    $sessionId =
        (int) 
        mysqli_insert_id(
            $conn
        );
}


/*
|--------------------------------------------------------------------------
| Existing answers
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


$selectedResult =
    mysqli_query(
        $conn,
        $selectedQuery
    );


if ($selectedResult) {

    while (
        $row =
        mysqli_fetch_assoc(
            $selectedResult
        )
    ) {

        $selectedAnswers[
            (int) 
            $row['question_id']
        ] =
            $row;
    }
}


$answeredIds =
    array_keys(
        $selectedAnswers
    );


/*
|--------------------------------------------------------------------------
| Current position
|--------------------------------------------------------------------------
*/

$positionKey =
    'block_position_' .
    $sessionId;


if (
    !isset(
    $_SESSION[$positionKey]
)
) {

    $_SESSION[$positionKey] =
        0;
}


$currentPosition =
    (int) 
    $_SESSION[$positionKey];


$currentPosition =
    max(
        0,
        min(
            $currentPosition,
            $totalQuestions - 1
        )
    );


/*
|--------------------------------------------------------------------------
| First unanswered
|--------------------------------------------------------------------------
*/

$firstUnanswered =
    $totalQuestions;


foreach (
    $questions
    as $index => $question
) {

    if (
        !isset(
        $selectedAnswers[
            (int) 
            $question['id']
        ]
    )
    ) {

        $firstUnanswered =
            $index;

        break;
    }
}


/*
|--------------------------------------------------------------------------
| Prevent skipping forward
|--------------------------------------------------------------------------
*/

if (
    $firstUnanswered <
    $totalQuestions &&
    $currentPosition >
    $firstUnanswered
) {

    $currentPosition =
        $firstUnanswered;
}


$_SESSION[$positionKey] =
    $currentPosition;


$currentQuestion =
    $questions[
        $currentPosition
    ];


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle =
    $block['name'];


require_once __DIR__ . '/../layout/header.php';

?>

<link rel="stylesheet" href="assets/css/style.css">


<section class="page-section block-solving-page" data-block-id="<?php
echo $blockId;
?>" data-session-id="<?php
echo $sessionId;
?>" data-total-questions="<?php
echo $totalQuestions;
?>">


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


            <?php if (
                !empty(
                $block['description']
            )
            ): ?>

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


    <div class="block-progress">

        <div class="block-progress-top">

            <span>
                Savol
            </span>

            <strong id="questionCounter">

                <?php
                echo $currentPosition + 1;
                ?>

                /

                <?php
                echo $totalQuestions;
                ?>

            </strong>

        </div>


        <div class="block-progress-bar">

            <div class="block-progress-fill" id="blockProgressFill" style="width: <?php
            echo (
                (
                    $currentPosition + 1
                )
                /
                $totalQuestions
            ) * 100;
            ?>%;"></div>

        </div>

    </div>


    <div class="question-card" id="questionCard" data-question-id="<?php
    echo (int) 
        $currentQuestion['id'];
    ?>">

        <div class="question-number">
            <?php
            echo $currentPosition + 1;
            ?>
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


        <?php if (
            count(
                $currentQuestion['images']
            ) > 0
        ): ?>

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


        <?php

        $currentQuestionId =
            (int) 
            $currentQuestion['id'];

        $currentAttempt =
            $selectedAnswers[
                $currentQuestionId
            ] ?? null;

        ?>


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
                    $selected =
                        $currentAttempt &&
                        $currentAttempt['answer'] ===
                        $option['option_key'];
                    ?>

                    <button type="button" class="question-option <?php
                    echo $selected
                        ? 'question-option-selected'
                        : '';
                    ?>" data-answer="<?php
                    echo htmlspecialchars(
                        $option['option_key'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>" <?php
                    echo $currentAttempt
                        ? 'disabled'
                        : '';
                    ?>>

                        <span class="question-option-key">
                            <?php
                            echo htmlspecialchars(
                                $option['option_key'],
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


            <?php

            $savedWritten = [
                'a' => '',
                'b' => ''
            ];


            if (
                $currentAttempt &&
                !empty(
                $currentAttempt['answer']
            )
            ) {

                $decoded =
                    json_decode(
                        (string) 
                        $currentAttempt['answer'],
                        true
                    );


                if (
                    is_array($decoded)
                ) {

                    $savedWritten['a'] =
                        (string) 
                        (
                            $decoded['a']
                            ?? ''
                        );

                    $savedWritten['b'] =
                        (string) 
                        (
                            $decoded['b']
                            ?? ''
                        );
                }
            }

            ?>


            <div class="question-written">


                <?php if (
                    trim(
                        (string) 
                        $currentQuestion['part_a_text']
                    ) !== ''
                ): ?>

                    <div class="question-written-part">

                        <div class="question-written-part-title">
                            A
                        </div>


                        <div class="question-written-part-text">

                            <?php
                            echo nl2br(
                                htmlspecialchars(
                                    $currentQuestion['part_a_text'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            );
                            ?>

                        </div>


                        <input type="text" class="question-answer-input" id="writtenAnswerA" placeholder="A qism javobi" value="<?php
                        echo htmlspecialchars(
                            $savedWritten['a'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>" <?php
                        echo $currentAttempt
                            ? 'disabled'
                            : '';
                        ?>>

                    </div>

                <?php endif; ?>


                <?php if (
                    trim(
                        (string) 
                        $currentQuestion['part_b_text']
                    ) !== ''
                ): ?>

                    <div class="question-written-part">

                        <div class="question-written-part-title">
                            B
                        </div>


                        <div class="question-written-part-text">

                            <?php
                            echo nl2br(
                                htmlspecialchars(
                                    $currentQuestion['part_b_text'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            );
                            ?>

                        </div>


                        <input type="text" class="question-answer-input" id="writtenAnswerB" placeholder="B qism javobi" value="<?php
                        echo htmlspecialchars(
                            $savedWritten['b'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>" <?php
                        echo $currentAttempt
                            ? 'disabled'
                            : '';
                        ?>>

                    </div>

                <?php endif; ?>


                <?php if (
                    !$currentAttempt
                ): ?>

                    <button type="button" class="question-answer-submit" id="writtenAnswerSubmit">
                        Javobni yuborish
                    </button>

                <?php endif; ?>


            </div>

        <?php endif; ?>


    </div>


    <div class="question-navigation">

        <button type="button" class="question-navigation-button" id="previousQuestion" <?php
        echo $currentPosition <= 0
            ? 'disabled'
            : '';
        ?>>
            ← Oldingi
        </button>


        <button type="button" class="question-navigation-button" id="nextQuestion" <?php
        echo $currentAttempt
            ? ''
            : 'disabled';
        ?>>

            <?php
            echo $currentPosition >=
                $totalQuestions - 1
                ? 'Tugatish'
                : 'Keyingi →';
            ?>

        </button>

    </div>


    <div class="block-result" id="blockResult" style="display:none;">

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
        function () {

            if (
                typeof lucide !== 'undefined'
            ) {
                lucide.createIcons();
            }


            const page =
                document.querySelector(
                    '.block-solving-page'
                );


            const blockId =
                Number(
                    page.dataset.blockId
                );


            const sessionId =
                Number(
                    page.dataset.sessionId
                );


            const totalQuestions =
                Number(
                    page.dataset.totalQuestions
                );


            let currentPosition =
                <?php
                echo $currentPosition;
                ?>;


            const questions =
                <?php

                echo json_encode(
                    array_map(
                        function (array $question): array {

                            return [

                                'id' =>
                                    (int) 
                                    $question['id'],

                                'type' =>
                                    $question[
                                        'question_type'
                                    ],

                                'text' =>
                                    $question['text'],

                                'part_a_text' =>
                                    $question[
                                        'part_a_text'
                                    ],

                                'part_b_text' =>
                                    $question[
                                        'part_b_text'
                                    ],

                                'options' =>
                                    $question[
                                        'options'
                                    ],

                                'images' =>
                                    $question[
                                        'images'
                                    ]

                            ];

                        },
                        $questions
                    ),
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                );

                ?>;


            const answerState =
                <?php
                echo json_encode(
                    $selectedAnswers,
                    JSON_UNESCAPED_UNICODE
                );
                ?>;


            function isAnswered(
                questionId
            ) {

                return Object.prototype.hasOwnProperty.call(
                    answerState,
                    String(questionId)
                );

            }


            function escapeHtml(
                value
            ) {

                const div =
                    document.createElement(
                        'div'
                    );

                div.textContent =
                    value ?? '';

                return div.innerHTML;

            }


            function escapeAttribute(
                value
            ) {

                return escapeHtml(
                    value
                )
                    .replace(
                        /"/g,
                        '&quot;'
                    )
                    .replace(
                        /'/g,
                        '&#039;'
                    );

            }


            function getWrittenAnswer(
                raw
            ) {

                if (!raw) {

                    return {
                        a: '',
                        b: ''
                    };
                }


                try {

                    const parsed =
                        JSON.parse(
                            raw
                        );


                    return {

                        a:
                            parsed.a ||
                            '',

                        b:
                            parsed.b ||
                            ''

                    };

                } catch (
                error
                ) {

                    return {
                        a: '',
                        b: ''
                    };

                }

            }


            function renderQuestion(
                position
            ) {

                if (
                    position < 0 ||
                    position >= questions.length
                ) {

                    return;
                }


                currentPosition =
                    position;


                const question =
                    questions[position];


                const card =
                    document.getElementById(
                        'questionCard'
                    );


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
                ).replace(
                    /\n/g,
                    '<br>'
                )
                    }
                </div>
            `;


                if (
                    question.images &&
                    question.images.length
                ) {

                    html += `
                    <div class="question-images">
                `;


                    question.images.forEach(
                        function (
                            image
                        ) {

                            html += `
                            <img
                                src="${escapeAttribute(
                                image
                            )}"
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
                        function (
                            option
                        ) {

                            const answered =
                                isAnswered(
                                    question.id
                                );


                            const selected =
                                answered &&
                                answerState[
                                    String(
                                        question.id
                                    )
                                ].answer ===
                                option.option_key;


                            html += `
                            <button
                                type="button"
                                class="question-option ${selected
                                    ? 'question-option-selected'
                                    : ''
                                }"
                                data-answer="${escapeAttribute(
                                    option.option_key
                                )}"
                                ${answered
                                    ? 'disabled'
                                    : ''
                                }
                            >

                                <span
                                    class="question-option-key"
                                >
                                    ${escapeHtml(
                                    option.option_key
                                )}
                                </span>

                                <span
                                    class="question-option-text"
                                >
                                    ${escapeHtml(
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

                    const answered =
                        isAnswered(
                            question.id
                        );


                    const stored =
                        answered
                            ? getWrittenAnswer(
                                answerState[
                                    String(
                                        question.id
                                    )
                                ].answer
                            )
                            : {
                                a: '',
                                b: ''
                            };


                    html += `
                    <div class="question-written">
                `;


                    if (
                        question.part_a_text
                    ) {

                        html += `
                        <div class="question-written-part">

                            <div class="question-written-part-title">
                                A
                            </div>

                            <div class="question-written-part-text">
                                ${escapeHtml(
                            question.part_a_text
                        ).replace(
                            /\n/g,
                            '<br>'
                        )
                            }
                            </div>

                            <input
                                type="text"
                                class="question-answer-input"
                                id="writtenAnswerA"
                                placeholder="A qism javobi"
                                value="${escapeAttribute(
                                stored.a
                            )}"
                                ${answered
                                ? 'disabled'
                                : ''
                            }
                            >

                        </div>
                    `;

                    }


                    if (
                        question.part_b_text
                    ) {

                        html += `
                        <div class="question-written-part">

                            <div class="question-written-part-title">
                                B
                            </div>

                            <div class="question-written-part-text">
                                ${escapeHtml(
                            question.part_b_text
                        ).replace(
                            /\n/g,
                            '<br>'
                        )
                            }
                            </div>

                            <input
                                type="text"
                                class="question-answer-input"
                                id="writtenAnswerB"
                                placeholder="B qism javobi"
                                value="${escapeAttribute(
                                stored.b
                            )}"
                                ${answered
                                ? 'disabled'
                                : ''
                            }
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


                card.innerHTML =
                    html;


                card.dataset.questionId =
                    question.id;


                updateProgress();

                updateNavigation();

                bindQuestionEvents();


                if (
                    typeof lucide !==
                    'undefined'
                ) {
                    lucide.createIcons();
                }

            }


            function updateProgress() {

                document.getElementById(
                    'questionCounter'
                ).textContent =
                    (
                        currentPosition + 1
                    ) +
                    ' / ' +
                    totalQuestions;


                document.getElementById(
                    'blockProgressFill'
                ).style.width =
                    (
                        (
                            currentPosition + 1
                        )
                        /
                        totalQuestions
                    ) *
                    100 +
                    '%';

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


                const question =
                    questions[
                    currentPosition
                    ];


                previous.disabled =
                    currentPosition <= 0;


                next.disabled =
                    !isAnswered(
                        question.id
                    );


                next.textContent =
                    currentPosition >=
                        totalQuestions - 1
                        ? 'Tugatish'
                        : 'Keyingi →';

            }


            function submitAnswer(
                questionId,
                answer,
                button,
                answerA = '',
                answerB = ''
            ) {

                if (
                    isAnswered(
                        questionId
                    )
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


                if (button) {
                    button.disabled = true;
                }


                const formData =
                    new FormData();


                formData.append(
                    'action',
                    'answer'
                );


                formData.append(
                    'session_id',
                    String(sessionId)
                );


                formData.append(
                    'question_id',
                    String(questionId)
                );


                formData.append(
                    'answer',
                    answer
                );


                formData.append(
                    'answer_a',
                    answerA
                );


                formData.append(
                    'answer_b',
                    answerB
                );


                fetch(
                    'block.php?id=' +
                    encodeURIComponent(
                        blockId
                    ),
                    {
                        method: 'POST',
                        body: formData,
                        credentials:
                            'same-origin'
                    }
                )
                    .then(
                        function (
                            response
                        ) {

                            return response.json();

                        }
                    )
                    .then(
                        function (
                            data
                        ) {

                            if (
                                !data.success
                            ) {

                                if (button) {
                                    button.disabled =
                                        false;
                                }


                                alert(
                                    data.message ||
                                    'Xatolik yuz berdi.'
                                );


                                return;
                            }


                            answerState[
                                String(
                                    questionId
                                )
                            ] = {

                                answer:
                                    question.type ===
                                        'written'

                                        ? JSON.stringify({
                                            a:
                                                answerA,
                                            b:
                                                answerB
                                        })

                                        : answer,

                                is_correct:
                                    data.is_correct

                            };


                            updateNavigation();


                            if (
                                data.finished
                            ) {

                                showResult(
                                    data.correct_count,
                                    data.total_questions
                                );

                            }

                        }
                    )
                    .catch(
                        function () {

                            if (button) {
                                button.disabled =
                                    false;
                            }


                            alert(
                                'Server bilan bog‘lanishda xatolik.'
                            );

                        }
                    );

            }


            function bindQuestionEvents() {

                document
                    .querySelectorAll(
                        '.question-option'
                    )
                    .forEach(
                        function (
                            button
                        ) {

                            button.addEventListener(
                                'click',
                                function () {

                                    const questionId =
                                        Number(
                                            document
                                                .getElementById(
                                                    'questionCard'
                                                )
                                                .dataset
                                                .questionId
                                        );


                                    submitAnswer(
                                        questionId,
                                        button.dataset.answer,
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


                if (
                    writtenButton
                ) {

                    writtenButton.addEventListener(
                        'click',
                        function () {

                            const questionId =
                                Number(
                                    document
                                        .getElementById(
                                            'questionCard'
                                        )
                                        .dataset
                                        .questionId
                                );


                            const inputA =
                                document.getElementById(
                                    'writtenAnswerA'
                                );


                            const inputB =
                                document.getElementById(
                                    'writtenAnswerB'
                                );


                            const answerA =
                                inputA
                                    ? inputA.value.trim()
                                    : '';


                            const answerB =
                                inputB
                                    ? inputB.value.trim()
                                    : '';


                            if (
                                !answerA &&
                                !answerB
                            ) {

                                return;
                            }


                            submitAnswer(
                                questionId,
                                '',
                                writtenButton,
                                answerA,
                                answerB
                            );

                        }
                    );

                }

            }


            function showResult(
                correct,
                total
            ) {

                document.getElementById(
                    'questionCard'
                ).style.display =
                    'none';


                document.querySelector(
                    '.question-navigation'
                ).style.display =
                    'none';


                document.getElementById(
                    'blockResultText'
                ).textContent =
                    correct +
                    ' / ' +
                    total +
                    ' ta savol to‘g‘ri.';


                document.getElementById(
                    'blockResult'
                ).style.display =
                    'block';


                if (
                    typeof lucide !==
                    'undefined'
                ) {
                    lucide.createIcons();
                }

            }


            document.getElementById(
                'previousQuestion'
            ).addEventListener(
                'click',
                function () {

                    if (
                        currentPosition > 0
                    ) {

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

                    const question =
                        questions[
                        currentPosition
                        ];


                    if (
                        !isAnswered(
                            question.id
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

                    } else {

                        const correct =
                            Object.values(
                                answerState
                            )
                                .filter(
                                    function (
                                        item
                                    ) {

                                        return (
                                            Number(
                                                item.is_correct
                                            ) === 1
                                        );

                                    }
                                )
                                .length;


                        showResult(
                            correct,
                            totalQuestions
                        );

                    }

                }
            );


            bindQuestionEvents();

        }
    );

</script>


<?php

require_once __DIR__ . '/../layout/footer.php';

?>