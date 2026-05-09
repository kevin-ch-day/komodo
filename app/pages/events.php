<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$offlineMode = $ctx['offline_mode'];
$liveMerged = $ctx['live_merged'];
$tableCountsSafe = $ctx['table_counts_safe'];
$eventReadinessKpis = $ctx['event_readiness_kpis'];
$eventCoreTables = $ctx['event_core_tables'];

?>
<section class="panel shell-section" aria-labelledby="events-heading">
    <h2 id="events-heading">Events</h2>
    <p class="section-lead">Cyber event tables (<code class="inline-code">cyber_events</code> through bridge tables),
        readiness views (<code class="inline-code">vw_event_study_event_readiness</code>, <code class="inline-code">vw_event_window_boundaries</code>), and related <code class="inline-code">COUNT(*)</code> telemetry.</p>

    <h3 class="subsection-heading">Readiness views</h3>
    <ul class="kpi-row">
        <?php foreach ($eventReadinessKpis as $key) {
            $ref = KOMODO_OFFLINE_VIEW_REFERENCE[$key] ?? null;
            $eb = komodo_metric_badge($offlineMode, $liveMerged, $key);
            ?>
            <li class="kpi">
                <div class="kpi-meta">
                    <span class="kpi-label"><code class="inline-code"><?= komodo_e($key) ?></code></span>
                    <span class="<?= komodo_e($eb['class']) ?>"><?= komodo_e($eb['text']) ?></span>
                </div>
                <span class="kpi-value"><?php echo komodo_metric_html($offlineMode, $liveMerged, $key, $ref); ?></span>
                <span class="kpi-hint"><?= $offlineMode ? 'placeholder' : 'rows' ?></span>
            </li>
        <?php } ?>
    </ul>

    <h3 class="subsection-heading">Event tables</h3>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Table</th>
                    <th scope="col" class="num">Rows</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eventCoreTables as $et) {
                    $ref = KOMODO_OFFLINE_TABLE_REFERENCE[$et] ?? null;
                    ?>
                    <tr>
                        <td><code class="inline-code"><?= komodo_e($et) ?></code></td>
                        <td class="num"><?php echo komodo_metric_html($offlineMode, $tableCountsSafe, $et, $ref); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
