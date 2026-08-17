<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_auth.php';
requireAdmin();

require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: questions.php');
    exit;
}


$questionId = isset($_POST['question_id'])
    ? (int) $_POST['question_id']
    : 0;


if ($questionId <= 0) {

    header(
        'Location: questions.php?delete=invalid'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load image paths before deletion
|--------------------------------------------------------------------------
*/

$imagePaths = array();


$imageQuery = "
    SELECT file_path
    FROM question_images
    WHERE question_id = $questionId
";

$imageResult =
    mysqli_query(
        $conn,
        $imageQuery
    );


if ($imageResult) {

    while (
        $image = mysqli_fetch_assoc(
            $imageResult
        )
    ) {

        $imagePaths[] =
            (string) $image['file_path'];
    }
}


/*
|--------------------------------------------------------------------------
| Permanent deletion
|--------------------------------------------------------------------------
|
| Several current foreign keys intentionally use RESTRICT.
| We therefore remove all dependent rows explicitly and atomically.
|
*/

mysqli_begin_transaction($conn);


try {

    /*
    |--------------------------------------------------------------------------
    | Delete dependent attempt/session-question data
    |--------------------------------------------------------------------------
    */

    $queries = [

        "
        DELETE FROM attempts
        WHERE question_id = $questionId
        ",

        "
        DELETE FROM mistake_session_questions
        WHERE question_id = $questionId
        ",

        "
        DELETE FROM mistake_queue
        WHERE question_id = $questionId
        ",

        "
        DELETE FROM readiness_questions
        WHERE question_id = $questionId
        ",

        "
        DELETE FROM exam_session_questions
        WHERE question_id = $questionId
        ",

        "
        DELETE FROM block_questions
        WHERE question_id = $questionId
        ",

        "
        DELETE FROM saved_questions
        WHERE question_id = $questionId
        ",

        "
        DELETE FROM question_images
        WHERE question_id = $questionId
        ",

        "
        DELETE FROM question_options
        WHERE question_id = $questionId
        ",

        "
        DELETE FROM questions
        WHERE id = $questionId
        LIMIT 1
        "
    ];


    foreach ($queries as $query) {

        if (
            !mysqli_query(
                $conn,
                $query
            )
        ) {

            throw new RuntimeException(
                mysqli_error($conn)
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Make sure question actually existed
    |--------------------------------------------------------------------------
    */

    if (
        mysqli_affected_rows($conn) !== 1
    ) {

        throw new RuntimeException(
            'Savol topilmadi.'
        );
    }


    mysqli_commit($conn);


    /*
    |--------------------------------------------------------------------------
    | Delete physical image files only when no question uses them
    |--------------------------------------------------------------------------
    */

    $uploadRoot =
        realpath(
            __DIR__ .
            '/../assets/uploads/questions'
        );


    if (
        $uploadRoot !== false
    ) {

        foreach (
            $imagePaths
            as $relativePath
        ) {

            $normalized =
                str_replace(
                    '\\',
                    '/',
                    $relativePath
                );


            if (
                strpos(
                    $normalized,
                    'assets/uploads/questions/'
                ) !== 0
            ) {
                continue;
            }


            $remainingPath =
                mysqli_real_escape_string(
                    $conn,
                    $relativePath
                );


            $usageQuery = "
                SELECT COUNT(*) AS total
                FROM question_images
                WHERE file_path = '$remainingPath'
            ";


            $usageResult =
                mysqli_query(
                    $conn,
                    $usageQuery
                );


            $usageCount = 0;


            if ($usageResult) {

                $usageRow =
                    mysqli_fetch_assoc(
                        $usageResult
                    );

                $usageCount =
                    (int) (
                        $usageRow['total'] ?? 0
                    );
            }


            if ($usageCount > 0) {
                continue;
            }


            $relativeFile =
                substr(
                    $normalized,
                    strlen(
                        'assets/uploads/questions/'
                    )
                );


            $relativeFile =
                basename(
                    $relativeFile
                );


            $absoluteFile =
                $uploadRoot .
                DIRECTORY_SEPARATOR .
                $relativeFile;


            if (
                is_file(
                    $absoluteFile
                )
            ) {

                @unlink(
                    $absoluteFile
                );
            }
        }
    }


    header(
        'Location: questions.php?delete=success'
    );

    exit;


} catch (Throwable $exception) {

    mysqli_rollback($conn);


    header(
        'Location: questions.php?delete=error'
    );

    exit;
}