<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shared student test engine
|--------------------------------------------------------------------------
| This file contains the common server-side mechanics used by student
| practice/exam pages:
| - question loading
| - answer evaluation
| - attempt persistence
| - mistake queue maintenance
| - saved questions
| - progress recalculation
|--------------------------------------------------------------------------
*/

function studentJson(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function studentH(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function studentQuestion(
    mysqli $conn,
    int $questionId
): ?array {
    $questionId = (int) $questionId;

    $result = mysqli_query(
        $conn,
        "
        SELECT
            q.id,
            q.topic_id,
            q.question_type,
            q.text,
            q.correct_answer,
            q.part_a_text,
            q.part_a_correct_answer,
            q.part_b_text,
            q.part_b_correct_answer
        FROM questions q
        WHERE q.id = $questionId
          AND q.is_active = 1
        LIMIT 1
        "
    );

    if (
        !$result ||
        mysqli_num_rows($result) === 0
    ) {
        return null;
    }

    $question =
        mysqli_fetch_assoc($result);

    $question['options'] = [];
    $question['images'] = [];

    $options =
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

    if ($options) {
        while (
            $option =
            mysqli_fetch_assoc($options)
        ) {
            $question['options'][] =
                $option;
        }
    }

    $images =
        mysqli_query(
            $conn,
            "
            SELECT
                file_path
            FROM question_images
            WHERE question_id = $questionId
            ORDER BY id ASC
            "
        );

    if ($images) {
        while (
            $image =
            mysqli_fetch_assoc($images)
        ) {
            $question['images'][] =
                $image['file_path'];
        }
    }

    return $question;
}

function studentEvaluateAnswer(
    array $question,
    string $answer,
    string $answerA,
    string $answerB
): array {
    $type =
        (string)
        $question['question_type'];

    if (
        $type === 'multiple_choice' ||
        $type === 'six_option'
    ) {
        if ($answer === '') {
            return [
                'valid' => false,
                'message' => 'Variantni tanlang.'
            ];
        }

        $correct =
            strtoupper(
                trim(
                    (string)
                    $question['correct_answer']
                )
            );

        return [
            'valid' => true,
            'is_correct' =>
                strcasecmp(
                    $answer,
                    $correct
                ) === 0,
            'stored_answer' => $answer,
            'correct_answer' => $correct,
            'part_a_correct' => null,
            'part_b_correct' => null
        ];
    }

    if ($type === 'written') {
        $hasA =
            trim(
                (string)
                $question['part_a_text']
            ) !== '';

        $hasB =
            trim(
                (string)
                $question['part_b_text']
            ) !== '';

        if (!$hasA && !$hasB) {
            return [
                'valid' => false,
                'message' =>
                    'Yozma savol noto‘g‘ri sozlangan.'
            ];
        }

        if (
            $hasA &&
            $answerA === ''
        ) {
            return [
                'valid' => false,
                'message' =>
                    'A qism javobini kiriting.'
            ];
        }

        if (
            $hasB &&
            $answerB === ''
        ) {
            return [
                'valid' => false,
                'message' =>
                    'B qism javobini kiriting.'
            ];
        }

        $aCorrect = !$hasA ||
            strcasecmp(
                $answerA,
                trim(
                    (string)
                    $question[
                        'part_a_correct_answer'
                    ]
                )
            ) === 0;

        $bCorrect = !$hasB ||
            strcasecmp(
                $answerB,
                trim(
                    (string)
                    $question[
                        'part_b_correct_answer'
                    ]
                )
            ) === 0;

        return [
            'valid' => true,
            'is_correct' =>
                $aCorrect &&
                $bCorrect,
            'stored_answer' =>
                json_encode(
                    [
                        'a' => $answerA,
                        'b' => $answerB
                    ],
                    JSON_UNESCAPED_UNICODE
                ),
            'correct_answer' => '',
            'part_a_correct' => $aCorrect,
            'part_b_correct' => $bCorrect
        ];
    }

    return [
        'valid' => false,
        'message' => 'Noma’lum savol turi.'
    ];
}

function studentUpdateMistakeQueue(
    mysqli $conn,
    int $userId,
    int $questionId,
    bool $isCorrect
): void {
    if ($isCorrect) {
        mysqli_query(
            $conn,
            "
            DELETE FROM mistake_queue
            WHERE user_id = $userId
              AND question_id = $questionId
            "
        );
        return;
    }

    mysqli_query(
        $conn,
        "
        INSERT IGNORE INTO mistake_queue (
            user_id,
            question_id
        )
        VALUES (
            $userId,
            $questionId
        )
        "
    );
}

function studentUpdateProgress(
    mysqli $conn,
    int $userId,
    ?int $topicId = null
): void {
    /*
     * Progress is based on unique active questions that the user has
     * answered correctly at least once.
     */
    $totalResult =
        mysqli_query(
            $conn,
            "
            SELECT COUNT(*) AS total
            FROM questions
            WHERE is_active = 1
            "
        );

    $correctResult =
        mysqli_query(
            $conn,
            "
            SELECT COUNT(DISTINCT a.question_id) AS total
            FROM attempts a
            INNER JOIN questions q
                ON q.id = a.question_id
               AND q.is_active = 1
            WHERE a.user_id = $userId
              AND a.is_correct = 1
            "
        );

    $total = 0;
    $correct = 0;

    if ($totalResult) {
        $row =
            mysqli_fetch_assoc(
                $totalResult
            );

        $total =
            (int)
            $row['total'];
    }

    if ($correctResult) {
        $row =
            mysqli_fetch_assoc(
                $correctResult
            );

        $correct =
            (int)
            $row['total'];
    }

    $percent =
        $total > 0
            ? round(
                (
                    $correct /
                    $total
                ) * 100,
                2
            )
            : 0;

    mysqli_query(
        $conn,
        "
        INSERT INTO user_progress (
            user_id,
            progress_percent
        )
        VALUES (
            $userId,
            $percent
        )
        ON DUPLICATE KEY UPDATE
            progress_percent = VALUES(progress_percent)
        "
    );

    if ($topicId !== null) {
        $topicId =
            (int)
            $topicId;

        $topicTotalResult =
            mysqli_query(
                $conn,
                "
                SELECT COUNT(*) AS total
                FROM questions
                WHERE topic_id = $topicId
                  AND is_active = 1
                "
            );

        $topicCorrectResult =
            mysqli_query(
                $conn,
                "
                SELECT COUNT(DISTINCT a.question_id) AS total
                FROM attempts a
                INNER JOIN questions q
                    ON q.id = a.question_id
                   AND q.is_active = 1
                WHERE a.user_id = $userId
                  AND a.is_correct = 1
                  AND q.topic_id = $topicId
                "
            );

        $topicTotal = 0;
        $topicCorrect = 0;

        if ($topicTotalResult) {
            $row =
                mysqli_fetch_assoc(
                    $topicTotalResult
                );

            $topicTotal =
                (int)
                $row['total'];
        }

        if ($topicCorrectResult) {
            $row =
                mysqli_fetch_assoc(
                    $topicCorrectResult
                );

            $topicCorrect =
                (int)
                $row['total'];
        }

        $topicPercent =
            $topicTotal > 0
                ? round(
                    (
                        $topicCorrect /
                        $topicTotal
                    ) * 100,
                    2
                )
                : 0;

        mysqli_query(
            $conn,
            "
            INSERT INTO user_topic_progress (
                user_id,
                topic_id,
                progress_percent
            )
            VALUES (
                $userId,
                $topicId,
                $topicPercent
            )
            ON DUPLICATE KEY UPDATE
                progress_percent = VALUES(progress_percent)
            "
        );
    }
}

function studentSaved(
    mysqli $conn,
    int $userId,
    int $questionId
): bool {
    $result =
        mysqli_query(
            $conn,
            "
            SELECT 1
            FROM saved_questions
            WHERE user_id = $userId
              AND question_id = $questionId
            LIMIT 1
            "
        );

    return $result &&
        mysqli_num_rows($result) > 0;
}

function studentToggleSaved(
    mysqli $conn,
    int $userId,
    int $questionId
): bool {
    if (
        studentSaved(
            $conn,
            $userId,
            $questionId
        )
    ) {
        mysqli_query(
            $conn,
            "
            DELETE FROM saved_questions
            WHERE user_id = $userId
              AND question_id = $questionId
            "
        );

        return false;
    }

    mysqli_query(
        $conn,
        "
        INSERT IGNORE INTO saved_questions (
            user_id,
            question_id
        )
        VALUES (
            $userId,
            $questionId
        )
        "
    );

    return true;
}

function studentScoreGrade(
    int $score
): string {
    if ($score >= 40) {
        return 'A+';
    }

    if ($score >= 35) {
        return 'A';
    }

    if ($score >= 30) {
        return 'B+';
    }

    if ($score >= 25) {
        return 'B';
    }

    if ($score >= 20) {
        return 'C+';
    }

    return 'C';
}
