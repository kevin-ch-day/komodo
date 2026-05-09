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
];

$badgeClass = static function (string $status, bool $index = false): string {
    $mapSec = [
        'not_started' => 'coverage-badge--not-started',
        'covers_suggested_window' => 'coverage-badge--ok',
        'has_prices' => 'coverage-badge--ok',
        'missing_start_window' => 'coverage-badge--warning',
        'missing_end_window' => 'coverage-badge--warning',
        'has_prices_window_unknown' => 'coverage-badge--unknown',
        'partial_unknown_dates' => 'coverage-badge--unknown',
        'partial' => 'coverage-badge--partial',
    ];
    $mapIdx = [
        'not_started' => 'coverage-badge--not-started',
        'has_prices' => 'coverage-badge--ok',
    ];
    $m = $index ? $mapIdx : $mapSec;

    return $m[$status] ?? 'coverage-badge--unknown';
};

$formatDateCell = static function (?string $v): string {
    if ($v === null || $v === '') {
        return '—';
    }
    return komodo_e((string) $v);
};

$truncateNote = static function (?string $n, int $max = 100): array {
    if ($n === null || $n === '') {
        return ['', '', false];
    }
    $plain = preg_replace('/\s+/', ' ', trim(strip_tags($n)));
    if ($plain === null) {
        $plain = '';
    }
    $shortened = strlen($plain) > $max;
    $show = $shortened ? substr($plain, 0, $max) . '…' : $plain;

    return [komodo_e($show), komodo_e($plain), $shortened];
};

$ss = $md['security_summary'];
$is = $md['index_summary'];
$byRole = is_array($ss) && isset($ss['by_role']) && is_array($ss['by_role']) ? $ss['by_role'] : [];
/** @var array<string, mixed> $ins */
$ins = is_array($md['insights'] ?? null) ? $md['insights'] : [];

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
        <h3 id="market-md-overview-label" class="subsection-heading subsection-heading-tight">Import coverage overview</h3>
        <?php if (!$md['available']) { ?>
            <p class="section-lead">Market data readiness needs a configured <code class="inline-code">app/config/local.php</code> and a running MariaDB instance. Offline mode cannot evaluate per-security windows or benchmarks.</p>
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
                        ?><strong><?= komodo_e((string) $pcw) ?>%</strong> fully cover suggested window<?php
                    } else {
                        ?>—<?php } ?></li>
                    <li><span class="compact-note"><?= komodo_e('Tickers with import_notes: ')
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

        <h4 class="subsection-heading subsection-heading-tight">Recommended import order</h4>
        <ol class="market-md-next-steps">
            <li>Import <strong>benchmark index</strong> daily prices into <code class="inline-code">index_daily_prices</code>.</li>
            <li>Import <strong>event-linked</strong> security prices into <code class="inline-code">security_daily_prices</code>.</li>
            <li>Import <strong>comparison / unlinked</strong> security prices.</li>
            <li>Re-run this page for coverage QA before event-study steps.</li>
        </ol>
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
                <h3 id="notes-spot-heading" class="subsection-heading subsection-heading-tight">Import notes spotlight</h3>
                <p class="compact-note">Tickers flagged in <code class="inline-code">vw_market_data_import_plan.import_notes</code> — resolve before widening coverage.</p>
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
                                <td><span class="coverage-badge <?= komodo_e($badgeClass($ist, true)) ?>"<?= $istDesc ? ' title="' . komodo_e($istDesc) . '"' : '' ?>><?= komodo_e($istLabel) ?></span></td>
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
            <p class="compact-note"><?= komodo_e('Imports that stop short of suggested dates or leave notes unresolved will bubble up here automatically (event-linked first).') ?></p>
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
                            [$noteDisp, $noteFull, $hasTitle] = $truncateNote(isset($prob['import_notes']) ? (string) $prob['import_notes'] : '');
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
                                <td><span class="coverage-badge <?= komodo_e($badgeClass($stProb)) ?>"<?= $stProbDesc ? ' title="' . komodo_e($stProbDesc) . '"' : '' ?>><?= komodo_e($stProbLabel) ?></span></td>
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
                <summary>Full import plan (<?= komodo_e((string) $totalPlan) ?> securities)</summary>
                <div class="table-scroll">
                    <table class="data-table data-table--sticky data-table--dense" aria-label="Full vw_market_data_import_plan coverage">
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
                                [$nDisp, $nFull, $hasTtl] = $truncateNote(isset($fw['import_notes']) ? (string) $fw['import_notes'] : '', 72);
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
                                    <td><span class="coverage-badge <?= komodo_e($badgeClass($fst)) ?>"<?= $fstDesc ? ' title="' . komodo_e($fstDesc) . '"' : '' ?>><?= komodo_e($fstLabel) ?></span></td>
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
