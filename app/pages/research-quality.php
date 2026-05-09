<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$offlineMode = $ctx['offline_mode'];
$liveMerged = $ctx['live_merged'];
$researchViews = $ctx['research_views'];

?>
<section class="panel shell-section" aria-labelledby="rq-heading">
    <h2 id="rq-heading">Research quality</h2>
    <p class="section-lead">Research quality and edge-case diagnostics (read-only). Primary labels are analyst-facing; technical view names stay in secondary metadata for source provenance and audit traceability.</p>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Diagnostic</th>
                    <th scope="col" class="num">Rows</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($researchViews as $viewKey) {
                    $ref = KOMODO_OFFLINE_VIEW_REFERENCE[$viewKey] ?? null;
                    $primary = komodo_label($viewKey, 'db_object');
                    $desc = komodo_describe($viewKey, 'db_object');
                    ?>
                    <tr>
                        <td>
                            <div class="label-stack">
                                <span class="label-primary"><?= komodo_e($primary) ?></span>
                                <?php if ($desc !== null) { ?>
                                    <span class="label-desc"><?= komodo_e($desc) ?></span>
                                <?php } ?>
                                <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($viewKey) ?></code></span>
                            </div>
                        </td>
                        <td class="num"><?php echo komodo_metric_html($offlineMode, $liveMerged, $viewKey, $ref); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
