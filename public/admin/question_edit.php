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


$pageTitle = 'Savolni tahrirlash';

$error = '';
$message = '';


/*
|--------------------------------------------------------------------------
| Load topics
|--------------------------------------------------------------------------
*/

$topics = array();

$topicQuery = "
    SELECT
        id,
        name
    FROM topics
    WHERE is_active = 1
    ORDER BY id ASC
";

$topicResult = mysqli_query(
    $conn,
    $topicQuery
);

if ($topicResult) {

    while ($topic = mysqli_fetch_assoc(
        $topicResult
    )) {

        $topics[] = $topic;
    }
}


/*
|--------------------------------------------------------------------------
| Save
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $topicId = isset($_POST['topic_id'])
        ? (int) $_POST['topic_id']
        : 0;

    $questionType = isset($_POST['question_type'])
        ? trim((string) $_POST['question_type'])
        : '';

    $text = isset($_POST['text'])
        ? trim((string) $_POST['text'])
        : '';

    $correctAnswer = isset(
        $_POST['correct_answer']
    )
        ? trim(
            (string)
            $_POST['correct_answer']
        )
        : '';

    $partAText = isset($_POST['part_a_text'])
        ? trim((string) $_POST['part_a_text'])
        : '';

    $partACorrect = isset(
        $_POST['part_a_correct_answer']
    )
        ? trim(
            (string)
            $_POST['part_a_correct_answer']
        )
        : '';

    $partBText = isset($_POST['part_b_text'])
        ? trim((string) $_POST['part_b_text'])
        : '';

    $partBCorrect = isset(
        $_POST['part_b_correct_answer']
    )
        ? trim(
            (string)
            $_POST['part_b_correct_answer']
        )
        : '';

    $isNew = isset($_POST['is_new'])
        ? 1
        : 0;

    $isActive = isset($_POST['is_active'])
        ? 1
        : 0;


    /*
    |--------------------------------------------------------------------------
    | Validate
    |--------------------------------------------------------------------------
    */

    if ($topicId <= 0) {

        $error = 'Mavzuni tanlang.';
    } elseif (
        $questionType !== 'multiple_choice' &&
        $questionType !== 'six_option' &&
        $questionType !== 'written'
    ) {

        $error = 'Savol turi noto‘g‘ri.';
    } elseif ($text === '') {

        $error = 'Savol matni bo‘sh bo‘lishi mumkin emas.';
    }


    if ($error === '') {

        $safeText =
            mysqli_real_escape_string(
                $conn,
                $text
            );

        $safeCorrect =
            mysqli_real_escape_string(
                $conn,
                $correctAnswer
            );

        $safePartAText =
            mysqli_real_escape_string(
                $conn,
                $partAText
            );

        $safePartACorrect =
            mysqli_real_escape_string(
                $conn,
                $partACorrect
            );

        $safePartBText =
            mysqli_real_escape_string(
                $conn,
                $partBText
            );

        $safePartBCorrect =
            mysqli_real_escape_string(
                $conn,
                $partBCorrect
            );


        $updateQuery = "
            UPDATE questions
            SET
                topic_id = $topicId,
                question_type = '$questionType',
                text = '$safeText',
                correct_answer = " .
            (
                $correctAnswer !== ''
                ? "'$safeCorrect'"
                : "NULL"
            ) .
            ",
                part_a_text = " .
            (
                $partAText !== ''
                ? "'$safePartAText'"
                : "NULL"
            ) .
            ",
                part_a_correct_answer = " .
            (
                $partACorrect !== ''
                ? "'$safePartACorrect'"
                : "NULL"
            ) .
            ",
                part_b_text = " .
            (
                $partBText !== ''
                ? "'$safePartBText'"
                : "NULL"
            ) .
            ",
                part_b_correct_answer = " .
            (
                $partBCorrect !== ''
                ? "'$safePartBCorrect'"
                : "NULL"
            ) .
            ",
                is_new = $isNew,
                is_active = $isActive
            WHERE id = $questionId
            LIMIT 1
        ";


        if (mysqli_query($conn, $updateQuery)) {


            /*
            |--------------------------------------------------------------------------
            | Replace options
            |--------------------------------------------------------------------------
            */

            $deleteOptions = "
                DELETE FROM question_options
                WHERE question_id = $questionId
            ";

            mysqli_query(
                $conn,
                $deleteOptions
            );


            if (
                $questionType ===
                'multiple_choice' ||
                $questionType ===
                'six_option'
            ) {

                $optionKeys =
                    $questionType ===
                    'six_option'
                    ? [
                        'A',
                        'B',
                        'C',
                        'D',
                        'E',
                        'F'
                    ]
                    : [
                        'A',
                        'B',
                        'C',
                        'D'
                    ];


                foreach ($optionKeys as $key) {

                    $field =
                        'option_' .
                        strtolower($key);

                    $optionText =
                        isset($_POST[$field])
                        ? trim(
                            (string)
                            $_POST[$field]
                        )
                        : '';

                    if ($optionText === '') {
                        continue;
                    }

                    $safeOption =
                        mysqli_real_escape_string(
                            $conn,
                            $optionText
                        );

                    $safeKey =
                        mysqli_real_escape_string(
                            $conn,
                            $key
                        );

                    $optionQuery = "
                        INSERT INTO question_options (
                            question_id,
                            option_key,
                            option_text
                        )
                        VALUES (
                            $questionId,
                            '$safeKey',
                            '$safeOption'
                        )
                    ";

                    mysqli_query(
                        $conn,
                        $optionQuery
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Replace images
            |--------------------------------------------------------------------------
            */

            $deleteImages = "
                DELETE FROM question_images
                WHERE question_id = $questionId
            ";

            mysqli_query(
                $conn,
                $deleteImages
            );


            if (
                isset($_POST['image_paths']) &&
                is_array($_POST['image_paths'])
            ) {

                foreach (
                    $_POST['image_paths']
                    as $imagePath
                ) {

                    $imagePath =
                        trim(
                            (string)
                            $imagePath
                        );

                    if ($imagePath === '') {
                        continue;
                    }

                    $safeImagePath =
                        mysqli_real_escape_string(
                            $conn,
                            $imagePath
                        );

                    $imageQuery = "
                        INSERT INTO question_images (
                            question_id,
                            file_path
                        )
                        VALUES (
                            $questionId,
                            '$safeImagePath'
                        )
                    ";

                    mysqli_query(
                        $conn,
                        $imageQuery
                    );
                }
            }


            $message =
                'Savol muvaffaqiyatli saqlandi.';
        } else {

            $error =
                'Saqlashda xatolik: ' .
                mysqli_error($conn);
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load question
|--------------------------------------------------------------------------
*/

$question = null;

$query = "
    SELECT
        id,
        topic_id,
        question_type,
        text,
        correct_answer,
        part_a_text,
        part_a_correct_answer,
        part_b_text,
        part_b_correct_answer,
        is_new,
        is_active
    FROM questions
    WHERE id = $questionId
    LIMIT 1
";

$result = mysqli_query(
    $conn,
    $query
);

if (
    !$result ||
    mysqli_num_rows($result) === 0
) {

    header('Location: questions.php');
    exit;
}

$question = mysqli_fetch_assoc($result);


/*
|--------------------------------------------------------------------------
| Load options
|--------------------------------------------------------------------------
*/

$options = array();

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

        $options[$option['option_key']] = $option['option_text'];
    }
}


/*
|--------------------------------------------------------------------------
| Load images
|--------------------------------------------------------------------------
*/

$images = array();

$imagesQuery = "
    SELECT
        id,
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

        $images[] = $image;
    }
}


?>
<link rel="stylesheet" href="../assets/css/admin.css">

<section class="page-section admin-page">

    <a href="questions.php" class="page-back">
        <span class="page-back-icon">←</span>
        <span>Savollar</span>
    </a>


    <div class="page-heading">

        <h1 class="page-title">
            Savol #<?php
                    echo $questionId;
                    ?>
        </h1>

        <p class="page-description">
            Savolni tahrirlash
        </p>

    </div>


    <?php if ($error !== ''): ?>

        <div class="admin-message admin-message-error">
            <?php
            echo htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </div>

    <?php endif; ?>


    <?php if ($message !== ''): ?>

        <div class="admin-message admin-message-success">
            <?php
            echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </div>

    <?php endif; ?>


    <form method="POST" class="admin-form">

        <div class="admin-form-grid">

            <div class="admin-form-field">

                <label>
                    Mavzu
                </label>

                <select name="topic_id" required>

                    <?php foreach ($topics as $topic): ?>

                        <option value="<?php
                                        echo (int) $topic['id'];
                                        ?>" <?php
                                        echo (int) $question['topic_id'] ===
                                            (int) $topic['id']
                                            ? 'selected'
                                            : '';
                                        ?>>
                            <?php
                            echo htmlspecialchars(
                                $topic['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="admin-form-field">

                <label>
                    Savol turi
                </label>

                <select name="question_type" id="questionType" required>

                    <option value="multiple_choice" <?php
                                                    echo $question['question_type'] ===
                                                        'multiple_choice'
                                                        ? 'selected'
                                                        : '';
                                                    ?>>
                        Variantli
                    </option>

                    <option value="six_option" <?php
                                                echo $question['question_type'] ===
                                                    'six_option'
                                                    ? 'selected'
                                                    : '';
                                                ?>>
                        6 variantli
                    </option>

                    <option value="written" <?php
                                            echo $question['question_type'] ===
                                                'written'
                                                ? 'selected'
                                                : '';
                                            ?>>
                        Yozma
                    </option>

                </select>

            </div>

        </div>


        <div class="admin-form-field">

            <label>
                Savol matni
            </label>

            <textarea name="text" rows="8" required><?php
                                                    echo htmlspecialchars(
                                                        $question['text'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );
                                                    ?></textarea>

        </div>


        <div id="optionsSection" class="admin-form-section">

            <h3>
                Variantlar
            </h3>


            <div class="admin-option-grid">

                <?php
                $editOptionKeys = [
                    'A',
                    'B',
                    'C',
                    'D',
                    'E',
                    'F'
                ];
                ?>


                <?php foreach (
                    $editOptionKeys
                    as $key
                ): ?>

                    <div class="
                            admin-form-field
                            option-field
                            option-<?php
                                    echo strtolower($key);
                                    ?>
                        ">

                        <label>
                            Variant <?php
                                    echo $key;
                                    ?>
                        </label>

                        <textarea name="option_<?php
                                                echo strtolower($key);
                                                ?>" rows="3"><?php
                                                            echo isset(
                                                                $options[$key]
                                                            )
                                                                ? htmlspecialchars(
                                                                    $options[$key],
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                )
                                                                : '';
                                                            ?></textarea>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>


        <div id="correctAnswerSection" class="admin-form-section">

            <div class="admin-form-field">

                <label>
                    To‘g‘ri javob
                </label>

                <input type="text" name="correct_answer" value="<?php
                                                                echo htmlspecialchars(
                                                                    (string)
                                                                    $question['correct_answer'],
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                );
                                                                ?>">

            </div>

        </div>


        <div class="admin-form-section">

            <h3>
                Yozma savol qismlari
            </h3>


            <div class="admin-form-field">

                <label>
                    A qism
                </label>

                <textarea name="part_a_text" rows="5"><?php
                                                        echo htmlspecialchars(
                                                            (string)
                                                            $question['part_a_text'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                        ?></textarea>

            </div>


            <div class="admin-form-field">

                <label>
                    A qism to‘g‘ri javobi
                </label>

                <input type="text" name="part_a_correct_answer" value="<?php
                                                                        echo htmlspecialchars(
                                                                            (string)
                                                                            $question['part_a_correct_answer'],
                                                                            ENT_QUOTES,
                                                                            'UTF-8'
                                                                        );
                                                                        ?>">

            </div>


            <div class="admin-form-field">

                <label>
                    B qism
                </label>

                <textarea name="part_b_text" rows="5"><?php
                                                        echo htmlspecialchars(
                                                            (string)
                                                            $question['part_b_text'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                        ?></textarea>

            </div>


            <div class="admin-form-field">

                <label>
                    B qism to‘g‘ri javobi
                </label>

                <input type="text" name="part_b_correct_answer" value="<?php
                                                                        echo htmlspecialchars(
                                                                            (string)
                                                                            $question['part_b_correct_answer'],
                                                                            ENT_QUOTES,
                                                                            'UTF-8'
                                                                        );
                                                                        ?>">

            </div>

        </div>


        <div class="admin-form-section">

            <h3>
                Rasm yo‘llari
            </h3>


            <div id="imagePathList" class="admin-image-path-list">

                <?php if (
                    count($images) > 0
                ): ?>

                    <?php foreach (
                        $images as $image
                    ): ?>

                        <div class="
                                admin-image-path-row
                            ">

                            <input type="text" name="image_paths[]" value="<?php
                                                                            echo htmlspecialchars(
                                                                                $image['file_path'],
                                                                                ENT_QUOTES,
                                                                                'UTF-8'
                                                                            );
                                                                            ?>">

                            <button type="button" class="
                                    admin-action-button
                                    remove-image-path
                                ">
                                ×
                            </button>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="
                            admin-image-path-row
                        ">

                        <input type="text" name="image_paths[]" placeholder="images/questions/example.png">

                    </div>

                <?php endif; ?>

            </div>


            <button type="button" class="admin-secondary-button" id="addImagePath">
                + Rasm yo‘li
            </button>

        </div>


        <div class="admin-form-grid">

            <label class="admin-checkbox">

                <input type="checkbox" name="is_new" value="1" <?php
                                                                echo (int) $question['is_new'] === 1
                                                                    ? 'checked'
                                                                    : '';
                                                                ?>>

                <span>
                    Yangi savol
                </span>

            </label>


            <label class="admin-checkbox">

                <input type="checkbox" name="is_active" value="1" <?php
                                                                    echo (int) $question['is_active'] === 1
                                                                        ? 'checked'
                                                                        : '';
                                                                    ?>>

                <span>
                    Faol
                </span>

            </label>

        </div>


        <div class="admin-form-actions">

            <a href="questions.php" class="admin-secondary-button">
                Bekor qilish
            </a>

            <button type="submit" class="admin-primary-button">
                Saqlash
            </button>

        </div>

    </form>

</section>


<script>
    document.addEventListener(
        'DOMContentLoaded',
        function() {

            const type =
                document.getElementById(
                    'questionType'
                );

            const optionsSection =
                document.getElementById(
                    'optionsSection'
                );

            const correctAnswerSection =
                document.getElementById(
                    'correctAnswerSection'
                );


            function updateType() {

                const value =
                    type.value;

                const optionFields =
                    document.querySelectorAll(
                        '.option-field'
                    );


                if (value === 'written') {

                    optionsSection.style.display =
                        'none';

                    correctAnswerSection.style.display =
                        'none';

                } else {

                    optionsSection.style.display =
                        'block';

                    correctAnswerSection.style.display =
                        'block';
                }


                optionFields.forEach(
                    function(field) {

                        const key =
                            field.className
                            .match(
                                /option-([a-f])/
                            );

                        if (!key) {
                            return;
                        }

                        const letter =
                            key[1].toUpperCase();


                        if (
                            value ===
                            'multiple_choice' &&
                            (
                                letter === 'E' ||
                                letter === 'F'
                            )
                        ) {

                            field.style.display =
                                'none';

                        } else {

                            field.style.display =
                                'block';
                        }

                    }
                );

            }


            type.addEventListener(
                'change',
                updateType
            );

            updateType();


            document
                .getElementById(
                    'addImagePath'
                )
                .addEventListener(
                    'click',
                    function() {

                        const list =
                            document.getElementById(
                                'imagePathList'
                            );

                        const row =
                            document.createElement(
                                'div'
                            );

                        row.className =
                            'admin-image-path-row';

                        row.innerHTML = `
                        <input
                            type="text"
                            name="image_paths[]"
                            placeholder="images/questions/example.png"
                        >
                        <button
                            type="button"
                            class="admin-action-button remove-image-path"
                        >
                            ×
                        </button>
                    `;

                        list.appendChild(row);

                    }
                );


            document.addEventListener(
                'click',
                function(event) {

                    if (
                        event.target.classList.contains(
                            'remove-image-path'
                        )
                    ) {

                        const row =
                            event.target.closest(
                                '.admin-image-path-row'
                            );

                        if (row) {
                            row.remove();
                        }

                    }

                }
            );

        }
    );
</script>


<?php

require_once __DIR__ . '/../../layout/footer.php';

?>