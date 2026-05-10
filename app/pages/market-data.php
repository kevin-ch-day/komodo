<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

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
    'price_import_readiness' => null,
    'readiness_conclusion' => null,
    'triage_next_batch_special_source' => [],
];

$ss = $md['security_summary'];
/** @var array<string, mixed>|null $readinessConclusion */
$readinessConclusion = is_array($md['readiness_conclusion'] ?? null) ? $md['readiness_conclusion'] : null;
$is = $md['index_summary'];
$byRole = is_array($ss) && isset($ss['by_role']) && is_array($ss['by_role']) ? $ss['by_role'] : [];
/** @var array<string, mixed>|null $pir */
$pir = is_array($md['price_import_readiness'] ?? null) ? $md['price_import_readiness'] : null;

$blockers = $md['available'] ? komodo_market_summary_blocker_lines($md) : [];

$totalSec = 0;
$withPrices = 0;
$elTotal = 0;
$elSpan = 0;
$compTotal = 0;
$compSpan = 0;
$idxTotal = 0;
$idxWith = 0;
$notesCount = 0;
if ($md['available'] && is_array($ss)) {
    $totalSec = (int) ($ss['total_securities'] ?? 0);
    $withPrices = (int) ($ss['securities_with_any_prices'] ?? 0);
    $elB = isset($byRole['event_linked_security']) && is_array($byRole['event_linked_security']) ? $byRole['event_linked_security'] : [];
    $compB = isset($byRole['comparison_or_unlinked_security']) && is_array($byRole['comparison_or_unlinked_security']) ? $byRole['comparison_or_unlinked_security'] : [];
    $elTotal = (int) ($elB['total'] ?? 0);
    $elSpan = (int) ($elB['covers_suggested_window'] ?? 0);
    $compTotal = (int) ($compB['total'] ?? 0);
    $compSpan = (int) ($compB['covers_suggested_window'] ?? 0);
    $notesCount = $pir !== null ? (int) ($pir['notes_count'] ?? 0) : (int) ($ss['securities_with_import_notes'] ?? 0);
}
if ($md['available'] && is_array($is)) {
    $idxTotal = (int) ($is['total_indexes'] ?? 0);
    $idxWith = (int) ($is['indexes_with_any_prices'] ?? 0);
}

/** @var list<string> $statusBullets */
$statusBullets = [];
if ($md['available'] && is_array($ss)) {
    if ($readinessConclusion !== null) {
        $statusBullets[] = 'Price loading is partially complete.';
        $statusBullets[] = 'The dataset is not event-study ready yet.';
        if (is_array($is) && (int) ($is['indexes_with_any_prices'] ?? 0) > 0) {
            $statusBullets[] = 'Benchmark daily-density still needs review.';
        }
        $planned = (int) ($readinessConclusion['planned_securities'] ?? 0);
        $ns = (int) ($readinessConclusion['not_started'] ?? 0);
        $elT = (int) ($readinessConclusion['event_linked_total'] ?? 0);
        $elNC = (int) ($readinessConclusion['event_linked_not_window_complete'] ?? 0);
        if ($planned > 0) {
            $statusBullets[] = sprintf('%d of %d securities have no price rows.', $ns, $planned);
        }
        if ($elT > 0) {
            $statusBullets[] = sprintf('%d of %d event-linked securities still need price attention.', $elNC, $elT);
        }
    } else {
        $statusBullets[] = 'Price loading is partially complete.';
        $statusBullets[] = 'The dataset is not event-study ready yet.';
        if (is_array($is) && (int) ($is['indexes_with_any_prices'] ?? 0) > 0) {
            $statusBullets[] = 'Benchmark daily-density still needs review.';
        }
        $planned = (int) ($ss['total_securities'] ?? 0);
        $ns = (int) ($ss['not_started'] ?? 0);
        $elB = isset($byRole['event_linked_security']) && is_array($byRole['event_linked_security']) ? $byRole['event_linked_security'] : [];
        $elT = (int) ($ss['event_linked_securities'] ?? 0);
        $elCovers = (int) ($elB['covers_suggested_window'] ?? 0);
        $elNC = max(0, $elT - $elCovers);
        if ($planned > 0) {
            $statusBullets[] = sprintf('%d of %d securities have no price rows.', $ns, $planned);
        }
        if ($elT > 0) {
            $statusBullets[] = sprintf('%d of %d event-linked securities still need price attention.', $elNC, $elT);
        }
    }
}

?>
<section class="panel shell-section market-data-page" aria-labelledby="market-heading">
    <h2 id="market-heading">Market Data Summary</h2>
    <p class="section-lead market-page-subtitle">Short landing view: current state, main blockers, and where to work next. Detail stays on <a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a> and <a class="footer-top-link" href="index.php?page=price-coverage">Coverage Summary</a>.</p>

    <?php if (!$md['available']) { ?>
        <section class="panel-nested panel-phase--inset market-data-overview" aria-labelledby="market-md-offline-label">
            <h3 id="market-md-offline-label" class="subsection-heading subsection-heading-tight">Offline</h3>
            <p class="section-lead">Connect <code class="inline-code">app/config/local.php</code> and MariaDB to load live coverage. This page needs the same telemetry as the price tools.</p>
            <span class="badge badge--placeholder">Coverage offline</span>
        </section>
    <?php } else { ?>
        <section class="panel-nested panel-phase--inset market-data-overview" aria-labelledby="market-md-status-label">
            <h3 id="market-md-status-label" class="subsection-heading subsection-heading-tight">Current status</h3>
            <?php if ($md['partial']) { ?>
                <p class="compact-note"><span class="badge badge--degraded"><?= komodo_e('Partial coverage load') ?></span> <?= komodo_e($md['message']) ?></p>
            <?php } else { ?>
                <p class="compact-note"><span class="badge badge--ready"><?= komodo_e('Live coverage') ?></span> <?= komodo_e($md['message']) ?></p>
            <?php } ?>
            <?php if ($md['errors'] !== []) { ?>
                <ul class="market-md-error-list compact-note" role="list">
                    <?php foreach ($md['errors'] as $err) { ?>
                        <li class="compact-note"><?= komodo_e($err) ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>
            <?php if ($statusBullets !== []) { ?>
                <ul class="market-md-status-list" role="list">
                    <?php foreach ($statusBullets as $b) { ?>
                        <li><?= komodo_e($b) ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>

            <div class="market-summary-grid market-md-summary-cards" aria-label="Coverage snapshot">
                <article class="stat-card market-summary-card">
                    <h4 class="stat-card__title">Securities with prices</h4>
                    <p class="stat-card__value"><?= komodo_e((string) $withPrices . ' / ' . $totalSec) ?></p>
                </article>
                <article class="stat-card market-summary-card">
                    <h4 class="stat-card__title">Event-linked Span OK</h4>
                    <p class="stat-card__value"><?= komodo_e((string) $elSpan . ' / ' . $elTotal) ?></p>
                    <p class="compact-note stat-card__dek">Span vs suggested window (± slack) — not trading-day density.</p>
                </article>
                <article class="stat-card market-summary-card">
                    <h4 class="stat-card__title">Comparison / control Span OK</h4>
                    <p class="stat-card__value"><?= komodo_e((string) $compSpan . ' / ' . $compTotal) ?></p>
                </article>
                <article class="stat-card market-summary-card">
                    <h4 class="stat-card__title">Benchmark indexes with rows</h4>
                    <p class="stat-card__value"><?= komodo_e((string) $idxWith . ' / ' . $idxTotal) ?></p>
                    <?php if ($idxWith > 0) { ?>
                        <p class="compact-note stat-card__dek"><span class="badge badge--warning"><?= komodo_e('Daily density not verified') ?></span></p>
                    <?php } ?>
                </article>
                <article class="stat-card market-summary-card">
                    <h4 class="stat-card__title">Special import notes</h4>
                    <p class="stat-card__value"><?= komodo_e((string) $notesCount) ?></p>
                    <p class="compact-note stat-card__dek">Ticker-level notes on the plan — see Price Worklist.</p>
                </article>
            </div>

            <h3 class="subsection-heading subsection-heading-tight" id="market-md-blockers-label">Main blockers</h3>
            <?php if ($blockers !== []) { ?>
                <ul class="market-md-blockers-list" role="list" aria-labelledby="market-md-blockers-label">
                    <?php foreach ($blockers as $line) { ?>
                        <li><?= komodo_e($line) ?></li>
                    <?php } ?>
                </ul>
            <?php } else { ?>
                <p class="compact-note">No blocker lines computed — check telemetry or Price Audit.</p>
            <?php } ?>

            <h3 class="subsection-heading subsection-heading-tight" id="market-md-next-label">Where to go next</h3>
            <ul class="market-md-next-routes" role="list" aria-labelledby="market-md-next-label">
                <li><a class="footer-top-link" href="index.php?page=price-import-queue">Price Worklist</a> — CSV, download, and import actions.</li>
                <li><a class="footer-top-link" href="index.php?page=price-coverage">Coverage Summary</a> — short readiness snapshot.</li>
                <li><a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a> — full plan, lineage, aligned density, technical counts.</li>
            </ul>

            <details class="market-md-collapsible">
                <summary>Coverage status legend</summary>
                <dl class="market-legend-grid">
                    <div><dt><span class="coverage-badge coverage-badge--not-started"><?= komodo_e(komodo_label('not_started', 'coverage_status')) ?></span></dt><dd><?= komodo_e(komodo_describe('not_started', 'coverage_status') ?? 'No price rows are loaded yet.') ?></dd></div>
                    <div><dt><span class="coverage-badge coverage-badge--warning"><?= komodo_e(komodo_label('missing_start_window', 'coverage_status')) ?></span></dt><dd><?= komodo_e(komodo_describe('missing_start_window', 'coverage_status') ?? '') ?></dd></div>
                    <div><dt><span class="coverage-badge coverage-badge--warning"><?= komodo_e(komodo_label('missing_end_window', 'coverage_status')) ?></span></dt><dd><?= komodo_e(komodo_describe('missing_end_window', 'coverage_status') ?? '') ?></dd></div>
                    <div><dt><span class="coverage-badge coverage-badge--ok"><?= komodo_e(komodo_coverage_catalog_label('covers_suggested_window')) ?></span></dt><dd><?= komodo_e(komodo_describe('covers_suggested_window', 'coverage_status') ?? '') ?></dd></div>
                    <div><dt><span class="coverage-badge coverage-badge--unknown"><?= komodo_e(komodo_label('has_prices_window_unknown', 'coverage_status')) ?></span></dt><dd><?= komodo_e(komodo_describe('has_prices_window_unknown', 'coverage_status') ?? '') ?></dd></div>
                    <div><dt><span class="coverage-badge coverage-badge--partial"><?= komodo_e(komodo_label('partial', 'coverage_status')) ?></span></dt><dd><?= komodo_e(komodo_describe('partial', 'coverage_status') ?? '') ?></dd></div>
                </dl>
            </details>

            <details class="market-md-collapsible">
                <summary>Suggested external load order</summary>
                <ol class="market-md-next-steps">
                    <li>Load <strong>benchmark index</strong> prices into <code class="inline-code">index_daily_prices</code> outside Komodo (confirm bar frequency).</li>
                    <li>Load <strong>event-linked security</strong> prices into <code class="inline-code">security_daily_prices</code> — see <a class="footer-top-link" href="index.php?page=price-import-queue">Price Worklist</a>.</li>
                    <li>Load <strong>comparison / unlinked</strong> security prices.</li>
                    <li>Use <a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a> for full-table QA; <a class="footer-top-link" href="index.php?page=price-coverage">Coverage Summary</a> for readiness.</li>
                </ol>
            </details>
        </section>
    <?php } ?>
</section>
