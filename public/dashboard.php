<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$pageTitle = 'Bosh sahifa';

require_once __DIR__ . '/../layout/header.php';

?>

<link rel="stylesheet" href="assets/css/style.css">

<section class="dashboard-page">

    <!-- Readiness -->

    <section class="dashboard-section readiness-section">

        <div class="readiness-card readiness-card-locked" id="readinessCard">

            <div class="readiness-card-icon">

                <i data-lucide="graduation-cap"></i>

            </div>

            <div class="readiness-card-content">

                <h3 class="readiness-title">
                    Sertifikat imtihoniga tayyormanmi?
                </h3>

                <p class="readiness-description">
                    Ochilmagan
                </p>

            </div>

        </div>

    </section>

    <!-- Total progress -->

    <section class="dashboard-section progress-section">

        <div class="section-header">

            <div class="section-heading">

                <h2 class="section-title">
                    Umumiy progress
                </h2>

            </div>

            <span class="progress-value">
                0%
            </span>

        </div>

        <div class="progress-card">

            <div class="progress-bar">

                <div class="progress-bar-fill" style="width: 0%;"></div>

            </div>

        </div>

    </section>

    <!-- Main training -->

    <section class="dashboard-section training-section">

        <div class="training-grid training-grid-primary">

            <!-- 20 question blocks -->

            <a href="blocks.php" class="training-card training-card-primary">

                <div class="training-card-icon">

                    <i data-lucide="clipboard-list"></i>

                </div>

                <div class="training-card-content">

                    <h3 class="training-card-title">
                        20 talik savollar
                    </h3>

                    <p class="training-card-description">
                        20 talik aralash savollar ro'yxati
                    </p>

                </div>

            </a>

            <!-- Questions by topic -->

            <a href="topics.php" class="training-card training-card-primary">

                <div class="training-card-icon">

                    <i data-lucide="book-open"></i>

                </div>

                <div class="training-card-content">

                    <h3 class="training-card-title">
                        Mavzulardan savollar
                    </h3>

                    <p class="training-card-description">
                        Tanlangan mavzudan masalalar yechish
                    </p>

                </div>

            </a>

            <!-- Competition -->

            <a href="competition.php" class="training-card training-card-primary">

                <div class="training-card-icon">

                    <i data-lucide="trophy"></i>

                </div>

                <div class="training-card-content">

                    <span class="training-card-label">
                        Bellashuv
                    </span>

                    <h3 class="training-card-title">
                        Bellashuv
                    </h3>

                    <p class="training-card-description">
                        Boshqalar bilan bellashing
                    </p>

                </div>

            </a>

        </div>

    </section>

    <!-- Secondary tools -->

    <section class="dashboard-section tools-section">

        <div class="training-grid training-grid-secondary">

            <!-- Keywords -->

            <a href="keywords.php" class="training-card training-card-secondary">

                <div class="training-card-icon">

                    <i data-lucide="key-round"></i>

                </div>

                <div class="training-card-content">

                    <h3 class="training-card-title">
                        Kalit so‘zlar
                    </h3>

                </div>

            </a>

            <!-- Formula sheet -->

            <a href="formulas.php" class="training-card training-card-secondary">

                <div class="training-card-icon">

                    <i data-lucide="sigma"></i>

                </div>

                <div class="training-card-content">

                    <h3 class="training-card-title">
                        Formulalar varaqasi
                    </h3>

                </div>

            </a>

            <!-- Mistakes -->

            <a href="mistakes.php" class="training-card training-card-secondary">

                <div class="training-card-icon">

                    <i data-lucide="circle-alert"></i>

                </div>

                <div class="training-card-content">

                    <h3 class="training-card-title">
                        Xatolarni tuzatish
                    </h3>

                </div>

            </a>

        </div>

    </section>

</section>

<!-- Readiness modal -->

<div class="modal-overlay" id="readinessModal" aria-hidden="true">

    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="readinessModalTitle">

        <button type="button" class="modal-close" id="readinessModalClose" aria-label="Yopish">

            <i data-lucide="x"></i>

        </button>

        <div class="modal-icon">

            <i data-lucide="graduation-cap"></i>

        </div>

        <h2 class="modal-title" id="readinessModalTitle">
            Sertifikat imtihoniga tayyormanmi?
        </h2>

        <p class="modal-description">
            Ushbu bo‘lim progress 90% ga yetganda ochiladi.
        </p>

        <div class="modal-progress">

            <div class="modal-progress-header">

                <span>
                    Hozirgi progress
                </span>

                <strong>
                    0%
                </strong>

            </div>

            <div class="modal-progress-bar">

                <div class="modal-progress-fill" style="width: 0%;"></div>

            </div>

        </div>

    </div>

</div>

<script src="https://unpkg.com/lucide@latest"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    const readinessCard =
        document.getElementById('readinessCard');

    const readinessModal =
        document.getElementById('readinessModal');

    const readinessModalClose =
        document.getElementById('readinessModalClose');

    if (
        !readinessCard ||
        !readinessModal ||
        !readinessModalClose
    ) {
        return;
    }

    function openReadinessModal() {

        readinessModal.classList.add('modal-open');

        readinessModal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add('modal-active');

    }

    function closeReadinessModal() {

        readinessModal.classList.remove('modal-open');

        readinessModal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.classList.remove('modal-active');

    }

    readinessCard.addEventListener(
        'click',
        openReadinessModal
    );

    readinessModalClose.addEventListener(
        'click',
        closeReadinessModal
    );

    readinessModal.addEventListener(
        'click',
        function(event) {

            if (event.target === readinessModal) {
                closeReadinessModal();
            }

        }
    );

    document.addEventListener(
        'keydown',
        function(event) {

            if (event.key === 'Escape') {
                closeReadinessModal();
            }

        }
    );

});
</script>

<?php

require_once __DIR__ . '/../layout/footer.php';

?>