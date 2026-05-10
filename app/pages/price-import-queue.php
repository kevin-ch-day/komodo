<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$md = $ctx['market_data'] ?? [
    'available' => false,
    'partial' => false,
    'security_rows' => [],
    'triage_needs_price' => [],
    'triage_needs_price_event_linked' => [],
    'triage_needs_price_comparison' => [],
    'triage_window_gaps' => [],
    'triage_historical_special' => [],
    'triage_special_notes' => [],
    'triage_special_notes_event_linked' => [],
    'triage_special_notes_comparison' => [],
    'triage_next_batch_normal' => [],
    'triage_next_batch_older_history' => [],
    'triage_next_batch_special_source' => [],
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

$needsEventLinked = is_array($md['triage_needs_price_event_linked'] ?? null) ? $md['triage_needs_price_event_linked'] : [];
$needsComparison = is_array($md['triage_needs_price_comparison'] ?? null) ? $md['triage_needs_price_comparison'] : [];
$batchNormal = is_array($md['triage_next_batch_normal'] ?? null) ? $md['triage_next_batch_normal'] : [];
$batchOlderHistory = is_array($md['triage_next_batch_older_history'] ?? null) ? $md['triage_next_batch_older_history'] : [];
$batchSpecialSource = is_array($md['triage_next_batch_special_source'] ?? null) ? $md['triage_next_batch_special_source'] : [];
$windowGaps = is_array($md['triage_window_gaps'] ?? null) ? $md['triage_window_gaps'] : [];
$historical = is_array($md['triage_historical_special'] ?? null) ? $md['triage_historical_special'] : [];
$specialNotesEl = is_array($md['triage_special_notes_event_linked'] ?? null) ? $md['triage_special_notes_event_linked'] : [];
$specialNotesComp = is_array($md['triage_special_notes_comparison'] ?? null) ? $md['triage_special_notes_comparison'] : [];
$showNextBatchAside = $batchNormal !== [] || $batchOlderHistory !== [] || $batchSpecialSource !== [];
/** @var array<string, int|null> $dash */
$dash = is_array($md['triage_dashboard'] ?? null) ? $md['triage_dashboard'] : [];
$totalPlan = is_array($md['security_rows'] ?? null) ? count($md['security_rows']) : 0;

$openTotal = (int) ($dash['open_total'] ?? 0);
$completedHidden = (int) ($dash['completed_plan_rows'] ?? 0);
$needsC = (int) ($dash['needs_count'] ?? 0);
$needsElC = count($needsEventLinked);
$needsCompC = count($needsComparison);
$windowC = (int) ($dash['window_count'] ?? 0);
$histC = (int) ($dash['historical_count'] ?? 0);
$specC = (int) ($dash['special_notes_count'] ?? 0);

?>
<section class="panel shell-section price-import-queue-page" aria-labelledby="piq-heading">
    <h2 id="piq-heading">Price Worklist</h2>
    <p class="section-lead"><strong>What to do next</strong> — short action rows only. Rows that already <strong>cover</strong> the plan window (±<?= (int) KOMODO_TRIAGE_WINDOW_SLACK_DAYS ?> calendar-day slack) are omitted; see the full grid on <a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a>. <strong>Why</strong> a window is wrong, lineage (e.g. FB/META), and aligned density: open the <a class="footer-top-link" href="index.php?page=companies">company</a> drilldown from each row. Snapshot: <a class="footer-top-link" href="index.php?page=price-coverage">Coverage Summary</a>. Imports run outside Komodo; read-only here.</p>

    <?php if (!$md['available']) { ?>
        <p class="env-note env-note--warn" role="status">Connect the database to build triage lists from <code class="inline-code">vw_market_data_import_plan</code>.</p>
    <?php } else { ?>

        <?php if (!empty($md['partial'])) { ?>
            <p class="env-note env-note--warn compact-note" role="status">Some coverage queries failed — triage may be incomplete until the database views respond.</p>
        <?php } ?>

        <div class="triage-summary-grid" role="region" aria-label="Triage counts">
            <article class="stat-card market-summary-card triage-summary-card triage-summary-card--open">
                <h3 class="stat-card__title">Open triage items</h3>
                <p class="stat-card__value"><?= komodo_e((string) $openTotal) ?></p>
                <p class="compact-note stat-card__dek">Unresolved in the buckets below.</p>
            </article>
            <article class="stat-card market-summary-card triage-summary-card">
                <h3 class="stat-card__title">Completed (hidden here)</h3>
                <p class="stat-card__value"><?= komodo_e((string) $completedHidden) ?><?php if ($totalPlan > 0) {
                    echo ' <span class="triage-summary-of">/ ' . komodo_e((string) $totalPlan) . '</span>';
                } ?></p>
                <p class="compact-note stat-card__dek">Span OK vs plan window — full plan &amp; density on <a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a>.</p>
            </article>
        </div>

        <?php if ($openTotal === 0 && $totalPlan > 0) { ?>
            <div class="env-note env-note--success triage-all-clear" role="status">
                <p class="triage-all-clear__title"><strong>Nothing left in triage.</strong> Every plan row reports <span class="coverage-badge coverage-badge--ok"><?= komodo_e(komodo_coverage_catalog_label('covers_suggested_window')) ?></span> in telemetry.</p>
                <p class="compact-note triage-all-clear__body">Continue readiness on <a class="footer-top-link" href="index.php?page=price-coverage">Coverage Summary</a>; benchmarks, lineage, aligned density, and full tables on <a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a>.</p>
            </div>
        <?php } ?>

        <aside class="panel-nested panel-phase--inset triage-workflow" aria-labelledby="triage-workflow-heading">
            <h3 id="triage-workflow-heading" class="subsection-heading subsection-heading-tight">Suggested work order</h3>
            <p class="compact-note">Use this sequence when planning external downloads — lineage mistakes are expensive to unwind.</p>
            <ol class="triage-workflow-steps compact-note">
                <li><a href="#tri-hist">Historical / lineage</a> — confirm identifiers before searching vendors (<?= komodo_e((string) $histC) ?>).</li>
                <li><a href="#tri-needs">Needs price data</a> — initial series pull (<?= komodo_e((string) $needsC) ?>).</li>
                <li><a href="#tri-window">Window gaps</a> — only if first/last bars miss the suggested window by <strong>more than <?= (int) KOMODO_TRIAGE_WINDOW_SLACK_DAYS ?> days</strong> (<?= komodo_e((string) $windowC) ?>).</li>
                <li><a href="#tri-spec">IPO / source callouts</a> — notes-driven checks (<?= komodo_e((string) $specC) ?>).</li>
            </ol>
            <nav class="triage-toc" aria-label="Jump to section">
                <span class="compact-note triage-toc-label">Jump:</span>
                <a class="footer-top-link" href="#tri-needs">Needs (<?= komodo_e((string) $needsC) ?>)</a>
                <span class="market-md-related-sep" aria-hidden="true">·</span>
                <a class="footer-top-link" href="#tri-window">Windows (<?= komodo_e((string) $windowC) ?>)</a>
                <span class="market-md-related-sep" aria-hidden="true">·</span>
                <a class="footer-top-link" href="#tri-hist">Lineage (<?= komodo_e((string) $histC) ?>)</a>
                <span class="market-md-related-sep" aria-hidden="true">·</span>
                <a class="footer-top-link" href="#tri-spec">Callouts (<?= komodo_e((string) $specC) ?>)</a>
                <span class="market-md-related-sep" aria-hidden="true">·</span>
                <a class="footer-top-link" href="#tri-full-plan">Full plan</a>
            </nav>
        </aside>

        <?php if ($md['available'] && $showNextBatchAside) { ?>
            <aside class="panel-nested panel-phase--inset triage-next-batch" aria-labelledby="triage-next-batch-heading">
                <h3 id="triage-next-batch-heading" class="subsection-heading subsection-heading-tight">Next download batch (event-linked)</h3>
                <p class="compact-note">Unresolved event-linked plan rows only. <strong>Normal next downloads</strong> are typical vendor CSV targets. <strong>Older historical coverage</strong> means bars exist but the first trade is still after the suggested window start (beyond ±<?= (int) KOMODO_TRIAGE_WINDOW_SLACK_DAYS ?> calendar-day slack) — extend history backward. <strong>Special-source exceptions</strong> (e.g. OTC ADR) are not standard download rows. Prioritized by lineage-style notes, event count, status, then ticker — not a checklist.</p>
                <?php foreach ($batchSpecialSource as $sx) {
                    $sym = (string) ($sx['ticker_symbol'] ?? '');
                    if ($sym === '') {
                        continue;
                    }
                    ?>
                <p class="compact-note triage-special-source-exception"><strong>Special-source exception:</strong> <code class="inline-code"><?= komodo_e($sym) ?></code> requires alternate OTC ADR historical source.</p>
                <?php } ?>
                <?php if ($batchNormal !== []) { ?>
                <h4 class="subsection-heading subsection-heading-tight triage-next-batch-subhead" id="tri-nb-normal">Normal next downloads</h4>
                <?php
                $komodo_nb_rows = $batchNormal;
                require __DIR__ . '/../partials/market_import_triage_next_batch_list.php';
                ?>
                <?php } ?>
                <?php if ($batchOlderHistory !== []) { ?>
                <h4 class="subsection-heading subsection-heading-tight triage-next-batch-subhead" id="tri-nb-older">Needs older historical coverage</h4>
                <p class="compact-note triage-next-batch-hint">Series is started but does not reach the early side of the suggested window — add older bars (e.g. missing years at the front of the plan span), then re-import.</p>
                <?php
                $komodo_nb_rows = $batchOlderHistory;
                require __DIR__ . '/../partials/market_import_triage_next_batch_list.php';
                ?>
                <?php } ?>
            </aside>
        <?php } ?>

        <section class="panel-nested panel-phase--inset queue-section queue-section--triage" aria-labelledby="tri-needs">
            <h3 id="tri-needs" class="subsection-heading">Needs price data <span class="triage-section-count"><?= komodo_e((string) $needsC) ?></span></h3>
            <p class="compact-note triage-needs-total-breakdown"><strong><?= komodo_e((string) $needsC) ?> total</strong> — <?= komodo_e((string) $needsElC) ?> event-linked, <?= komodo_e((string) $needsCompC) ?> comparison/control.</p>
            <p class="compact-note">No rows in <code class="inline-code">security_daily_prices</code> yet — primary &ldquo;find / download / import&rdquo; list. If a symbol is also in <a href="#tri-hist">lineage</a>, resolve the identifier first.</p>

            <h4 class="subsection-heading subsection-heading-tight triage-subheading" id="tri-needs-el">Event-linked <span class="triage-section-count triage-section-count--sub"><?= komodo_e((string) $needsElC) ?></span></h4>
            <p class="compact-note">Companies tied to dataset events — pull these first.</p>
            <?php
            $komodo_triage_rows = $needsEventLinked;
            $komodo_triage_mode = 'needs';
            $komodo_triage_aria_label = 'Event-linked securities needing initial price import';
            $komodo_triage_empty_html = komodo_e('No event-linked tickers in this bucket.');
            require __DIR__ . '/../partials/market_import_triage_table.php';
            ?>

            <details class="triage-needs-comparison-wrap">
                <summary class="triage-needs-comparison-summary">Comparison / control <span class="triage-section-count triage-section-count--sub"><?= komodo_e((string) $needsCompC) ?></span></summary>
                <p class="compact-note">Benchmark or control names — lower priority than event-linked.</p>
                <?php
                $komodo_triage_rows = $needsComparison;
                $komodo_triage_mode = 'needs';
                $komodo_triage_aria_label = 'Comparison or control securities needing initial price import';
                $komodo_triage_empty_html = komodo_e('No comparison/control tickers in this bucket.');
                require __DIR__ . '/../partials/market_import_triage_table.php';
                ?>
            </details>
        </section>

        <section class="panel-nested panel-phase--inset queue-section queue-section--triage" aria-labelledby="tri-window">
            <h3 id="tri-window" class="subsection-heading">Loaded but missing window coverage <span class="triage-section-count"><?= komodo_e((string) $windowC) ?></span></h3>
            <p class="compact-note triage-window-lead">Prices exist, but loaded first/last bars miss the plan window by <strong>more than <?= (int) KOMODO_TRIAGE_WINDOW_SLACK_DAYS ?> calendar days</strong> at that end. Use the row for the next import; open <strong>View company detail</strong> for plan vs loaded span, notes, lineage, and <?= komodo_e(KOMODO_ALIGNED_DAILY_DENSITY_LABEL) ?>. Full proof: <a class="footer-top-link" href="index.php?page=price-audit#audit-aligned-density-heading">Price Audit</a>.</p>
            <?php
            $komodo_triage_rows = $windowGaps;
            $komodo_triage_mode = 'window';
            $komodo_triage_aria_label = 'Securities with loaded prices but window gaps';
            $komodo_triage_empty_html = komodo_e('No window gaps flagged for loaded series.');
            require __DIR__ . '/../partials/market_import_triage_table.php';
            ?>
        </section>

        <section class="panel-nested panel-phase--inset queue-section queue-section--triage" aria-labelledby="tri-hist">
            <h3 id="tri-hist" class="subsection-heading">Historical ticker / special handling <span class="triage-section-count"><?= komodo_e((string) $histC) ?></span></h3>
            <p class="compact-note"><code class="inline-code">import_notes</code> flag historical tickers, renames, or continuity risk (e.g. FB vs META). <?= komodo_e(komodo_fb_meta_lineage_import_policy_paragraph()) ?></p>
            <?php
            $komodo_triage_rows = $historical;
            $komodo_triage_mode = 'historical';
            $komodo_triage_aria_label = 'Historical ticker and lineage handling';
            $komodo_triage_empty_html = komodo_e('No lineage-style notes in the current plan.');
            require __DIR__ . '/../partials/market_import_triage_table.php';
            ?>
        </section>

        <section class="panel-nested panel-phase--inset queue-section queue-section--triage" aria-labelledby="tri-spec">
            <h3 id="tri-spec" class="subsection-heading">Special import notes <span class="triage-section-count"><?= komodo_e((string) $specC) ?></span></h3>
            <p class="compact-note">IPO, listing, availability, verify, or source checks in <code class="inline-code">import_notes</code> (excluding rows already in <a href="#tri-hist">lineage</a>). Not-started rows with only these callouts land here instead of &ldquo;Needs price data.&rdquo;</p>

            <h4 class="subsection-heading subsection-heading-tight triage-subheading" id="tri-spec-el">Event-linked <span class="triage-section-count triage-section-count--sub"><?= komodo_e((string) count($specialNotesEl)) ?></span></h4>
            <p class="compact-note">Dataset-event tickers with operational callouts — review before bulk download.</p>
            <?php
            $komodo_triage_rows = $specialNotesEl;
            $komodo_triage_mode = 'special_notes';
            $komodo_triage_aria_label = 'Event-linked special import notes';
            $komodo_triage_empty_html = komodo_e('No event-linked rows in this bucket.');
            require __DIR__ . '/../partials/market_import_triage_table.php';
            ?>

            <h4 class="subsection-heading subsection-heading-tight triage-subheading" id="tri-spec-comp">Comparison / control <span class="triage-section-count triage-section-count--sub"><?= komodo_e((string) count($specialNotesComp)) ?></span></h4>
            <p class="compact-note">Benchmark or control names with the same style of callouts.</p>
            <?php
            $komodo_triage_rows = $specialNotesComp;
            $komodo_triage_mode = 'special_notes';
            $komodo_triage_aria_label = 'Comparison or control special import notes';
            $komodo_triage_empty_html = komodo_e('No comparison/control rows in this bucket.');
            require __DIR__ . '/../partials/market_import_triage_table.php';
            ?>
        </section>

        <?php if ($totalPlan > 0) { ?>
            <p class="compact-note" id="tri-full-plan">Full <code class="inline-code">vw_market_data_import_plan</code> table (<?= komodo_e((string) $totalPlan) ?> securities, including window-complete rows): <a class="footer-top-link" href="index.php?page=price-audit#full-plan-heading">Price Audit</a>.</p>
        <?php } ?>

    <?php } ?>
</section>
