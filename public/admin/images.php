<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_auth.php';
requireAdmin();

$pageTitle = 'Rasmlar';

$uploadDirectory = __DIR__ . '/../assets/uploads/questions';
$uploadRelativePath = 'assets/uploads/questions';

$message = '';
$messageType = '';

$pickerMode = (
    isset($_GET['picker']) &&
    $_GET['picker'] === '1'
);


/*
|--------------------------------------------------------------------------
| Create upload directory
|--------------------------------------------------------------------------
*/

if (!is_dir($uploadDirectory)) {

    if (!mkdir(
        $uploadDirectory,
        0775,
        true
    )) {

        $message =
            'Rasm papkasini yaratib bo‘lmadi.';

        $messageType = 'error';
    }
}


/*
|--------------------------------------------------------------------------
| Upload
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'upload'
) {

    if (
        !isset($_FILES['image']) ||
        !is_array($_FILES['image'])
    ) {

        $message = 'Rasm tanlanmadi.';
        $messageType = 'error';

    } else {

        $file = $_FILES['image'];

        if (
            !isset($file['error']) ||
            $file['error'] !== UPLOAD_ERR_OK
        ) {

            $message =
                'Rasm yuklashda xatolik yuz berdi.';
            $messageType = 'error';

        } else {

            $originalName =
                (string) $file['name'];

            $tmpName =
                (string) $file['tmp_name'];

            $fileSize =
                (int) $file['size'];


            /*
            |--------------------------------------------------------------------------
            | Validate size
            |--------------------------------------------------------------------------
            */

            if ($fileSize <= 0) {

                $message =
                    'Rasm fayli bo‘sh.';
                $messageType = 'error';

            } elseif ($fileSize > 10 * 1024 * 1024) {

                $message =
                    'Rasm hajmi 10 MB dan oshmasligi kerak.';
                $messageType = 'error';

            } else {

                $imageInfo =
                    @getimagesize($tmpName);


                if (
                    $imageInfo === false
                ) {

                    $message =
                        'Faqat haqiqiy rasm fayllarini yuklash mumkin.';
                    $messageType = 'error';

                } else {

                    $mimeType =
                        (string) (
                            $imageInfo['mime'] ?? ''
                        );


                    $allowedTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'image/gif' => 'gif'
                    ];


                    if (
                        !isset(
                            $allowedTypes[$mimeType]
                        )
                    ) {

                        $message =
                            'Qo‘llab-quvvatlanmaydigan rasm turi.';
                        $messageType = 'error';

                    } else {

                        $extension =
                            $allowedTypes[$mimeType];


                        /*
                        |--------------------------------------------------------------------------
                        | Generate safe filename
                        |--------------------------------------------------------------------------
                        */

                        $baseName =
                            pathinfo(
                                $originalName,
                                PATHINFO_FILENAME
                            );

                        $baseName =
                            preg_replace(
                                '/[^a-zA-Z0-9_-]+/',
                                '-',
                                $baseName
                            );

                        $baseName =
                            trim(
                                (string) $baseName,
                                '-'
                            );


                        if ($baseName === '') {
                            $baseName = 'image';
                        }


                        $filename =
                            $baseName .
                            '-' .
                            date('YmdHis') .
                            '-' .
                            bin2hex(
                                random_bytes(4)
                            ) .
                            '.' .
                            $extension;


                        $destination =
                            $uploadDirectory .
                            DIRECTORY_SEPARATOR .
                            $filename;


                        if (
                            move_uploaded_file(
                                $tmpName,
                                $destination
                            )
                        ) {

                            $message =
                                'Rasm muvaffaqiyatli yuklandi.';
                            $messageType =
                                'success';

                        } else {

                            $message =
                                'Rasmni saqlab bo‘lmadi.';
                            $messageType =
                                'error';
                        }
                    }
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Delete image
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'delete'
) {

    $filename =
        isset($_POST['filename'])
            ? basename(
                (string) $_POST['filename']
            )
            : '';


    if ($filename === '') {

        $message =
            'Rasm tanlanmadi.';
        $messageType =
            'error';

    } else {

        $absolutePath =
            $uploadDirectory .
            DIRECTORY_SEPARATOR .
            $filename;

        $relativePath =
            $uploadRelativePath .
            '/' .
            $filename;


        if (!is_file($absolutePath)) {

            $message =
                'Rasm topilmadi.';
            $messageType =
                'error';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Check whether image is used
            |--------------------------------------------------------------------------
            */

            require_once __DIR__ . '/../../includes/db.php';

            $safePath =
                mysqli_real_escape_string(
                    $conn,
                    $relativePath
                );

            $usageQuery = "
                SELECT COUNT(*) AS total
                FROM question_images
                WHERE file_path = '$safePath'
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

                $message =
                    'Bu rasm savolda ishlatilmoqda. Avval uni savollardan olib tashlang.';
                $messageType =
                    'error';

            } elseif (
                unlink($absolutePath)
            ) {

                $message =
                    'Rasm o‘chirildi.';
                $messageType =
                    'success';

            } else {

                $message =
                    'Rasmni o‘chirib bo‘lmadi.';
                $messageType =
                    'error';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Read gallery
|--------------------------------------------------------------------------
*/

$images = array();

if (
    is_dir($uploadDirectory)
) {

    $files =
        scandir(
            $uploadDirectory
        );

    if ($files !== false) {

        foreach ($files as $filename) {

            if (
                $filename === '.' ||
                $filename === '..'
            ) {
                continue;
            }


            $absolutePath =
                $uploadDirectory .
                DIRECTORY_SEPARATOR .
                $filename;


            if (!is_file($absolutePath)) {
                continue;
            }


            $extension =
                strtolower(
                    (string) pathinfo(
                        $filename,
                        PATHINFO_EXTENSION
                    )
                );


            if (
                !in_array(
                    $extension,
                    [
                        'jpg',
                        'jpeg',
                        'png',
                        'webp',
                        'gif'
                    ],
                    true
                )
            ) {
                continue;
            }


            $images[] = [
                'filename' => $filename,
                'path' =>
                    $uploadRelativePath .
                    '/' .
                    $filename,
                'size' =>
                    (int) filesize(
                        $absolutePath
                    ),
                'modified' =>
                    (int) filemtime(
                        $absolutePath
                    )
            ];
        }
    }
}


usort(
    $images,
    function (
        array $a,
        array $b
    ): int {
        return $b['modified']
            <=> $a['modified'];
    }
);

?>
<link rel="stylesheet" href="../assets/css/admin.css">

<?php if ($pickerMode): ?>

<main class="admin-page admin-content-page image-picker-page">

    <div class="admin-page-header">

        <div>

            <span class="admin-eyebrow">
                IMAGE LIBRARY
            </span>

            <h1 class="admin-page-title">
                Rasm tanlash
            </h1>

            <p class="admin-page-description">
                Savol uchun mavjud rasmlardan birini tanlang.
            </p>

        </div>

    </div>


    <div class="admin-image-library-grid">

        <?php if (count($images) === 0): ?>

        <div class="admin-empty">

            <div class="admin-empty-icon">
                <i data-lucide="image-off"></i>
            </div>

            <h3>
                Rasmlar mavjud emas
            </h3>

            <p>
                Avval rasm yuklang.
            </p>

        </div>

        <?php else: ?>

        <?php foreach ($images as $image): ?>

        <button type="button" class="admin-image-card admin-image-picker-card" data-image-path="<?php
                            echo htmlspecialchars(
                                $image['path'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>">

            <span class="admin-image-preview">

                <img src="../<?php
                                    echo htmlspecialchars(
                                        $image['path'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?>" alt="">

            </span>


            <span class="admin-image-card-info">

                <strong>
                    <?php
                                echo htmlspecialchars(
                                    $image['filename'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                </strong>

                <small>
                    <?php
                                echo number_format(
                                    $image['size'] / 1024,
                                    1
                                );
                                ?>
                    KB
                </small>

            </span>

        </button>

        <?php endforeach; ?>

        <?php endif; ?>

    </div>

</main>


<?php else: ?>

<main class="admin-page admin-content-page">

    <a href="index.php" class="page-back">
        <span class="page-back-icon">
            ←
        </span>
        <span>
            Dashboard
        </span>
    </a>


    <div class="admin-page-header">

        <div>

            <span class="admin-eyebrow">
                IMAGE LIBRARY
            </span>

            <h1 class="admin-page-title">
                Rasmlar
            </h1>

            <p class="admin-page-description">
                Savollarda ishlatiladigan rasmlarni yuklang va boshqaring.
            </p>

        </div>

    </div>


    <?php if ($message !== ''): ?>

    <div class="admin-message admin-message-<?php
                echo $messageType;
            ?>">

        <?php
                echo htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

    </div>

    <?php endif; ?>


    <section class="admin-form-card">

        <div class="admin-form-card-header">

            <div>

                <h2>
                    Rasm yuklash
                </h2>

                <p>
                    JPG, PNG, WEBP yoki GIF. Maksimal 10 MB.
                </p>

            </div>

        </div>


        <form method="POST" enctype="multipart/form-data" class="admin-image-upload-form">

            <input type="hidden" name="action" value="upload">


            <label class="admin-upload-dropzone" for="imageUploadInput">

                <i data-lucide="upload"></i>

                <strong>
                    Rasm tanlang
                </strong>

                <span>
                    Kompyuterdan rasm yuklash uchun bosing
                </span>

            </label>


            <input type="file" id="imageUploadInput" name="image" accept="image/jpeg,image/png,image/webp,image/gif"
                required class="admin-upload-input">


            <div class="admin-form-actions">

                <button type="submit" class="admin-primary-button">
                    <i data-lucide="upload"></i>
                    Yuklash
                </button>

            </div>

        </form>

    </section>


    <section class="admin-section">

        <div class="admin-section-header">

            <h2 class="admin-section-title">
                Rasm kutubxonasi
            </h2>

            <p class="admin-section-description">
                Jami <?= count($images) ?> ta rasm
            </p>

        </div>


        <div class="admin-image-library-grid">

            <?php if (count($images) === 0): ?>

            <div class="admin-empty">

                <div class="admin-empty-icon">
                    <i data-lucide="images"></i>
                </div>

                <h3>
                    Rasm yo‘q
                </h3>

                <p>
                    Yuqoridagi forma orqali birinchi rasmni yuklang.
                </p>

            </div>

            <?php else: ?>

            <?php foreach ($images as $image): ?>

            <article class="admin-image-card">

                <div class="admin-image-preview">

                    <img src="../<?php
                                        echo htmlspecialchars(
                                            $image['path'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>" alt="">

                </div>


                <div class="admin-image-card-info">

                    <strong>
                        <?php
                                    echo htmlspecialchars(
                                        $image['filename'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                    </strong>


                    <small>
                        <?php
                                    echo number_format(
                                        $image['size'] / 1024,
                                        1
                                    );
                                    ?>
                        KB
                    </small>

                </div>


                <div class="admin-image-card-actions">

                    <button type="button" class="admin-secondary-button admin-small-button" onclick="
                                        window.open(
                                            `<?php
                                            echo htmlspecialchars(
                                                '../' . $image['path'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );?>`,
                                            '_blank'
                                        );
                                    ">
                        <i data-lucide="external-link"></i>
                        Ko‘rish
                    </button>


                    <form method="POST" onsubmit="
                                        return confirm(
                                            'Bu rasmni o‘chirmoqchimisiz?'
                                        );
                                    ">

                        <input type="hidden" name="action" value="delete">

                        <input type="hidden" name="filename" value="<?php
                                        echo htmlspecialchars(
                                            $image['filename'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>">

                        <button type="submit" class="admin-action-button admin-danger-button admin-small-button">
                            <i data-lucide="trash-2"></i>
                            O‘chirish
                        </button>

                    </form>

                </div>

            </article>

            <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </section>

</main>

<?php endif; ?>


<script src="https://unpkg.com/lucide@latest"></script>

<script>
document.addEventListener(
    'DOMContentLoaded',
    function() {

        if (
            typeof lucide !== 'undefined'
        ) {

            lucide.createIcons();

        }


        document.querySelectorAll(
            '.admin-image-picker-card'
        ).forEach(
            function(card) {

                card.addEventListener(
                    'click',
                    function() {

                        const path =
                            card.dataset.imagePath;


                        if (
                            window.opener &&
                            !window.opener.closed
                        ) {

                            window.opener.postMessage({
                                    type: 'osonsertifikat-image-selected',
                                    path: path
                                },
                                window.location.origin
                            );

                            window.close();

                            return;
                        }


                        if (
                            window.parent &&
                            window.parent !== window
                        ) {

                            window.parent.postMessage({
                                    type: 'osonsertifikat-image-selected',
                                    path: path
                                },
                                window.location.origin
                            );
                        }

                    }
                );

            }
        );

    }
);
</script>