<?php

declare(strict_types=1);

$nfRouteKeys = array_keys(komodo_page_routes());
sort($nfRouteKeys, SORT_STRING);

?>
<section class="panel shell-section" aria-labelledby="nf-heading">
    <h2 id="nf-heading">Page not found</h2>
    <p class="section-lead">Unknown or unsupported <code class="inline-code">page</code> query value. Valid route keys: <?= komodo_e(implode(', ', $nfRouteKeys)) ?>.</p>
    <p><a class="footer-top-link" href="index.php?page=dashboard">Return to dashboard</a></p>
</section>
