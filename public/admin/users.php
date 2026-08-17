<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/admin_auth.php';

requireAdmin();

$pageTitle = 'Foydalanuvchilar';


$search = trim(
    (string) ($_GET['search'] ?? '')
);


$conditions = array(
    '1 = 1'
);


if ($search !== '') {

    $safeSearch = mysqli_real_escape_string(
        $conn,
        $search
    );

    $conditions[] = "
        (
            username LIKE '%$safeSearch%'
            OR email LIKE '%$safeSearch%'
            OR id LIKE '%$safeSearch%'
        )
    ";
}


$where = implode(
    ' AND ',
    $conditions
);


$users = array();

$query = "
    SELECT
        id,
        username,
        email,
        avatar_path,
        created_at
    FROM users
    WHERE $where
    ORDER BY id DESC
    LIMIT 300
";


$result = mysqli_query(
    $conn,
    $query
);


if ($result) {

    while (
        $row = mysqli_fetch_assoc($result)
    ) {

        $users[] = $row;
    }
}

?>

<link rel="stylesheet" href="../assets/css/admin.css">


<section class="admin-page admin-content-page">

    <div class="admin-page-header">

        <div>

            <span class="admin-eyebrow">
                ADMIN PANEL
            </span>

            <h1 class="admin-page-title">
                Foydalanuvchilar
            </h1>

            <p class="admin-page-description">
                Ro‘yxatdan o‘tgan foydalanuvchilar
            </p>

        </div>

    </div>


    <form method="GET" class="admin-filter-card">

        <div class="admin-filter-grid">

            <div class="admin-form-field">

                <label>
                    Qidirish
                </label>

                <input type="text" name="search" value="<?php
                    echo htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>" placeholder="Login, email yoki ID">

            </div>


            <div class="admin-filter-button">

                <button type="submit" class="btn btn-outline-light">
                    <i data-lucide="search"></i>
                    Qidirish
                </button>

            </div>

        </div>

    </form>


    <div class="admin-table-card">

        <div class="table-responsive">

            <table class="table admin-table">

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Foydalanuvchi
                        </th>

                        <th>
                            Email
                        </th>

                        <th>
                            Ro‘yxatdan o‘tgan
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (
                        count($users) > 0
                    ): ?>

                    <?php foreach (
                            $users
                            as $user
                        ): ?>

                    <tr>

                        <td>

                            <span class="admin-table-id">
                                #
                                <?php
                                        echo (int)
                                            $user['id'];
                                        ?>
                            </span>

                        </td>


                        <td>

                            <div class="admin-user-cell">

                                <div class="admin-user-avatar">

                                    <?php if (
                                                !empty(
                                                    $user[
                                                        'avatar_path'
                                                    ]
                                                )
                                            ): ?>

                                    <img src="<?php
                                                    echo htmlspecialchars(
                                                        $user[
                                                            'avatar_path'
                                                        ],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );
                                                    ?>" alt="">

                                    <?php else: ?>

                                    <?php
                                                echo strtoupper(
                                                    mb_substr(
                                                        $user[
                                                            'username'
                                                        ],
                                                        0,
                                                        1
                                                    )
                                                );
                                                ?>

                                    <?php endif; ?>

                                </div>


                                <div>

                                    <strong>
                                        <?php
                                                echo htmlspecialchars(
                                                    $user[
                                                        'username'
                                                    ],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                    </strong>

                                </div>

                            </div>

                        </td>


                        <td>

                            <?php if (
                                        !empty(
                                            $user['email']
                                        )
                                    ): ?>

                            <span class="admin-table-muted">

                                <?php
                                            echo htmlspecialchars(
                                                $user['email'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                            </span>

                            <?php else: ?>

                            <span class="admin-table-muted">
                                —
                            </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <span class="admin-table-muted">

                                <?php
                                        echo htmlspecialchars(
                                            $user[
                                                'created_at'
                                            ],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                            </span>

                        </td>

                    </tr>

                    <?php endforeach; ?>

                    <?php else: ?>

                    <tr>

                        <td colspan="4" class="admin-table-empty">
                            Foydalanuvchilar topilmadi.
                        </td>

                    </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</section>


<script src="https://unpkg.com/lucide@latest"></script>

<script>
document.addEventListener(
    'DOMContentLoaded',
    function() {

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

    }
);
</script>
<script src="../assets/js/admin.js"></script>