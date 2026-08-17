<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$pageTitle = 'Xatolarni tuzatish';

require_once __DIR__ . '/../layout/header.php';

?>
<link rel="stylesheet" href="assets/css/style.css">
<section class="page-section mistakes-page">
    <a href="dashboard.php" class="page-back">
        <span class="page-back-icon">←</span>
        <span>Orqaga</span>
    </a>
    <div class="page-heading">

        <h1 class="page-title">
            Xatolarni tuzatish
        </h1>

        <p class="page-description">
            Noto‘g‘ri javob berilgan savollar.
        </p>

    </div>


    <div class="mistakes-content">

        <!-- Mistakes queue will be loaded here -->

    </div>

</section>


<?php

require_once __DIR__ . '/../layout/footer.php';

?>