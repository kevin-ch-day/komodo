<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$md = $ctx['market_data'] ?? [
    'available' => false,
    'partial' => false,
    'security_summary' => null,
    'index_summary' => null,
    'readiness_conclusion' => null,
    'price_import_readiness' => null,
];

$ss = $md['security_summary'];
$is = $md['index_summary'];
$byRole = is_array($ss) && isset($ss['by_role']) && is_array($ss['by_role']) ? $ss['by_role'] : [];
/** @var array<string, mixed>|null $rc */
$rc = is_array($md['readiness_conclusion'] ?? null) ? $md['readiness_conclusion'] : null;
/** @var array<string, mixed>|null $pir */
$pir = is_array($md['price_import_readiness'] ?? null) ? $md['price_import_readiness'] : null;

$elBucket = isset($byRole['event_linked_security']) && is_array($byRole['event_linked_security'])
    ? $byRole['event_linked_security']
    : [];

?>
<section class="panel shell-section price-coverage-page" aria-labelledby="pcov-heading">
    <h2 id="pcov-heading">Price coverage</h2>
    <p class="section-lead">Readiness summary for event-study prep: <strong>are we close to ready, and what is blocking?</strong> This page stays short. Use <a class="footer-top-link" href="index.php?page=price-import-queue">Price import triage</a> for the action worklist and <a class="footer-top-link" href="index.php?page=price-audit">Price audit</a> for the full <code class="inline-code">vw_market_data_import_plan</code> table, role breakdown, indexes, lineage, aligned density, and technical row counts.</p>

    <nav class="market-md-related" aria-label="Related pages">
        <span class="compact-note">Related:</span>
        <a class="footer-top-link" href="index.php?page=market-data">Market Data</a>
        <span class="market-md-related-sep" aria-hidden="true">·</span>
        <a class="footer-top-link" href="index.php?page=price-import-queue">Price import triage</a>
        <span class="market-md-related-sep" aria-hidden="true">·</span>
        <a class="footer-top-link" href="index.php?page=price-audit">Price audit</a>
    </nav>

    <?php if (!$md['available']) { ?>
        <p class="env-note env-note--warn" role="status">Readiness telemetry is hidden until the database is connected.</p>
    <?php } else { ?>

        <?php if ($rc !== null) { ?>
            <aside class="panel-nested panel-phase--inset market-readiness-conclusion" role="region" aria-labelledby="pcov-conclusion-heading">
                <h3 id="pcov-conclusion-heading" class="subsection-heading subsection-heading-tight">Current conclusion</h3>
                <p class="section-lead market-readiness-conclusion__body"><?= komodo_e((string) ($rc['paragraph'] ?? '')) ?></p>
            </aside>
        <?php } ?>

        <?php
        $komodo_pir = $pir;
        require __DIR__ . '/../partials/price_import_readiness_section.php';
        ?>

        <aside class="panel-nested panel-muted market-dq-warnings" role="note" aria-labelledby="pcov-dq-heading">
            <h3 id="pcov-dq-heading" class="subsection-heading subsection-heading-tight">Data quality warnings</h3>
            <ul class="compact-note market-dq-warnings__list">
                <li>Benchmark rows may be present, but <strong>daily</strong> trading-day completeness must be reviewed before event-study calculations.</li>
                <li>Many current CSV sources appear weekly, not daily.</li>
                <li><code class="inline-code">security_daily_prices</code> should hold <strong>daily</strong> trading-day bars only — do not import weekly files into that table. <strong>First and last bar dates alone do not prove daily density</strong> — a series can still be weekly or gapped between those dates.</li>
                <li>Window span checks use ±<?= (int) KOMODO_TRIAGE_WINDOW_SLACK_DAYS ?> calendar-day <strong>slack at each end</strong> of the suggested range; that is separate from <a class="footer-top-link" href="index.php?page=price-audit#audit-aligned-density-heading"><?= komodo_e(KOMODO_ALIGNED_DAILY_DENSITY_LABEL) ?></a> on Price audit.</li>
                <li><strong>FB / META:</strong> Event-linked <code class="inline-code">FB</code> can be window-complete in the plan; the ongoing concern is <strong>source-label lineage</strong> (not missing FB prices by default). <?= komodo_e(komodo_fb_meta_lineage_import_policy_paragraph()) ?></li>
            </ul>
        </aside>

        <section class="panel-nested panel-phase--inset" aria-labelledby="pcov-snapshot-heading">
            <h3 id="pcov-snapshot-heading" class="subsection-heading subsection-heading-tight">Event-linked &amp; benchmark snapshot</h3>
            <?php if (!is_array($ss)) { ?>
                <p class="compact-note env-note env-note--warn" role="status">Security summary unavailable.</p>
            <?php } else { ?>
                <p class="section-lead"><strong>Event-linked securities:</strong> <?php
                if ($rc !== null) {
                    echo komodo_e(
                        (string) $rc['event_linked_covers_window']
                        . ' of '
                        . (string) $rc['event_linked_total']
                        . ' cover the suggested import window (±'
                        . (string) KOMODO_TRIAGE_WINDOW_SLACK_DAYS
                        . ' calendar-day slack at each end).'
                    );
                } else {
                    $cov = (string) ($elBucket['covers_suggested_window'] ?? '—');
                    $tot = (string) ($elBucket['total'] ?? '—');
                    echo komodo_e($cov . ' of ' . $tot . ' cover the suggested window (telemetry).');
                }
                ?></p>
            <?php } ?>
            <?php if (!is_array($is)) { ?>
                <p class="compact-note env-note env-note--warn" role="status">Index summary unavailable.</p>
            <?php } else {
                $idxPx = (int) ($is['indexes_with_any_prices'] ?? 0);
                $idxTot = (int) ($is['total_indexes'] ?? 0);
                $idxBars = (int) ($is['total_index_price_rows'] ?? 0);
                ?>
                <p class="section-lead"><strong>Benchmark indexes:</strong> <?= komodo_e((string) $idxPx . ' of ' . (string) $idxTot . ' configured indexes have price rows') ?><?php
                if ($idxBars > 0) {
                    echo komodo_e(
                        ' (' . number_format($idxBars) . ' loaded bars, span '
                        . (string) ($is['first_index_price_date'] ?? '—')
                        . ' → '
                        . (string) ($is['last_index_price_date'] ?? '—')
                        . ').'
                    );
                } else {
                    echo komodo_e('.');
                }
                ?> Treat loaded benchmarks as <strong>pipeline validation</strong> until you confirm dense <strong>daily</strong> trading-day data — see the index table on <a class="footer-top-link" href="index.php?page=price-audit#index-coverage">Price audit</a>.</p>
            <?php } ?>
        </section>

        <p class="compact-note" role="navigation">Work queue: <a class="footer-top-link" href="index.php?page=price-import-queue">Price import triage</a>. Full audit tables &amp; diagnostics: <a class="footer-top-link" href="index.php?page=price-audit">Price audit</a>.</p>

        <?php if ($md['partial'] && ($md['errors'] ?? []) !== []) { ?>
            <ul class="compact-note env-note env-note--warn" role="list">
                <?php foreach ($md['errors'] as $err) { ?>
                    <li><?= komodo_e((string) $err) ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

    <?php } ?>
</section>
