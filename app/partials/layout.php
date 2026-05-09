<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */
/** @var string $current_page */
/** @var string $doc_title_short */
/** @var string $komodo_main_html */

$pageDesc = 'Komodo v'
    . KOMODO_APP_VERSION
    . ' — local read-only cyber–finance event-study control room for MariaDB readiness, imports, QA views, and pipeline gaps (work in progress).';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= komodo_e($pageDesc) ?>">
    <meta name="theme-color" content="#0c0f14">
    <title><?= komodo_e('Komodo v' . KOMODO_APP_VERSION . ' · ' . $doc_title_short) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="komodo-dash">
    <a class="skip-link" href="#main">Skip to content</a>

    <div class="app-shell">
        <?php require __DIR__ . '/sidebar.php'; ?>

        <div class="shell-main">
            <main id="main" class="main-column">
                <?= $komodo_main_html ?>
            </main>

            <?php require __DIR__ . '/footer.php'; ?>
        </div>
    </div>
</body>
</html>
