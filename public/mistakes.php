<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

require_once __DIR__ . '/../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

function mistakeJson(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );
    exit;
}

function mh(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| AJAX: load one question
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    ($_GET['action'] ?? '') === 'question'
) {
    $questionId = (int) ($_GET['question_id'] ?? 0);

    if ($questionId <= 0) {
        mistakeJson([
            'success' => false,
            'message' => 'Noto‘g‘ri savol.'
        ]);
    }

    $query = "
        SELECT
            q.id,
            q.question_type,
            q.text,
            q.part_a_text,
            q.part_b_text
        FROM mistake_queue mq
        INNER JOIN questions q
            ON q.id = mq.question_id
        WHERE mq.user_id = $userId
          AND q.id = $questionId
          AND q.is_active = 1
        LIMIT 1
    ";

    $result = mysqli_query($conn, $query);

    if (!$result || mysqli_num_rows($result) === 0) {
        mistakeJson([
            'success' => false,
            'message' => 'Savol topilmadi.'
        ]);
    }

    $question = mysqli_fetch_assoc($result);

    $question['options'] = [];
    $question['images'] = [];

    $optionResult = mysqli_query(
        $conn,
        "
        SELECT option_key, option_text
        FROM question_options
        WHERE question_id = $questionId
        ORDER BY option_key ASC
        "
    );

    if ($optionResult) {
        while ($option = mysqli_fetch_assoc($optionResult)) {
            $question['options'][] = $option;
        }
    }

    $imageResult = mysqli_query(
        $conn,
        "
        SELECT file_path
        FROM question_images
        WHERE question_id = $questionId
        ORDER BY id ASC
        "
    );

    if ($imageResult) {
        while ($image = mysqli_fetch_assoc($imageResult)) {
            $question['images'][] = $image['file_path'];
        }
    }

    $attemptQuery = "
        SELECT answer, is_correct
        FROM attempts
        WHERE user_id = $userId
          AND question_id = $questionId
        ORDER BY id DESC
        LIMIT 1
    ";

    $attemptResult = mysqli_query($conn, $attemptQuery);

    $answered = false;
    $savedAnswer = '';
    $savedCorrect = false;

    if ($attemptResult && mysqli_num_rows($attemptResult) > 0) {
        $attempt = mysqli_fetch_assoc($attemptResult);
        $answered = true;
        $savedAnswer = (string) $attempt['answer'];
        $savedCorrect = (bool) $attempt['is_correct'];
    }

    mistakeJson([
        'success' => true,
        'question' => $question,
        'answered' => $answered,
        'answer' => $savedAnswer,
        'is_correct' => $savedCorrect
    ]);
}

/*
|--------------------------------------------------------------------------
| AJAX: submit one mistake answer
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'answer'
) {
    $questionId = (int) ($_POST['question_id'] ?? 0);
    $answer = trim((string) ($_POST['answer'] ?? ''));
    $answerA = trim((string) ($_POST['answer_a'] ?? ''));
    $answerB = trim((string) ($_POST['answer_b'] ?? ''));

    if ($questionId <= 0) {
        mistakeJson([
            'success' => false,
            'message' => 'Noto‘g‘ri savol.'
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
        FROM mistake_queue mq
        INNER JOIN questions q
            ON q.id = mq.question_id
        WHERE mq.user_id = $userId
          AND q.id = $questionId
          AND q.is_active = 1
        LIMIT 1
    ";

    $questionResult = mysqli_query($conn, $questionQuery);

    if (
        !$questionResult ||
        mysqli_num_rows($questionResult) === 0
    ) {
        mistakeJson([
            'success' => false,
            'message' => 'Ushbu savol hozir xatolar ro‘yxatida emas.'
        ]);
    }

    $question = mysqli_fetch_assoc($questionResult);
    $questionType = (string) $question['question_type'];

    $isCorrect = false;
    $correctAnswerForClient = '';
    $storedAnswer = '';

    if (
        $questionType === 'multiple_choice' ||
        $questionType === 'six_option'
    ) {
        if ($answer === '') {
            mistakeJson([
                'success' => false,
                'message' => 'Variantni tanlang.'
            ]);
        }

        $correctAnswerForClient =
            strtoupper(
                trim((string) $question['correct_answer'])
            );

        $isCorrect =
            strcasecmp(
                $answer,
                $correctAnswerForClient
            ) === 0;

        $storedAnswer = $answer;
    } elseif ($questionType === 'written') {

        $hasPartA =
            trim((string) $question['part_a_text']) !== '';

        $hasPartB =
            trim((string) $question['part_b_text']) !== '';

        $partAIsCorrect = true;
        $partBIsCorrect = true;

        if ($hasPartA) {
            if ($answerA === '') {
                mistakeJson([
                    'success' => false,
                    'message' => 'A qism javobini kiriting.'
                ]);
            }

            $partAIsCorrect =
                strcasecmp(
                    $answerA,
                    trim(
                        (string)
                        $question['part_a_correct_answer']
                    )
                ) === 0;
        }

        if ($hasPartB) {
            if ($answerB === '') {
                mistakeJson([
                    'success' => false,
                    'message' => 'B qism javobini kiriting.'
                ]);
            }

            $partBIsCorrect =
                strcasecmp(
                    $answerB,
                    trim(
                        (string)
                        $question['part_b_correct_answer']
                    )
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
    } else {
        mistakeJson([
            'success' => false,
            'message' => 'Noma’lum savol turi.'
        ]);
    }

    /*
     * Store the answer as a normal attempt.
     * Mistake queue remains until the answer is correct.
     */
    $safeAnswer = mysqli_real_escape_string($conn, $storedAnswer);

    mysqli_begin_transaction($conn);

    try {

        $attemptQuery = "
            INSERT INTO attempts (
                user_id,
                question_id,
                answer,
                is_correct
            )
            VALUES (
                $userId,
                $questionId,
                '$safeAnswer',
                " . ($isCorrect ? '1' : '0') . "
            )
        ";

        if (!mysqli_query($conn, $attemptQuery)) {
            throw new RuntimeException(mysqli_error($conn));
        }

        if ($isCorrect) {
            $queueQuery = "
                DELETE FROM mistake_queue
                WHERE user_id = $userId
                  AND question_id = $questionId
            ";
        } else {
            $queueQuery = "
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

        if (!mysqli_query($conn, $queueQuery)) {
            throw new RuntimeException(mysqli_error($conn));
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);

        mistakeJson([
            'success' => false,
            'message' => 'Javobni saqlashda xatolik.'
        ]);
    }

    mistakeJson([
        'success' => true,
        'is_correct' => $isCorrect,
        'correct_answer' => $correctAnswerForClient
    ]);
}

/*
|--------------------------------------------------------------------------
| Current mistake queue for page
|--------------------------------------------------------------------------
*/
$mistakes = [];

$query = "
    SELECT
        mq.question_id,
        q.text,
        q.question_type,
        t.name AS topic_name
    FROM mistake_queue mq
    INNER JOIN questions q
        ON q.id = mq.question_id
    INNER JOIN topics t
        ON t.id = q.topic_id
    WHERE mq.user_id = $userId
      AND q.is_active = 1
    ORDER BY mq.id ASC
";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $mistakes[] = $row;
    }
}

$mistakeCount = count($mistakes);
$pageTitle = 'Xatolarni tuzatish';

require_once __DIR__ . '/../layout/header.php';
?>

<link rel="stylesheet" href="assets/css/style.css">

<section class="page-section mistakes-page">

    <a href="dashboard.php" class="page-back">
        <span class="page-back-icon">←</span>
        <span>Orqaga</span>
    </a>

    <div class="page-heading">

        <h1 class="page-title">
            Xatolarni tuzatish
        </h1>

        <p class="page-description">
            Noto‘g‘ri javob bergan savollaringizni qayta yeching.
        </p>

    </div>

    <?php if ($mistakeCount > 0): ?>

        <div
            class="mistake-navigation"
            id="mistakeNavigation"
        >

            <?php foreach ($mistakes as $index => $mistake): ?>

                <button
                    type="button"
                    class="mistake-nav-number <?php
                        echo $index === 0
                            ? 'is-current'
                            : '';
                    ?>"
                    data-position="<?php echo $index; ?>"
                    data-question-id="<?php
                        echo (int) $mistake['question_id'];
                    ?>"
                >
                    <?php echo (int) $mistake['question_id']; ?>
                </button>

            <?php endforeach; ?>

        </div>

        <div
            class="question-feedback"
            id="mistakeFeedback"
            hidden
        ></div>

        <div
            class="mistake-progress"
            id="mistakeProgressText"
        >
            1 / <?php echo $mistakeCount; ?>
        </div>

        <div
            class="question-card"
            id="mistakeQuestionCard"
        ></div>

        <div class="question-navigation">

            <button
                type="button"
                class="question-navigation-button"
                id="mistakePrevious"
                disabled
            >
                ← Oldingi
            </button>

            <button
                type="button"
                class="question-navigation-button"
                id="mistakeNext"
                disabled
            >
                Keyingi →
            </button>

        </div>

        <div
            class="block-result"
            id="mistakeResult"
            hidden
        >

            <div class="block-result-content">

                <div class="block-result-icon">
                    <i data-lucide="circle-check"></i>
                </div>

                <h2>
                    Xatolar yakunlandi
                </h2>

                <p>
                    Barcha mavjud xatolar to‘g‘ri yechildi.
                </p>

                <button
                    type="button"
                    class="block-result-button"
                    id="mistakeReload"
                >
                    Yangilash
                </button>

            </div>

        </div>

        <script>
        window.OSON_MISTAKES = <?php
            echo json_encode(
                $mistakes,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_AMP |
                JSON_HEX_QUOT
            );
        ?>;
        </script>

        <script>
        document.addEventListener('DOMContentLoaded', function () {

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const mistakes =
                window.OSON_MISTAKES || [];

            const questionCard =
                document.getElementById(
                    'mistakeQuestionCard'
                );

            const navigation =
                document.getElementById(
                    'mistakeNavigation'
                );

            const feedback =
                document.getElementById(
                    'mistakeFeedback'
                );

            const progressText =
                document.getElementById(
                    'mistakeProgressText'
                );

            const previousButton =
                document.getElementById(
                    'mistakePrevious'
                );

            const nextButton =
                document.getElementById(
                    'mistakeNext'
                );

            let currentPosition = 0;
            let currentQuestion = null;
            let feedbackTimer = null;
            let isSubmitting = false;


            function escapeHtml(value) {

                const div =
                    document.createElement('div');

                div.textContent =
                    value ?? '';

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


            function clearFeedback() {

                feedback.hidden = true;
                feedback.className =
                    'question-feedback';
                feedback.textContent = '';
            }


            function showFeedback(isCorrect) {

                feedback.hidden = false;

                feedback.className =
                    'question-feedback ' +
                    (
                        isCorrect
                            ? 'is-correct'
                            : 'is-wrong'
                    );

                feedback.textContent =
                    isCorrect
                        ? 'To‘g‘ri javob!'
                        : 'Noto‘g‘ri javob. To‘g‘ri javob yashil bilan ko‘rsatildi.';
            }


            function updateNavigation() {

                previousButton.disabled =
                    currentPosition <= 0;

                nextButton.disabled =
                    currentQuestion === null;

                nextButton.textContent =
                    currentPosition >=
                    mistakes.length - 1
                        ? 'Tugatish'
                        : 'Keyingi →';


                navigation
                    .querySelectorAll(
                        '.mistake-nav-number'
                    )
                    .forEach(function (button) {

                        const position =
                            Number(
                                button.dataset.position
                            );

                        button.classList.toggle(
                            'is-current',
                            position === currentPosition
                        );

                        button.disabled = false;
                    });


                progressText.textContent =
                    (
                        currentPosition + 1
                    ) +
                    ' / ' +
                    mistakes.length;
            }


            function renderQuestion(questionData) {

                clearFeedback();

                currentQuestion =
                    questionData;


                let html = `
                    <div class="mistake-question-topic">
                        ${escapeHtml(
                            questionData.topic_name
                        )}
                    </div>

                    <div class="question-number">
                        ${questionData.id}
                    </div>

                    <div class="question-text">
                        ${
                            escapeHtml(
                                questionData.text
                            ).replace(
                                /\n/g,
                                '<br>'
                            )
                        }
                    </div>

                    <div
                        class="question-card-loading"
                        id="mistakeLoading"
                    >
                        Savol yuklanmoqda...
                    </div>
                `;


                questionCard.innerHTML =
                    html;


                fetch(
                    'mistakes.php?action=question&question_id=' +
                    encodeURIComponent(
                        questionData.id
                    ),
                    {
                        credentials:
                            'same-origin'
                    }
                )
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {

                    if (!data.success) {

                        questionCard.innerHTML =
                            '<p>Savolni yuklashda xatolik yuz berdi.</p>';

                        return;
                    }


                    renderQuestionContent(data);

                })
                .catch(function () {

                    questionCard.innerHTML =
                        '<p>Savolni yuklashda xatolik yuz berdi.</p>';

                });
            }


            function renderQuestionContent(data) {

                const question =
                    data.question;

                const answered =
                    Boolean(
                        data.answered
                    );


                let html = `
                    <div class="mistake-question-topic">
                        ${escapeHtml(
                            currentQuestion.topic_name
                        )}
                    </div>

                    <div class="question-number">
                        ${question.id}
                    </div>

                    <div class="question-text">
                        ${
                            escapeHtml(
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
                    question.type ===
                        'multiple_choice' ||
                    question.type ===
                        'six_option'
                ) {

                    html += `
                        <div class="question-options">
                    `;


                    question.options.forEach(function (option) {

                        html += `
                            <button
                                type="button"
                                class="question-option"
                                data-answer="${escapeAttribute(
                                    option.option_key
                                )}"
                                ${answered ? 'disabled' : ''}
                            >
                                <span class="question-option-key">
                                    ${escapeHtml(
                                        option.option_key
                                    )}
                                </span>

                                <span class="question-option-text">
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

                    });


                    html += `
                        </div>
                    `;

                } else {

                    let saved = {
                        a: '',
                        b: ''
                    };


                    if (data.answer) {

                        try {

                            const parsed =
                                JSON.parse(
                                    data.answer
                                );

                            saved.a =
                                parsed.a ||
                                '';

                            saved.b =
                                parsed.b ||
                                '';

                        } catch (error) {
                            saved.a = '';
                            saved.b = '';
                        }
                    }


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
                                    ${
                                        escapeHtml(
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
                                    id="mistakeAnswerA"
                                    placeholder="A qism javobi"
                                    value="${escapeAttribute(
                                        saved.a
                                    )}"
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
                                    ${
                                        escapeHtml(
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
                                    id="mistakeAnswerB"
                                    placeholder="B qism javobi"
                                    value="${escapeAttribute(
                                        saved.b
                                    )}"
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
                                id="mistakeWrittenSubmit"
                            >
                                Javobni yuborish
                            </button>
                        `;
                    }


                    html += `
                        </div>
                    `;
                }


                questionCard.innerHTML =
                    html;


                bindAnswerEvents();
                updateNavigation();

                if (
                    typeof lucide !==
                    'undefined'
                ) {
                    lucide.createIcons();
                }
            }


            function applyOptionFeedback(
                selectedAnswer,
                correctAnswer
            ) {

                questionCard
                    .querySelectorAll(
                        '.question-option'
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
                                    correctAnswer
                                ).toUpperCase()
                            ) {

                                button.classList.add(
                                    'question-option-correct'
                                );
                            }


                            if (
                                value.toUpperCase() ===
                                String(
                                    selectedAnswer
                                ).toUpperCase() &&
                                value.toUpperCase() !==
                                String(
                                    correctAnswer
                                ).toUpperCase()
                            ) {

                                button.classList.add(
                                    'question-option-wrong'
                                );
                            }


                            button.disabled = true;
                        }
                    );
            }


            function submitAnswer(
                answer,
                answerA,
                answerB,
                button
            ) {

                if (
                    isSubmitting ||
                    !currentQuestion
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


                formData.append(
                    'action',
                    'answer'
                );


                formData.append(
                    'question_id',
                    String(
                        currentQuestion.id
                    )
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
                    'mistakes.php?action=answer',
                    {
                        method: 'POST',
                        body: formData,
                        credentials:
                            'same-origin'
                    }
                )
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {

                    if (
                        !data.success
                    ) {

                        isSubmitting = false;

                        if (button) {
                            button.disabled = false;
                        }

                        alert(
                            data.message ||
                            'Xatolik yuz berdi.'
                        );

                        return;
                    }


                    if (
                        currentQuestion.type ===
                            'multiple_choice' ||
                        currentQuestion.type ===
                            'six_option'
                    ) {

                        applyOptionFeedback(
                            answer,
                            data.correct_answer
                        );
                    }


                    showFeedback(
                        Boolean(
                            data.is_correct
                        )
                    );


                    clearTimeout(
                        feedbackTimer
                    );


                    feedbackTimer =
                        setTimeout(
                            function () {

                                isSubmitting = false;


                                /*
                                 * Correct mistake:
                                 * remove from current navigation.
                                 */
                                if (
                                    data.is_correct
                                ) {

                                    mistakes.splice(
                                        currentPosition,
                                        1
                                    );


                                    if (
                                        mistakes.length === 0
                                    ) {

                                        questionCard.hidden =
                                            true;

                                        document.querySelector(
                                            '.question-navigation'
                                        ).hidden =
                                            true;

                                        navigation.hidden =
                                            true;

                                        document.getElementById(
                                            'mistakeResult'
                                        ).hidden =
                                            false;

                                        if (
                                            typeof lucide !==
                                            'undefined'
                                        ) {
                                            lucide.createIcons();
                                        }

                                        return;
                                    }


                                    if (
                                        currentPosition >=
                                        mistakes.length
                                    ) {

                                        currentPosition =
                                            mistakes.length - 1;
                                    }


                                    renderQuestion(
                                        mistakes[
                                            currentPosition
                                        ]
                                    );


                                    return;
                                }


                                /*
                                 * Wrong again:
                                 * automatically continue.
                                 */
                                if (
                                    currentPosition <
                                    mistakes.length - 1
                                ) {

                                    currentPosition++;

                                    renderQuestion(
                                        mistakes[
                                            currentPosition
                                        ]
                                    );

                                } else {

                                    /*
                                     * Last mistake was wrong.
                                     * Keep it visible so the user
                                     * can select it again.
                                     */
                                    updateNavigation();

                                }

                            },
                            900
                        );

                })
                .catch(function () {

                    isSubmitting = false;

                    if (button) {
                        button.disabled = false;
                    }

                    alert(
                        'Server bilan bog‘lanishda xatolik.'
                    );

                });
            }


            function bindAnswerEvents() {

                questionCard
                    .querySelectorAll(
                        '.question-option'
                    )
                    .forEach(
                        function (button) {

                            button.addEventListener(
                                'click',
                                function () {

                                    submitAnswer(
                                        String(
                                            button.dataset.answer
                                        ),
                                        '',
                                        '',
                                        button
                                    );

                                }
                            );

                        }
                    );


                const writtenButton =
                    document.getElementById(
                        'mistakeWrittenSubmit'
                    );


                if (writtenButton) {

                    writtenButton.addEventListener(
                        'click',
                        function () {

                            const inputA =
                                document.getElementById(
                                    'mistakeAnswerA'
                                );

                            const inputB =
                                document.getElementById(
                                    'mistakeAnswerB'
                                );


                            submitAnswer(
                                '',
                                inputA
                                    ? inputA.value.trim()
                                    : '',
                                inputB
                                    ? inputB.value.trim()
                                    : '',
                                writtenButton
                            );

                        }
                    );
                }
            }


            navigation
                .querySelectorAll(
                    '.mistake-nav-number'
                )
                .forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function () {

                                const position =
                                    Number(
                                        button.dataset.position
                                    );


                                if (
                                    position >= 0 &&
                                    position < mistakes.length
                                ) {

                                    clearTimeout(
                                        feedbackTimer
                                    );

                                    isSubmitting = false;

                                    currentPosition =
                                        position;

                                    renderQuestion(
                                        mistakes[
                                            currentPosition
                                        ]
                                    );
                                }

                            }
                        );

                    }
                );


            previousButton.addEventListener(
                'click',
                function () {

                    if (
                        currentPosition > 0
                    ) {

                        clearTimeout(
                            feedbackTimer
                        );

                        isSubmitting = false;

                        currentPosition--;

                        renderQuestion(
                            mistakes[
                                currentPosition
                            ]
                        );
                    }

                }
            );


            nextButton.addEventListener(
                'click',
                function () {

                    if (
                        currentPosition <
                        mistakes.length - 1
                    ) {

                        currentPosition++;

                        renderQuestion(
                            mistakes[
                                currentPosition
                            ]
                        );
                    }

                }
            );


            document.getElementById(
                'mistakeReload'
            ).addEventListener(
                'click',
                function () {
                    window.location.reload();
                }
            );


            renderQuestion(
                mistakes[
                    currentPosition
                ]
            );

        });
        </script>

    <?php else: ?>

        <div class="blocks-content">

            <div class="blocks-empty">

                <div class="blocks-empty-icon">
                    <i data-lucide="circle-check"></i>
                </div>

                <h3>
                    Xatolar yo‘q
                </h3>

                <p>
                    Hozircha noto‘g‘ri javob bergan savollaringiz mavjud emas.
                </p>

            </div>

        </div>

    <?php endif; ?>

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
?>
