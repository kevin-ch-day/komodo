<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$offlineMode = $ctx['offline_mode'];
$liveMerged = $ctx['live_merged'];
$researchViews = $ctx['research_views'];

?>
<section class="panel shell-section" aria-labelledby="rq-heading">
    <h2 id="rq-heading">Research quality</h2>
    <p class="section-lead">QA and edge-case diagnostic views.</p>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">View</th>
                    <th scope="col" class="num">Rows</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($researchViews as $viewKey) {
                    $ref = KOMODO_OFFLINE_VIEW_REFERENCE[$viewKey] ?? null;
                    ?>
                    <tr>
                        <td><code class="inline-code"><?= komodo_e($viewKey) ?></code></td>
                        <td class="num"><?php echo komodo_metric_html($offlineMode, $liveMerged, $viewKey, $ref); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
