<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAuth();

require_once __DIR__ . '/../includes/db.php';

$pageTitle = '20 talik savollar';

$blocks = array();

$query = "
    SELECT
        b.id,
        b.name,
        b.generation,
        b.description,
        COUNT(bq.question_id) AS question_count
    FROM blocks b
    LEFT JOIN block_questions bq
        ON bq.block_id = b.id
    WHERE b.is_active = 1
    GROUP BY
        b.id,
        b.name,
        b.generation,
        b.description
    ORDER BY b.id ASC
";

$result = mysqli_query($conn, $query);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {
        $blocks[] = $row;
    }
}

require_once __DIR__ . '/../layout/header.php';

?>
<link rel="stylesheet" href="assets/css/style.css">
<section class="page-section blocks-page">

    <a href="dashboard.php" class="page-back">

        <span class="page-back-icon">
            ←
        </span>

        <span>
            Orqaga
        </span>

    </a>


    <div class="page-heading">

        <h1 class="page-title">
            20 talik savollar
        </h1>

        <p class="page-description">
            Aralash savollarni yeching
        </p>

    </div>


    <?php if (count($blocks) > 0): ?>

        <div class="blocks-grid">

            <?php foreach ($blocks as $block): ?>

                <a href="block.php?id=<?php echo (int) $block['id']; ?>" class="block-card">

                    <div class="block-card-icon">

                        <i data-lucide="clipboard-list"></i>

                    </div>


                    <div class="block-card-content">

                        <h3 class="block-card-title">

                            <?php
                            echo htmlspecialchars(
                                $block['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </h3>


                        <?php if (!empty($block['description'])): ?>

                            <p class="block-card-description">

                                <?php
                                echo htmlspecialchars(
                                    $block['description'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            </p>

                        <?php endif; ?>


                        <div class="block-card-meta">

                            <span>
                                <?php
                                echo (int) $block['question_count'];
                                ?>
                                ta savol
                            </span>

                            <span>
                                Blok <?php echo (int) $block['id']; ?>
                            </span>

                        </div>

                    </div>


                    <div class="block-card-arrow">

                        <i data-lucide="arrow-right"></i>

                    </div>

                </a>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="blocks-content">

            <div class="blocks-empty">

                <div class="blocks-empty-icon">

                    <i data-lucide="clipboard-list"></i>

                </div>

                <h3>
                    Hozircha bloklar mavjud emas
                </h3>

                <p>
                    Yangi savollar bloklari tez orada qo‘shiladi.
                </p>

            </div>

        </div>

    <?php endif; ?>

</section>


<script src="https://unpkg.com/lucide@latest"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

    });
</script>


<?php

require_once __DIR__ . '/../layout/footer.php';

?>