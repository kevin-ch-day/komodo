<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

/** @var array<string, mixed> */
$companies = $ctx['companies'] ?? [
    'available' => false,
    'partial' => false,
    'mode' => 'offline',
    'message' => 'Company exploration was not loaded.',
    'errors' => [],
    'summary' => null,
    'sector_summary' => [],
    'industry_summary' => [],
    'rows' => [],
    'attention' => [
        'event_linked_without_prices' => [],
        'event_linked_window_issues' => [],
        'import_notes' => [],
        'multiple_event_companies' => [],
        'missing_sector_or_industry' => [],
    ],
];

$sum = is_array($companies['summary'] ?? null) ? $companies['summary'] : null;
$att = is_array($companies['attention'] ?? null) ? $companies['attention'] : [];

$pageParam = $_GET['companies_page'] ?? null;
$perPageParam = $_GET['per_page'] ?? null;

$companiesPage = 1;
if (is_string($pageParam) && ctype_digit($pageParam)) {
    $p = (int) $pageParam;
    $companiesPage = $p > 0 ? $p : 1;
}

$allowedPerPage = [10, 15, 20];
$perPage = 15;
if (is_string($perPageParam) && ctype_digit($perPageParam)) {
    $pp = (int) $perPageParam;
    if (in_array($pp, $allowedPerPage, true)) {
        $perPage = $pp;
    }
}

$buildCompaniesUrl = static function (int $page, int $perPage): string {
    $page = $page > 0 ? $page : 1;
    $qs = http_build_query([
        'page' => 'companies',
        'companies_page' => $page,
        'per_page' => $perPage,
    ]);

    return 'index.php?' . $qs;
};

/** @param list<array<string, mixed>> $rows */
$paginate = static function (array $rows, int $page, int $perPage): array {
    $total = count($rows);
    $perPage = max(1, $perPage);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $totalPages);
    $offset = ($page - 1) * $perPage;
    $slice = array_slice($rows, $offset, $perPage);
    $start = $total === 0 ? 0 : $offset + 1;
    $end = $total === 0 ? 0 : min($offset + $perPage, $total);

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'start' => $start,
        'end' => $end,
        'rows' => $slice,
    ];
};

$elNoPx = (array) ($att['event_linked_without_prices'] ?? []);
$elWin = (array) ($att['event_linked_window_issues'] ?? []);
/** @var array<string, mixed> $sumSafe */
$sumSafe = is_array($sum) ? $sum : [];

?>
<section class="panel shell-section companies-page" aria-labelledby="companies-heading">
    <div class="companies-hero" aria-label="Companies listing header">
        <div class="companies-hero__left">
            <h2 id="companies-heading" class="companies-hero__title">Companies</h2>
            <p class="companies-hero__subtitle">Company and security catalog for the cybersecurity–finance event-study dataset. Price readiness lives on <a class="footer-top-link" href="index.php?page=price-coverage">Coverage Summary</a>; CSV and import work on <a class="footer-top-link" href="index.php?page=price-import-queue">Price Worklist</a>; full plan QA on <a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a>.</p>
            <?php if (!$companies['available']) { ?>
                <span class="badge badge--primary badge--offline">Offline</span>
            <?php } else { ?>
                <?php if (!empty($companies['partial'])) { ?>
                    <span class="badge badge--primary badge--degraded">Partial</span>
                <?php } else { ?>
                    <span class="badge badge--primary badge--live">Live</span>
                <?php } ?>
            <?php } ?>
        </div>
        <div class="companies-hero__right" aria-label="Catalog snapshot">
            <?php if ($companies['available'] && $sum) {
                $elTotal = (int) ($sum['event_linked_securities'] ?? 0);
                $elNeed = (int) ($sum['event_linked_needing_price_attention_count'] ?? 0);
                ?>
                <p class="companies-hero__signal"><strong><?= komodo_e((string) ($sum['total_securities'] ?? '—')) ?></strong> securities in the import plan.</p>
                <p class="companies-hero__signal"><strong><?= komodo_e((string) $elTotal) ?></strong> event-linked securities in scope; <strong><?= komodo_e((string) $elNeed) ?></strong> still need price attention.</p>
            <?php } else { ?>
                <p class="companies-hero__signal">Company catalog loads in live DB mode.</p>
            <?php } ?>
        </div>
    </div>

    <?php if (!$companies['available']) { ?>
        <section class="panel-nested panel-phase--inset" aria-label="Companies offline state">
            <p class="section-lead"><?= komodo_e((string) $companies['message']) ?></p>
            <p class="compact-note">Connect MariaDB via <code class="inline-code">app/config/local.php</code> to load live company/security rows.</p>
        </section>
    <?php } else { ?>
        <?php if (!empty($companies['errors'])) { ?>
            <ul class="market-md-error-list compact-note" role="list" aria-label="Companies warnings">
                <?php foreach ((array) $companies['errors'] as $err) { ?>
                    <li class="compact-note"><?= komodo_e((string) $err) ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <div class="companies-kpi-strip" aria-label="Companies KPI strip">
            <div class="companies-kpi"><span class="companies-kpi__label">Companies</span><span class="companies-kpi__value"><?= $sum ? komodo_e((string) ($sum['total_companies'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label">Securities</span><span class="companies-kpi__value"><?= $sum ? komodo_e((string) ($sum['total_securities'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label" title="<?= komodo_e(komodo_describe('event_linked_security', 'role') ?? '') ?>">Event-linked securities</span><span class="companies-kpi__value"><?= $sum ? komodo_e((string) ($sum['event_linked_securities'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label" title="<?= komodo_e(komodo_describe('comparison_or_unlinked_security', 'role') ?? '') ?>">Comparison / control</span><span class="companies-kpi__value"><?= $sum ? komodo_e((string) ($sum['comparison_or_unlinked_securities'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label">Companies with events</span><span class="companies-kpi__value"><?= $sum ? komodo_e((string) ($sum['companies_with_events'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label">Missing classification</span><span class="companies-kpi__value"><?= $sum ? komodo_e((string) ($sum['missing_classification_companies'] ?? '—')) : '—' ?></span></div>
        </div>

        <aside class="panel-nested panel-muted companies-attention-strip" role="region" aria-labelledby="companies-attention-heading">
            <h3 id="companies-attention-heading" class="subsection-heading subsection-heading-tight">Attention</h3>
            <ul class="compact-note companies-attention-strip__list">
                <li><strong>Event-linked, no price rows (<?= komodo_e((string) ($sumSafe['event_linked_without_prices_count'] ?? count($elNoPx))) ?>):</strong> <?php
                if ($elNoPx === []) {
                    ?>None.<?php
                } else {
                    $tickers = array_map(static fn ($it) => (string) ($it['ticker_symbol'] ?? ''), $elNoPx);
                    echo komodo_e(implode(', ', array_filter($tickers)));
                } ?></li>
                <li><strong>Event-linked, window not covered (prices present, span/slack issue) (<?= komodo_e((string) count($elWin)) ?>):</strong> <?php
                if ($elWin === []) {
                    ?>None.<?php
                } else {
                    $parts = [];
                    foreach ($elWin as $wi) {
                        $t = (string) ($wi['ticker_symbol'] ?? '');
                        $st = (string) ($wi['coverage_status'] ?? '');
                        $parts[] = $t !== '' ? ($t . ' (' . $st . ')') : $st;
                    }
                    echo komodo_e(implode('; ', $parts));
                } ?></li>
            </ul>
            <p class="compact-note"><strong>FB / META (lineage, not a missing-price issue):</strong> Event-linked <code class="inline-code">FB</code> can be window-complete; vendors may label historical Facebook files <code class="inline-code">META</code> — map pre–June 2022 rows to the <code class="inline-code">FB</code> security record when events tie to <code class="inline-code">FB</code>. Policy: <a class="footer-top-link" href="index.php?page=price-coverage">Coverage Summary</a> · <a class="footer-top-link" href="index.php?page=price-audit#lineage-heading">Price Audit (lineage)</a>.</p>
            <p class="compact-note"><strong><?= komodo_e((string) ($sumSafe['companies_with_multiple_events'] ?? 0)) ?></strong> companies have repeated cyber events (see catalog for which).</p>
            <p class="compact-note">Next steps for prices: <a class="footer-top-link" href="index.php?page=price-import-queue">Price Worklist</a> · Readiness: <a class="footer-top-link" href="index.php?page=price-coverage">Coverage Summary</a> · Raw plan / notes: <a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a>.</p>
        </aside>

        <details class="market-md-collapsible companies-tech-sources">
            <summary>Technical sources (audit)</summary>
            <ul class="market-insight-checklist compact-note">
                <li><span class="label-primary">Driving rowset</span> <span class="label-secondary"><code class="inline-code inline-code--subtle">vw_market_data_import_plan</code></span></li>
                <li><span class="label-primary">Company metadata</span> <span class="label-secondary"><code class="inline-code inline-code--subtle">companies</code>, <code class="inline-code inline-code--subtle">sectors</code>, <code class="inline-code inline-code--subtle">industries</code></span></li>
                <li><span class="label-primary">Event linkage</span> <span class="label-secondary"><code class="inline-code inline-code--subtle">cyber_event_securities</code></span></li>
            </ul>
        </details>

        <?php
        $sectors = (array) ($companies['sector_summary'] ?? []);
        $industries = (array) ($companies['industry_summary'] ?? []);
        $sectorMax = 1;
        foreach ($sectors as $row) {
            $sectorMax = max($sectorMax, (int) ($row['count'] ?? 0));
        }
        $industryMax = 1;
        foreach ($industries as $row) {
            $industryMax = max($industryMax, (int) ($row['count'] ?? 0));
        }
        ?>
        <div class="companies-distribution" aria-label="Sector and industry distribution">
            <section class="panel-nested panel-muted companies-distribution__panel" aria-labelledby="sector-snap">
                <h3 id="sector-snap" class="subsection-heading subsection-heading-tight">Sector distribution</h3>
                <?php if ($sectors === []) { ?>
                    <p class="compact-note">—</p>
                <?php } else { ?>
                    <ul class="companies-dist-list" aria-label="Sector distribution list">
                        <?php foreach ($sectors as $row) {
                            $label = (string) ($row['label'] ?? '—');
                            $count = (int) ($row['count'] ?? 0);
                            $pct = (int) round(100 * ($count / $sectorMax));
                            ?>
                            <li class="companies-dist-row">
                                <span class="companies-dist-row__label"><?= komodo_e($label) ?></span>
                                <span class="companies-dist-row__bar" aria-hidden="true"><span class="companies-dist-row__barFill" style="width: <?= komodo_e((string) $pct) ?>%"></span></span>
                                <span class="companies-dist-row__count"><?= komodo_e((string) $count) ?></span>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </section>
            <section class="panel-nested panel-muted companies-distribution__panel" aria-labelledby="industry-snap">
                <h3 id="industry-snap" class="subsection-heading subsection-heading-tight">Industry distribution</h3>
                <?php if ($industries === []) { ?>
                    <p class="compact-note">—</p>
                <?php } else { ?>
                    <ul class="companies-dist-list" aria-label="Industry distribution list">
                        <?php foreach ($industries as $row) {
                            $label = (string) ($row['label'] ?? '—');
                            $count = (int) ($row['count'] ?? 0);
                            $pct = (int) round(100 * ($count / $industryMax));
                            ?>
                            <li class="companies-dist-row">
                                <span class="companies-dist-row__label"><?= komodo_e($label) ?></span>
                                <span class="companies-dist-row__bar" aria-hidden="true"><span class="companies-dist-row__barFill" style="width: <?= komodo_e((string) $pct) ?>%"></span></span>
                                <span class="companies-dist-row__count"><?= komodo_e((string) $count) ?></span>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </section>
        </div>

        <h3 class="subsection-heading" id="companies-table">Company / security table</h3>
        <p class="compact-note">Ticker-grain catalog (a company may appear more than once). Coverage uses the short <strong>Span OK</strong> label when first/last loaded bars match the plan window (± slack) — trading-day density is a separate check on <a class="footer-top-link" href="index.php?page=price-audit">Price Audit</a>. <strong>Flags</strong> summarize plan cues; full <code class="inline-code">import_notes</code> text is on hover and in audit tables. For CSV work use <a class="footer-top-link" href="index.php?page=price-import-queue">Price Worklist</a>. Hover a company name for internal ids.</p>
        <?php
        /** @var list<array<string, mixed>> $allRows */
        $allRows = (array) ($companies['rows'] ?? []);
        $pg = $paginate($allRows, $companiesPage, $perPage);
        ?>
        <?php if ($pg['total'] > 0) { ?>
            <div class="companies-table-controls" aria-label="Companies table controls">
                <p class="compact-note">Showing <?= komodo_e((string) $pg['start']) ?>–<?= komodo_e((string) $pg['end']) ?> of <?= komodo_e((string) $pg['total']) ?> securities.</p>
                <div class="companies-pager">
                    <?php if ($pg['page'] > 1) { ?>
                        <a class="footer-top-link" href="<?= komodo_e($buildCompaniesUrl($pg['page'] - 1, $pg['per_page'])) ?>" aria-label="Previous page">Prev</a>
                    <?php } else { ?>
                        <span class="companies-pager__disabled" aria-hidden="true">Prev</span>
                    <?php } ?>
                    <span class="companies-pager__meta">Page <?= komodo_e((string) $pg['page']) ?> of <?= komodo_e((string) $pg['total_pages']) ?></span>
                    <?php if ($pg['page'] < $pg['total_pages']) { ?>
                        <a class="footer-top-link" href="<?= komodo_e($buildCompaniesUrl($pg['page'] + 1, $pg['per_page'])) ?>" aria-label="Next page">Next</a>
                    <?php } else { ?>
                        <span class="companies-pager__disabled" aria-hidden="true">Next</span>
                    <?php } ?>
                </div>
                <div class="companies-per-page" aria-label="Rows per page">
                    <span class="compact-note">Per page:</span>
                    <?php foreach ($allowedPerPage as $pp) {
                        $isCur = $pp === (int) $pg['per_page'];
                        ?>
                        <?php if ($isCur) { ?>
                            <span class="companies-per-page__current" aria-current="true"><?= komodo_e((string) $pp) ?></span>
                        <?php } else { ?>
                            <a class="footer-top-link" href="<?= komodo_e($buildCompaniesUrl(1, $pp)) ?>"><?= komodo_e((string) $pp) ?></a>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
        <div class="table-scroll">
            <table class="data-table data-table--sticky data-table--dense data-table--sticky-left data-table--labeled-mobile" aria-labelledby="companies-table">
                <thead>
                    <tr>
                        <th scope="col">Company / ticker</th>
                        <th scope="col">Sector / industry</th>
                        <th scope="col">Role</th>
                        <th scope="col" class="num">Events</th>
                        <th scope="col">Price (plan)</th>
                        <th scope="col">Flags</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array) ($pg['rows'] ?? []) as $r) {
                        $roleKey = (string) ($r['price_import_role'] ?? '');
                        $roleLabel = komodo_label_safe($roleKey, 'role');
                        $roleDesc = $roleKey !== '' ? komodo_describe($roleKey, 'role') : null;

                        $covKey = (string) ($r['coverage_status'] ?? 'unavailable_or_error');
                        $covLabel = komodo_coverage_catalog_label($covKey);
                        $covDesc = komodo_describe($covKey, 'coverage_status');
                        $covClass = komodo_coverage_badge_css($covKey);

                        $rawImpNote = isset($r['import_notes']) ? trim((string) $r['import_notes']) : '';
                        $notePlainFull = $rawImpNote !== '' ? trim(preg_replace('/\s+/', ' ', strip_tags($rawImpNote))) : '';
                        $catalogFlags = komodo_company_security_catalog_flags($r);
                        $flagsTitle = $notePlainFull !== ''
                            ? ' title="' . komodo_e($notePlainFull) . '"'
                            : '';

                        $secEv = (int) ($r['security_event_count'] ?? 0);
                        $planEv = (int) ($r['linked_event_count'] ?? 0);
                        $events = max($secEv, $planEv);
                        $companyName = (string) ($r['company_name'] ?? ($r['legal_name'] ?? ''));
                        $ticker = (string) ($r['ticker_symbol'] ?? '');
                        $companyId = (string) ($r['company_id'] ?? '');
                        $securityId = (string) ($r['security_id'] ?? '');

                        $sector = (string) ($r['sector_name'] ?? '—');
                        $industry = (string) ($r['industry_name'] ?? '—');
                        $exchange = (string) ($r['exchange_code'] ?? '');
                        $idsTitle = 'company_id=' . $companyId . '; security_id=' . $securityId;
                        ?>
                        <tr>
                            <td data-label="Company / ticker">
                                <div class="label-stack">
                                    <span class="label-primary">
                                        <a class="companies-link" href="index.php?page=company&company_id=<?= komodo_e($companyId) ?>" title="<?= komodo_e($idsTitle) ?>"><?= komodo_e($companyName) ?></a>
                                    </span>
                                    <span class="label-secondary">
                                        <code class="inline-code"><?= komodo_e($ticker) ?></code>
                                        <?php if ($exchange !== '') { ?>
                                            <span class="compact-note"><?= komodo_e($exchange) ?></span>
                                        <?php } ?>
                                    </span>
                                </div>
                            </td>
                            <td data-label="Sector / industry">
                                <div class="label-stack">
                                    <span class="label-primary"><?= komodo_e($sector) ?></span>
                                    <span class="label-secondary"><?= komodo_e($industry) ?></span>
                                </div>
                            </td>
                            <td data-label="Role">
                                <span class="label-primary"<?= $roleDesc ? ' title="' . komodo_e($roleDesc . ($roleKey !== '' ? ' (' . $roleKey . ')' : '')) . '"' : '' ?>><?= komodo_e($roleLabel) ?></span>
                            </td>
                            <td class="num" data-label="Events"><?= komodo_e((string) $events) ?></td>
                            <td data-label="Price (plan)">
                                <span class="coverage-badge <?= komodo_e($covClass) ?>"<?= $covDesc ? ' title="' . komodo_e($covDesc) . '"' : '' ?>><?= komodo_e($covLabel) ?></span>
                            </td>
                            <td class="companies-flags-cell compact-note" data-label="Flags"<?= $flagsTitle ?>><?php if ($catalogFlags === []) { ?>
                                —
                            <?php } else { ?>
                                <ul class="companies-flag-list" role="list">
                                    <?php foreach ($catalogFlags as $fl) { ?>
                                        <li><span class="company-flag-chip"><?= komodo_e($fl['label']) ?></span></li>
                                    <?php } ?>
                                </ul>
                            <?php } ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    <?php } ?>
</section>
