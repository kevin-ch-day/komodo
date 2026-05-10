<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$offlineMode = $ctx['offline_mode'];
$liveMerged = $ctx['live_merged'];
$marketKpis = $ctx['market_kpis'];

$md = $ctx['market_data'] ?? [
    'available' => false,
    'partial' => false,
    'security_rows' => [],
    'index_rows' => [],
    'security_summary' => null,
    'index_summary' => null,
    'top_problem_securities' => [],
    'notes_preview' => [],
    'price_import_readiness' => null,
    'readiness_conclusion' => null,
    'loaded_but_incomplete' => [],
    'lineage_rows' => [],
    'aligned_daily_density' => [
        'ok' => false,
        'rows' => [],
        'error' => null,
    ],
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
$loadedInc = is_array($md['loaded_but_incomplete'] ?? null) ? $md['loaded_but_incomplete'] : [];
$lineageRows = is_array($md['lineage_rows'] ?? null) ? $md['lineage_rows'] : [];
/** @var array{ok: bool, rows: list<array<string, mixed>>, error: ?string} $ad */
$ad = is_array($md['aligned_daily_density'] ?? null) ? $md['aligned_daily_density'] : ['ok' => false, 'rows' => [], 'error' => null];

$lineageFb = null;
$lineageMeta = null;
foreach ($lineageRows as $lr) {
    $ts = strtoupper((string) ($lr['ticker_symbol'] ?? ''));
    if ($ts === 'FB') {
        $lineageFb = $lr;
    }
    if ($ts === 'META') {
        $lineageMeta = $lr;
    }
}

?>
<section class="panel shell-section price-audit-page" aria-labelledby="audit-heading">
    <h2 id="audit-heading">Price audit</h2>
    <p class="section-lead">Technical audit tables for <code class="inline-code">vw_market_data_import_plan</code>, indexes, lineage, aligned trading-day density, and whitelist row counts. For the short readiness answer (&ldquo;are we event-study ready, what is blocking?&rdquo;), use <a class="footer-top-link" href="index.php?page=price-coverage">Price coverage</a>; for the action worklist, use <a class="footer-top-link" href="index.php?page=price-import-queue">Price import triage</a>.</p>

    <nav class="market-md-related" aria-label="Related pages">
        <span class="compact-note">Related:</span>
        <a class="footer-top-link" href="index.php?page=market-data">Market Data</a>
        <span class="market-md-related-sep" aria-hidden="true">·</span>
        <a class="footer-top-link" href="index.php?page=price-coverage">Price coverage</a>
        <span class="market-md-related-sep" aria-hidden="true">·</span>
        <a class="footer-top-link" href="index.php?page=price-import-queue">Price import triage</a>
    </nav>

    <?php if (!$md['available']) { ?>
        <p class="env-note env-note--warn" role="status">Audit tables are hidden until the database is connected.</p>
    <?php } else { ?>

        <section class="panel-nested panel-phase--inset" aria-labelledby="audit-pipeline-heading">
            <h3 id="audit-pipeline-heading" class="subsection-heading subsection-heading-tight">Pipeline loaded vs analysis-ready</h3>
            <p class="section-lead">Loaded in the pipeline ≠ analysis-ready. A security can have rows in <code class="inline-code">security_daily_prices</code> and still be unsuitable for an event window if the suggested start or end is missing, the series is sparse, or the ticker does not match the historical event identifier. Window checks use <strong>±<?= (int) KOMODO_TRIAGE_WINDOW_SLACK_DAYS ?> calendar days</strong> slack at <strong>each</strong> end of the suggested range — only gaps <strong>larger than</strong> that slack are flagged (e.g. Jan 1 plan start vs Jan 2 first bar is <strong>not</strong> a window gap). <strong>First and last bar alone do not prove daily density</strong> — a series could still be weekly or gapped between those dates; treat trading-day completeness as a separate review (see data quality warnings above).</p>
            <ul class="compact-note market-pipeline-examples">
                <li><strong>AAP:</strong> loaded and covers the suggested window (within slack).</li>
                <li><strong>AMZN:</strong> may show as covering the window if the first bar is within <?= (int) KOMODO_TRIAGE_WINDOW_SLACK_DAYS ?> calendar days of the suggested start; larger gaps still flag missing start.</li>
                <li><strong>FB / META:</strong> Event-linked <code class="inline-code">FB</code> can show full window coverage; the operational issue is vendor <strong>source labels</strong> (historical Facebook prices exported under <code class="inline-code">META</code>). <?= komodo_e(komodo_fb_meta_lineage_import_policy_paragraph()) ?></li>
                <li><strong>TSLA / MARA:</strong> high-volatility comparison names in the plan — useful to test whether benchmark, peer, and comparison-group results stay stable when volatile market behavior is present; not framed as &ldquo;contaminating&rdquo; the design.</li>
            </ul>
        </section>

        <h3 class="subsection-heading" id="loaded-incomplete">Loaded but incomplete</h3>
        <p class="compact-note">Securities with price rows where the first or last loaded trade date is <strong>more than <?= (int) KOMODO_TRIAGE_WINDOW_SLACK_DAYS ?> calendar days</strong> outside the suggested import window at that end — not analysis-ready for that window. Misalignments <strong>within</strong> that slack (e.g. Jan 1 vs Jan 2) are <strong>not</strong> listed here (same rule as Price import triage). Weekly or sparse series can still pass this span test — density is a separate check.</p>
        <?php if ($md['security_rows'] === [] && $md['partial']) { ?>
            <p class="compact-note env-note env-note--warn">Security rows could not be loaded.</p>
        <?php } elseif ($loadedInc === []) { ?>
            <p class="compact-note" role="status">None — no loaded rows are flagged missing start or end of the suggested window.</p>
        <?php } else { ?>
            <div class="table-scroll">
                <table class="data-table data-table--sticky" aria-labelledby="loaded-incomplete">
                    <thead>
                        <tr>
                            <th scope="col">Ticker</th>
                            <th scope="col">Company</th>
                            <th scope="col">Role</th>
                            <th scope="col">Suggested window</th>
                            <th scope="col">First / last bar</th>
                            <th scope="col" class="num">Price rows</th>
                            <th scope="col">Issue / status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loadedInc as $row) {
                            $st = (string) ($row['coverage_status'] ?? '');
                            $stLabel = komodo_label($st, 'coverage_status');
                            $stDesc = komodo_describe($st, 'coverage_status');
                            $sd = komodo_normalize_date_string($row['suggested_import_start_date'] ?? null) ?? '';
                            $ed = komodo_normalize_date_string($row['suggested_import_end_date'] ?? null) ?? '';
                            $wf = komodo_normalize_date_string($row['first_price_date'] ?? null);
                            $wl = komodo_normalize_date_string($row['last_price_date'] ?? null);
                            $barSpan = ($wf ?? '') !== '' && ($wl ?? '') !== ''
                                ? komodo_e($wf . ' → ' . $wl)
                                : '—';
                            $roleKey = (string) ($row['price_import_role'] ?? '');
                            $roleLabel = komodo_label($roleKey, 'role');
                            $roleDesc = $roleKey !== '' ? komodo_describe($roleKey, 'role') : null;
                            ?>
                            <tr>
                                <td><code class="inline-code"><?= komodo_e((string) ($row['ticker_symbol'] ?? '')) ?></code></td>
                                <td><?= komodo_e((string) ($row['display_name'] ?: ($row['security_name'] ?? ''))) ?></td>
                                <td>
                                    <div class="label-stack">
                                        <span class="label-primary"<?= $roleDesc ? ' title="' . komodo_e($roleDesc) . '"' : '' ?>><?= komodo_e($roleLabel) ?></span>
                                        <?php if ($roleKey !== '') { ?>
                                            <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($roleKey) ?></code></span>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td><?= $sd !== '' && $ed !== '' ? komodo_e($sd . ' → ' . $ed) : '—'; ?></td>
                                <td class="compact-note"><?php echo $barSpan; ?></td>
                                <td class="num"><?= komodo_e((string) ($row['price_rows'] ?? 0)) ?></td>
                                <td><span class="coverage-badge <?= komodo_e(komodo_coverage_badge_css($st)) ?>"<?= $stDesc ? ' title="' . komodo_e($stDesc) . '"' : '' ?>><?= komodo_e($stLabel) ?></span></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <section class="panel-nested panel-phase--inset" aria-labelledby="audit-aligned-density-heading">
            <h3 id="audit-aligned-density-heading" class="subsection-heading subsection-heading-tight"><?= komodo_e(KOMODO_ALIGNED_DAILY_DENSITY_LABEL) ?> <span class="compact-note">(trading-day readiness signal)</span></h3>
            <p class="section-lead">Readiness signal only — not a final &ldquo;analysis-ready&rdquo; verdict and not a replacement for span/slack window checks. Counts are the Section&nbsp;7 intersection model: US trading days from <code class="inline-code">vw_us_trading_days</code> that fall in each plan row&rsquo;s suggested import window, versus loaded <code class="inline-code">security_daily_prices</code> rows whose <code class="inline-code">trade_date</code> matches that calendar day. Weekly-style or sparse extracts can yield low ratios (for example long comparison windows with far fewer distinct aligned days than expected) even when first/last bar dates look plausible. Event-linked window policy is unchanged; comparison/control suggested windows still use the 2014 floor where the plan applies it.</p>
            <ul class="compact-note">
                <li>Sorted for review: event-linked tickers first, then lowest aligned ratio — surfaces sparse series similar to audit probe results.</li>
                <li>Quick density (distinct trade dates in the window, audit Section&nbsp;6) can count off-calendar rows; this table is the stricter alignment check.</li>
            </ul>
            <?php if (!$ad['ok']) { ?>
                <p class="compact-note env-note env-note--warn" role="status"><?php if ($md['available']) { ?>Could not load aligned density (<?= komodo_e((string) ($ad['error'] ?? 'unknown')) ?>). Confirm <code class="inline-code">vw_us_trading_days</code> is available.<?php } else { ?>Connect the database to compute aligned trading-day density.<?php } ?></p>
            <?php } elseif (($ad['rows'] ?? []) === []) { ?>
                <p class="compact-note" role="status">No plan rows to evaluate.</p>
            <?php } else { ?>
                <div class="table-scroll">
                    <table class="data-table data-table--dense" aria-labelledby="audit-aligned-density-heading">
                        <thead>
                            <tr>
                                <th scope="col">Ticker</th>
                                <th scope="col">Role</th>
                                <th scope="col">Suggested window</th>
                                <th scope="col" class="num">Expected US TDs</th>
                                <th scope="col" class="num">Loaded (aligned)</th>
                                <th scope="col" class="num">Ratio</th>
                                <th scope="col">First / last aligned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ad['rows'] as $drow) {
                                $dRole = (string) ($drow['price_import_role'] ?? '');
                                $ds = komodo_normalize_date_string($drow['suggested_import_start_date'] ?? null) ?? '';
                                $de = komodo_normalize_date_string($drow['suggested_import_end_date'] ?? null) ?? '';
                                $win = ($ds !== '' && $de !== '') ? ($ds . ' → ' . $de) : '—';
                                $exp = (int) ($drow['expected_trading_days'] ?? 0);
                                $load = (int) ($drow['loaded_aligned_days'] ?? 0);
                                $ratioRaw = $drow['aligned_density_ratio'] ?? null;
                                $ratioStr = ($exp > 0 && $ratioRaw !== null && $ratioRaw !== '')
                                    ? (string) $ratioRaw
                                    : '—';
                                $fa = komodo_normalize_date_string($drow['first_aligned_trade_date'] ?? null);
                                $la = komodo_normalize_date_string($drow['last_aligned_trade_date'] ?? null);
                                $spanAl = ($fa !== null && $la !== null) ? ($fa . ' → ' . $la) : '—';
                                ?>
                                <tr>
                                    <td><code class="inline-code"><?= komodo_e((string) ($drow['ticker_symbol'] ?? '')) ?></code></td>
                                    <td><?= komodo_e(komodo_label($dRole, 'role')) ?></td>
                                    <td class="compact-note"><?= komodo_e($win) ?></td>
                                    <td class="num"><?= komodo_e((string) $exp) ?></td>
                                    <td class="num"><?= komodo_e((string) $load) ?></td>
                                    <td class="num"><?= komodo_e($ratioStr) ?></td>
                                    <td class="compact-note"><?= komodo_e($spanAl) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

        <section class="panel-nested panel-phase--inset" aria-labelledby="lineage-heading">
            <h3 id="lineage-heading" class="subsection-heading subsection-heading-tight">Historical ticker / lineage issues</h3>
            <p class="section-lead">Event records may reference legacy tickers while vendor exports use current listings. Komodo does not infer ticker continuity from filenames or export labels — map historical rows to the correct <code class="inline-code">security_id</code> at import time.</p>
            <ul class="compact-note market-lineage-callouts">
                <li><?= komodo_e(komodo_fb_meta_lineage_import_policy_paragraph()) ?></li>
                <li><strong>Plan snapshot:</strong> <?php if ($lineageFb !== null) {
                    $fbEv = (int) ($lineageFb['linked_event_count'] ?? 0);
                    $fbPx = (int) ($lineageFb['price_rows'] ?? 0);
                    echo komodo_e((string) $fbEv) ?> linked event(s) on <code class="inline-code">FB</code>, <?php
                    if ($fbPx === 0) {
                        ?>no loaded prices in this plan.<?php
                    } else {
                        echo komodo_e((string) $fbPx) ?> price row(s) on <code class="inline-code">FB</code> in this plan.<?php
                    }
                } else {
                    ?>Confirm the event-linked legacy ticker in the import plan; it may not appear as <code class="inline-code">FB</code> in every snapshot.<?php
                }
                if ($lineageMeta !== null) {
                    $mPx = (int) ($lineageMeta['price_rows'] ?? 0);
                    ?> <code class="inline-code">META</code> plan row: <?php
                    echo komodo_e((string) $mPx) ?> price row(s) — separate from FB-tagged pre-rename windows unless continuity rules say otherwise.<?php
                } ?></li>
            </ul>
            <?php if ($lineageFb !== null || $lineageMeta !== null) { ?>
                <div class="table-scroll">
                    <table class="data-table data-table--dense" aria-label="Lineage tickers from plan">
                        <thead>
                            <tr>
                                <th scope="col">Ticker</th>
                                <th scope="col">Role</th>
                                <th scope="col" class="num">Events</th>
                                <th scope="col" class="num">Price rows</th>
                                <th scope="col">Status</th>
                                <th scope="col">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ([$lineageFb, $lineageMeta] as $lr) {
                                if ($lr === null) {
                                    continue;
                                }
                                $lst = (string) ($lr['coverage_status'] ?? '');
                                $lstLabel = komodo_label($lst, 'coverage_status');
                                $lstDesc = komodo_describe($lst, 'coverage_status');
                                [$lnDisp, $lnFull, $lnTitle] = komodo_note_preview(isset($lr['import_notes']) ? (string) $lr['import_notes'] : '', 120);
                                $lrRole = (string) ($lr['price_import_role'] ?? '');
                                ?>
                                <tr>
                                    <td><code class="inline-code"><?= komodo_e((string) ($lr['ticker_symbol'] ?? '')) ?></code></td>
                                    <td><?= komodo_e(komodo_label($lrRole, 'role')) ?></td>
                                    <td class="num"><?= isset($lr['linked_event_count']) ? komodo_e((string) $lr['linked_event_count']) : '—' ?></td>
                                    <td class="num"><?= komodo_e((string) ($lr['price_rows'] ?? 0)) ?></td>
                                    <td><span class="coverage-badge <?= komodo_e(komodo_coverage_badge_css($lst)) ?>"<?= $lstDesc ? ' title="' . komodo_e($lstDesc) . '"' : '' ?>><?= komodo_e($lstLabel) ?></span></td>
                                    <td class="compact-note"><?php if ($lnDisp !== '') { ?>
                                        <span<?= $lnTitle ? ' title="' . $lnFull . '"' : '' ?>><?= $lnDisp ?></span>
                                    <?php } else {
                                        echo '—';
                                    } ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

        <h3 class="subsection-heading" id="priority-attention">Priority attention</h3>
        <p class="compact-note">Sorted for action: event-linked first, higher event count, import-note lineage flags, not-started and window gaps before other problem states. Rows with multiple events or lineage notes are highlighted. For an action-only worklist that hides window-complete tickers, use <a class="footer-top-link" href="index.php?page=price-import-queue">Price import triage</a>.</p>
        <?php if ($md['security_rows'] === [] && $md['partial']) { ?>
            <p class="compact-note env-note env-note--warn">Security rows could not be loaded.</p>
        <?php } elseif (($md['top_problem_securities'] ?? []) === [] && ($md['security_rows'] ?? []) !== []) { ?>
            <p class="env-note env-note--success" role="status">Nothing in the problem slice — all securities are outside flagged coverage states for this cut.</p>
        <?php } elseif (($md['top_problem_securities'] ?? []) === []) { ?>
            <p class="compact-note">—</p>
        <?php } else { ?>
            <div class="table-scroll">
                <table class="data-table data-table--sticky" aria-labelledby="priority-attention">
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
                            $trClass = komodo_priority_attention_row_class($prob);
                            ?>
                            <tr<?= $trClass !== '' ? ' class="' . komodo_e($trClass) . '"' : '' ?>>
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

        <?php
        $previewNotes = is_array($md['notes_preview'] ?? null) ? $md['notes_preview'] : [];
        if ($previewNotes !== []) { ?>
            <aside class="panel-nested panel-muted market-notes-spotlight" aria-labelledby="notes-spot-heading">
                <h3 id="notes-spot-heading" class="subsection-heading subsection-heading-tight">Special import notes spotlight</h3>
                <p class="compact-note">Tickers flagged in <code class="inline-code">vw_market_data_import_plan.import_notes</code>.</p>
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
                        <?php foreach (['event_linked_security', 'comparison_or_unlinked_security'] as $rk) {
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

        <?php if (($md['security_rows'] ?? []) !== []) {
            $totalPlan = count($md['security_rows']);
            ?>
            <h3 class="subsection-heading" id="full-plan-heading">Full market data plan</h3>
            <p class="compact-note"><code class="inline-code">vw_market_data_import_plan</code> — <?= komodo_e((string) $totalPlan) ?> securities.</p>
            <div class="table-scroll">
                <table class="data-table data-table--sticky data-table--dense" aria-labelledby="full-plan-heading">
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
        <?php } ?>

        <section class="panel-nested panel-phase--inset" aria-labelledby="method-note-heading">
            <h3 id="method-note-heading" class="subsection-heading subsection-heading-tight">Finance interpretation / method note</h3>
            <ul class="compact-note price-readiness-method__list">
                <li><strong>Benchmark indexes</strong> supply market-model series for abnormal returns — <strong>row presence does not imply daily completeness</strong>; verify calendar coverage separately.</li>
                <li><strong>Event-linked securities</strong> anchor primary observations; bars should span the <strong>suggested import window</strong> when possible.</li>
                <li><strong>Comparison securities</strong> support robustness checks — still require adequate window coverage when used in analysis.</li>
                <li><strong>Event-study calculations</strong> run outside Komodo after you validate windows and data density.</li>
            </ul>
        </section>

        <details class="market-md-collapsible market-technical-counts">
            <summary>Technical counts</summary>
            <p class="compact-note">Whitelist SELECT row counts for pipeline objects (audit).</p>
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

    <?php } ?>
</section>
