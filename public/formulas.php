<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$pageTitle = 'Formulalar varaqasi';

require_once __DIR__ . '/../layout/header.php';

?>
<link rel="stylesheet" href="assets/css/style.css">
<section class="page-section formulas-page">
    <a href="dashboard.php" class="page-back">
        <span class="page-back-icon">←</span>
        <span>Orqaga</span>
    </a>
    <div class="page-heading">

        <h1 class="page-title">
            Formulalar varaqasi
        </h1>

        <p class="page-description">
            Fizika formulalari.
        </p>

    </div>


    <div class="formulas-content">

        <!-- Formulas will be loaded here -->

    </div>

</section>


<?php

require_once __DIR__ . '/../layout/footer.php';

?>