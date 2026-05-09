<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$offlineMode = $ctx['offline_mode'];
$liveMerged = $ctx['live_merged'];
$marketKpis = $ctx['market_kpis'];

?>
<section class="panel shell-section" aria-labelledby="market-heading">
    <h2 id="market-heading">Market Data</h2>
    <p class="section-lead">Import targets, calendars, and daily price warehouses.</p>
    <ul class="kpi-row">
        <?php foreach ($marketKpis as $key) {
            $ref = KOMODO_OFFLINE_TABLE_REFERENCE[$key] ?? (KOMODO_OFFLINE_VIEW_REFERENCE[$key] ?? null);
            $mb = komodo_metric_badge($offlineMode, $liveMerged, $key);
            ?>
            <li class="kpi">
                <div class="kpi-meta">
                    <span class="kpi-label"><code class="inline-code"><?= komodo_e($key) ?></code></span>
                    <span class="<?= komodo_e($mb['class']) ?>"><?= komodo_e($mb['text']) ?></span>
                </div>
                <span class="kpi-value"><?php echo komodo_metric_html($offlineMode, $liveMerged, $key, $ref); ?></span>
                <span class="kpi-hint"><?= $offlineMode ? 'placeholder' : 'rows' ?></span>
            </li>
        <?php } ?>
    </ul>
</section>
