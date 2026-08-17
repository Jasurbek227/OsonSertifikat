<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$pageTitle = 'Mavzulardan savollar';

require_once __DIR__ . '/../layout/header.php';

?>
<link rel="stylesheet" href="assets/css/style.css">
<section class="page-section topics-page">
    <a href="dashboard.php" class="page-back">
        <span class="page-back-icon">←</span>
        <span>Orqaga</span>
    </a>
    <div class="page-heading">

        <h1 class="page-title">
            Mavzulardan savollar
        </h1>

        <p class="page-description">
            Mavzuni tanlang.
        </p>

    </div>


    <div class="topics-grid">

        <!-- Topics will be loaded here -->

    </div>

</section>


<?php

require_once __DIR__ . '/../layout/footer.php';

?>