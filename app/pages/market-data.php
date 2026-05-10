<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$offlineMode = $ctx['offline_mode'];
$liveMerged = $ctx['live_merged'];
/** @var array<string, mixed> */
$md = $ctx['market_data'] ?? [
    'available' => false,
    'partial' => false,
    'mode' => 'offline',
    'message' => 'Market data context was not loaded.',
    'errors' => [],
    'security_summary' => null,
    'index_summary' => null,
    'security_rows' => [],
    'index_rows' => [],
    'top_problem_securities' => [],
    'insights' => [
        'headline' => 'Market data context was not loaded.',
        'pct_securities_not_started' => null,
        'pct_covers_suggested_window' => null,
        'index_load_stage' => 'unknown',
        'next_step' => '',
        'checklist' => [],
    ],
    'notes_preview' => [],
    'price_import_readiness' => null,
    'queue_loaded_event_linked' => [],
    'queue_pending_event_linked' => [],
    'queue_loaded_comparison' => [],
    'queue_pending_comparison' => [],
    'queue_rows_with_import_notes' => [],
    'queue_securities_with_price_rows' => [],
    'readiness_conclusion' => null,
    'triage_needs_price' => [],
    'triage_needs_price_event_linked' => [],
    'triage_needs_price_comparison' => [],
    'triage_special_notes_event_linked' => [],
    'triage_special_notes_comparison' => [],
    'triage_next_batch_normal' => [],
    'triage_next_batch_older_history' => [],
    'triage_next_batch_special_source' => [],
    'triage_window_gaps' => [],
    'triage_historical_special' => [],
    'triage_special_notes' => [],
    'triage_dashboard' => [
        'open_total' => 0,
        'needs_count' => 0,
        'window_count' => 0,
        'historical_count' => 0,
        'special_notes_count' => 0,
        'completed_plan_rows' => 0,
        'plan_total' => 0,
        'covers_from_summary' => null,
    ],
];

$ss = $md['security_summary'];
/** @var array<string, mixed>|null $readinessConclusion */
$readinessConclusion = is_array($md['readiness_conclusion'] ?? null) ? $md['readiness_conclusion'] : null;
$is = $md['index_summary'];
$byRole = is_array($ss) && isset($ss['by_role']) && is_array($ss['by_role']) ? $ss['by_role'] : [];
/** @var array<string, mixed> $ins */
$ins = is_array($md['insights'] ?? null) ? $md['insights'] : [];
/** @var array<string, mixed>|null $pir */
$pir = is_array($md['price_import_readiness'] ?? null) ? $md['price_import_readiness'] : null;

?>
<section class="panel shell-section market-data-page" aria-labelledby="market-heading">
    <h2 id="market-heading">Market Data</h2>
    <p class="section-lead market-page-subtitle">High-level price import status. Use <a class="footer-top-link" href="index.php?page=price-import-queue">Price import triage</a> for the work list, <a class="footer-top-link" href="index.php?page=price-coverage">Price coverage</a> for the readiness summary, and <a class="footer-top-link" href="index.php?page=price-audit">Price audit</a> for full-plan tables and diagnostics.</p>

    <nav class="market-md-related" aria-label="Related pages">
        <span class="compact-note">Related:</span>
        <a class="footer-top-link" href="index.php?page=price-import-queue">Price import triage</a>
        <span class="market-md-related-sep" aria-hidden="true">·</span>
        <a class="footer-top-link" href="index.php?page=price-coverage">Price coverage</a>
        <span class="market-md-related-sep" aria-hidden="true">·</span>
        <a class="footer-top-link" href="index.php?page=price-audit">Price audit</a>
        <span class="market-md-related-sep" aria-hidden="true">·</span>
        <a class="footer-top-link" href="index.php?page=dataset">Dataset</a>
        <span class="market-md-related-sep" aria-hidden="true">·</span>
        <a class="footer-top-link" href="index.php?page=data-gaps">Data gaps</a>
        <span class="market-md-related-sep" aria-hidden="true">·</span>
        <a class="footer-top-link" href="index.php?page=pipeline">Pipeline</a>
    </nav>

    <section class="panel-nested panel-phase--inset market-data-overview" aria-labelledby="market-md-overview-label">
        <h3 id="market-md-overview-label" class="subsection-heading subsection-heading-tight">Overview</h3>
        <?php if (!$md['available']) { ?>
            <p class="section-lead">Connect <code class="inline-code">app/config/local.php</code> and MariaDB to evaluate coverage. Offline mode cannot load live telemetry.</p>
            <span class="badge badge--placeholder">Coverage offline</span>
        <?php } else { ?>
            <div class="market-md-mode-row">
                <?php if ($md['partial']) { ?>
                    <span class="badge badge--degraded"><?= komodo_e('Partial coverage load') ?></span>
                <?php } else { ?>
                    <span class="badge badge--ready"><?= komodo_e('Live coverage') ?></span>
                <?php } ?>
                <p class="section-lead market-md-mode-lead"><?= komodo_e($md['message']) ?></p>
            </div>
            <?php if ($md['errors'] !== []) { ?>
                <ul class="market-md-error-list compact-note" role="list">
                    <?php foreach ($md['errors'] as $err) { ?>
                        <li class="compact-note"><?= komodo_e($err) ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>
        <?php } ?>

        <div class="market-insight-bar" role="region" aria-label="Coverage snapshot">
            <p class="market-insight-bar__headline"><?= komodo_e((string) ($ins['headline'] ?? '')) ?></p>
            <?php if ($md['available'] && is_array($ss)) {
                $pns = $ins['pct_securities_not_started'] ?? null;
                $pcw = $ins['pct_covers_suggested_window'] ?? null;
                ?>
                <ul class="market-insight-metrics" aria-label="Coverage percentages">
                    <li><?php if ($pns !== null) {
                        ?><strong><?= komodo_e((string) $pns) ?>%</strong> securities not started<?php
                    } else {
                        ?>—<?php } ?></li>
                    <li><?php if ($pcw !== null) {
                        ?><strong><?= komodo_e((string) $pcw) ?>%</strong> fully cover suggested import window<?php
                    } else {
                        ?>—<?php } ?></li>
                    <li><span class="compact-note"><?= komodo_e('Tickers with special import notes: ')
                        ?><strong><?= komodo_e((string) ($ss['securities_with_import_notes'] ?? 0)) ?></strong></span></li>
                </ul>
            <?php } ?>
            <?php if (($ins['next_step'] ?? '') !== '') { ?>
                <p class="market-insight-bar__next"><strong>Next:</strong> <?= komodo_e((string) $ins['next_step']) ?></p>
            <?php } ?>
            <?php if (($ins['checklist'] ?? []) !== []) { ?>
                <ul class="market-insight-checklist compact-note">
                    <?php foreach ((array) $ins['checklist'] as $item) { ?>
                        <li><?= komodo_e((string) $item) ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>

        <?php if ($md['available'] && $readinessConclusion !== null) { ?>
            <aside class="panel-nested panel-phase--inset market-readiness-conclusion market-readiness-conclusion--compact" role="region" aria-labelledby="market-conclusion-heading">
                <h3 id="market-conclusion-heading" class="subsection-heading subsection-heading-tight">Current conclusion</h3>
                <p class="section-lead"><?= komodo_e((string) ($readinessConclusion['short_paragraph'] ?? '')) ?></p>
                <p class="compact-note">Readiness summary and what blocks event-study prep: <a class="footer-top-link" href="index.php?page=price-coverage">Price coverage</a>. Full <code class="inline-code">vw_market_data_import_plan</code> table, lineage, aligned density, technical counts: <a class="footer-top-link" href="index.php?page=price-audit">Price audit</a>.</p>
            </aside>
        <?php } ?>

        <?php if ($md['available'] && is_array($is) && (int) ($is['indexes_with_any_prices'] ?? 0) > 0) { ?>
            <aside class="panel-nested panel-muted market-benchmark-sparse-warning" role="note" aria-label="Benchmark coverage caveat">
                <p class="compact-note"><strong>Benchmark caveat:</strong> Benchmark rows are loaded for the indexes in scope, but <strong>daily trading-day coverage</strong> should be reviewed before event-study calculations. Calendar coverage may be sparse (e.g. weekly or monthly bars) — treat loaded benchmarks as <strong>pipeline validation</strong> until you confirm dense daily data.</p>
            </aside>
        <?php } ?>

        <details class="market-md-collapsible">
            <summary>Coverage status legend</summary>
            <dl class="market-legend-grid">
                <div><dt><span class="coverage-badge coverage-badge--not-started"><?= komodo_e(komodo_label('not_started', 'coverage_status')) ?></span></dt><dd><?= komodo_e(komodo_describe('not_started', 'coverage_status') ?? 'No price rows are loaded yet.') ?></dd></div>
                <div><dt><span class="coverage-badge coverage-badge--warning"><?= komodo_e(komodo_label('missing_start_window', 'coverage_status')) ?></span></dt><dd><?= komodo_e(komodo_describe('missing_start_window', 'coverage_status') ?? '') ?></dd></div>
                <div><dt><span class="coverage-badge coverage-badge--warning"><?= komodo_e(komodo_label('missing_end_window', 'coverage_status')) ?></span></dt><dd><?= komodo_e(komodo_describe('missing_end_window', 'coverage_status') ?? '') ?></dd></div>
                <div><dt><span class="coverage-badge coverage-badge--ok"><?= komodo_e(komodo_label('covers_suggested_window', 'coverage_status')) ?></span></dt><dd><?= komodo_e(komodo_describe('covers_suggested_window', 'coverage_status') ?? '') ?></dd></div>
                <div><dt><span class="coverage-badge coverage-badge--unknown"><?= komodo_e(komodo_label('has_prices_window_unknown', 'coverage_status')) ?></span></dt><dd><?= komodo_e(komodo_describe('has_prices_window_unknown', 'coverage_status') ?? '') ?></dd></div>
                <div><dt><span class="coverage-badge coverage-badge--partial"><?= komodo_e(komodo_label('partial', 'coverage_status')) ?></span></dt><dd><?= komodo_e(komodo_describe('partial', 'coverage_status') ?? '') ?></dd></div>
            </dl>
        </details>

        <h4 class="subsection-heading subsection-heading-tight">Suggested external load order</h4>
        <ol class="market-md-next-steps">
            <li>Load <strong>benchmark index</strong> prices into <code class="inline-code">index_daily_prices</code> outside Komodo (review bar frequency vs research needs).</li>
            <li>Load <strong>event-linked security</strong> prices into <code class="inline-code">security_daily_prices</code> — see <a class="footer-top-link" href="index.php?page=price-import-queue">Price import triage</a>.</li>
            <li>Load <strong>comparison / unlinked</strong> security prices.</li>
            <li>Use <a class="footer-top-link" href="index.php?page=price-audit">Price audit</a> for full-table QA after each batch; <a class="footer-top-link" href="index.php?page=price-coverage">Price coverage</a> for the readiness snapshot.</li>
        </ol>

        <?php
        $komodo_pir = $pir;
        require __DIR__ . '/../partials/price_import_readiness_section.php';
        ?>

        <?php if ($md['available'] && $ss) { ?>
            <h3 class="subsection-heading subsection-heading-tight" id="role-summary-mini">Security coverage by role (summary)</h3>
            <div class="table-scroll">
                <table class="data-table role-summary-table" aria-labelledby="role-summary-mini">
                    <thead>
                        <tr>
                            <th scope="col">Role</th>
                            <th scope="col" class="num">Total</th>
                            <th scope="col" class="num">Not started</th>
                            <th scope="col" class="num">Has prices</th>
                            <th scope="col" class="num">Covers window</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (['event_linked_security', 'comparison_or_unlinked_security'] as $rk) {
                            $b = isset($byRole[$rk]) && is_array($byRole[$rk]) ? $byRole[$rk] : [];
                            $rlabel = komodo_label($rk, 'role');
                            $rdesc = komodo_describe($rk, 'role');
                            ?>
                            <tr>
                                <th scope="row">
                                    <span class="label-primary"<?= $rdesc ? ' title="' . komodo_e($rdesc) . '"' : '' ?>><?= komodo_e($rlabel) ?></span>
                                </th>
                                <td class="num"><?= komodo_e((string) ($b['total'] ?? 0)) ?></td>
                                <td class="num"><?= komodo_e((string) ($b['not_started'] ?? 0)) ?></td>
                                <td class="num"><?= komodo_e((string) ($b['has_prices'] ?? 0)) ?></td>
                                <td class="num"><?= komodo_e((string) ($b['covers_suggested_window'] ?? 0)) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </section>
</section>
