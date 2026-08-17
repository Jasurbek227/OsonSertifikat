<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_auth.php';
requireAdmin();

require_once __DIR__ . '/../../includes/db.php';

$questionId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($questionId <= 0) {
    header('Location: questions.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Do not physically delete.
|--------------------------------------------------------------------------
|
| Questions may already be referenced by:
|
| - block_questions
| - attempts
| - mistake_queue
| - exam_session_questions
| - readiness_questions
| - saved_questions
|
| Therefore deactivate instead.
|
*/

$query = "
    UPDATE questions
    SET is_active = 0
    WHERE id = $questionId
    LIMIT 1
";

mysqli_query(
    $conn,
    $query
);

header('Location: questions.php');

exit;