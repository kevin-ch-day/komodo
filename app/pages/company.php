<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

/** @var array<string, mixed> */
$cd = $ctx['company'] ?? [
    'available' => false,
    'partial' => false,
    'mode' => 'offline',
    'message' => 'Company detail was not loaded.',
    'errors' => [],
    'not_found' => false,
    'company_id' => 0,
    'profile' => null,
    'securities' => [],
    'events' => [],
    'summary' => null,
    'trace_sources' => [],
];

$profile = is_array($cd['profile'] ?? null) ? $cd['profile'] : null;
$summary = is_array($cd['summary'] ?? null) ? $cd['summary'] : null;
$securities = (array) ($cd['securities'] ?? []);
$events = (array) ($cd['events'] ?? []);
$trace = (array) ($cd['trace_sources'] ?? []);

$companyNotFound = !empty($cd['not_found']);
$heroTitle = 'Company';
if ($companyNotFound) {
    $heroTitle = 'Company not found';
} elseif ($profile !== null) {
    $heroTitle = (string) ($profile['display_name'] ?? 'Company');
}

?>
<section class="panel shell-section company-page" aria-labelledby="company-heading">
    <nav class="market-md-related" aria-label="Back links">
        <a class="footer-top-link" href="index.php?page=companies">← Back to Companies</a>
    </nav>

    <div class="companies-hero company-hero" aria-label="Company detail header">
        <div class="companies-hero__left">
            <h2 id="company-heading" class="companies-hero__title"><?= komodo_e($heroTitle) ?></h2>
            <p class="compact-note company-hero__portal-note">Read-only cybersecurity–finance drilldown — not trading or investment advice.</p>
            <?php if ($companyNotFound) { ?>
                <p class="companies-hero__subtitle">No company matches <code class="inline-code">company_id=<?= komodo_e((string) ($cd['company_id'] ?? '')) ?></code> in the catalog.</p>
            <?php } elseif ($profile) { ?>
                <p class="companies-hero__subtitle">
                    <?= komodo_e((string) ($profile['legal_name'] ?? '')) ?>
                    <?php if (($profile['sector_name'] ?? '') !== '' || ($profile['industry_name'] ?? '') !== '') { ?>
                        · <?= komodo_e((string) ($profile['sector_name'] ?? '—')) ?> / <?= komodo_e((string) ($profile['industry_name'] ?? '—')) ?>
                    <?php } ?>
                </p>
                <div class="company-meta-row">
                    <span class="label-secondary"><code class="inline-code inline-code--subtle">company_id=<?= komodo_e((string) ($profile['company_id'] ?? $cd['company_id'])) ?></code></span>
                    <?php if (($profile['company_role'] ?? '') !== '') { ?>
                        <?php
                        $crKey = (string) $profile['company_role'];
                        $crLabel = komodo_label_safe($crKey, 'company_role');
                        $crDesc = komodo_describe($crKey, 'company_role');
                        ?>
                        <div class="label-stack">
                            <span class="label-primary"<?= $crDesc ? ' title="' . komodo_e($crDesc) . '"' : '' ?>><?= komodo_e($crLabel) ?></span>
                            <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($crKey) ?></code></span>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <p class="companies-hero__subtitle">Read-only company drilldown.</p>
            <?php } ?>

            <?php if (!$cd['available']) { ?>
                <span class="badge badge--primary badge--offline">Offline</span>
            <?php } elseif ($companyNotFound) { ?>
                <span class="badge badge--primary badge--missing">Not found</span>
            <?php } elseif (!empty($cd['partial'])) { ?>
                <span class="badge badge--primary badge--degraded">Partial</span>
            <?php } else { ?>
                <span class="badge badge--primary badge--live">Live</span>
            <?php } ?>
        </div>
        <div class="companies-hero__right" aria-label="Company drilldown status">
            <p class="companies-hero__signal"><?= komodo_e((string) $cd['message']) ?></p>
            <?php if (!empty($cd['errors'])) { ?>
                <ul class="market-md-error-list compact-note">
                    <?php foreach ((array) $cd['errors'] as $err) { ?>
                        <li><?= komodo_e((string) $err) ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </div>

    <?php if (!$cd['available']) { ?>
        <section class="panel-nested panel-phase--inset" aria-label="Company offline state">
            <p class="section-lead"><?= komodo_e((string) $cd['message']) ?></p>
            <p class="compact-note">This drilldown requires a live MariaDB connection.</p>
        </section>
    <?php } elseif (!empty($cd['not_found'])) { ?>
        <section class="panel-nested panel-phase--inset" aria-label="Company not found">
            <p class="section-lead">Company not found.</p>
            <p class="compact-note">Return to the Companies page and choose a valid company.</p>
        </section>
    <?php } elseif ($profile === null) { ?>
        <section class="panel-nested panel-phase--inset" aria-label="Company invalid state">
            <p class="section-lead"><?= komodo_e((string) $cd['message']) ?></p>
        </section>
    <?php } else { ?>

        <?php if (($profile['notes'] ?? '') !== '') { ?>
            <details class="market-md-collapsible">
                <summary>Company notes</summary>
                <p class="compact-note"><?= komodo_e((string) $profile['notes']) ?></p>
            </details>
        <?php } ?>

        <?php /* Snapshot cards */ ?>
        <div class="companies-kpi-strip" aria-label="Company snapshot KPIs">
            <div class="companies-kpi"><span class="companies-kpi__label">Securities</span><span class="companies-kpi__value"><?= $summary ? komodo_e((string) ($summary['total_securities'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label">Linked events</span><span class="companies-kpi__value"><?= $summary ? komodo_e((string) ($summary['linked_events'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label">Event-linked</span><span class="companies-kpi__value"><?= $summary ? komodo_e((string) ($summary['event_linked_securities'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label">Missing prices</span><span class="companies-kpi__value"><?= $summary ? komodo_e((string) ($summary['securities_without_prices'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label">Import notes</span><span class="companies-kpi__value"><?= $summary ? komodo_e((string) ($summary['securities_with_import_notes'] ?? '—')) : '—' ?></span></div>
        </div>

        <?php /* Securities table */ ?>
        <h3 class="subsection-heading" id="company-securities">Market data — per security</h3>
        <p class="compact-note">Grain: <code class="inline-code">vw_market_data_import_plan</code> joined to this company’s listings. <strong>Read the table below</strong> for this company’s dates, span status, missing range, next step, import notes, and <?= komodo_e(KOMODO_ALIGNED_DAILY_DENSITY_LABEL) ?> (same trading-day model as <a class="footer-top-link" href="index.php?page=price-audit#audit-aligned-density-heading">Price Audit</a>, which stays the raw proof). <a class="footer-top-link" href="index.php?page=price-import-queue">Price Worklist</a> lists what to do next only.</p>
        <details class="market-md-collapsible company-market-field-guide">
            <summary>Field guide — common window / import patterns (SWI, DIS, FTNT, PANW, TSLA, FB/META, JBSAY)</summary>
            <ul class="compact-note company-market-field-guide__list">
                <li><strong>SWI-style gap:</strong> Event-linked security with prices that <strong>end before</strong> the plan window ends — extend daily imports through the missing range; the per-security row shows exact trailing dates.</li>
                <li><strong>DIS-style refresh:</strong> Small <strong>end</strong> gap vs the plan — refresh or widen the vendor window through the plan end; often a minor CSV extend/re-import.</li>
                <li><strong>FTNT / PANW (comparison, 2014–2017 floor):</strong> Long early plan windows for comparison names — backfill early daily bars only if that control is still in the analysis set; check import notes and <?= komodo_e(KOMODO_ALIGNED_DAILY_DENSITY_LABEL) ?> before deep history work.</li>
                <li><strong>TSLA (high-volatility comparison):</strong> Same idea — extended backfill may be optional; notes may warn about volatility/wildcard usage.</li>
                <li><strong>FB / META (lineage):</strong> Events may reference <code class="inline-code">FB</code> while files use <code class="inline-code">META</code> — map vendor rows to the correct <code class="inline-code">security_id</code>; span OK on <code class="inline-code">FB</code> does not fix source-label continuity. See <a class="footer-top-link" href="index.php?page=price-coverage">Coverage Summary</a> and <a class="footer-top-link" href="index.php?page=price-audit#lineage-heading">Price Audit (lineage)</a> for policy.</li>
                <li><strong>JBSAY (OTC ADR special source):</strong> Not a standard daily vendor pull — alternate OTC ADR historical source; plan <code class="inline-code">import_notes</code> usually call this out.</li>
            </ul>
            <p class="compact-note company-market-field-guide__footer">Proof tables, full plan rows, and technical counts stay on <a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a> — not duplicated here.</p>
        </details>
        <p class="compact-note company-securities-mobile-hint">On very narrow screens this table becomes a tall card per security; a more compact “summary first, details expandable” layout may be added later.</p>
        <div class="table-scroll">
            <table class="data-table data-table--sticky data-table--dense data-table--company-securities data-table--labeled-mobile" aria-labelledby="company-securities">
                <thead>
                    <tr>
                        <th scope="col">Security</th>
                        <th scope="col">Plan window</th>
                        <th scope="col">Loaded span</th>
                        <th scope="col">Span status</th>
                        <th scope="col">What's wrong</th>
                        <th scope="col">Missing daily range</th>
                        <th scope="col">Suggested next action</th>
                        <th scope="col"><?= komodo_e(KOMODO_ALIGNED_DAILY_DENSITY_LABEL) ?></th>
                        <th scope="col">Import notes</th>
                        <th scope="col" class="num">Linked events</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($securities as $s) {
                        $roleKey = (string) ($s['price_import_role'] ?? '');
                        $roleLabel = komodo_label_safe($roleKey, 'role');
                        $roleDesc = $roleKey !== '' ? komodo_describe($roleKey, 'role') : null;
                        $covKey = (string) ($s['coverage_status'] ?? 'unavailable_or_error');
                        $covLabel = komodo_label_safe($covKey, 'coverage_status');
                        $covDesc = komodo_describe($covKey, 'coverage_status');
                        $covClass = komodo_coverage_badge_css($covKey);
                        $pr = (int) ($s['price_rows'] ?? 0);
                        [$noteDisp, $noteFull, $noteT] = komodo_note_preview(isset($s['import_notes']) ? (string) $s['import_notes'] : '', 200);
                        $planWinHtml = komodo_html_date_window_stack(
                            $s['suggested_import_start_date'] ?? null,
                            $s['suggested_import_end_date'] ?? null
                        );
                        $loadedSpanHtml = $pr > 0
                            ? komodo_html_date_window_stack($s['first_price_date'] ?? null, $s['last_price_date'] ?? null)
                            : '';
                        $explain = komodo_company_security_worklist_explain($s);
                        $densityRow = is_array($s['aligned_daily_density'] ?? null) ? $s['aligned_daily_density'] : null;
                        $tk = (string) ($s['ticker_symbol'] ?? '');
                        $secName = (string) ($s['security_name'] ?? '');
                        $exch = (string) ($s['exchange_code'] ?? '');
                        $activeYes = ((int) ($s['is_active'] ?? 0)) === 1;
                        ?>
                        <tr title="<?= komodo_e('security_id=' . (string) ($s['security_id'] ?? '')) ?>">
                            <td data-label="Security">
                                <div class="label-stack company-security-identity">
                                    <span class="label-primary"><?= komodo_e($secName !== '' ? $secName : $tk) ?></span>
                                    <span class="label-secondary"><?php if ($tk !== '') { ?>
                                        <a class="companies-link" href="index.php?page=price-import-queue" title="Price Worklist — find <?= komodo_e($tk) ?>"><code class="inline-code"><?= komodo_e($tk) ?></code></a>
                                    <?php } else { ?>—<?php } ?>
                                        <?php if ($exch !== '') { ?>
                                            <span class="compact-note"><?= komodo_e(' · ' . $exch) ?></span>
                                        <?php } ?>
                                        <span class="compact-note"><?= komodo_e(' · ' . ($activeYes ? 'active' : 'inactive')) ?></span>
                                    </span>
                                    <span class="label-secondary"><?php if ($roleKey !== '') { ?>
                                        <span class="label-primary"<?= $roleDesc ? ' title="' . komodo_e($roleDesc) . '"' : '' ?>><?= komodo_e($roleLabel) ?></span>
                                        <code class="inline-code inline-code--subtle"><?= komodo_e($roleKey) ?></code>
                                    <?php } else { ?>—<?php } ?></span>
                                </div>
                            </td>
                            <td class="triage-date-window-stack" data-label="Plan window"><?php echo $planWinHtml; ?></td>
                            <td class="triage-date-window-stack" data-label="Loaded span"><?php if ($loadedSpanHtml !== '') {
                                echo $loadedSpanHtml;
                            } else { ?>
                                <span class="compact-note"><?= komodo_e('No bars loaded') ?></span>
                            <?php } ?></td>
                            <td data-label="Span status">
                                <div class="label-stack">
                                    <span class="coverage-badge <?= komodo_e($covClass) ?>"<?= $covDesc ? ' title="' . komodo_e($covDesc) . '"' : '' ?>><?= komodo_e($covLabel) ?></span>
                                    <span class="compact-note"><?= komodo_e((string) $pr) ?> price rows</span>
                                </div>
                            </td>
                            <td class="compact-note company-security-problem-cell" data-label="What's wrong"><?= komodo_e($explain['problem']) ?></td>
                            <td class="compact-note" data-label="Missing daily range"><?= komodo_e($explain['missing']) ?></td>
                            <td class="compact-note" data-label="Next action"><?= komodo_e($explain['next']) ?></td>
                            <td class="compact-note" data-label="<?= komodo_e(KOMODO_ALIGNED_DAILY_DENSITY_LABEL) ?>"><?php echo komodo_html_aligned_density_compact($densityRow); ?></td>
                            <td class="compact-note" data-label="Import notes"><?php if ($noteDisp !== '') { ?>
                                <span<?= $noteT ? ' title="' . $noteFull . '"' : '' ?>><?= $noteDisp ?></span>
                            <?php } else { echo '—'; } ?></td>
                            <td class="num" data-label="Linked events"><?= komodo_e((string) ((int) ($s['security_event_count'] ?? ($s['linked_event_count'] ?? 0)))) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php /* Events table */ ?>
        <h3 class="subsection-heading" id="company-events">Linked cyber events</h3>
        <?php if ($events === []) { ?>
            <p class="compact-note">No linked cyber events found through this company’s securities.</p>
        <?php } else { ?>
            <div class="table-scroll">
                <table class="data-table data-table--sticky data-table--dense data-table--labeled-mobile" aria-labelledby="company-events">
                    <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col">Type</th>
                            <th scope="col">Severity</th>
                            <th scope="col">Confidence</th>
                            <th scope="col">Disclosure date</th>
                            <th scope="col">First trading day</th>
                            <th scope="col">Readiness</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $e) {
                            $dates = is_array($e['dates'] ?? null) ? $e['dates'] : [];
                            $disc = $dates['disclosure_date'] ?? ($dates['disclosed_date'] ?? ($dates['disclosure'] ?? null));
                            $disc = $disc ? (string) $disc : '—';
                            $ready = $e['readiness'] ?? null;
                            $ftd = '—';
                            $readyText = '—';
                            $readyRaw = null;
                            if (is_array($ready)) {
                                $ftdCandidate = $ready['first_trading_day'] ?? ($ready['first_trading_date'] ?? null);
                                $ftdNorm = komodo_normalize_date_string($ftdCandidate);
                                $ftd = $ftdNorm ?? '—';
                                $readyRaw = (string) ($ready['readiness_status'] ?? ($ready['status'] ?? 'ok'));
                                $readyText = $readyRaw !== '' ? komodo_format_identifier($readyRaw) : '—';
                            }
                            ?>
                            <tr title="<?= komodo_e('cyber_event_id=' . (string) ($e['cyber_event_id'] ?? '')) ?>">
                                <td data-label="Event"><?= komodo_e((string) ($e['event_name'] ?? '')) ?></td>
                                <td class="compact-note" data-label="Type"><?= komodo_e(komodo_format_identifier((string) ($e['event_type'] ?? '—'))) ?></td>
                                <td class="compact-note" data-label="Severity"><?= komodo_e((string) ($e['severity_level'] ?? '—')) ?></td>
                                <td class="compact-note" data-label="Confidence"><?= komodo_e((string) ($e['confidence_level'] ?? '—')) ?></td>
                                <td class="compact-note" data-label="Disclosure date"><?= komodo_e($disc) ?></td>
                                <td class="compact-note" data-label="First trading day"><?= komodo_e($ftd) ?></td>
                                <td class="compact-note" data-label="Readiness">
                                    <div class="label-stack">
                                        <span class="label-primary"><?= komodo_e($readyText) ?></span>
                                        <?php if (is_string($readyRaw) && $readyRaw !== '' && $readyRaw !== $readyText) { ?>
                                            <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($readyRaw) ?></code></span>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <?php /* Market data rollup */ ?>
        <h3 class="subsection-heading" id="company-coverage">Market data coverage</h3>
        <section class="panel-nested panel-muted market-md-snapshot-card">
            <p class="compact-note">Evaluated against suggested import windows in <code class="inline-code inline-code--subtle">vw_market_data_import_plan</code> (same rules as <a class="footer-top-link" href="index.php?page=price-coverage">Coverage Summary</a>).</p>
            <?php if ($summary) { ?>
                <ul class="market-insight-metrics" aria-label="Company market coverage metrics">
                    <li><strong><?= komodo_e((string) ($summary['total_securities'] ?? 0)) ?></strong> tickers</li>
                    <li><strong><?= komodo_e((string) ($summary['securities_with_prices'] ?? 0)) ?></strong> with prices</li>
                    <li><strong><?= komodo_e((string) ($summary['securities_without_prices'] ?? 0)) ?></strong> not started</li>
                </ul>
                <p class="compact-note company-coverage-next"><strong>Where next:</strong> <a class="footer-top-link" href="index.php?page=price-import-queue">Price Worklist</a> (imports) · <a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a> (full plan &amp; density) · <a class="footer-top-link" href="index.php?page=market-data">Market Data Summary</a> (landing).</p>
            <?php } ?>
        </section>

        <details class="market-md-collapsible">
            <summary>Technical sources (audit)</summary>
            <ul class="market-insight-checklist compact-note">
                <?php foreach ($trace as $src) { ?>
                    <li><span class="label-primary"><?= komodo_e(komodo_label((string) $src, 'db_object')) ?></span> <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e((string) $src) ?></code></span></li>
                <?php } ?>
            </ul>
        </details>
    <?php } ?>
</section>

