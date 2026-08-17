<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

require_once __DIR__ . '/../includes/db.php';

$userId =
    (int)
    ($_SESSION['user_id'] ?? 0);

$topics = [];

$result =
    mysqli_query(
        $conn,
        "
        SELECT
            t.id,
            t.name,
            t.slug,
            COUNT(q.id) AS question_count,
            COALESCE(
                utp.progress_percent,
                0
            ) AS progress_percent
        FROM topics t
        LEFT JOIN questions q
            ON q.topic_id = t.id
           AND q.is_active = 1
        LEFT JOIN user_topic_progress utp
            ON utp.topic_id = t.id
           AND utp.user_id = $userId
        WHERE t.is_active = 1
        GROUP BY
            t.id,
            t.name,
            t.slug,
            utp.progress_percent
        ORDER BY t.id ASC
        "
    );

if ($result) {
    while (
        $row =
        mysqli_fetch_assoc($result)
    ) {
        $topics[] =
            $row;
    }
}

$pageTitle = 'Mavzular';

require_once __DIR__ . '/../layout/header.php';

?>

<link rel="stylesheet" href="assets/css/style.css">

<section class="page-section student-topics-page">

    <a
        href="dashboard.php"
        class="page-back"
    >
        ← Orqaga
    </a>

    <div class="page-heading">

        <h1 class="page-title">
            Mavzular
        </h1>

    </div>


    <div class="student-topic-grid">

        <?php foreach ($topics as $topic): ?>

            <?php
            $percent =
                min(
                    100,
                    max(
                        0,
                        (float)
                        $topic['progress_percent']
                    )
                );
            ?>

            <a
                href="topic.php?id=<?php
                    echo (int)
                    $topic['id'];
                ?>"
                class="student-topic-card"
            >

                <div class="student-topic-card-top">

                    <div class="student-topic-icon">
                        <i data-lucide="book-open"></i>
                    </div>

                    <span>
                        <?php
                        echo (int)
                        $topic['question_count'];
                        ?>
                        ta
                    </span>

                </div>


                <h2>
                    <?php
                    echo htmlspecialchars(
                        $topic['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </h2>


                <div class="student-topic-progress">

                    <div class="student-topic-progress-bar">

                        <span
                            style="width: <?php
                                echo $percent;
                            ?>%;"
                        ></span>

                    </div>

                    <strong>
                        <?php
                        echo rtrim(
                            rtrim(
                                number_format(
                                    $percent,
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

                </div>

            </a>

        <?php endforeach; ?>

    </div>

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
.student-topic-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.student-topic-card {
    padding: 18px;
    color: inherit;
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px;
    text-decoration: none;
    transition:
        transform 160ms ease,
        border-color 160ms ease,
        background 160ms ease;
}

.student-topic-card:hover {
    color: inherit;
    background: rgba(255,255,255,.04);
    border-color: rgba(61,156,255,.30);
    transform: translateY(-2px);
}

.student-topic-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.student-topic-card-top > span {
    opacity: .5;
    font-size: 11px;
}

.student-topic-icon {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #65aff9;
    background: rgba(61,156,255,.10);
    border: 1px solid rgba(61,156,255,.17);
    border-radius: 9px;
}

.student-topic-card h2 {
    margin: 15px 0 14px;
    font-size: 15px;
}

.student-topic-progress {
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-topic-progress-bar {
    height: 6px;
    flex: 1;
    overflow: hidden;
    background: rgba(255,255,255,.08);
    border-radius: 99px;
}

.student-topic-progress-bar span {
    display: block;
    height: 100%;
    background: #3d9cff;
    border-radius: inherit;
}

.student-topic-progress strong {
    min-width: 38px;
    text-align: right;
    font-size: 11px;
    opacity: .65;
}

@media (max-width: 700px) {
    .student-topic-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>
