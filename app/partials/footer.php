<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

?>
<footer class="site-footer site-footer--shell">
    <p>Komodo v<?= komodo_e(KOMODO_APP_VERSION) ?> · read-only · no INSERT / UPDATE / DELETE from this dashboard.</p>
    <p class="footer-meta">Page generated <time datetime="<?= komodo_e($ctx['page_generated_atom']) ?>"><?= komodo_e($ctx['page_generated_human']) ?></time> · <a class="footer-top-link" href="index.php?page=dashboard">Back to dashboard</a></p>
</footer>
