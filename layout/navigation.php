<nav class="main-navigation">

    <div class="navigation-container">

        <a
            href="dashboard.php"
            class="site-logo"
        >
            Oson Sertifikat
        </a>


        <div class="navigation-links">

            <a
                href="dashboard.php"
                class="navigation-link"
            >
                Bosh sahifa
            </a>

            <a
                href="progress.php"
                class="navigation-link"
            >
                Progress
            </a>

            <a
                href="blocks.php"
                class="navigation-link"
            >
                Bloklar
            </a>

            <a
                href="exam.php"
                class="navigation-link"
            >
                Milliy sertifikat
            </a>

            <a
                href="mistakes.php"
                class="navigation-link"
            >
                Xatolar
            </a>

            <a
                href="readiness.php"
                class="navigation-link"
            >
                Imtihonga tayyormanmi?
            </a>

        </div>


        <div class="navigation-user">

            <a
                href="profile.php"
                class="user-profile-link"
            >
                <?= htmlspecialchars($_SESSION['username'] ?? '') ?>
            </a>

            <a
                href="logout.php"
                class="logout-link"
            >
                Chiqish
            </a>

        </div>

    </div>

</nav>