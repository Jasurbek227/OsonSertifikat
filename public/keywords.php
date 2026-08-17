<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$pageTitle = 'Kalit so‘zlar';

require_once __DIR__ . '/../layout/header.php';

?>
<link rel="stylesheet" href="assets/css/style.css">
<section class="page-section keywords-page">
    <a href="dashboard.php" class="page-back">
        <span class="page-back-icon">←</span>
        <span>Orqaga</span>
    </a>
    <div class="page-heading">

        <h1 class="page-title">
            Kalit so‘zlar
        </h1>

        <p class="page-description">
            Fizikadagi muhim kalit so‘zlar.
        </p>

    </div>


    <div class="keywords-content">

        <!-- Keywords will be loaded here -->

    </div>

</section>


<?php

require_once __DIR__ . '/../layout/footer.php';

?>