<?php
declare(strict_types=1);

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function loadQuestion(mysqli $conn, int $questionId): ?array
{
    $questionId = (int) $questionId;

    $result = mysqli_query(
        $conn,
        "SELECT id, topic_id, question_type, text, correct_answer,
                part_a_text, part_a_correct_answer,
                part_b_text, part_b_correct_answer,
                is_new, is_active
         FROM questions
         WHERE id = {$questionId} AND is_active = 1
         LIMIT 1"
    );

    if (!$result) {
        throw new RuntimeException(mysqli_error($conn));
    }

    $question = mysqli_fetch_assoc($result);

    if (!$question) {
        return null;
    }

    $options = mysqli_query(
        $conn,
        "SELECT option_key, option_text
         FROM question_options
         WHERE question_id = {$questionId}
         ORDER BY id ASC"
    );

    if (!$options) {
        throw new RuntimeException(mysqli_error($conn));
    }

    $question['options'] = [];

    while ($row = mysqli_fetch_assoc($options)) {
        $question['options'][] = $row;
    }

    $images = mysqli_query(
        $conn,
        "SELECT id, file_path
         FROM question_images
         WHERE question_id = {$questionId}
         ORDER BY id ASC"
    );

    if (!$images) {
        throw new RuntimeException(mysqli_error($conn));
    }

    $question['images'] = [];

    while ($row = mysqli_fetch_assoc($images)) {
        $question['images'][] = $row;
    }

    return $question;
}

function checkQuestionAnswer(array $question, string $answer): bool
{
    if ($question['question_type'] === 'written') {
        return trim($answer) === trim((string) $question['correct_answer']);
    }

    return strtoupper(trim($answer)) ===
           strtoupper(trim((string) $question['correct_answer']));
}
