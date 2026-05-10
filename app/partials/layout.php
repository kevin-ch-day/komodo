<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */
/** @var string $current_page */
/** @var string $doc_title_short */
/** @var string $komodo_main_html */

$pageDesc = 'Komodo v'
    . KOMODO_APP_VERSION
    . ' — read-only cybersecurity–finance research portal for MariaDB telemetry, event-study preparation, price import readiness, QA views, and pipeline gaps. Not for trading or investment advice. Local development / research use.';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= komodo_e($pageDesc) ?>">
    <meta name="theme-color" content="#16151b">
    <title><?= komodo_e('Komodo v' . KOMODO_APP_VERSION . ' · ' . $doc_title_short) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        document.documentElement.classList.add('js');
    </script>
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
    <script src="../assets/js/sidebar-nav.js" defer></script>
</body>
</html>
