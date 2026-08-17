<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/student_engine.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId =
    (int)
    ($_SESSION['user_id'] ?? 0);

$blockId =
    (int)
    ($_GET['id'] ?? 0);

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
    ($_POST['action'] ?? '') === 'answer'
) {
    $sessionId =
        (int)
        ($_POST['session_id'] ?? 0);

    $questionId =
        (int)
        ($_POST['question_id'] ?? 0);

    $answer =
        trim(
            (string)
            ($_POST['answer'] ?? '')
        );

    $answerA =
        trim(
            (string)
            ($_POST['answer_a'] ?? '')
        );

    $answerB =
        trim(
            (string)
            ($_POST['answer_b'] ?? '')
        );

    $sessionResult =
        mysqli_query(
            $conn,
            "
            SELECT
                id,
                total_questions,
                status
            FROM block_sessions
            WHERE id = $sessionId
              AND user_id = $userId
              AND block_id = $blockId
            LIMIT 1
            "
        );

    if (
        !$sessionResult ||
        mysqli_num_rows($sessionResult) === 0
    ) {
        studentJson([
            'success' => false,
            'message' => 'Sessiya topilmadi.'
        ]);
    }

    $session =
        mysqli_fetch_assoc(
            $sessionResult
        );

    if (
        $session['status'] !== 'active'
    ) {
        studentJson([
            'success' => false,
            'message' =>
                'Blok sessiyasi faol emas.'
        ]);
    }

    $question =
        studentQuestion(
            $conn,
            $questionId
        );

    if ($question === null) {
        studentJson([
            'success' => false,
            'message' => 'Savol topilmadi.'
        ]);
    }

    $belongsResult =
        mysqli_query(
            $conn,
            "
            SELECT 1
            FROM block_questions
            WHERE block_id = $blockId
              AND question_id = $questionId
            LIMIT 1
            "
        );

    if (
        !$belongsResult ||
        mysqli_num_rows($belongsResult) === 0
    ) {
        studentJson([
            'success' => false,
            'message' =>
                'Savol ushbu blokka tegishli emas.'
        ]);
    }

    $existing =
        mysqli_query(
            $conn,
            "
            SELECT id
            FROM attempts
            WHERE user_id = $userId
              AND question_id = $questionId
              AND block_session_id = $sessionId
            LIMIT 1
            "
        );

    if (
        $existing &&
        mysqli_num_rows($existing) > 0
    ) {
        studentJson([
            'success' => false,
            'message' =>
                'Bu savolga allaqachon javob berilgansiz.'
        ]);
    }

    $evaluation =
        studentEvaluateAnswer(
            $question,
            $answer,
            $answerA,
            $answerB
        );

    if (
        !($evaluation['valid'] ?? false)
    ) {
        studentJson([
            'success' => false,
            'message' =>
                $evaluation['message'] ??
                'Javobni tekshirishda xatolik.'
        ]);
    }

    $safeAnswer =
        mysqli_real_escape_string(
            $conn,
            (string)
            $evaluation['stored_answer']
        );

    mysqli_begin_transaction(
        $conn
    );

    try {
        $attemptQuery =
            "
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
                    $evaluation['is_correct']
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

        studentUpdateMistakeQueue(
            $conn,
            $userId,
            $questionId,
            (bool)
            $evaluation['is_correct']
        );

        studentUpdateProgress(
            $conn,
            $userId,
            (int)
            $question['topic_id']
        );

        $progressResult =
            mysqli_query(
                $conn,
                "
                SELECT
                    COUNT(DISTINCT question_id)
                        AS answered_count,
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
                "
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
            mysqli_query(
                $conn,
                "
                UPDATE block_sessions
                SET
                    status = 'completed',
                    finished_at = NOW(),
                    score = $correctCount
                WHERE id = $sessionId
                AND user_id = $userId
                LIMIT 1
                "
            );
        }

        mysqli_commit(
            $conn
        );
    } catch (Throwable $exception) {
        mysqli_rollback(
            $conn
        );

        studentJson([
            'success' => false,
            'message' =>
                'Javobni saqlashda xatolik.'
        ]);
    }

    studentJson([
        'success' => true,
        'is_correct' =>
            (bool)
            $evaluation['is_correct'],
        'correct_answer' =>
            (string)
            ($evaluation['correct_answer'] ?? ''),
        'part_a_correct' =>
            $evaluation['part_a_correct'],
        'part_b_correct' =>
            $evaluation['part_b_correct'],
        'answered_count' =>
            $answeredCount,
        'correct_count' =>
            $correctCount,
        'total_questions' =>
            $totalQuestions,
        'finished' =>
            $finished
    ]);
}

/*
|--------------------------------------------------------------------------
| Load block
|--------------------------------------------------------------------------
*/
$blockResult =
    mysqli_query(
        $conn,
        "
        SELECT
            id,
            name,
            description
        FROM blocks
        WHERE id = $blockId
          AND is_active = 1
        LIMIT 1
        "
    );

if (
    !$blockResult ||
    mysqli_num_rows($blockResult) === 0
) {
    header(
        'Location: blocks.php'
    );
    exit;
}

$block =
    mysqli_fetch_assoc(
        $blockResult
    );

/*
|--------------------------------------------------------------------------
| Questions
|--------------------------------------------------------------------------
*/
$questions = [];

$result =
    mysqli_query(
        $conn,
        "
        SELECT
            bq.question_id
        FROM block_questions bq
        INNER JOIN questions q
            ON q.id = bq.question_id
           AND q.is_active = 1
        WHERE bq.block_id = $blockId
        ORDER BY bq.id ASC
        "
    );

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $questions[] =
            studentQuestion(
                $conn,
                (int)
                $row['question_id']
            );
    }
}

$questions =
    array_values(
        array_filter(
            $questions
        )
    );

$totalQuestions =
    count($questions);

if ($totalQuestions === 0) {
    header(
        'Location: blocks.php'
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/
$sessionId = 0;

$activeResult =
    mysqli_query(
        $conn,
        "
        SELECT id
        FROM block_sessions
        WHERE user_id = $userId
          AND block_id = $blockId
          AND status = 'active'
        ORDER BY id DESC
        LIMIT 1
        "
    );

if (
    $activeResult &&
    mysqli_num_rows($activeResult) > 0
) {
    $active =
        mysqli_fetch_assoc(
            $activeResult
        );

    $sessionId =
        (int)
        $active['id'];
} else {
    mysqli_query(
        $conn,
        "
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
        "
    );

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
$answers = [];

$answersResult =
    mysqli_query(
        $conn,
        "
        SELECT
            question_id,
            answer,
            is_correct
        FROM attempts
        WHERE user_id = $userId
          AND block_session_id = $sessionId
        "
    );

if ($answersResult) {
    while ($row = mysqli_fetch_assoc($answersResult)) {
        $answers[
            (string)
            $row['question_id']
        ] =
            $row;
    }
}

$positionKey =
    'student_block_position_' .
    $sessionId;

if (
    !isset(
        $_SESSION[$positionKey]
    )
) {
    $_SESSION[$positionKey] =
        0;
}

$position =
    max(
        0,
        min(
            (int)
            $_SESSION[$positionKey],
            $totalQuestions - 1
        )
    );

$firstUnanswered =
    $totalQuestions;

foreach (
    $questions as $index => $question
) {
    if (
        !isset(
            $answers[
                (string)
                $question['id']
            ]
        )
    ) {
        $firstUnanswered =
            $index;
        break;
    }
}

if (
    $firstUnanswered <
    $totalQuestions &&
    $position >
    $firstUnanswered
) {
    $position =
        $firstUnanswered;
}

$_SESSION[$positionKey] =
    $position;

$pageTitle =
    (string)
    $block['name'];

require_once __DIR__ . '/../layout/header.php';

?>

<link rel="stylesheet" href="assets/css/style.css">

<section
    class="page-section block-solving-page student-test-page"
    data-block-id="<?php echo $blockId; ?>"
    data-session-id="<?php echo $sessionId; ?>"
>

    <a
        href="blocks.php"
        class="page-back"
    >
        ← Bloklar
    </a>


    <div class="student-test-header">

        <div>

            <span class="student-test-label">
                BLOK
            </span>

            <h1 class="page-title">
                <?php
                echo studentH(
                    (string)
                    $block['name']
                );
                ?>
            </h1>

        </div>

        <strong id="studentQuestionCounter">
            <?php
            echo $position + 1;
            ?> /
            <?php
            echo $totalQuestions;
            ?>
        </strong>

    </div>


    <div class="student-test-progress">

        <span
            id="studentTestProgress"
            style="width: <?php
                echo (
                    (
                        $position + 1
                    ) /
                    $totalQuestions *
                    100
                );
            ?>%;"
        ></span>

    </div>


    <div
        class="student-test-navigator"
        id="studentTestNavigator"
    >

        <?php foreach (
            $questions as $index => $question
        ): ?>

            <?php
            $qid =
                (int)
                $question['id'];

            $answered =
                isset(
                    $answers[
                        (string)
                        $qid
                    ]
                );
            ?>

            <button
                type="button"
                class="student-test-nav <?php
                    echo $index === $position
                        ? 'is-current'
                        : '';

                    echo $answered
                        ? ' is-answered'
                        : '';
                ?>"
                data-position="<?php
                    echo $index;
                ?>"
                <?php
                echo $index > $position
                    ? 'disabled'
                    : '';
                ?>
            >
                <?php
                echo $index + 1;
                ?>
            </button>

        <?php endforeach; ?>

    </div>


    <div
        id="studentQuestionFeedback"
        class="student-question-feedback"
        hidden
    ></div>


    <div
        class="student-question-shell"
        id="studentQuestionShell"
    ></div>


    <div
        class="student-test-bottom"
        id="studentTestBottom"
    >

        <button
            type="button"
            id="studentPrevious"
            class="student-nav-button"
        >
            ← Oldingi
        </button>


        <button
            type="button"
            id="studentNext"
            class="student-nav-button"
        >
            Keyingi →
        </button>

    </div>


    <div
        id="studentBlockResult"
        class="student-result"
        hidden
    >

        <div class="student-result-icon">
            <i data-lucide="circle-check"></i>
        </div>

        <strong id="studentResultScore"></strong>

        <span>
            natija
        </span>

        <a href="blocks.php">
            Bloklarga qaytish
        </a>

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

        const questions =
            <?php
            echo json_encode(
                $questions,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );
            ?>;

        const answers =
            <?php
            echo json_encode(
                $answers,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );
            ?>;

        const blockId =
            <?php
            echo $blockId;
            ?>;

        const sessionId =
            <?php
            echo $sessionId;
            ?>;

        let position =
            <?php
            echo $position;
            ?>;

        let submitting =
            false;

        let feedbackTimer =
            null;


        const shell =
            document.getElementById(
                'studentQuestionShell'
            );

        const feedback =
            document.getElementById(
                'studentQuestionFeedback'
            );


        function esc(value) {

            const div =
                document.createElement(
                    'div'
                );

            div.textContent =
                value ?? '';

            return div.innerHTML;
        }


        function attr(value) {

            return esc(
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


        function writtenValue(raw) {

            try {
                return JSON.parse(
                    raw
                ) || {};
            } catch (e) {
                return {};
            }

        }


        function answered(id) {

            return Object.prototype.hasOwnProperty.call(
                answers,
                String(id)
            );

        }


        function render() {

            clearTimeout(
                feedbackTimer
            );

            feedback.hidden =
                true;

            feedback.textContent =
                '';

            feedback.className =
                'student-question-feedback';


            const question =
                questions[position];

            const stored =
                answers[
                    String(
                        question.id
                    )
                ] || null;


            let html = `
                <div class="student-question-number">
                    Savol ${position + 1}
                </div>

                <div class="student-question-text">
                    ${
                        esc(
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
                    <div class="student-question-images">
                `;

                question.images.forEach(
                    function (image) {

                        html += `
                            <img
                                src="${attr(image)}"
                                alt=""
                            >
                        `;

                    }
                );

                html += `
                    </div>
                `;
            }


            if (
                question.question_type ===
                    'multiple_choice' ||
                question.question_type ===
                    'six_option'
            ) {

                html += `
                    <div class="student-answer-options">
                `;


                question.options.forEach(
                    function (option) {

                        const selected =
                            stored &&
                            String(
                                stored.answer
                            ) ===
                            String(
                                option.option_key
                            );


                        html += `
                            <button
                                type="button"
                                class="student-answer-option ${
                                    selected
                                        ? 'is-selected'
                                        : ''
                                }"
                                data-answer="${attr(
                                    option.option_key
                                )}"
                                ${stored ? 'disabled' : ''}
                            >

                                <span class="student-option-key">
                                    ${esc(
                                        option.option_key
                                    )}
                                </span>

                                <span class="student-option-text">
                                    ${
                                        esc(
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

                const saved =
                    stored
                        ? writtenValue(
                            stored.answer
                        )
                        : {};


                html += `
                    <div class="student-written-answer">
                `;


                if (
                    question.part_a_text
                ) {

                    html += `
                        <div class="student-written-part">

                            <strong>A</strong>

                            <div>
                                ${
                                    esc(
                                        question.part_a_text
                                    ).replace(
                                        /\n/g,
                                        '<br>'
                                    )
                                }
                            </div>

                            <input
                                type="text"
                                id="answerA"
                                value="${attr(
                                    saved.a || ''
                                )}"
                                placeholder="Javob"
                                ${stored ? 'disabled' : ''}
                            >

                        </div>
                    `;

                }


                if (
                    question.part_b_text
                ) {

                    html += `
                        <div class="student-written-part">

                            <strong>B</strong>

                            <div>
                                ${
                                    esc(
                                        question.part_b_text
                                    ).replace(
                                        /\n/g,
                                        '<br>'
                                    )
                                }
                            </div>

                            <input
                                type="text"
                                id="answerB"
                                value="${attr(
                                    saved.b || ''
                                )}"
                                placeholder="Javob"
                                ${stored ? 'disabled' : ''}
                            >

                        </div>
                    `;

                }


                if (!stored) {

                    html += `
                        <button
                            type="button"
                            class="student-submit-written"
                            id="submitWritten"
                        >
                            Javobni tekshirish
                        </button>
                    `;

                }


                html += `
                    </div>
                `;
            }


            shell.innerHTML =
                html;


            updateNavigation();

            bindEvents();

            if (
                typeof lucide !==
                'undefined'
            ) {
                lucide.createIcons();
            }
        }


        function updateNavigation() {

            document.getElementById(
                'studentQuestionCounter'
            ).textContent =
                (
                    position + 1
                ) +
                ' / ' +
                questions.length;


            document.getElementById(
                'studentTestProgress'
            ).style.width =
                (
                    (
                        position + 1
                    ) /
                    questions.length *
                    100
                ) +
                '%';


            document.getElementById(
                'studentPrevious'
            ).disabled =
                position <= 0;


            const current =
                questions[position];


            document.getElementById(
                'studentNext'
            ).disabled =
                !answered(
                    current.id
                );


            document.getElementById(
                'studentNext'
            ).textContent =
                position >=
                questions.length - 1
                    ? 'Tugatish'
                    : 'Keyingi →';


            document
                .querySelectorAll(
                    '.student-test-nav'
                )
                .forEach(
                    function (button) {

                        const p =
                            Number(
                                button.dataset.position
                            );

                        button.classList.toggle(
                            'is-current',
                            p === position
                        );

                        button.classList.toggle(
                            'is-answered',
                            answered(
                                questions[p].id
                            )
                        );

                        button.disabled =
                            p > position;

                    }
                );
        }


        function showFeedback(
            correct
        ) {

            feedback.hidden =
                false;

            feedback.className =
                'student-question-feedback ' +
                (
                    correct
                        ? 'is-correct'
                        : 'is-wrong'
                );

            feedback.textContent =
                correct
                    ? 'To‘g‘ri!'
                    : 'Noto‘g‘ri. To‘g‘ri javob yashil bilan ko‘rsatildi.';
        }


        function colorOptions(
            selected,
            correct
        ) {

            document
                .querySelectorAll(
                    '.student-answer-option'
                )
                .forEach(
                    function (button) {

                        const value =
                            String(
                                button.dataset.answer
                            );


                        if (
                            value.toUpperCase() ===
                            String(
                                correct
                            ).toUpperCase()
                        ) {

                            button.classList.add(
                                'is-correct'
                            );
                        }


                        if (
                            value.toUpperCase() ===
                            String(
                                selected
                            ).toUpperCase() &&
                            value.toUpperCase() !==
                            String(
                                correct
                            ).toUpperCase()
                        ) {

                            button.classList.add(
                                'is-wrong'
                            );
                        }

                        button.disabled =
                            true;
                    }
                );
        }


        function submit(
            answer,
            answerA,
            answerB,
            button
        ) {

            if (
                submitting ||
                answered(
                    questions[position].id
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


            submitting =
                true;


            if (button) {
                button.disabled =
                    true;
            }


            const form =
                new FormData();

            form.append(
                'action',
                'answer'
            );

            form.append(
                'session_id',
                String(sessionId)
            );

            form.append(
                'question_id',
                String(
                    questions[position].id
                )
            );

            form.append(
                'answer',
                answer
            );

            form.append(
                'answer_a',
                answerA
            );

            form.append(
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
                    body: form,
                    credentials:
                        'same-origin'
                }
            )
            .then(
                function (response) {
                    return response.json();
                }
            )
            .then(
                function (data) {

                    if (
                        !data.success
                    ) {

                        submitting =
                            false;

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


                    answers[
                        String(
                            questions[position].id
                        )
                    ] = {

                        answer:
                            answer ||
                            JSON.stringify({
                                a:
                                    answerA,
                                b:
                                    answerB
                            }),

                        is_correct:
                            data.is_correct
                    };


                    if (
                        questions[position]
                            .question_type ===
                            'multiple_choice' ||
                        questions[position]
                            .question_type ===
                            'six_option'
                    ) {

                        colorOptions(
                            answer,
                            data.correct_answer
                        );

                    } else {

                        document
                            .querySelectorAll(
                                '.student-written-part input'
                            )
                            .forEach(
                                function (input) {
                                    input.disabled = true;
                                }
                            );
                    }


                    showFeedback(
                        data.is_correct
                    );


                    updateNavigation();


                    feedbackTimer =
                        setTimeout(
                            function () {

                                submitting =
                                    false;


                                if (
                                    data.finished
                                ) {

                                    showResult(
                                        data.correct_count,
                                        data.total_questions
                                    );

                                    return;
                                }


                                if (
                                    position <
                                    questions.length - 1
                                ) {

                                    position++;

                                    render();

                                } else {

                                    showResult(
                                        data.correct_count,
                                        data.total_questions
                                    );
                                }

                            },
                            900
                        );

                }
            )
            .catch(
                function () {

                    submitting =
                        false;

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


        function bindEvents() {

            document
                .querySelectorAll(
                    '.student-answer-option'
                )
                .forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function () {

                                submit(
                                    button.dataset.answer,
                                    '',
                                    '',
                                    button
                                );

                            }
                        );

                    }
                );


            const written =
                document.getElementById(
                    'submitWritten'
                );

            if (written) {

                written.addEventListener(
                    'click',
                    function () {

                        const a =
                            document.getElementById(
                                'answerA'
                            );

                        const b =
                            document.getElementById(
                                'answerB'
                            );

                        submit(
                            '',
                            a
                                ? a.value.trim()
                                : '',
                            b
                                ? b.value.trim()
                                : '',
                            written
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
                'studentQuestionShell'
            ).hidden =
                true;

            document.getElementById(
                'studentTestNavigator'
            ).hidden =
                true;

            document.getElementById(
                'studentTestBottom'
            ).hidden =
                true;


            document.getElementById(
                'studentResultScore'
            ).textContent =
                correct +
                ' / ' +
                total;


            document.getElementById(
                'studentBlockResult'
            ).hidden =
                false;

            if (
                typeof lucide !==
                'undefined'
            ) {
                lucide.createIcons();
            }
        }


        document.getElementById(
            'studentPrevious'
        ).addEventListener(
            'click',
            function () {

                if (
                    position > 0
                ) {

                    clearTimeout(
                        feedbackTimer
                    );

                    submitting =
                        false;

                    position--;

                    render();
                }

            }
        );


        document.getElementById(
            'studentNext'
        ).addEventListener(
            'click',
            function () {

                if (
                    !answered(
                        questions[position].id
                    )
                ) {
                    return;
                }

                if (
                    position <
                    questions.length - 1
                ) {

                    position++;

                    render();

                } else {

                    const correct =
                        Object.values(
                            answers
                        ).filter(
                            function (item) {
                                return Number(
                                    item.is_correct
                                ) === 1;
                            }
                        ).length;

                    showResult(
                        correct,
                        questions.length
                    );
                }

            }
        );


        document
            .querySelectorAll(
                '.student-test-nav'
            )
            .forEach(
                function (button) {

                    button.addEventListener(
                        'click',
                        function () {

                            const target =
                                Number(
                                    button.dataset.position
                                );

                            if (
                                target <=
                                position
                            ) {

                                clearTimeout(
                                    feedbackTimer
                                );

                                submitting =
                                    false;

                                position =
                                    target;

                                render();
                            }

                        }
                    );

                }
            );


        render();

    }
);
</script>

<style>
.student-test-header {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 10px;
}

.student-test-label {
    display: block;
    margin-bottom: 6px;
    color: #55aaff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.4px;
}

.student-test-header strong {
    opacity: .65;
    font-size: 13px;
}

.student-test-progress {
    height: 6px;
    overflow: hidden;
    margin-bottom: 12px;
    background: rgba(255,255,255,.08);
    border-radius: 99px;
}

.student-test-progress span {
    display: block;
    height: 100%;
    background: #3d9cff;
    border-radius: inherit;
    transition: width 200ms ease;
}

.student-test-navigator {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 12px;
}

.student-test-nav {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: inherit;
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 7px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 750;
}

.student-test-nav.is-current {
    color: #fff;
    background: rgba(61,156,255,.14);
    border-color: rgba(61,156,255,.45);
}

.student-test-nav.is-answered {
    color: #9ce2b8;
    border-color: rgba(85,202,137,.25);
}

.student-test-nav:disabled {
    opacity: .38;
    cursor: default;
}

.student-question-feedback {
    margin-bottom: 10px;
    padding: 11px 13px;
    border-radius: 9px;
    font-size: 12px;
}

.student-question-feedback.is-correct {
    color: #a0e3b9;
    background: rgba(85,202,137,.10);
    border: 1px solid rgba(85,202,137,.25);
}

.student-question-feedback.is-wrong {
    color: #ffb4bb;
    background: rgba(239,92,102,.10);
    border: 1px solid rgba(239,92,102,.25);
}

.student-question-shell {
    padding: 20px;
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px;
}

.student-question-number {
    margin-bottom: 8px;
    color: #7f8b99;
    font-size: 11px;
}

.student-question-text {
    font-size: 15px;
    line-height: 1.65;
}

.student-question-images {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 16px;
}

.student-question-images img {
    max-width: 100%;
    max-height: 420px;
    margin: 0 auto;
    border-radius: 9px;
}

.student-answer-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 18px;
}

.student-answer-option {
    width: 100%;
    min-height: 52px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 11px;
    color: inherit;
    background: rgba(255,255,255,.018);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 9px;
    cursor: pointer;
    text-align: left;
    transition:
        transform 150ms ease,
        background 150ms ease,
        border-color 150ms ease;
}

.student-answer-option:hover {
    transform: translateY(-1px);
    background: rgba(255,255,255,.035);
}

.student-option-key {
    width: 31px;
    height: 31px;
    flex: 0 0 31px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 7px;
    font-size: 11px;
    font-weight: 800;
}

.student-option-text {
    flex: 1;
    line-height: 1.5;
    font-size: 13px;
}

.student-answer-option.is-correct {
    color: #b9edca !important;
    background: rgba(85,202,137,.13) !important;
    border-color: rgba(85,202,137,.45) !important;
}

.student-answer-option.is-wrong {
    color: #ffb7bd !important;
    background: rgba(239,92,102,.13) !important;
    border-color: rgba(239,92,102,.45) !important;
}

.student-written-answer {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 18px;
}

.student-written-part {
    display: flex;
    flex-direction: column;
    gap: 9px;
    padding: 14px;
    background: rgba(255,255,255,.018);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 9px;
}

.student-written-part strong {
    width: 29px;
    height: 29px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,.06);
    border-radius: 7px;
}

.student-written-part input {
    min-height: 43px;
    padding: 0 12px;
    color: inherit;
    background: rgba(0,0,0,.14);
    border: 1px solid rgba(255,255,255,.10);
    border-radius: 8px;
}

.student-submit-written {
    min-height: 43px;
    align-self: flex-start;
    padding: 0 15px;
    color: #fff;
    background: #2f83df;
    border: 1px solid #4292ec;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}

.student-test-bottom {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-top: 12px;
}

.student-nav-button {
    min-height: 42px;
    padding: 0 14px;
    color: inherit;
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.09);
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}

.student-nav-button:disabled {
    opacity: .35;
    cursor: default;
}

.student-result {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 7px;
    padding: 34px 20px;
    text-align: center;
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px;
}

.student-result-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ce2b8;
    background: rgba(85,202,137,.10);
    border-radius: 50%;
}

.student-result > strong {
    font-size: 32px;
}

.student-result > span {
    opacity: .5;
    font-size: 11px;
}

.student-result > a {
    margin-top: 9px;
    color: #71b8fa;
    text-decoration: none;
}

@media (max-width: 620px) {
    .student-test-header {
        align-items: stretch;
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>
