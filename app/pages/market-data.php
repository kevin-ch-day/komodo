<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$offlineMode = $ctx['offline_mode'];
$liveMerged = $ctx['live_merged'];
$marketKpis = $ctx['market_kpis'];
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
    'data_sources' => [],
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
];

$formatDateCell = static function (?string $v): string {
    if ($v === null || $v === '') {
        return '—';
    }
    return komodo_e((string) $v);
};

$ss = $md['security_summary'];
$is = $md['index_summary'];
$byRole = is_array($ss) && isset($ss['by_role']) && is_array($ss['by_role']) ? $ss['by_role'] : [];
/** @var array<string, mixed> $ins */
$ins = is_array($md['insights'] ?? null) ? $md['insights'] : [];
/** @var array<string, mixed>|null $pir */
$pir = is_array($md['price_import_readiness'] ?? null) ? $md['price_import_readiness'] : null;

?>
<section class="panel shell-section market-data-page" aria-labelledby="market-heading">
    <h2 id="market-heading">Market Data</h2>

    <nav class="market-md-related" aria-label="Related pages">
        <span class="compact-note">Related:</span>
        <a class="footer-top-link" href="index.php?page=dataset">Dataset</a>
        <span class="market-md-related-sep" aria-hidden="true">·</span>
        <a class="footer-top-link" href="index.php?page=data-gaps">Data gaps</a>
        <span class="market-md-related-sep" aria-hidden="true">·</span>
        <a class="footer-top-link" href="index.php?page=pipeline">Pipeline</a>
    </nav>

    <?php /* A. Overview */ ?>
    <section class="panel-nested panel-phase--inset market-data-overview" aria-labelledby="market-md-overview-label">
        <h3 id="market-md-overview-label" class="subsection-heading subsection-heading-tight">Market data and security price coverage</h3>
        <?php if (!$md['available']) { ?>
            <p class="section-lead">This read-only portal needs a configured <code class="inline-code">app/config/local.php</code> and a running MariaDB instance. Offline mode cannot evaluate per-security windows or benchmark indexes.</p>
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

        <?php /* KPI strip from whitelist counts */ ?>
        <details class="market-md-collapsible">
            <summary>Raw database object row counts (whitelist SELECT)</summary>
            <ul class="kpi-row market-kpi-row--nested">
                <?php foreach ($marketKpis as $key) {
                    $ref = KOMODO_OFFLINE_TABLE_REFERENCE[$key] ?? (KOMODO_OFFLINE_VIEW_REFERENCE[$key] ?? null);
                    $mb = komodo_metric_badge($offlineMode, $liveMerged, $key);
                    $objLabel = komodo_label($key, 'db_object');
                    $objDesc = komodo_describe($key, 'db_object');
                    ?>
                    <li class="kpi">
                        <div class="kpi-meta">
                            <span class="kpi-label"<?= $objDesc ? ' title="' . komodo_e($objDesc) . '"' : '' ?>>
                                <span class="label-primary"><?= komodo_e($objLabel) ?></span>
                                <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($key) ?></code></span>
                            </span>
                            <span class="<?= komodo_e($mb['class']) ?>"><?= komodo_e($mb['text']) ?></span>
                        </div>
                        <span class="kpi-value"><?php echo komodo_metric_html($offlineMode, $liveMerged, $key, $ref); ?></span>
                        <span class="kpi-hint"><?= $offlineMode ? 'placeholder' : 'rows' ?></span>
                    </li>
                <?php } ?>
            </ul>
        </details>

        <h4 class="subsection-heading subsection-heading-tight">Suggested external load order</h4>
        <ol class="market-md-next-steps">
            <li>Load <strong>benchmark index</strong> daily prices (e.g. into <code class="inline-code">index_daily_prices</code>) using your pipeline outside Komodo.</li>
            <li>Load <strong>event-linked security</strong> prices (e.g. into <code class="inline-code">security_daily_prices</code>) outside Komodo.</li>
            <li>Load <strong>comparison / unlinked security</strong> prices outside Komodo.</li>
            <li>Re-run this read-only page for coverage QA before event-study preparation steps elsewhere.</li>
        </ol>

        <section class="panel-nested panel-phase--inset price-import-readiness" aria-labelledby="price-readiness-heading">
            <h3 id="price-readiness-heading" class="subsection-heading subsection-heading-tight">Price import readiness</h3>
            <p class="section-lead price-import-readiness__lead"><strong>Price import readiness</strong> summarizes whether benchmark indexes and security price coverage support <strong>event-study preparation</strong>. Read-only guidance only — run all loads outside Komodo.</p>
            <?php if ($pir === null) { ?>
                <p class="compact-note" role="status">Price import readiness is unavailable until market data summaries load (database connection required).</p>
            <?php } else {
                /** @var array<string, mixed> $ov */
                $ov = $pir['overall'];
                /** @var array<string, mixed> $bm */
                $bm = $pir['benchmark'];
                /** @var array<string, mixed> $el */
                $el = $pir['event_linked'];
                /** @var array<string, mixed> $cp */
                $cp = $pir['comparison'];
                $notesCount = (int) ($pir['notes_count'] ?? 0);
                $nextAction = (string) ($pir['next_action'] ?? '');
                $techLine = static function (array $r): string {
                    $t = $r['technical'] ?? [];
                    if (!is_array($t) || $t === []) {
                        return '';
                    }
                    $codes = array_map(static fn ($c) => '<code class="inline-code inline-code--subtle">' . komodo_e((string) $c) . '</code>', $t);

                    return implode(' · ', $codes);
                };
                ?>
            <div class="price-readiness-overall">
                <span class="compact-note">Overall price import readiness (telemetry)</span>
                <span class="coverage-badge <?= komodo_e((string) ($ov['badge_class'] ?? 'coverage-badge--unknown')) ?>"><?= komodo_e((string) ($ov['label'] ?? '—')) ?></span>
            </div>

            <div class="market-summary-grid price-readiness-cards" aria-label="Price import readiness by area">
                <article class="stat-card market-summary-card price-readiness-card">
                    <h4 class="stat-card__title">Benchmark indexes</h4>
                    <p class="stat-card__value"><span class="coverage-badge <?= komodo_e((string) ($bm['badge_class'] ?? '')) ?>"><?= komodo_e((string) ($bm['label'] ?? '—')) ?></span></p>
                    <p class="compact-note stat-card__dek"><?= komodo_e((string) ($bm['dek'] ?? '')) ?></p>
                    <p class="compact-note price-readiness-card__tech"><?= $techLine($bm) ?></p>
                </article>
                <article class="stat-card market-summary-card price-readiness-card">
                    <h4 class="stat-card__title">Event-linked securities</h4>
                    <p class="stat-card__value"><span class="coverage-badge <?= komodo_e((string) ($el['badge_class'] ?? '')) ?>"><?= komodo_e((string) ($el['label'] ?? '—')) ?></span></p>
                    <p class="compact-note stat-card__dek"><?= komodo_e((string) ($el['dek'] ?? '')) ?></p>
                    <p class="compact-note price-readiness-card__tech"><?= $techLine($el) ?></p>
                </article>
                <article class="stat-card market-summary-card price-readiness-card">
                    <h4 class="stat-card__title">Comparison / unlinked securities</h4>
                    <p class="stat-card__value"><span class="coverage-badge <?= komodo_e((string) ($cp['badge_class'] ?? '')) ?>"><?= komodo_e((string) ($cp['label'] ?? '—')) ?></span></p>
                    <p class="compact-note stat-card__dek"><?= komodo_e((string) ($cp['dek'] ?? '')) ?></p>
                    <p class="compact-note price-readiness-card__tech"><?= $techLine($cp) ?></p>
                </article>
                <article class="stat-card market-summary-card price-readiness-card">
                    <h4 class="stat-card__title">Special import notes</h4>
                    <p class="stat-card__value"><?= komodo_e((string) $notesCount) ?></p>
                    <p class="compact-note stat-card__dek"><?= $notesCount === 0
                        ? 'No tickers flagged with import notes in the current market data plan.'
                        : 'Tickers with non-empty import notes — review required before widening external price loads (see notes preview below).'; ?></p>
                    <p class="compact-note price-readiness-card__tech"><code class="inline-code inline-code--subtle">vw_market_data_import_plan</code></p>
                </article>
            </div>

            <div class="price-readiness-next" role="region" aria-label="Recommended next action">
                <p class="price-readiness-next__label">Recommended next action</p>
                <p class="price-readiness-next__body"><?= komodo_e($nextAction) ?></p>
            </div>

            <details class="market-md-collapsible price-readiness-method">
                <summary>Finance interpretation / method note</summary>
                <ul class="compact-note price-readiness-method__list">
                    <li><strong>Benchmark indexes</strong> supply the market model series (e.g. for abnormal returns) — load <code class="inline-code">index_daily_prices</code> before scaling security work.</li>
                    <li><strong>Event-linked securities</strong> are the primary names tied to events; their <strong>security price coverage</strong> must span the <strong>suggested import window</strong> for core event-study observations.</li>
                    <li><strong>Comparison securities</strong> (and unlinked plan rows) support robustness, peer context, or placebo-style checks — still require window coverage when used.</li>
                    <li><strong>Event-study preparation</strong> assumes benchmarks and material securities meet full <strong>security price coverage</strong> for the <strong>suggested import window</strong> before running estimation outside Komodo — re-run this page after each external load batch.</li>
                </ul>
            </details>
            <?php } ?>
        </section>
    </section>

    <?php if (!$md['available']) { ?>
        <p class="env-note env-note--warn" role="status">Detailed coverage tables are hidden until the database is connected.</p>
    <?php } else { ?>

        <?php /* B. Summary cards */ ?>
        <div class="market-summary-grid" aria-label="Coverage summary">
            <article class="stat-card market-summary-card">
                <h3 class="stat-card__title">Securities in plan</h3>
                <p class="stat-card__value"><?= is_array($ss) ? komodo_e((string) ($ss['total_securities'] ?? '—')) : '—' ?></p>
            </article>
            <article class="stat-card market-summary-card">
                <h3 class="stat-card__title">Event-linked</h3>
                <p class="stat-card__value"><?= is_array($ss) ? komodo_e((string) ($ss['event_linked_securities'] ?? '—')) : '—' ?></p>
            </article>
            <article class="stat-card market-summary-card">
                <h3 class="stat-card__title">Comparison / unlinked</h3>
                <p class="stat-card__value"><?= is_array($ss) ? komodo_e((string) ($ss['comparison_or_unlinked_securities'] ?? '—')) : '—' ?></p>
            </article>
            <article class="stat-card market-summary-card">
                <h3 class="stat-card__title">Securities with prices</h3>
                <p class="stat-card__value"><?= is_array($ss) ? komodo_e((string) ($ss['securities_with_any_prices'] ?? '—')) : '—' ?></p>
            </article>
            <article class="stat-card market-summary-card">
                <h3 class="stat-card__title">Not started (zero rows)</h3>
                <p class="stat-card__value"><?= is_array($ss) ? komodo_e((string) ($ss['not_started'] ?? '—')) : '—' ?></p>
            </article>
            <article class="stat-card market-summary-card">
                <h3 class="stat-card__title">Indexes with prices / total</h3>
                <p class="stat-card__value"><?php if (is_array($is)) {
                    $a = (string) ($is['indexes_with_any_prices'] ?? '—');
                    $t = (string) ($is['total_indexes'] ?? '—');
                    echo komodo_e($a . ' / ' . $t);
                } else {
                    echo '—';
                } ?></p>
                <?php if (is_array($is) && ((int) ($is['total_index_price_rows'] ?? 0)) > 0) { ?>
                    <p class="compact-note stat-card__dek"><?= komodo_e(
                        'Bars: '
                        . number_format((int) $is['total_index_price_rows'])
                        . ', span '
                        . (string) ($is['first_index_price_date'] ?? '—')
                        . ' → '
                        . (string) ($is['last_index_price_date'] ?? '—')
                    ) ?></p>
                <?php } ?>
            </article>
        </div>

        <?php
        /** @var list<array{ticker_symbol: string, import_notes: string}> $previewNotes */
        $previewNotes = is_array($md['notes_preview'] ?? null) ? $md['notes_preview'] : [];
        if ($previewNotes !== []) { ?>
            <aside class="panel-nested panel-muted market-notes-spotlight" aria-labelledby="notes-spot-heading">
                <h3 id="notes-spot-heading" class="subsection-heading subsection-heading-tight">Special import notes spotlight</h3>
                <p class="compact-note">Tickers flagged in <code class="inline-code">vw_market_data_import_plan.import_notes</code> — review required before widening external price coverage.</p>
                <ul class="market-notes-spotlight-list">
                    <?php foreach ($previewNotes as $pv) { ?>
                        <li>
                            <strong><code class="inline-code"><?= komodo_e($pv['ticker_symbol']) ?></code></strong>
                            <span class="compact-note"><?= komodo_e($pv['import_notes']) ?></span>
                        </li>
                    <?php } ?>
                </ul>
            </aside>
        <?php } ?>

        <?php /* C. Index coverage */ ?>
        <h3 class="subsection-heading" id="index-coverage">Index price coverage</h3>
        <?php if ($md['partial'] && $md['index_rows'] === []) { ?>
            <p class="compact-note env-note env-note--warn">Index coverage could not be loaded.</p>
        <?php } else { ?>
            <div class="table-scroll">
                <table class="data-table data-table--sticky" aria-labelledby="index-coverage">
                    <thead>
                        <tr>
                            <th scope="col">Code</th>
                            <th scope="col">Name</th>
                            <th scope="col" class="num">Rows</th>
                            <th scope="col">First</th>
                            <th scope="col">Last</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($md['index_rows'] as $ix) {
                            $ist = (string) ($ix['coverage_status'] ?? 'not_started');
                            $istLabel = komodo_label($ist, 'coverage_status');
                            $istDesc = komodo_describe($ist, 'coverage_status');
                            ?>
                            <tr>
                                <td><code class="inline-code"><?= komodo_e((string) ($ix['index_code'] ?? '')) ?></code></td>
                                <td><?= komodo_e((string) ($ix['index_name'] ?? '')) ?></td>
                                <td class="num"><?= komodo_e((string) ($ix['price_rows'] ?? '0')) ?></td>
                                <td><?= $formatDateCell(komodo_normalize_date_string($ix['first_price_date'] ?? null)) ?></td>
                                <td><?= $formatDateCell(komodo_normalize_date_string($ix['last_price_date'] ?? null)) ?></td>
                                <td><span class="coverage-badge <?= komodo_e(komodo_coverage_badge_css($ist, 'index')) ?>"<?= $istDesc ? ' title="' . komodo_e($istDesc) . '"' : '' ?>><?= komodo_e($istLabel) ?></span></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <?php /* D. By role */ ?>
        <h3 class="subsection-heading" id="role-summary">Security coverage by role</h3>
        <?php if (!$ss) { ?>
            <p class="compact-note env-note env-note--warn">Security coverage summary unavailable.</p>
        <?php } else { ?>
            <div class="table-scroll">
                <table class="data-table role-summary-table" aria-labelledby="role-summary">
                    <thead>
                        <tr>
                            <th scope="col">Role</th>
                            <th scope="col" class="num">Total</th>
                            <th scope="col" class="num">Not started</th>
                            <th scope="col" class="num">Has prices</th>
                            <th scope="col" class="num">Covers window</th>
                            <th scope="col" class="num">Missing start</th>
                            <th scope="col" class="num">Missing end</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach (['event_linked_security', 'comparison_or_unlinked_security'] as $rk) {
                            $b = isset($byRole[$rk]) && is_array($byRole[$rk]) ? $byRole[$rk] : [];
                            $rlabel = komodo_label($rk, 'role');
                            $rdesc = komodo_describe($rk, 'role');
                            ?>
                            <tr>
                                <th scope="row">
                                    <div class="label-stack">
                                        <span class="label-primary"<?= $rdesc ? ' title="' . komodo_e($rdesc) . '"' : '' ?>><?= komodo_e($rlabel) ?></span>
                                        <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($rk) ?></code></span>
                                    </div>
                                </th>
                                <td class="num"><?= komodo_e((string) ($b['total'] ?? 0)) ?></td>
                                <td class="num"><?= komodo_e((string) ($b['not_started'] ?? 0)) ?></td>
                                <td class="num"><?= komodo_e((string) ($b['has_prices'] ?? 0)) ?></td>
                                <td class="num"><?= komodo_e((string) ($b['covers_suggested_window'] ?? 0)) ?></td>
                                <td class="num"><?= komodo_e((string) ($b['missing_start_window'] ?? 0)) ?></td>
                                <td class="num"><?= komodo_e((string) ($b['missing_end_window'] ?? 0)) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <?php /* E. Top problems */ ?>
        <h3 class="subsection-heading" id="problem-tickers">Tickers to address first</h3>
        <p class="compact-note">These tables are security/ticker-grain. Resolve benchmark indexes first, then event-linked tickers, then comparison tickers.</p>
        <?php if ($md['security_rows'] === [] && $md['partial']) { ?>
            <p class="compact-note env-note env-note--warn">Security rows could not be loaded.</p>
        <?php } elseif (($md['top_problem_securities'] ?? []) === [] && ($md['security_rows'] ?? []) !== []) { ?>
            <p class="env-note env-note--success" role="status">Nothing in the “problem ticker” slice right now — every security is outside the flagged coverage states (typically all-green vs suggested windows).</p>
            <p class="compact-note"><?= komodo_e('External loads that stop short of suggested dates or leave notes unresolved will surface here automatically (event-linked first).') ?></p>
        <?php } elseif (($md['top_problem_securities'] ?? []) === []) { ?>
            <p class="compact-note">—</p>
        <?php } else { ?>
            <div class="table-scroll">
                <table class="data-table data-table--sticky" aria-labelledby="problem-tickers">
                    <thead>
                        <tr>
                            <th scope="col">Ticker</th>
                            <th scope="col">Company</th>
                            <th scope="col">Role</th>
                            <th scope="col" class="num">Events</th>
                            <th scope="col">Suggested window</th>
                            <th scope="col" class="num">Price rows</th>
                            <th scope="col">Status</th>
                            <th scope="col">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($md['top_problem_securities'] as $prob) {
                            $stProb = (string) ($prob['coverage_status'] ?? '');
                            $stProbLabel = komodo_label($stProb, 'coverage_status');
                            $stProbDesc = komodo_describe($stProb, 'coverage_status');
                            [$noteDisp, $noteFull, $hasTitle] = komodo_note_preview(isset($prob['import_notes']) ? (string) $prob['import_notes'] : '', 100);
                            $roleKey = (string) ($prob['price_import_role'] ?? '');
                            $roleLabel = komodo_label($roleKey, 'role');
                            $roleDesc = $roleKey !== '' ? komodo_describe($roleKey, 'role') : null;
                            ?>
                            <tr>
                                <td><code class="inline-code"><?= komodo_e((string) ($prob['ticker_symbol'] ?? '')) ?></code></td>
                                <td><?= komodo_e((string) ($prob['display_name'] ?: ($prob['security_name'] ?? ''))) ?></td>
                                <td>
                                    <div class="label-stack">
                                        <span class="label-primary"<?= $roleDesc ? ' title="' . komodo_e($roleDesc) . '"' : '' ?>><?= komodo_e($roleLabel) ?></span>
                                        <?php if ($roleKey !== '') { ?>
                                            <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($roleKey) ?></code></span>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td class="num"><?= isset($prob['linked_event_count']) ? komodo_e((string) $prob['linked_event_count']) : '—' ?></td>
                                <td><?php
                                    $sd = komodo_normalize_date_string($prob['suggested_import_start_date'] ?? null) ?? '';
                                    $ed = komodo_normalize_date_string($prob['suggested_import_end_date'] ?? null) ?? '';
                            echo $sd !== '' && $ed !== ''
                                ? komodo_e($sd . ' → ' . $ed)
                                : '—'; ?></td>
                                <td class="num"><?= komodo_e((string) ($prob['price_rows'] ?? 0)) ?></td>
                                <td><span class="coverage-badge <?= komodo_e(komodo_coverage_badge_css($stProb)) ?>"<?= $stProbDesc ? ' title="' . komodo_e($stProbDesc) . '"' : '' ?>><?= komodo_e($stProbLabel) ?></span></td>
                                <td class="compact-note"><?php if ($noteDisp !== '') { ?>
                                    <span<?= $hasTitle ? ' title="' . $noteFull . '"' : '' ?>><?= $noteDisp ?></span>
                                <?php } else {
                                    echo '—';
                                } ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <?php /* Full plan (collapsible) */ ?>
        <?php if (($md['security_rows'] ?? []) !== []) {
            $totalPlan = count($md['security_rows']);
            ?>
            <details class="market-md-collapsible market-md-collapsible--plan">
                <summary>Full market data plan (<?= komodo_e((string) $totalPlan) ?> securities)</summary>
                <div class="table-scroll">
                    <table class="data-table data-table--sticky data-table--dense" aria-label="Full market data plan coverage (vw_market_data_import_plan)">
                        <thead>
                            <tr>
                                <th scope="col">Ticker</th>
                                <th scope="col">Company</th>
                                <th scope="col">Role</th>
                                <th scope="col">Exch.</th>
                                <th scope="col" class="num">Events</th>
                                <th scope="col">Suggested window</th>
                                <th scope="col" class="num">Price rows</th>
                                <th scope="col">First / last bar</th>
                                <th scope="col">Status</th>
                                <th scope="col">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($md['security_rows'] as $fw) {
                                $fst = (string) ($fw['coverage_status'] ?? '');
                                $fstLabel = komodo_label($fst, 'coverage_status');
                                $fstDesc = komodo_describe($fst, 'coverage_status');
                                [$nDisp, $nFull, $hasTtl] = komodo_note_preview(isset($fw['import_notes']) ? (string) $fw['import_notes'] : '', 72);
                                $sd = komodo_normalize_date_string($fw['suggested_import_start_date'] ?? null) ?? '';
                                $ed = komodo_normalize_date_string($fw['suggested_import_end_date'] ?? null) ?? '';
                                $wf = komodo_normalize_date_string($fw['first_price_date'] ?? null);
                                $wl = komodo_normalize_date_string($fw['last_price_date'] ?? null);
                                $barSpan = ($wf ?? '') !== '' && ($wl ?? '') !== ''
                                    ? komodo_e($wf . ' → ' . $wl)
                                    : '—';
                                $roleKey = (string) ($fw['price_import_role'] ?? '');
                                $roleLabel = komodo_label($roleKey, 'role');
                                $roleDesc = $roleKey !== '' ? komodo_describe($roleKey, 'role') : null;
                                ?>
                                <tr>
                                    <td><code class="inline-code"><?= komodo_e((string) ($fw['ticker_symbol'] ?? '')) ?></code></td>
                                    <td><?= komodo_e((string) ($fw['display_name'] ?: ($fw['security_name'] ?? ''))) ?></td>
                                    <td>
                                        <div class="label-stack">
                                            <span class="label-primary"<?= $roleDesc ? ' title="' . komodo_e($roleDesc) . '"' : '' ?>><?= komodo_e($roleLabel) ?></span>
                                            <?php if ($roleKey !== '') { ?>
                                                <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($roleKey) ?></code></span>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td><?= komodo_e((string) ($fw['exchange_code'] ?? '—')) ?></td>
                                    <td class="num"><?= isset($fw['linked_event_count']) ? komodo_e((string) $fw['linked_event_count']) : '—' ?></td>
                                    <td><?= $sd !== '' && $ed !== '' ? komodo_e($sd . ' → ' . $ed) : '—'; ?></td>
                                    <td class="num"><?= komodo_e((string) ($fw['price_rows'] ?? 0)) ?></td>
                                    <td class="compact-note"><?php echo $barSpan; ?></td>
                                    <td><span class="coverage-badge <?= komodo_e(komodo_coverage_badge_css($fst)) ?>"<?= $fstDesc ? ' title="' . komodo_e($fstDesc) . '"' : '' ?>><?= komodo_e($fstLabel) ?></span></td>
                                    <td class="compact-note"><?php if ($nDisp !== '') { ?>
                                        <span<?= $hasTtl ? ' title="' . $nFull . '"' : '' ?>><?= $nDisp ?></span>
                                    <?php } else {
                                        echo '—';
                                    } ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php } ?>

        <?php /* F. Data sources */ ?>
        <h3 class="subsection-heading" id="data-sources-heading">Data sources</h3>
        <?php if ($md['partial'] && $md['data_sources'] === []) { ?>
            <p class="compact-note env-note env-note--warn">Data sources could not be loaded.</p>
        <?php } else { ?>
            <div class="table-scroll">
                <table class="data-table" aria-labelledby="data-sources-heading">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Type</th>
                            <th scope="col">URL</th>
                            <th scope="col">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($md['data_sources'] as $ds) {
                            $name = (string) ($ds['source_name'] ?? '');
                            $isYahoo = stripos($name, 'yahoo finance') !== false;
                            ?>
                            <tr<?= $isYahoo ? ' class="data-table__highlight"' : '' ?>>
                                <td class="num"><?= komodo_e((string) ($ds['data_source_id'] ?? '')) ?></td>
                                <td>
                                    <?= komodo_e($name) ?>
                                    <?php if ($isYahoo) { ?>
                                        <span class="coverage-badge coverage-badge--ok">Yahoo Finance</span>
                                    <?php } ?>
                                </td>
                                <td><?= komodo_e((string) ($ds['source_type'] ?? '')) ?></td>
                                <td class="compact-note"><?php
                                    $u = (string) ($ds['base_url'] ?? '');
                            echo $u !== ''
                                ? '<a class="footer-top-link" href="' . komodo_e($u) . '" target="_blank" rel="noopener noreferrer">' . komodo_e($u) . '</a>'
                                : '—'; ?></td>
                                <td class="compact-note"><?php
                                    $nn = isset($ds['notes']) ? (string) $ds['notes'] : '';
                                    if ($nn === '') {
                                        echo '—';
                                    } else {
                                        $squish = preg_replace('/\s+/', ' ', trim($nn));
                                        $squish = is_string($squish) ? $squish : $nn;
                                        $short = strlen($squish) > 80 ? substr($squish, 0, 80) . '…' : $squish;
                                        echo komodo_e($short);
                                    } ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

    <?php } ?>
</section>
