<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_auth.php';
requireAdmin();

require_once __DIR__ . '/../../includes/db.php';

$pageTitle = 'Yangi savol';

$error = '';

$topics = array();


/*
|--------------------------------------------------------------------------
| Topics
|--------------------------------------------------------------------------
*/

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

    while ($topic = mysqli_fetch_assoc($topicResult)) {
        $topics[] = $topic;
    }
}


/*
|--------------------------------------------------------------------------
| Form values
|--------------------------------------------------------------------------
*/

$postedQuestionType = isset($_POST['question_type'])
    ? trim((string) $_POST['question_type'])
    : '';

$postedVariantCount = isset($_POST['variant_count'])
    ? (int) $_POST['variant_count']
    : 4;

if ($postedVariantCount !== 4 && $postedVariantCount !== 5) {
    $postedVariantCount = 4;
}

if ($postedQuestionType === 'six_option') {
    $postedVariantCount = 6;
}

if ($postedQuestionType === 'written') {
    $postedVariantCount = 0;
}


/*
|--------------------------------------------------------------------------
| Create question
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $topicId = isset($_POST['topic_id'])
        ? (int) $_POST['topic_id']
        : 0;

    $questionType = $postedQuestionType;

    $text = isset($_POST['text'])
        ? trim((string) $_POST['text'])
        : '';

    $correctAnswer = isset($_POST['correct_answer'])
        ? strtoupper(
            trim((string) $_POST['correct_answer'])
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


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($topicId <= 0) {

        $error = 'Mavzuni tanlang.';
    } elseif (
        $questionType !== 'multiple_choice' &&
        $questionType !== 'six_option' &&
        $questionType !== 'written'
    ) {

        $error = 'Savol turini tanlang.';
    } elseif ($text === '') {

        $error = 'Savol matnini kiriting.';
    }


    /*
    |--------------------------------------------------------------------------
    | Determine option count
    |--------------------------------------------------------------------------
    */

    $optionKeys = array();

    if (
        $error === '' &&
        $questionType === 'multiple_choice'
    ) {

        if (
            $postedVariantCount !== 4 &&
            $postedVariantCount !== 5
        ) {

            $error =
                'Variantlar soni 4 yoki 5 bo‘lishi kerak.';
        } else {

            $optionKeys = [
                'A',
                'B',
                'C',
                'D'
            ];

            if ($postedVariantCount === 5) {
                $optionKeys[] = 'E';
            }
        }
    }


    if (
        $error === '' &&
        $questionType === 'six_option'
    ) {

        $optionKeys = [
            'A',
            'B',
            'C',
            'D',
            'E',
            'F'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Validate options
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        count($optionKeys) > 0
    ) {

        foreach ($optionKeys as $key) {

            $fieldName =
                'option_' .
                strtolower($key);

            $optionText = isset($_POST[$fieldName])
                ? trim(
                    (string) 
                    $_POST[$fieldName]
                )
                : '';

            if ($optionText === '') {

                $error =
                    'Barcha variant maydonlarini to‘ldiring.';

                break;
            }
        }


        if (
            $error === '' &&
            !in_array(
                $correctAnswer,
                $optionKeys,
                true
            )
        ) {

            $error =
                'To‘g‘ri javob mavjud variantlardan biri bo‘lishi kerak.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validate written question
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $questionType === 'written'
    ) {

        $hasPartA = (
            $partAText !== '' &&
            $partACorrect !== ''
        );

        $hasPartB = (
            $partBText !== '' &&
            $partBCorrect !== ''
        );


        if (
            !$hasPartA &&
            !$hasPartB
        ) {

            $error =
                'Yozma savol uchun kamida A yoki B qismni to‘liq kiriting.';
        }


        $correctAnswer = '';
    }


    /*
    |--------------------------------------------------------------------------
    | Only keep relevant fields
    |--------------------------------------------------------------------------
    */

    if (
        $questionType !== 'written'
    ) {

        $partAText = '';
        $partACorrect = '';
        $partBText = '';
        $partBCorrect = '';
    } else {

        $correctAnswer = '';
    }


    /*
    |--------------------------------------------------------------------------
    | Save question
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $safeText = mysqli_real_escape_string(
            $conn,
            $text
        );

        $safeCorrectAnswer =
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


        $query = "
            INSERT INTO questions (
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
            )
            VALUES (
                $topicId,
                '$questionType',
                '$safeText',
                " .
            (
                $correctAnswer !== ''
                ? "'$safeCorrectAnswer'"
                : "NULL"
            ) .
            ",
                " .
            (
                $partAText !== ''
                ? "'$safePartAText'"
                : "NULL"
            ) .
            ",
                " .
            (
                $partACorrect !== ''
                ? "'$safePartACorrect'"
                : "NULL"
            ) .
            ",
                " .
            (
                $partBText !== ''
                ? "'$safePartBText'"
                : "NULL"
            ) .
            ",
                " .
            (
                $partBCorrect !== ''
                ? "'$safePartBCorrect'"
                : "NULL"
            ) .
            ",
                $isNew,
                1
            )
        ";


        if (!mysqli_query($conn, $query)) {

            $error =
                'Savol yaratishda xatolik: ' .
                mysqli_error($conn);
        } else {

            $questionId =
                (int) mysqli_insert_id($conn);


            /*
            |--------------------------------------------------------------------------
            | Save options
            |--------------------------------------------------------------------------
            */

            if (
                $questionType === 'multiple_choice' ||
                $questionType === 'six_option'
            ) {

                foreach ($optionKeys as $key) {

                    $fieldName =
                        'option_' .
                        strtolower($key);

                    $optionText =
                        trim(
                            (string) 
                            $_POST[$fieldName]
                        );

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


                    if (
                        !mysqli_query(
                            $conn,
                            $optionQuery
                        )
                    ) {

                        $error =
                            'Variantlarni saqlashda xatolik: ' .
                            mysqli_error($conn);

                        break;
                    }
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Save images
            |--------------------------------------------------------------------------
            */

            if (
                $error === '' &&
                isset($_POST['image_paths']) &&
                is_array($_POST['image_paths'])
            ) {

                foreach (
                    $_POST['image_paths']
                    as $imagePath
                ) {

                    $imagePath =
                        trim(
                            (string) $imagePath
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


                    if (
                        !mysqli_query(
                            $conn,
                            $imageQuery
                        )
                    ) {

                        $error =
                            'Rasm yo‘lini saqlashda xatolik: ' .
                            mysqli_error($conn);

                        break;
                    }
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                header(
                    'Location: question_edit.php?id=' .
                    $questionId .
                    '&created=1'
                );

                exit;
            }
        }
    }
}

?>
<!DOCTYPE html>

<html lang="uz">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Yangi savol — Oson Sertifikat
    </title>

    <link rel="stylesheet" href="../assets/css/admin.css">

</head>

<body>

    <main class="admin-page admin-content-page">


        <!-- Header -->

        <div class="admin-page-header">

            <div>

                <span class="admin-eyebrow">
                    ADMIN PANEL
                </span>

                <h1 class="admin-page-title">
                    Yangi savol
                </h1>

                <p class="admin-page-description">
                    Savol ma’lumotlarini kiriting
                </p>

            </div>

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


        <form method="POST" class="admin-form" id="questionCreateForm" novalidate>


            <!-- Basic information -->

            <div class="admin-form-card">

                <div class="admin-form-grid">


                    <!-- Topic -->

                    <div class="admin-form-field">

                        <label for="topicId">
                            Mavzu
                        </label>


                        <select name="topic_id" id="topicId" required>

                            <option value="" disabled selected hidden>
                                Tanlang
                            </option>


                            <?php foreach (
                                $topics
                                as $topic
                            ): ?>

                                <option value="<?php
                                echo (int) 
                                    $topic['id'];
                                ?>" <?php
                                if (
                                    isset(
                                    $_POST['topic_id']
                                ) &&
                                    (
                                        (int) 
                                        $_POST['topic_id'] ===
                                        (int) 
                                        $topic['id']
                                    )
                                ) {
                                    echo 'selected';
                                }
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


                    <!-- Question type -->

                    <div class="admin-form-field">

                        <label for="questionType">
                            Savol turi
                        </label>


                        <select name="question_type" id="questionType" required>

                            <option value="" disabled selected hidden>
                                Tanlang
                            </option>


                            <option value="multiple_choice" <?php
                            echo (
                                $postedQuestionType ===
                                'multiple_choice'
                            )
                                ? 'selected'
                                : '';
                            ?>>
                                Variantli
                            </option>


                            <option value="six_option" <?php
                            echo (
                                $postedQuestionType ===
                                'six_option'
                            )
                                ? 'selected'
                                : '';
                            ?>>
                                6 variantli
                            </option>


                            <option value="written" <?php
                            echo (
                                $postedQuestionType ===
                                'written'
                            )
                                ? 'selected'
                                : '';
                            ?>>
                                Yozma
                            </option>

                        </select>

                    </div>


                </div>


                <!-- Question text -->

                <div class="admin-form-field">

                    <label for="questionText">
                        Savol matni
                    </label>


                    <textarea name="text" id="questionText" rows="8" required><?php
                    echo isset($_POST['text'])
                        ? htmlspecialchars(
                            (string) 
                            $_POST['text'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        : '';
                    ?></textarea>

                </div>

            </div>


            <!-- Variant section -->

            <div id="optionsSection" class="admin-form-card">

                <div class="admin-form-card-header">

                    <div>

                        <h2>
                            Variantlar
                        </h2>

                        <p>
                            Javob variantlarini kiriting.
                        </p>

                    </div>

                </div>


                <!-- Variant count -->

                <div id="variantCountWrapper" class="admin-form-field">

                    <label for="variantCount">
                        Variantlar soni
                    </label>


                    <select name="variant_count" id="variantCount">

                        <option value="4" <?php
                        echo (
                            $postedVariantCount === 4
                        )
                            ? 'selected'
                            : '';
                        ?>>
                            4 ta
                        </option>


                        <option value="5" <?php
                        echo (
                            $postedVariantCount === 5
                        )
                            ? 'selected'
                            : '';
                        ?>>
                            5 ta
                        </option>

                    </select>

                </div>


                <!-- Variant fields -->

                <div id="optionFieldsContainer">

                    <?php
                    $createOptionKeys = [
                        'A',
                        'B',
                        'C',
                        'D',
                        'E',
                        'F'
                    ];
                    ?>


                    <?php foreach (
                        $createOptionKeys
                        as $key
                    ): ?>

                        <div class="
                            admin-option-row
                            option-field
                            option-<?php
                            echo strtolower($key);
                            ?>
                        " data-option="<?php
                        echo $key;
                        ?>">


                            <input type="text" name="option_<?php
                            echo strtolower($key);
                            ?>" placeholder="Variant <?php
                            echo $key;
                            ?>"><?php
                            $optionField =
                                'option_' .
                                strtolower($key);

                            echo isset(
                                $_POST[$optionField]
                            )
                                ? htmlspecialchars(
                                    (string) 
                                    $_POST[$optionField],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : '';
                            ?>

                        </div>

                    <?php endforeach; ?>

                </div>
                <!-- Add variant -->

                <button type="button" class="btn btn-outline-light" id="addVariantButton">
                    <i data-lucide="plus"></i>
                    Variant qo‘shish
                </button>


                <!-- Correct answer -->

                <div class="admin-form-field">

                    <select name="correct_answer" id="correctAnswer" required>

                        <option value="" disabled selected hidden>
                            To‘g‘ri javob
                        </option>


                        <option value="A">
                            A
                        </option>

                        <option value="B">
                            B
                        </option>

                        <option value="C">
                            C
                        </option>

                        <option value="D">
                            D
                        </option>

                        <option value="E">
                            E
                        </option>

                        <option value="F">
                            F
                        </option>

                    </select>

                </div>




            </div>


            <!-- Written section -->

            <div id="writtenSection" class="admin-form-card">

                <div class="admin-form-card-header">

                    <div>

                        <h2>
                            Yozma savol qismlari
                        </h2>

                        <p>
                            Yozma topshiriqning qismlarini kiriting.
                        </p>

                    </div>

                </div>


                <div class="admin-form-field">

                    <label for="partAText">
                        A qism savoli
                    </label>


                    <textarea name="part_a_text" id="partAText" rows="5"><?php
                    echo isset(
                        $_POST['part_a_text']
                    )
                        ? htmlspecialchars(
                            (string) 
                            $_POST['part_a_text'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        : '';
                    ?></textarea>

                </div>


                <div class="admin-form-field">

                    <label for="partACorrect">
                        A qism javobi
                    </label>


                    <input type="text" name="part_a_correct_answer" id="partACorrect" value="<?php
                    echo isset(
                        $_POST['part_a_correct_answer']
                    )
                        ? htmlspecialchars(
                            (string) 
                            $_POST['part_a_correct_answer'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        : '';
                    ?>">

                </div>


                <div class="admin-form-field">

                    <label for="partBText">
                        B qism savoli
                    </label>


                    <textarea name="part_b_text" id="partBText" rows="5"><?php
                    echo isset(
                        $_POST['part_b_text']
                    )
                        ? htmlspecialchars(
                            (string) 
                            $_POST['part_b_text'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        : '';
                    ?></textarea>

                </div>


                <div class="admin-form-field">

                    <label for="partBCorrect">
                        B qism javobi
                    </label>


                    <input type="text" name="part_b_correct_answer" id="partBCorrect" value="<?php
                    echo isset(
                        $_POST['part_b_correct_answer']
                    )
                        ? htmlspecialchars(
                            (string) 
                            $_POST['part_b_correct_answer'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        : '';
                    ?>">

                </div>

            </div>


            <!-- Images -->

            <div class="admin-form-card">

                <div class="admin-form-card-header">

                    <div>

                        <h2>
                            Rasmlar
                        </h2>

                        <p>
                            Savolga rasm biriktiring.
                        </p>

                    </div>

                </div>


                <div id="selectedImageList" class="admin-selected-image-list"></div>


                <input type="hidden" name="image_paths[]" id="selectedImagePath" value="">


                <button type="button" class="btn btn-outline-light" id="openImagePicker">
                    <i data-lucide="images"></i>
                    Rasmlar kutubxonasidan tanlash
                </button>

            </div>


            <!-- New question -->

            <label class="admin-checkbox">

                <input type="checkbox" name="is_new" value="1" <?php
                echo isset($_POST['is_new'])
                    ? 'checked'
                    : '';
                ?>>

                <span>
                    Yangi savol
                </span>

            </label>


            <!-- Actions -->

            <div class="admin-form-actions">

                <a href="questions.php" class="btn btn-outline-light">
                    Bekor qilish
                </a>


                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save"></i>
                    Savol yaratish
                </button>

            </div>

        </form>

    </main>


    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function () {

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }


                const questionType =
                    document.getElementById('questionType');

                const optionsSection =
                    document.getElementById('optionsSection');

                const writtenSection =
                    document.getElementById('writtenSection');

                const variantCountWrapper =
                    document.getElementById('variantCountWrapper');

                const variantCount =
                    document.getElementById('variantCount');

                const correctAnswer =
                    document.getElementById('correctAnswer');

                const addVariantButton =
                    document.getElementById('addVariantButton');

                const optionRows =
                    document.querySelectorAll('.option-field');


                function getVariantCount() {

                    if (questionType.value === 'six_option') {
                        return 6;
                    }

                    if (questionType.value === 'multiple_choice') {
                        return parseInt(
                            variantCount.value,
                            10
                        ) || 4;
                    }

                    return 0;
                }


                function getActiveLetters() {

                    const letters = [
                        'A',
                        'B',
                        'C',
                        'D',
                        'E',
                        'F'
                    ];

                    return letters.slice(
                        0,
                        getVariantCount()
                    );
                }


                function clearField(row) {

                    const textarea =
                        row.querySelector('textarea');

                    if (textarea) {
                        textarea.value = '';
                    }
                }


                function updateOptionFields() {

                    const activeLetters =
                        getActiveLetters();


                    optionRows.forEach(
                        function (row) {

                            const letter =
                                row.dataset.option;


                            if (
                                activeLetters.includes(letter)
                            ) {

                                row.hidden = false;

                            } else {

                                row.hidden = true;

                                clearField(row);
                            }

                        }
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | + Variant button
                    |--------------------------------------------------------------------------
                    */

                    if (
                        questionType.value === 'multiple_choice' &&
                        getVariantCount() === 4
                    ) {

                        addVariantButton.hidden = false;

                    } else {

                        addVariantButton.hidden = true;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Correct answer choices
                    |--------------------------------------------------------------------------
                    */

                    const currentValue =
                        correctAnswer.value;


                    correctAnswer.innerHTML = '';


                    const placeholder =
                        document.createElement('option');

                    placeholder.value = '';
                    placeholder.textContent = 'Tanlang';
                    placeholder.disabled = true;
                    placeholder.hidden = true;

                    correctAnswer.appendChild(
                        placeholder
                    );


                    activeLetters.forEach(
                        function (letter) {

                            const option =
                                document.createElement('option');

                            option.value = letter;
                            option.textContent = letter;

                            correctAnswer.appendChild(
                                option
                            );

                        }
                    );


                    if (
                        activeLetters.includes(currentValue)
                    ) {

                        correctAnswer.value =
                            currentValue;

                    } else {

                        correctAnswer.value = '';
                    }

                }


                function updateQuestionType() {

                    const type =
                        questionType.value;


                    /*
                    |--------------------------------------------------------------------------
                    | Default
                    |--------------------------------------------------------------------------
                    */

                    optionsSection.hidden = true;
                    writtenSection.hidden = true;

                    variantCountWrapper.hidden = true;

                    correctAnswer.disabled = true;
                    correctAnswer.required = false;

                    addVariantButton.hidden = true;


                    /*
                    |--------------------------------------------------------------------------
                    | Variantli
                    |--------------------------------------------------------------------------
                    */

                    if (type === 'multiple_choice') {

                        optionsSection.hidden = false;

                        writtenSection.hidden = true;

                        variantCountWrapper.hidden = false;

                        variantCount.disabled = false;

                        correctAnswer.disabled = false;
                        correctAnswer.required = true;


                        updateOptionFields();

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | 6 variantli
                    |--------------------------------------------------------------------------
                    */

                    if (type === 'six_option') {

                        optionsSection.hidden = false;

                        writtenSection.hidden = true;

                        variantCountWrapper.hidden = true;

                        variantCount.disabled = true;

                        correctAnswer.disabled = false;
                        correctAnswer.required = true;

                        variantCount.value = '4';


                        updateOptionFields();

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Yozma
                    |--------------------------------------------------------------------------
                    */

                    if (type === 'written') {

                        optionsSection.hidden = true;

                        writtenSection.hidden = false;

                        variantCountWrapper.hidden = true;

                        variantCount.disabled = true;

                        correctAnswer.value = '';
                        correctAnswer.disabled = true;
                        correctAnswer.required = false;


                        optionRows.forEach(
                            function (row) {
                                clearField(row);
                                row.hidden = true;
                            }
                        );

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Tanlang
                    |--------------------------------------------------------------------------
                    */

                    correctAnswer.value = '';

                    optionRows.forEach(
                        function (row) {
                            row.hidden = true;
                        }
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | Question type change
                |--------------------------------------------------------------------------
                */

                questionType.addEventListener(
                    'change',
                    updateQuestionType
                );


                /*
                |--------------------------------------------------------------------------
                | 4 -> 5 variants
                |--------------------------------------------------------------------------
                */

                variantCount.addEventListener(
                    'change',
                    updateOptionFields
                );


                addVariantButton.addEventListener(
                    'click',
                    function () {

                        if (
                            questionType.value !==
                            'multiple_choice'
                        ) {
                            return;
                        }


                        variantCount.value = '5';

                        updateOptionFields();

                    }
                );


                


                /*
                |--------------------------------------------------------------------------
                | Initial state
                |--------------------------------------------------------------------------
                */

                updateQuestionType();

            }
        );
    </script>
    <script src="../assets/js/admin-image-picker.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>

</html>