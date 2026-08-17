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

studentUpdateProgress(
    $conn,
    $userId
);

$progress = 0;

$progressResult =
    mysqli_query(
        $conn,
        "
        SELECT progress_percent
        FROM user_progress
        WHERE user_id = $userId
        LIMIT 1
        "
    );

if ($progressResult) {
    $row =
        mysqli_fetch_assoc(
            $progressResult
        );

    $progress =
        (float)
        (
            $row['progress_percent']
            ?? 0
        );
}

$unlockProgress = 95;

if ($progress < $unlockProgress) {
    $pageTitle = 'Imtihonga tayyormanmi?';

    require_once __DIR__ . '/../layout/header.php';
    ?>

    <link rel="stylesheet" href="assets/css/style.css">

    <section class="page-section">

        <a
            href="dashboard.php"
            class="page-back"
        >
            ← Orqaga
        </a>

        <div class="student-readiness-locked">

            <div class="student-readiness-icon">
                <i data-lucide="lock"></i>
            </div>

            <h1>
                Imtihonga tayyormanmi?
            </h1>

            <p>
                <?php
                echo rtrim(
                    rtrim(
                        number_format(
                            $progress,
                            2,
                            '.',
                            ''
                        ),
                        '0'
                    ),
                    '.'
                );
                ?>% / 95%
            </p>

            <div class="student-readiness-progress">
                <span
                    style="width: <?php
                        echo min(
                            100,
                            (
                                $progress /
                                $unlockProgress *
                                100
                            )
                        );
                    ?>%;"
                ></span>
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

    <style>
    .student-readiness-locked {
        max-width: 520px;
        margin: 50px auto;
        padding: 30px;
        text-align: center;
        background: rgba(255,255,255,.025);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 13px;
    }

    .student-readiness-icon {
        width: 54px;
        height: 54px;
        margin: 0 auto 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #8c98a5;
        background: rgba(255,255,255,.05);
        border-radius: 50%;
    }

    .student-readiness-locked p {
        opacity: .55;
        font-size: 12px;
    }

    .student-readiness-progress {
        height: 7px;
        overflow: hidden;
        margin-top: 14px;
        background: rgba(255,255,255,.08);
        border-radius: 99px;
    }

    .student-readiness-progress span {
        display: block;
        height: 100%;
        background: #3d9cff;
    }
    </style>

    <?php
    require_once __DIR__ . '/../layout/footer.php';
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
                status
            FROM readiness_sessions
            WHERE id = $sessionId
              AND user_id = $userId
            LIMIT 1
            "
        );

    if (
        !$sessionResult ||
        mysqli_num_rows(
            $sessionResult
        ) === 0
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
            'message' => 'Imtihon sessiyasi faol emas.'
        ]);
    }

    $startedResult =
        mysqli_query(
            $conn,
            "
            SELECT
                TIMESTAMPDIFF(
                    SECOND,
                    started_at,
                    NOW()
                ) AS elapsed
            FROM readiness_sessions
            WHERE id = $sessionId
              AND user_id = $userId
            LIMIT 1
            "
        );

    $elapsed = 0;

    if ($startedResult) {
        $row =
            mysqli_fetch_assoc(
                $startedResult
            );

        $elapsed =
            (int)
            $row['elapsed'];
    }

    if ($elapsed >= 150 * 60) {
        mysqli_query(
            $conn,
            "
            UPDATE readiness_sessions
            SET
                status = 'completed',
                finished_at = NOW()
            WHERE id = $sessionId
              AND user_id = $userId
            LIMIT 1
            "
        );

        studentJson([
            'success' => false,
            'expired' => true,
            'message' => 'Vaqt tugadi.'
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

    $belongs =
        mysqli_query(
            $conn,
            "
            SELECT 1
            FROM readiness_questions
            WHERE session_id = $sessionId
              AND question_id = $questionId
            LIMIT 1
            "
        );

    if (
        !$belongs ||
        mysqli_num_rows($belongs) === 0
    ) {
        studentJson([
            'success' => false,
            'message' => 'Savol ushbu imtihonga tegishli emas.'
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
              AND readiness_session_id = $sessionId
            LIMIT 1
            "
        );

    if (
        $existing &&
        mysqli_num_rows($existing) > 0
    ) {
        studentJson([
            'success' => false,
            'message' => 'Bu savolga allaqachon javob berilgansiz.'
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
                $evaluation['message']
                ?? 'Javobni tekshirishda xatolik.'
        ]);
    }

    $safeAnswer =
        mysqli_real_escape_string(
            $conn,
            (string)
            $evaluation['stored_answer']
        );

    mysqli_begin_transaction($conn);

    try {
        mysqli_query(
            $conn,
            "
            INSERT INTO attempts (
                user_id,
                question_id,
                readiness_session_id,
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
            "
        );

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

        $countResult =
            mysqli_query(
                $conn,
                "
                SELECT
                    COUNT(*) AS answered,
                    COALESCE(
                        SUM(
                            CASE
                                WHEN is_correct = 1
                                THEN 1
                                ELSE 0
                            END
                        ),
                        0
                    ) AS correct
                FROM attempts
                WHERE user_id = $userId
                  AND readiness_session_id = $sessionId
                "
            );

        $answered = 0;
        $correct = 0;

        if ($countResult) {
            $count =
                mysqli_fetch_assoc(
                    $countResult
                );

            $answered =
                (int)
                $count['answered'];

            $correct =
                (int)
                $count['correct'];
        }

        $finished =
            $answered >= 55;

        if ($finished) {
            $percentage =
                round(
                    (
                        $correct /
                        55
                    ) * 100,
                    2
                );

            $result =
                $percentage >= 70
                    ? 'ready'
                    : 'not_ready';

            mysqli_query(
                $conn,
                "
                UPDATE readiness_sessions
                SET
                    status = 'completed',
                    finished_at = NOW(),
                    score = $correct,
                    percentage = $percentage,
                    result = '$result'
                WHERE id = $sessionId
                  AND user_id = $userId
                LIMIT 1
                "
            );
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);

        studentJson([
            'success' => false,
            'message' => 'Javobni saqlashda xatolik.'
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
        'answered' =>
            $answered,
        'correct' =>
            $correct,
        'finished' =>
            $finished,
        'elapsed' =>
            $elapsed
    ]);
}

/*
|--------------------------------------------------------------------------
| Active readiness session
|--------------------------------------------------------------------------
*/
$sessionId = 0;

$sessionResult =
    mysqli_query(
        $conn,
        "
        SELECT
            id,
            started_at,
            status
        FROM readiness_sessions
        WHERE user_id = $userId
          AND status = 'active'
        ORDER BY id DESC
        LIMIT 1
        "
    );

if (
    $sessionResult &&
    mysqli_num_rows($sessionResult) > 0
) {
    $session =
        mysqli_fetch_assoc(
            $sessionResult
        );

    $sessionId =
        (int)
        $session['id'];
} else {
    /*
     * Approximate official structure from the current schema:
     * 35 objective + 20 written = 55.
     * The schema currently has no parent-problem/order column,
     * so exact official problem grouping cannot yet be encoded.
     */
    $questionIds = [];

    $objectiveResult =
        mysqli_query(
            $conn,
            "
            SELECT id
            FROM questions
            WHERE is_active = 1
              AND question_type IN (
                  'multiple_choice',
                  'six_option'
              )
            ORDER BY RAND()
            LIMIT 35
            "
        );

    if ($objectiveResult) {
        while (
            $row =
            mysqli_fetch_assoc(
                $objectiveResult
            )
        ) {
            $questionIds[] =
                (int)
                $row['id'];
        }
    }

    $writtenResult =
        mysqli_query(
            $conn,
            "
            SELECT id
            FROM questions
            WHERE is_active = 1
              AND question_type = 'written'
            ORDER BY RAND()
            LIMIT 20
            "
        );

    if ($writtenResult) {
        while (
            $row =
            mysqli_fetch_assoc(
                $writtenResult
            )
        ) {
            $questionIds[] =
                (int)
                $row['id'];
        }
    }

    if (
        count($questionIds) < 55
    ) {
        $fallbackResult =
            mysqli_query(
                $conn,
                "
                SELECT id
                FROM questions
                WHERE is_active = 1
                ORDER BY RAND()
                LIMIT 55
                "
            );

        $questionIds = [];

        if ($fallbackResult) {
            while (
                $row =
                mysqli_fetch_assoc(
                    $fallbackResult
                )
            ) {
                $questionIds[] =
                    (int)
                    $row['id'];
            }
        }
    }

    if (
        count($questionIds) < 55
    ) {
        $pageTitle = 'Imtihonga tayyormanmi?';

        require_once __DIR__ . '/../layout/header.php';
        ?>

        <section class="page-section">
            <a href="dashboard.php" class="page-back">
                ← Orqaga
            </a>

            <div class="blocks-content">
                <div class="blocks-empty">
                    <h3>
                        Imtihon uchun yetarli savol mavjud emas.
                    </h3>
                </div>
            </div>
        </section>

        <?php
        require_once __DIR__ . '/../layout/footer.php';
        exit;
    }

    mysqli_query(
        $conn,
        "
        INSERT INTO readiness_sessions (
            user_id
        )
        VALUES (
            $userId
        )
        "
    );

    $sessionId =
        (int)
        mysqli_insert_id(
            $conn
        );

    foreach (
        $questionIds as $questionId
    ) {
        mysqli_query(
            $conn,
            "
            INSERT INTO readiness_questions (
                session_id,
                question_id
            )
            VALUES (
                $sessionId,
                $questionId
            )
            "
        );
    }
}

/*
|--------------------------------------------------------------------------
| Load readiness questions
|--------------------------------------------------------------------------
*/
$questions = [];

$result =
    mysqli_query(
        $conn,
        "
        SELECT
            rq.id AS session_question_id,
            q.id,
            q.question_type,
            q.text,
            q.part_a_text,
            q.part_b_text
        FROM readiness_questions rq
        INNER JOIN questions q
            ON q.id = rq.question_id
        WHERE rq.session_id = $sessionId
          AND q.is_active = 1
        ORDER BY rq.id ASC
        "
    );

if ($result) {
    while (
        $row =
        mysqli_fetch_assoc($result)
    ) {
        $questions[] =
            studentQuestion(
                $conn,
                (int)
                $row['id']
            );
    }
}

$questions =
    array_values(
        array_filter(
            $questions
        )
    );

$pageTitle =
    'Imtihonga tayyormanmi?';

require_once __DIR__ . '/../layout/header.php';

?>

<link rel="stylesheet" href="assets/css/style.css">

<section
    class="page-section student-test-page readiness-test-page"
    data-session-id="<?php echo $sessionId; ?>"
>

    <a
        href="dashboard.php"
        class="page-back"
    >
        ← Bosh sahifa
    </a>


    <div class="readiness-test-top">

        <div>

            <span>
                IMTIHON
            </span>

            <h1 class="page-title">
                Imtihonga tayyormanmi?
            </h1>

        </div>

        <strong id="readinessTimer">
            150:00
        </strong>

    </div>


    <div class="readiness-test-progress">

        <span id="readinessProgressBar"></span>

    </div>


    <div
        class="readiness-test-navigator"
        id="readinessNavigator"
    ></div>


    <div
        class="readiness-test-question"
        id="readinessQuestion"
    ></div>


    <div class="readiness-test-controls">

        <button
            type="button"
            id="readinessPrevious"
            disabled
        >
            ← Oldingi
        </button>

        <button
            type="button"
            id="readinessNext"
            disabled
        >
            Keyingi →
        </button>

    </div>


    <div
        id="readinessResult"
        hidden
        class="student-result"
    >

        <div class="student-result-icon">
            <i data-lucide="graduation-cap"></i>
        </div>

        <strong id="readinessScore">
        </strong>

        <span id="readinessGrade">
        </span>

        <a href="dashboard.php">
            Bosh sahifaga qaytish
        </a>

    </div>

</section>


<script src="https://unpkg.com/lucide@latest"></script>

<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {

        if (
            typeof lucide !==
            'undefined'
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

        const sessionId =
            <?php
            echo $sessionId;
            ?>;

        let position = 0;

        const answers = {};

        let submitting = false;

        let timerSeconds = 150 * 60;


        const shell =
            document.getElementById(
                'readinessQuestion'
            );


        const navigator =
            document.getElementById(
                'readinessNavigator'
            );


        const timer =
            document.getElementById(
                'readinessTimer'
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

            return esc(value)
                .replace(
                    /"/g,
                    '&quot;'
                )
                .replace(
                    /'/g,
                    '&#039;'
                );
        }


        function buildNavigator() {

            navigator.innerHTML = '';

            questions.forEach(
                function (
                    question,
                    index
                ) {

                    const button =
                        document.createElement(
                            'button'
                        );

                    button.type =
                        'button';

                    button.textContent =
                        String(
                            index + 1
                        );

                    button.disabled =
                        false;

                    button.className =
                        'readiness-nav-number';

                    button.addEventListener(
                        'click',
                        function () {

                            position =
                                index;

                            render();

                        }
                    );

                    navigator.appendChild(
                        button
                    );
                }
            );

        }


        function render() {

            const question =
                questions[position];

            const stored =
                answers[
                    String(
                        question.id
                    )
                ] || null;


            let html = `
                <div class="readiness-question-number">
                    Savol ${position + 1}
                </div>

                <div class="readiness-question-text">
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
                                ${
                                    stored
                                        ? 'disabled'
                                        : ''
                                }
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

                let saved = {};

                if (stored) {

                    try {
                        saved =
                            JSON.parse(
                                stored.answer
                            ) || {};
                    } catch (e) {
                        saved = {};
                    }
                }


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
                                id="readinessA"
                                type="text"
                                value="${attr(
                                    saved.a || ''
                                )}"
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
                                id="readinessB"
                                type="text"
                                value="${attr(
                                    saved.b || ''
                                )}"
                                ${stored ? 'disabled' : ''}
                            >

                        </div>
                    `;

                }


                if (!stored) {

                    html += `
                        <button
                            type="button"
                            id="readinessSubmitWritten"
                            class="student-submit-written"
                        >
                            Javobni yuborish
                        </button>
                    `;
                }


                html += `
                    </div>
                `;
            }


            shell.innerHTML =
                html;


            updateControls();

            bind();

            updateNavigator();
        }


        function updateNavigator() {

            navigator
                .querySelectorAll(
                    '.readiness-nav-number'
                )
                .forEach(
                    function (button, index) {

                        button.classList.toggle(
                            'is-current',
                            index === position
                        );

                        button.classList.toggle(
                            'is-answered',
                            !!answers[
                                String(
                                    questions[index].id
                                )
                            ]
                        );

                    }
                );
        }


        function updateControls() {

            document.getElementById(
                'readinessPrevious'
            ).disabled =
                position <= 0;

            document.getElementById(
                'readinessNext'
            ).disabled =
                !answers[
                    String(
                        questions[position].id
                    )
                ];


            document.getElementById(
                'readinessProgressBar'
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
                'readinessNext'
            ).textContent =
                position >=
                questions.length - 1
                    ? 'Tugatish'
                    : 'Keyingi →';
        }


        function updateTimer() {

            const minutes =
                Math.floor(
                    timerSeconds / 60
                );

            const seconds =
                timerSeconds % 60;

            timer.textContent =
                String(minutes)
                    .padStart(2, '0') +
                ':' +
                String(seconds)
                    .padStart(2, '0');


            timer.classList.toggle(
                'is-warning',
                timerSeconds <= 600
            );


            if (timerSeconds <= 0) {

                location.reload();

                return;
            }

            timerSeconds--;

        }


        function bind() {

            shell
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
                                    ''
                                );

                            }
                        );

                    }
                );


            const written =
                document.getElementById(
                    'readinessSubmitWritten'
                );

            if (written) {

                written.addEventListener(
                    'click',
                    function () {

                        const a =
                            document.getElementById(
                                'readinessA'
                            );

                        const b =
                            document.getElementById(
                                'readinessB'
                            );

                        submit(
                            '',
                            a
                                ? a.value.trim()
                                : '',
                            b
                                ? b.value.trim()
                                : ''
                        );

                    }
                );

            }

        }


        function submit(
            answer,
            answerA,
            answerB
        ) {

            if (
                submitting ||
                answers[
                    String(
                        questions[position].id
                    )
                ]
            ) {
                return;
            }


            submitting = true;


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
                'readiness.php',
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

                    submitting = false;


                    if (!data.success) {

                        if (
                            data.expired
                        ) {

                            location.reload();
                            return;
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
                        data.finished
                    ) {

                        showResult(
                            data.correct,
                            55
                        );

                        return;
                    }


                    /*
                     * Readiness is an exam:
                     * answer -> feedback -> automatic next.
                     * We deliberately don't block navigation.
                     */

                    setTimeout(
                        function () {

                            if (
                                position <
                                questions.length - 1
                            ) {

                                position++;

                                render();

                            }

                        },
                        700
                    );

                }
            )
            .catch(
                function () {

                    submitting = false;

                    alert(
                        'Server bilan bog‘lanishda xatolik.'
                    );

                }
            );
        }


        function showResult(
            correct,
            total
        ) {

            shell.hidden =
                true;

            navigator.hidden =
                true;

            document.getElementById(
                'readinessPrevious'
            ).hidden =
                true;

            document.getElementById(
                'readinessNext'
            ).hidden =
                true;


            const percentage =
                (
                    correct /
                    total *
                    100
                );


            document.getElementById(
                'readinessScore'
            ).textContent =
                correct +
                ' / ' +
                total;


            document.getElementById(
                'readinessGrade'
            ).textContent =
                percentage >= 70
                    ? 'Tayyor'
                    : 'Hali tayyor emas';


            document.getElementById(
                'readinessResult'
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
            'readinessPrevious'
        ).addEventListener(
            'click',
            function () {

                if (
                    position > 0
                ) {

                    position--;

                    render();
                }

            }
        );


        document.getElementById(
            'readinessNext'
        ).addEventListener(
            'click',
            function () {

                if (
                    !answers[
                        String(
                            questions[position].id
                        )
                    ]
                ) {
                    return;
                }

                if (
                    position <
                    questions.length - 1
                ) {

                    position++;

                    render();

                }

            }
        );


        buildNavigator();

        render();

        updateTimer();

        setInterval(
            updateTimer,
            1000
        );

    }
);
</script>

<style>
.readiness-test-top {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
}

.readiness-test-top > div > span {
    display: block;
    margin-bottom: 6px;
    color: #55aaff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.4px;
}

.readiness-test-top > strong {
    font-size: 24px;
    font-variant-numeric: tabular-nums;
}

.readiness-test-top > strong.is-warning {
    color: #ffb7a8;
}

.readiness-test-progress {
    height: 6px;
    overflow: hidden;
    margin: 10px 0;
    background: rgba(255,255,255,.08);
    border-radius: 99px;
}

.readiness-test-progress span {
    display: block;
    height: 100%;
    background: #3d9cff;
}

.readiness-test-navigator {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-bottom: 12px;
}

.readiness-nav-number {
    width: 32px;
    height: 32px;
    color: inherit;
    background: rgba(255,255,255,.02);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 7px;
    cursor: pointer;
    font-size: 10px;
    font-weight: 750;
}

.readiness-nav-number.is-current {
    color: #fff;
    background: rgba(61,156,255,.14);
    border-color: rgba(61,156,255,.45);
}

.readiness-nav-number.is-answered {
    color: #9ce2b8;
    border-color: rgba(85,202,137,.25);
}

.readiness-test-question {
    padding: 20px;
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px;
}

.readiness-question-number {
    margin-bottom: 8px;
    color: #7f8b99;
    font-size: 11px;
}

.readiness-question-text {
    font-size: 15px;
    line-height: 1.65;
}

.readiness-test-controls {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-top: 10px;
}

.readiness-test-controls button {
    min-height: 42px;
    padding: 0 14px;
    color: inherit;
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}

.readiness-test-controls button:disabled {
    opacity: .35;
    cursor: default;
}
</style>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>
