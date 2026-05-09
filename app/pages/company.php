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

?>
<section class="panel shell-section company-page" aria-labelledby="company-heading">
    <nav class="market-md-related" aria-label="Back links">
        <a class="footer-top-link" href="index.php?page=companies">← Back to Companies</a>
    </nav>

    <div class="companies-hero company-hero" aria-label="Company detail header">
        <div class="companies-hero__left">
            <h2 id="company-heading" class="companies-hero__title"><?= $profile ? komodo_e((string) ($profile['display_name'] ?? 'Company')) : 'Company' ?></h2>
            <p class="compact-note company-hero__portal-note">Read-only cybersecurity–finance drilldown — not trading or investment advice.</p>
            <?php if ($profile) { ?>
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
            <?php } else { ?>
                <?php if (!empty($cd['partial'])) { ?>
                    <span class="badge badge--primary badge--degraded">Partial</span>
                <?php } else { ?>
                    <span class="badge badge--primary badge--live">Live</span>
                <?php } ?>
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
        <h3 class="subsection-heading" id="company-securities">Securities / tickers</h3>
        <p class="compact-note">This section is security/ticker-grain. A company may have multiple securities or historical tickers in scope.</p>
        <div class="table-scroll">
            <table class="data-table data-table--sticky data-table--dense" aria-labelledby="company-securities">
                <thead>
                    <tr>
                        <th scope="col">Ticker</th>
                        <th scope="col">Security</th>
                        <th scope="col">Exchange</th>
                        <th scope="col">Active</th>
                        <th scope="col">Role</th>
                        <th scope="col" class="num">Events</th>
                        <th scope="col">Suggested window</th>
                        <th scope="col">Price coverage</th>
                        <th scope="col">Notes</th>
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
                        $first = komodo_normalize_date_string($s['first_price_date'] ?? null);
                        $last = komodo_normalize_date_string($s['last_price_date'] ?? null);
                        $span = $pr > 0 && $first && $last ? ($first . ' → ' . $last) : 'No prices loaded';
                        [$noteDisp, $noteFull, $noteT] = komodo_note_preview(isset($s['import_notes']) ? (string) $s['import_notes'] : '', 140);
                        $sd = komodo_normalize_date_string($s['suggested_import_start_date'] ?? null) ?? '';
                        $ed = komodo_normalize_date_string($s['suggested_import_end_date'] ?? null) ?? '';
                        $window = ($sd !== '' && $ed !== '') ? ($sd . ' → ' . $ed) : '—';
                        ?>
                        <tr title="<?= komodo_e('security_id=' . (string) ($s['security_id'] ?? '')) ?>">
                            <td><code class="inline-code"><?= komodo_e((string) ($s['ticker_symbol'] ?? '')) ?></code></td>
                            <td><?= komodo_e((string) ($s['security_name'] ?? '')) ?></td>
                            <td class="compact-note"><?= komodo_e((string) ($s['exchange_code'] ?? '—')) ?></td>
                            <td><?= !empty($s['is_active']) ? 'Yes' : 'No' ?></td>
                            <td>
                                <div class="label-stack">
                                    <span class="label-primary"<?= $roleDesc ? ' title="' . komodo_e($roleDesc) . '"' : '' ?>><?= komodo_e($roleLabel) ?></span>
                                    <?php if ($roleKey !== '') { ?>
                                        <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($roleKey) ?></code></span>
                                    <?php } ?>
                                </div>
                            </td>
                            <td class="num"><?= komodo_e((string) ((int) ($s['security_event_count'] ?? ($s['linked_event_count'] ?? 0)))) ?></td>
                            <td class="compact-note"><?= komodo_e($window) ?></td>
                            <td>
                                <div class="label-stack">
                                    <span class="label-primary">
                                        <span class="coverage-badge <?= komodo_e($covClass) ?>"<?= $covDesc ? ' title="' . komodo_e($covDesc) . '"' : '' ?>><?= komodo_e($covLabel) ?></span>
                                        <span class="compact-note">· <?= komodo_e((string) $pr) ?> rows</span>
                                    </span>
                                    <span class="label-secondary"><?= komodo_e($span) ?></span>
                                </div>
                            </td>
                            <td class="compact-note"><?php if ($noteDisp !== '') { ?>
                                <span<?= $noteT ? ' title="' . $noteFull . '"' : '' ?>><?= $noteDisp ?></span>
                            <?php } else { echo '—'; } ?></td>
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
                <table class="data-table data-table--sticky data-table--dense" aria-labelledby="company-events">
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
                                <td><?= komodo_e((string) ($e['event_name'] ?? '')) ?></td>
                                <td class="compact-note"><?= komodo_e(komodo_format_identifier((string) ($e['event_type'] ?? '—'))) ?></td>
                                <td class="compact-note"><?= komodo_e((string) ($e['severity_level'] ?? '—')) ?></td>
                                <td class="compact-note"><?= komodo_e((string) ($e['confidence_level'] ?? '—')) ?></td>
                                <td class="compact-note"><?= komodo_e($disc) ?></td>
                                <td class="compact-note"><?= komodo_e($ftd) ?></td>
                                <td class="compact-note">
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
            <p class="compact-note">This company’s tickers are evaluated against the suggested import windows in <code class="inline-code inline-code--subtle">vw_market_data_import_plan</code>.</p>
            <?php if ($summary) { ?>
                <ul class="market-insight-metrics" aria-label="Company market coverage metrics">
                    <li><strong><?= komodo_e((string) ($summary['total_securities'] ?? 0)) ?></strong> tickers</li>
                    <li><strong><?= komodo_e((string) ($summary['securities_with_prices'] ?? 0)) ?></strong> with prices</li>
                    <li><strong><?= komodo_e((string) ($summary['securities_without_prices'] ?? 0)) ?></strong> not started</li>
                </ul>
                <p class="market-insight-bar__next"><strong>Next:</strong> Import index prices first, then this company’s event-linked tickers.</p>
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

