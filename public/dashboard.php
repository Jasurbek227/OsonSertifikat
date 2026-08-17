<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/student_engine.php';

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

$mistakeCount = 0;

$mistakeResult =
    mysqli_query(
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
    $row =
        mysqli_fetch_assoc(
            $mistakeResult
        );

    $mistakeCount =
        (int)
        $row['total'];
}

$topicCount = 0;

$topicResult =
    mysqli_query(
        $conn,
        "
        SELECT COUNT(*) AS total
        FROM topics
        WHERE is_active = 1
        "
    );

if ($topicResult) {
    $row =
        mysqli_fetch_assoc(
            $topicResult
        );

    $topicCount =
        (int)
        $row['total'];
}

$readinessUnlocked =
    $progress >= 95;

$pageTitle =
    'Bosh sahifa';

require_once __DIR__ . '/../layout/header.php';

?>

<link rel="stylesheet" href="assets/css/style.css">

<section class="dashboard-page student-dashboard-v2">


    <section class="dashboard-hero">

        <div>

            <span class="dashboard-hero-label">
                TAYYORGARLIK
            </span>

            <h1>
                Davom eting.
            </h1>

        </div>


        <div class="dashboard-hero-progress">

            <strong>
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
                ?>%
            </strong>

            <div class="dashboard-hero-progress-bar">

                <span
                    style="width: <?php
                        echo min(
                            100,
                            max(
                                0,
                                $progress
                            )
                        );
                    ?>%;"
                ></span>

            </div>

        </div>

    </section>


    <section class="student-dashboard-grid">

        <a
            href="blocks.php"
            class="student-dashboard-card primary"
        >

            <div class="student-dashboard-card-icon">
                <i data-lucide="clipboard-list"></i>
            </div>

            <h2>
                Bloklar
            </h2>

        </a>


        <a
            href="topics.php"
            class="student-dashboard-card primary"
        >

            <div class="student-dashboard-card-icon">
                <i data-lucide="book-open"></i>
            </div>

            <h2>
                Mavzular
            </h2>

            <span>
                <?php echo $topicCount; ?>
            </span>

        </a>


        <a
            href="mistakes.php"
            class="student-dashboard-card"
        >

            <div class="student-dashboard-card-icon">
                <i data-lucide="circle-alert"></i>
            </div>

            <h2>
                Xatolar
            </h2>

            <span>
                <?php echo $mistakeCount; ?>
            </span>

        </a>


        <a
            href="formulas.php"
            class="student-dashboard-card"
        >

            <div class="student-dashboard-card-icon">
                <i data-lucide="sigma"></i>
            </div>

            <h2>
                Formulalar
            </h2>

        </a>


        <a
            href="keywords.php"
            class="student-dashboard-card"
        >

            <div class="student-dashboard-card-icon">
                <i data-lucide="key-round"></i>
            </div>

            <h2>
                Kalit so‘zlar
            </h2>

        </a>


        <a
            href="readiness.php"
            class="student-dashboard-card readiness-dashboard-card <?php
                echo $readinessUnlocked
                    ? 'is-unlocked'
                    : 'is-locked';
            ?>"
        >

            <div class="student-dashboard-card-icon">

                <i
                    data-lucide="<?php
                    echo $readinessUnlocked
                        ? 'graduation-cap'
                        : 'lock';
                    ?>"
                ></i>

            </div>

            <h2>
                Imtihonga tayyormanmi?
            </h2>

        </a>

    </section>


    <?php if (!$readinessUnlocked): ?>

        <div class="dashboard-readiness-note">

            <i data-lucide="lock"></i>

            <span>
                Imtihon 95% progressda ochiladi.
            </span>

        </div>

    <?php endif; ?>


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
.student-dashboard-v2 {
    max-width: 980px;
    margin: 0 auto;
}

.dashboard-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 22px;
    margin-bottom: 14px;
    background: linear-gradient(145deg, #121b25, #0d131b);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 14px;
}

.dashboard-hero-label {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.6px;
    color: #59aafa;
}

.dashboard-hero h1 {
    margin: 8px 0 0;
    font-size: 26px;
}

.dashboard-hero-progress {
    width: 210px;
}

.dashboard-hero-progress strong {
    display: block;
    margin-bottom: 7px;
    text-align: right;
    font-size: 24px;
}

.dashboard-hero-progress-bar {
    height: 7px;
    overflow: hidden;
    background: rgba(255,255,255,.08);
    border-radius: 99px;
}

.dashboard-hero-progress-bar span {
    display: block;
    height: 100%;
    background: #3d9cff;
    border-radius: inherit;
}

.student-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.student-dashboard-card {
    position: relative;
    min-height: 130px;
    padding: 17px;
    color: inherit;
    background: rgba(255,255,255,.022);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 11px;
    text-decoration: none;
    transition:
        transform 160ms ease,
        border-color 160ms ease,
        background 160ms ease;
}

.student-dashboard-card:hover {
    color: inherit;
    transform: translateY(-2px);
    background: rgba(255,255,255,.035);
    border-color: rgba(61,156,255,.30);
}

.student-dashboard-card.primary {
    min-height: 145px;
    background: linear-gradient(145deg, #131d28, #101720);
}

.student-dashboard-card.readiness-dashboard-card {
    grid-column: 1 / -1;
    min-height: 105px;
}

.student-dashboard-card.readiness-dashboard-card.is-locked {
    opacity: .58;
}

.student-dashboard-card-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #61aff7;
    background: rgba(61,156,255,.09);
    border: 1px solid rgba(61,156,255,.17);
    border-radius: 9px;
}

.student-dashboard-card h2 {
    margin: 17px 0 0;
    font-size: 14px;
}

.student-dashboard-card > span {
    position: absolute;
    top: 18px;
    right: 17px;
    opacity: .48;
    font-size: 11px;
}

.dashboard-readiness-note {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    color: #8e9ba9;
    font-size: 11px;
}

.dashboard-readiness-note svg {
    width: 14px;
    height: 14px;
}

@media (max-width: 760px) {
    .dashboard-hero {
        align-items: stretch;
        flex-direction: column;
    }

    .dashboard-hero-progress {
        width: 100%;
    }

    .dashboard-hero-progress strong {
        text-align: left;
    }

    .student-dashboard-grid {
        grid-template-columns: 1fr 1fr;
    }

    .student-dashboard-card.readiness-dashboard-card {
        grid-column: 1 / -1;
    }
}

@media (max-width: 480px) {
    .student-dashboard-grid {
        grid-template-columns: 1fr;
    }

    .student-dashboard-card.readiness-dashboard-card {
        grid-column: auto;
    }
}
</style>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>
