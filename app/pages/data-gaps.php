<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$offlineMode = (bool) $ctx['offline_mode'];
/** @var array<string, mixed> */
$md = $ctx['market_data'] ?? [];
/** @var array<string, array{identifier: string, count: ?int, status: string}> */
$tableSafe = is_array($ctx['table_counts_safe'] ?? null) ? $ctx['table_counts_safe'] : [];

/** @var array<string, mixed> */
$dg = komodo_build_data_gaps_view_model($md, $offlineMode, $tableSafe);

$readinessShort = null;
if (is_array($md['readiness_conclusion'] ?? null)) {
    $rc = $md['readiness_conclusion'];
    $readinessShort = isset($rc['short_paragraph']) ? (string) $rc['short_paragraph'] : null;
}

$severityClass = static function (string $sev): string {
    return match ($sev) {
        'blocking_now' => 'severity-pill--blocking',
        'needs_review' => 'severity-pill--review',
        'expected_later' => 'severity-pill--expected',
        default => 'severity-pill--info',
    };
};

$ps = $dg['price_summary'];
/** @var array{blocking_now: int, needs_review: int, expected_later: int, informational: int} */
$tally = $dg['severity_tally'];
$cp = $dg['coverage_progress'];
$nextSteps = is_array($dg['next_steps'] ?? null) ? $dg['next_steps'] : [];
$ro = is_array($dg['readiness_overall'] ?? null) ? $dg['readiness_overall'] : null;
$insightsHeadline = (string) ($dg['insights_headline'] ?? '');
$triageOpen = (int) ($dg['triage_open_total'] ?? 0);
$progressPct = $cp['pct'] ?? null;
$progressWidth = $progressPct !== null ? max(0, min(100, $progressPct)) : 0;

?>
<section class="panel shell-section data-gaps-page" aria-labelledby="dg-heading">
    <h2 id="dg-heading">Data gaps</h2>
    <p class="section-lead">Readiness gaps that still block event-study analysis. This page summarizes missing price coverage, incomplete windows, ticker-lineage issues, benchmark coverage concerns, and expected empty analysis-output tables.</p>

    <nav class="data-gaps-jump" aria-label="On this page">
        <span class="data-gaps-jump__label">On this page:</span>
        <a class="data-gaps-jump__link" href="#dg-glance">At a glance</a>
        <?php if ($dg['telemetry_ok'] && (int) $cp['planned'] > 0 && $progressPct !== null) { ?>
            <span class="data-gaps-jump__sep" aria-hidden="true">·</span>
            <a class="data-gaps-jump__link" href="#dg-progress">Window coverage</a>
        <?php } ?>
        <span class="data-gaps-jump__sep" aria-hidden="true">·</span>
        <a class="data-gaps-jump__link" href="#dg-next">Next steps</a>
        <span class="data-gaps-jump__sep" aria-hidden="true">·</span>
        <a class="data-gaps-jump__link" href="#dg-narrative">Narrative</a>
        <span class="data-gaps-jump__sep" aria-hidden="true">·</span>
        <a class="data-gaps-jump__link" href="#dg-cards">Gap cards</a>
        <span class="data-gaps-jump__sep" aria-hidden="true">·</span>
        <a class="data-gaps-jump__link" href="#dg-price">Price table</a>
        <span class="data-gaps-jump__sep" aria-hidden="true">·</span>
        <a class="data-gaps-jump__link" href="#dg-lineage">Lineage</a>
        <span class="data-gaps-jump__sep" aria-hidden="true">·</span>
        <a class="data-gaps-jump__link" href="#dg-tech-collapsible">Technical</a>
    </nav>

    <section id="dg-glance" class="data-gaps-at-a-glance panel-nested panel-phase--inset" aria-labelledby="dg-glance-label">
        <h3 id="dg-glance-label" class="subsection-heading subsection-heading-tight">At a glance</h3>
        <?php if ($dg['telemetry_ok']) { ?>
            <div class="data-gaps-glance-head">
                <?php if ($ro !== null) { ?>
                    <span class="coverage-badge <?= komodo_e((string) ($ro['badge_class'] ?? 'coverage-badge--partial')) ?>"><?= komodo_e((string) ($ro['label'] ?? 'Status')) ?></span>
                <?php } ?>
                <?php if ($insightsHeadline !== '') { ?>
                    <p class="data-gaps-glance-headline"><?= komodo_e($insightsHeadline) ?></p>
                <?php } else { ?>
                    <p class="data-gaps-glance-headline compact-note">Coverage snapshot from the same telemetry as Market Data.</p>
                <?php } ?>
            </div>
            <div class="data-gaps-tally-grid" role="group" aria-label="Gap severity counts">
                <div class="data-gaps-tally data-gaps-tally--blocking">
                    <span class="data-gaps-tally__value"><?= (int) $tally['blocking_now'] ?></span>
                    <span class="data-gaps-tally__label">Blocking now</span>
                </div>
                <div class="data-gaps-tally data-gaps-tally--review">
                    <span class="data-gaps-tally__value"><?= (int) $tally['needs_review'] ?></span>
                    <span class="data-gaps-tally__label">Needs review</span>
                </div>
                <div class="data-gaps-tally data-gaps-tally--expected">
                    <span class="data-gaps-tally__value"><?= (int) $tally['expected_later'] ?></span>
                    <span class="data-gaps-tally__label">Expected later</span>
                </div>
                <div class="data-gaps-tally data-gaps-tally--info">
                    <span class="data-gaps-tally__value"><?= (int) $tally['informational'] ?></span>
                    <span class="data-gaps-tally__label">Informational</span>
                </div>
            </div>
            <p class="compact-note data-gaps-triage-hint">
                <strong><?= (int) $triageOpen ?></strong> open triage item(s) (needs price, window gaps, lineage, special notes) —
                <a class="footer-top-link" href="index.php?page=price-import-queue">open triage</a>.
                <?php if ((int) $tally['blocking_now'] === 0 && (int) $ps['planned'] > 0) { ?>
                    <span class="data-gaps-quiet-ok">No gap cards flagged as blocking right now.</span>
                <?php } ?>
            </p>
        <?php } else { ?>
            <p class="env-note env-note--muted data-gaps-offline-note"><?= komodo_e($dg['conclusion']) ?></p>
        <?php } ?>
    </section>

    <?php if ($dg['telemetry_ok'] && (int) $cp['planned'] > 0 && $progressPct !== null) { ?>
        <section id="dg-progress" class="data-gaps-progress panel-nested panel-phase--inset" aria-labelledby="dg-progress-label">
            <h3 id="dg-progress-label" class="subsection-heading subsection-heading-tight">Suggested window coverage</h3>
            <p class="compact-note">Share of plan rows that fully cover the suggested import window (±<?= (int) KOMODO_TRIAGE_WINDOW_SLACK_DAYS ?> calendar days slack at each end — only gaps <strong>larger than</strong> that slack count as incomplete), same rule as triage “complete”. Does not measure weekly/sparse density inside the window.</p>
            <div class="data-gaps-progress__meta">
                <span class="data-gaps-progress__frac"><?= (int) $cp['covers'] ?> / <?= (int) $cp['planned'] ?> plan rows</span>
                <span class="data-gaps-progress__pct"><?= (int) $progressPct ?>%</span>
            </div>
            <div class="data-gaps-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $progressWidth ?>" aria-labelledby="dg-progress-label">
                <div class="data-gaps-progress__fill" style="width: <?= (int) $progressWidth ?>%;"></div>
            </div>
        </section>
    <?php } ?>

    <section id="dg-next" class="data-gaps-next panel-nested panel-phase--inset" aria-labelledby="dg-next-label">
        <h3 id="dg-next-label" class="subsection-heading subsection-heading-tight">What to do next</h3>
        <p class="compact-note">Derived from the same readiness logic as Market Data Summary (next action + short checklist). Not a substitute for your import runbook.</p>
        <?php if ($nextSteps !== []) { ?>
            <ol class="data-gaps-next__list">
                <?php foreach ($nextSteps as $step) { ?>
                    <li><?= komodo_e($step) ?></li>
                <?php } ?>
            </ol>
        <?php } elseif ($dg['telemetry_ok']) { ?>
            <p class="data-gaps-next__empty compact-note">No scripted checklist lines right now. Use <a class="footer-top-link" href="index.php?page=price-import-queue">Price Worklist</a> for the prioritized queue or <a class="footer-top-link" href="index.php?page=market-data">Market Data Summary</a> for the short landing view.</p>
        <?php } else { ?>
            <p class="data-gaps-next__empty compact-note">Connect the database to sync this list with live readiness telemetry.</p>
        <?php } ?>
    </section>

    <section id="dg-narrative" class="panel-nested panel-phase--inset data-gaps-conclusion" aria-labelledby="dg-conclusion-label">
        <h3 id="dg-conclusion-label" class="subsection-heading subsection-heading-tight">Narrative</h3>
        <?php if ($dg['telemetry_partial']) { ?>
            <p class="compact-note"><span class="badge badge--degraded">Partial telemetry</span> Some market-data queries did not load fully — numbers below may undercount.</p>
        <?php } ?>
        <?php if ($dg['telemetry_ok']) { ?>
            <p class="data-gaps-conclusion__lead"><?= komodo_e($dg['conclusion']) ?></p>
        <?php } else { ?>
            <p class="compact-note">Use the <strong>At a glance</strong> section above when the database is offline. After connecting, this block mirrors the same narrative as before.</p>
        <?php } ?>
        <?php if ($readinessShort !== null && $readinessShort !== '' && ($dg['telemetry_ok'] ?? false)) { ?>
            <p class="compact-note data-gaps-conclusion__metrics"><?= komodo_e($readinessShort) ?></p>
        <?php } ?>
    </section>

    <section id="dg-cards" class="data-gaps-section" aria-labelledby="dg-cards-label">
        <h3 id="dg-cards-label" class="subsection-heading">Gap cards (priority order)</h3>
        <p class="compact-note">Sorted with <strong>blocking</strong> first, then review, then expected. Each card links to the page where you drill into rows.</p>
        <div class="data-gaps-card-grid" role="list">
            <?php foreach ($dg['blocking_cards'] as $card) {
                $accent = (string) ($card['accent'] ?? 'info');
                ?>
                <article class="data-gaps-card panel-nested data-gaps-card--accent-<?= komodo_e($accent) ?>" role="listitem">
                    <div class="data-gaps-card__head">
                        <span class="severity-pill <?= komodo_e($severityClass((string) $card['severity'])) ?>"><?= komodo_e((string) $card['severity_label']) ?></span>
                        <h4 class="data-gaps-card__title"><?= komodo_e((string) $card['title']) ?></h4>
                    </div>
                    <p class="data-gaps-card__count" aria-label="Metric"><?= komodo_e((string) $card['count_label']) ?></p>
                    <p class="data-gaps-card__dek compact-note"><?= komodo_e((string) $card['dek']) ?></p>
                    <p class="data-gaps-card__link"><a class="footer-top-link" href="<?= komodo_e((string) $card['href']) ?>">Open related page →</a></p>
                </article>
            <?php } ?>
        </div>
    </section>

    <section id="dg-price" class="data-gaps-section panel-nested panel-phase--inset" aria-labelledby="dg-price-label">
        <h3 id="dg-price-label" class="subsection-heading">Coverage gaps by ticker</h3>
        <p class="compact-note">Summary from <code class="inline-code">vw_market_data_import_plan</code> and <code class="inline-code">security_daily_prices</code> aggregates — not a full audit. Detail: <a class="footer-top-link" href="index.php?page=price-import-queue">Price Worklist</a>, <a class="footer-top-link" href="index.php?page=price-coverage">Coverage Summary</a> (readiness), <a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a> (tables).</p>
        <div class="table-scroll">
            <table class="data-table data-gaps-summary-table data-table--labeled-mobile">
                <thead>
                    <tr>
                        <th scope="col">Metric</th>
                        <th scope="col" class="numeric">Count</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="<?= (int) $ps['not_started'] > 0 ? 'data-table__highlight' : '' ?>">
                        <td data-label="Metric">Planned securities (import plan)</td>
                        <td class="numeric" data-label="Count"><?= (int) $ps['planned'] ?></td>
                    </tr>
                    <tr>
                        <td data-label="Metric">Securities with at least one price row</td>
                        <td class="numeric" data-label="Count"><?= (int) $ps['with_prices'] ?></td>
                    </tr>
                    <tr class="<?= (int) $ps['not_started'] > 0 ? 'data-table__highlight' : '' ?>">
                        <td data-label="Metric">Securities still not started (no price rows)</td>
                        <td class="numeric" data-label="Count"><?= (int) $ps['not_started'] ?></td>
                    </tr>
                    <tr class="<?= (int) $ps['event_linked_no_prices'] > 0 ? 'data-table__highlight' : '' ?>">
                        <td data-label="Metric">Event-linked with no price rows</td>
                        <td class="numeric" data-label="Count"><?= (int) $ps['event_linked_no_prices'] ?></td>
                    </tr>
                    <tr>
                        <td data-label="Metric">Event-linked with <code class="inline-code">not_started</code> status (role bucket)</td>
                        <td class="numeric" data-label="Count"><?= (int) $ps['event_linked_not_started_role'] ?></td>
                    </tr>
                    <tr class="<?= (int) $ps['window_incomplete'] > 0 ? 'data-table__highlight' : '' ?>">
                        <td data-label="Metric">Loaded but incomplete vs suggested window (triage window-gap bucket)</td>
                        <td class="numeric" data-label="Count"><?= (int) $ps['window_incomplete'] ?></td>
                    </tr>
                    <tr>
                        <td data-label="Metric">Plan rows covering suggested window (±<?= (int) KOMODO_TRIAGE_WINDOW_SLACK_DAYS ?>d slack)</td>
                        <td class="numeric" data-label="Count"><?= (int) $ps['covers_complete'] ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section id="dg-lineage" class="data-gaps-section panel-nested panel-phase--inset" aria-labelledby="dg-lineage-label">
        <h3 id="dg-lineage-label" class="subsection-heading">Historical ticker / lineage</h3>
        <div class="data-gaps-callout">
            <?php foreach ($dg['lineage']['paragraphs'] as $para) { ?>
                <p class="compact-note"><?= komodo_e($para) ?></p>
            <?php } ?>
        </div>
        <?php
        $hist = $dg['lineage']['historical_tickers'];
        if ($hist !== []) { ?>
            <p class="compact-note"><strong>Tickers in triage lineage bucket (sample):</strong> <?= komodo_e(implode(', ', $hist)) ?></p>
        <?php } ?>
        <?php
        $fb = $dg['lineage']['fb_row'];
        $meta = $dg['lineage']['meta_row'];
        if (is_array($fb) || is_array($meta)) { ?>
            <div class="table-scroll">
                <table class="data-table data-gaps-mini-table data-table--labeled-mobile">
                    <thead>
                        <tr>
                            <th scope="col">Ticker</th>
                            <th scope="col" class="numeric">Price rows</th>
                            <th scope="col">Coverage status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (is_array($fb)) { ?>
                            <tr>
                                <td data-label="Ticker"><code class="inline-code">FB</code></td>
                                <td class="numeric" data-label="Price rows"><?= (int) ($fb['price_rows'] ?? 0) ?></td>
                                <td data-label="Coverage status"><code class="inline-code"><?= komodo_e((string) ($fb['coverage_status'] ?? '')) ?></code></td>
                            </tr>
                        <?php } ?>
                        <?php if (is_array($meta)) { ?>
                            <tr>
                                <td data-label="Ticker"><code class="inline-code">META</code></td>
                                <td class="numeric" data-label="Price rows"><?= (int) ($meta['price_rows'] ?? 0) ?></td>
                                <td data-label="Coverage status"><code class="inline-code"><?= komodo_e((string) ($meta['coverage_status'] ?? '')) ?></code></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </section>

    <section class="data-gaps-section panel-nested panel-phase--inset" aria-labelledby="dg-benchmark-label">
        <h3 id="dg-benchmark-label" class="subsection-heading">Benchmark / market data quality</h3>
        <p class="compact-note"><?= komodo_e($dg['benchmark']['dek']) ?></p>
        <p class="compact-note"><strong>Live snapshot:</strong> <?= komodo_e($dg['benchmark']['index_headline']) ?></p>
        <p class="compact-note"><a class="footer-top-link" href="index.php?page=market-data">Market Data Summary</a> · <a class="footer-top-link" href="index.php?page=price-coverage">Coverage Summary</a> · <a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a></p>
    </section>

    <section class="data-gaps-section panel-nested panel-phase--inset" aria-labelledby="dg-es-label">
        <h3 id="dg-es-label" class="subsection-heading">Event-study outputs</h3>
        <p class="compact-note"><?= komodo_e($dg['event_study']['dek']) ?></p>
        <?php
        $er = $dg['event_study']['runs'];
        $es = $dg['event_study']['results'];
        ?>
        <ul class="compact-list compact-note" role="list">
            <li><code class="inline-code">event_study_runs</code>: <?= $er === null ? komodo_e('unavailable') : komodo_e((string) $er) ?> row(s)</li>
            <li><code class="inline-code">event_study_results</code>: <?= $es === null ? komodo_e('unavailable') : komodo_e((string) $es) ?> row(s)</li>
        </ul>
        <p class="compact-note">Classified as <span class="severity-pill severity-pill--expected">Expected later</span> — pending analysis phase, not a failure of imports.</p>
    </section>

    <details class="market-md-collapsible data-gaps-collapsible" id="dg-tech-collapsible">
        <summary>Technical zero-row checks (dashboard whitelist)</summary>
        <p class="compact-note">Lower-priority table counts. Expand only when you are reconciling against the dashboard metrics table.</p>
        <div class="table-scroll">
            <table class="data-table data-table--labeled-mobile">
                <thead>
                    <tr>
                        <th scope="col">Table</th>
                        <th scope="col">Detail</th>
                        <th scope="col">Severity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dg['technical'] as $row) { ?>
                        <tr>
                            <td data-label="Table"><code class="inline-code"><?= komodo_e($row['area']) ?></code></td>
                            <td data-label="Detail"><?= komodo_e($row['detail']) ?></td>
                            <td data-label="Severity"><span class="severity-pill <?= komodo_e($severityClass((string) $row['severity'])) ?>"><?= komodo_e($row['severity_label']) ?></span></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </details>
</section>
