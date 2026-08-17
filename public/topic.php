<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/student_engine.php';

$userId =
    (int)
    ($_SESSION['user_id'] ?? 0);

$topicId =
    (int)
    ($_GET['id'] ?? 0);

if ($topicId <= 0) {
    header('Location: topics.php');
    exit;
}

$topicResult =
    mysqli_query(
        $conn,
        "
        SELECT id, name
        FROM topics
        WHERE id = $topicId
          AND is_active = 1
        LIMIT 1
        "
    );

if (
    !$topicResult ||
    mysqli_num_rows($topicResult) === 0
) {
    header('Location: topics.php');
    exit;
}

$topic =
    mysqli_fetch_assoc(
        $topicResult
    );

$questions = [];

$result =
    mysqli_query(
        $conn,
        "
        SELECT
            q.id,
            q.question_type,
            q.text,
            q.is_new
        FROM questions q
        WHERE q.topic_id = $topicId
          AND q.is_active = 1
        ORDER BY q.id ASC
        "
    );

if ($result) {
    while (
        $row =
        mysqli_fetch_assoc($result)
    ) {
        $questions[] =
            $row;
    }
}

$total =
    count($questions);

$pageTitle =
    (string)
    $topic['name'];

require_once __DIR__ . '/../layout/header.php';

?>

<link rel="stylesheet" href="assets/css/style.css">

<section
    class="page-section topic-practice-page"
    data-topic-id="<?php
        echo $topicId;
    ?>"
>

    <a
        href="topics.php"
        class="page-back"
    >
        ← Mavzular
    </a>


    <div class="page-heading">

        <h1 class="page-title">
            <?php
            echo htmlspecialchars(
                $topic['name'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </h1>

    </div>


    <?php if ($total > 0): ?>

        <div class="topic-practice-intro">

            <span>
                <?php echo $total; ?> ta savol
            </span>

            <a
                href="topic.php?id=<?php
                    echo $topicId;
                ?>&start=1"
                class="topic-practice-start"
            >
                Boshlash
                <i data-lucide="arrow-right"></i>
            </a>

        </div>


        <div class="topic-question-list">

            <?php foreach (
                $questions as $index => $question
            ): ?>

                <a
                    href="block.php?topic=<?php
                        echo $topicId;
                    ?>&question=<?php
                        echo (int)
                        $question['id'];
                    ?>"
                    class="topic-question-row"
                >

                    <span class="topic-question-number">
                        <?php echo $index + 1; ?>
                    </span>

                    <span class="topic-question-text">
                        <?php
                        echo htmlspecialchars(
                            $question['text'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </span>

                    <i data-lucide="arrow-right"></i>

                </a>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="blocks-content">

            <div class="blocks-empty">

                <div class="blocks-empty-icon">
                    <i data-lucide="book-open"></i>
                </div>

                <h3>
                    Bu mavzuda savollar yo‘q
                </h3>

            </div>

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
.topic-practice-intro {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 14px;
    padding: 14px 16px;
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 10px;
}

.topic-practice-intro > span {
    opacity: .6;
    font-size: 12px;
}

.topic-practice-start {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 13px;
    color: #fff;
    background: #2f83df;
    border: 1px solid #4292ec;
    border-radius: 8px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
}

.topic-question-list {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.topic-question-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    color: inherit;
    background: rgba(255,255,255,.02);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 9px;
    text-decoration: none;
}

.topic-question-row:hover {
    color: inherit;
    background: rgba(255,255,255,.035);
    border-color: rgba(61,156,255,.30);
}

.topic-question-number {
    width: 30px;
    height: 30px;
    flex: 0 0 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #86c4fb;
    background: rgba(61,156,255,.09);
    border-radius: 7px;
    font-size: 11px;
    font-weight: 750;
}

.topic-question-text {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    font-size: 12px;
}

.topic-question-row > svg {
    width: 15px;
    height: 15px;
    opacity: .5;
}
</style>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>
