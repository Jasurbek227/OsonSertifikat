<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/student_engine.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Progress
|--------------------------------------------------------------------------
*/
studentUpdateProgress($conn, $userId);

$progress = 0.0;

$progressResult = mysqli_query(
    $conn,
    "
    SELECT progress_percent
    FROM user_progress
    WHERE user_id = $userId
    LIMIT 1
    "
);

if ($progressResult) {
    $row = mysqli_fetch_assoc($progressResult);
    $progress = (float) ($row['progress_percent'] ?? 0);
}

$progress = min(100, max(0, $progress));

/*
|--------------------------------------------------------------------------
| Correctly solved unique questions
|--------------------------------------------------------------------------
*/
$completedQuestions = 0;

$completedResult = mysqli_query(
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

if ($completedResult) {
    $row = mysqli_fetch_assoc($completedResult);
    $completedQuestions = (int) ($row['total'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Total active questions
|--------------------------------------------------------------------------
*/
$totalQuestions = 0;

$totalQuestionResult = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM questions
    WHERE is_active = 1
    "
);

if ($totalQuestionResult) {
    $row = mysqli_fetch_assoc($totalQuestionResult);
    $totalQuestions = (int) ($row['total'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Mistakes
|--------------------------------------------------------------------------
*/
$mistakeCount = 0;

$mistakeResult = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM mistake_queue mq
    INNER JOIN questions q
        ON q.id = mq.question_id
    WHERE mq.user_id = $userId
      AND q.is_active = 1
    "
);

if ($mistakeResult) {
    $row = mysqli_fetch_assoc($mistakeResult);
    $mistakeCount = (int) ($row['total'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Topics
|--------------------------------------------------------------------------
*/
$topicCount = 0;

$topicResult = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM topics
    WHERE is_active = 1
    "
);

if ($topicResult) {
    $row = mysqli_fetch_assoc($topicResult);
    $topicCount = (int) ($row['total'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Readiness
|--------------------------------------------------------------------------
*/
$readinessUnlocked = $progress >= 95;

$progressDisplay = rtrim(
    rtrim(
        number_format($progress, 2, '.', ''),
        '0'
    ),
    '.'
);

$pageTitle = 'Bosh sahifa';

require_once __DIR__ . '/../layout/header.php';

?>

<link rel="stylesheet" href="assets/css/dashboard.css">

<section
    class="dashboard-page dashboard-v3"
    style="--dashboard-progress: <?= number_format($progress, 2, '.', '') ?>%;"
>

    <!-- Progress -->
    <section class="dashboard-progress-card">

        <div class="dashboard-progress-top">

            <div class="dashboard-progress-main">

                <span class="dashboard-progress-label">
                    TAYYORGARLIK
                </span>

                <div class="dashboard-progress-value">
                    <?= $progressDisplay ?>%
                </div>

            </div>


            <div class="dashboard-progress-side">

                <span class="dashboard-progress-side-main">
                    <?= $completedQuestions ?> ta
                </span>

                <span class="dashboard-progress-side-sub">
                    / <?= $totalQuestions ?> savol
                </span>

            </div>

        </div>


        <div
            class="dashboard-progress-track"
            role="progressbar"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="<?= $progressDisplay ?>"
            aria-label="Tayyorgarlik progressi"
        >
            <span class="dashboard-progress-fill"></span>
        </div>

    </section>


    <!-- Main navigation cards -->
    <section class="dashboard-card-grid">


        <!-- Blocks -->
        <a
            href="blocks.php"
            class="dashboard-card dashboard-card-blue"
        >

            <div class="dashboard-card-icon">
                <i data-lucide="clipboard-list"></i>
            </div>

            <div class="dashboard-card-content">
                <h2>
                    Bloklar
                </h2>
            </div>

            <i
                data-lucide="arrow-up-right"
                class="dashboard-card-arrow"
            ></i>

        </a>


        <!-- Topics -->
        <a
            href="topics.php"
            class="dashboard-card dashboard-card-purple"
        >

            <div class="dashboard-card-icon">
                <i data-lucide="book-open"></i>
            </div>

            <div class="dashboard-card-content">
                <h2>
                    Mavzular
                </h2>
            </div>

            <span class="dashboard-card-count">
                <?= $topicCount ?>
            </span>

            <i
                data-lucide="arrow-up-right"
                class="dashboard-card-arrow"
            ></i>

        </a>


        <!-- Mistakes -->
        <a
            href="mistakes.php"
            class="dashboard-card dashboard-card-red"
        >

            <div class="dashboard-card-icon">
                <i data-lucide="heart-pulse"></i>
            </div>

            <div class="dashboard-card-content">
                <h2>
                    Xatolar
                </h2>
            </div>

            <span class="dashboard-card-count">
                <?= $mistakeCount ?>
            </span>

            <i
                data-lucide="arrow-up-right"
                class="dashboard-card-arrow"
            ></i>

        </a>


        <!-- Formulas -->
        <a
            href="formulas.php"
            class="dashboard-card dashboard-card-yellow"
        >

            <div class="dashboard-card-icon">
                <i data-lucide="sigma"></i>
            </div>

            <div class="dashboard-card-content">
                <h2>
                    Formulalar
                </h2>
            </div>

            <i
                data-lucide="arrow-up-right"
                class="dashboard-card-arrow"
            ></i>

        </a>


        <!-- Keywords -->
        <a
            href="keywords.php"
            class="dashboard-card dashboard-card-cyan"
        >

            <div class="dashboard-card-icon">
                <i data-lucide="key-round"></i>
            </div>

            <div class="dashboard-card-content">
                <h2>
                    Kalit so‘zlar
                </h2>
            </div>

            <i
                data-lucide="arrow-up-right"
                class="dashboard-card-arrow"
            ></i>

        </a>


        <!-- Readiness -->
        <a
            href="readiness.php"
            class="
                dashboard-card
                dashboard-card-green
                dashboard-readiness
                <?= $readinessUnlocked
                    ? 'is-unlocked'
                    : 'is-locked';
                ?>
            "
        >

            <div class="dashboard-card-icon">
                <i
                    data-lucide="<?= $readinessUnlocked
                        ? 'graduation-cap'
                        : 'lock';
                    ?>"
                ></i>
            </div>

            <div class="dashboard-card-content">

                <h2>
                    Imtihonga tayyormanmi?
                </h2>

                <span>
                    <?= $readinessUnlocked
                        ? 'Boshlash'
                        : '95% progressda ochiladi';
                    ?>
                </span>

            </div>

            <?php if ($readinessUnlocked): ?>

                <i
                    data-lucide="arrow-up-right"
                    class="dashboard-card-arrow"
                ></i>

            <?php else: ?>

                <span class="dashboard-lock-badge">
                    🔒
                </span>

            <?php endif; ?>

        </a>


    </section>

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

<?php
require_once __DIR__ . '/../layout/footer.php';
?>
