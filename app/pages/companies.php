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

?>
<section class="panel shell-section companies-page" aria-labelledby="companies-heading">
    <div class="companies-hero" aria-label="Companies listing header">
        <div class="companies-hero__left">
            <h2 id="companies-heading" class="companies-hero__title">Companies</h2>
            <p class="companies-hero__subtitle">Read-only listing: companies, tickers, cyber event linkage, sector coverage, and price import readiness.</p>
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
        <div class="companies-hero__right" aria-label="Current research signal">
            <?php if ($companies['available'] && $sum) { ?>
                <p class="companies-hero__signal"><strong><?= komodo_e((string) ($sum['total_securities'] ?? '—')) ?></strong> securities in scope.</p>
                <p class="companies-hero__signal"><strong><?= komodo_e((string) ($sum['securities_without_prices'] ?? '—')) ?></strong> still missing prices.</p>
                <p class="companies-hero__signal"><strong><?= komodo_e((string) ($sum['event_linked_securities'] ?? '—')) ?></strong> event-linked securities need price coverage first.</p>
            <?php } else { ?>
                <p class="companies-hero__signal">Company exploration loads in live DB mode.</p>
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

        <?php /* KPI strip */ ?>
        <div class="companies-kpi-strip" aria-label="Companies KPI strip">
            <div class="companies-kpi"><span class="companies-kpi__label">Companies</span><span class="companies-kpi__value"><?= $sum ? komodo_e((string) ($sum['total_companies'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label">Securities</span><span class="companies-kpi__value"><?= $sum ? komodo_e((string) ($sum['total_securities'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label" title="<?= komodo_e(komodo_describe('event_linked_security', 'role') ?? '') ?>">Event-linked</span><span class="companies-kpi__value"><?= $sum ? komodo_e((string) ($sum['event_linked_securities'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label" title="<?= komodo_e(komodo_describe('comparison_or_unlinked_security', 'role') ?? '') ?>">Comparison</span><span class="companies-kpi__value"><?= $sum ? komodo_e((string) ($sum['comparison_or_unlinked_securities'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label">Companies w/ events</span><span class="companies-kpi__value"><?= $sum ? komodo_e((string) ($sum['companies_with_events'] ?? '—')) : '—' ?></span></div>
            <div class="companies-kpi"><span class="companies-kpi__label">Missing prices</span><span class="companies-kpi__value"><?= $sum ? komodo_e((string) ($sum['securities_without_prices'] ?? '—')) : '—' ?></span></div>
        </div>

        <details class="market-md-collapsible companies-tech-sources">
            <summary>Technical sources (audit)</summary>
            <ul class="market-insight-checklist compact-note">
                <li><span class="label-primary">Driving rowset</span> <span class="label-secondary"><code class="inline-code inline-code--subtle">vw_market_data_import_plan</code></span></li>
                <li><span class="label-primary">Company metadata</span> <span class="label-secondary"><code class="inline-code inline-code--subtle">companies</code>, <code class="inline-code inline-code--subtle">sectors</code>, <code class="inline-code inline-code--subtle">industries</code></span></li>
                <li><span class="label-primary">Ticker metadata</span> <span class="label-secondary"><code class="inline-code inline-code--subtle">securities</code></span></li>
                <li><span class="label-primary">Price aggregates</span> <span class="label-secondary"><code class="inline-code inline-code--subtle">security_daily_prices</code> (COUNT/MIN/MAX)</span></li>
                <li><span class="label-primary">Event linkage</span> <span class="label-secondary"><code class="inline-code inline-code--subtle">cyber_event_securities</code> (COUNT DISTINCT)</span></li>
            </ul>
        </details>

        <?php /* B. Sector / Industry distribution snapshot */ ?>
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

        <?php /* C. Attention queue */ ?>
        <h3 class="subsection-heading" id="companies-attention">Attention queue</h3>
        <?php
        $queueLimit = 10;
        $queueSpecs = [
            'event_linked_without_prices' => ['title' => 'Event-linked tickers missing prices', 'countKey' => 'event_linked_securities'],
            'import_notes' => ['title' => 'Special import notes', 'countKey' => 'securities_with_import_notes'],
            'multiple_event_companies' => ['title' => 'Companies with repeated cyber events', 'countKey' => 'companies_with_multiple_events'],
            'missing_sector_or_industry' => ['title' => 'Missing classification', 'countKey' => null],
        ];
        ?>
        <div class="companies-queue-grid" aria-label="Attention queue cards">
            <?php foreach ($queueSpecs as $k => $spec) {
                $full = (array) ($att[$k] ?? []);
                $total = count($full);
                $show = array_slice($full, 0, $queueLimit);
                $more = max(0, $total - count($show));
                $headingCount = $total;
                if ($sum && $spec['countKey']) {
                    $headingCount = (int) ($sum[$spec['countKey']] ?? $total);
                }
                $hid = 'queue-' . $k;
                ?>
                <section class="panel-nested panel-muted companies-queue-card" aria-labelledby="<?= komodo_e($hid) ?>">
                    <div class="companies-queue-card__head">
                        <h4 id="<?= komodo_e($hid) ?>" class="subsection-heading subsection-heading-tight"><?= komodo_e($spec['title']) ?></h4>
                        <span class="coverage-badge coverage-badge--not-started"><?= komodo_e((string) $headingCount) ?></span>
                    </div>
                    <?php if ($show === []) { ?>
                        <p class="compact-note">—</p>
                    <?php } else { ?>
                        <ul class="companies-queue-list">
                            <?php foreach ($show as $it) {
                                $cName = (string) ($it['company_name'] ?? '');
                                $ticker = (string) ($it['ticker_symbol'] ?? '');
                                $cev = (int) ($it['company_event_count'] ?? 0);
                                $sev = (int) ($it['security_event_count'] ?? 0);
                                $evHint = $cev > 0 ? ($cev . ' events') : ($sev > 0 ? ($sev . ' events') : '');
                                ?>
                                <li class="companies-queue-item">
                                    <span class="companies-queue-item__left">
                                        <span class="label-primary"><?= komodo_e($cName) ?></span>
                                        <?php if ($ticker !== '') { ?>
                                            <code class="inline-code inline-code--subtle"><?= komodo_e($ticker) ?></code>
                                        <?php } ?>
                                    </span>
                                    <span class="companies-queue-item__right"><?= $evHint !== '' ? komodo_e($evHint) : '' ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                        <?php if ($more > 0) { ?>
                            <p class="compact-note companies-queue-more">+ <?= komodo_e((string) $more) ?> more</p>
                        <?php } ?>
                    <?php } ?>
                </section>
            <?php } ?>
        </div>

        <?php /* D. Main table */ ?>
        <h3 class="subsection-heading" id="companies-table">Company / security table</h3>
        <p class="compact-note">This table is security/ticker-grain. A company may appear more than once when multiple securities or historical tickers are in scope.</p>
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
            <table class="data-table data-table--sticky data-table--dense data-table--sticky-left" aria-labelledby="companies-table">
                <thead>
                    <tr>
                        <th scope="col">Company / ticker</th>
                        <th scope="col">Sector / industry</th>
                        <th scope="col">Role</th>
                        <th scope="col" class="num">Events</th>
                        <th scope="col">Price coverage</th>
                        <th scope="col">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array) ($pg['rows'] ?? []) as $r) {
                        $roleKey = (string) ($r['price_import_role'] ?? '');
                        $roleLabel = komodo_label_safe($roleKey, 'role');
                        $roleDesc = $roleKey !== '' ? komodo_describe($roleKey, 'role') : null;

                        $covKey = (string) ($r['coverage_status'] ?? 'unavailable_or_error');
                        $covLabel = komodo_label_safe($covKey, 'coverage_status');
                        $covDesc = komodo_describe($covKey, 'coverage_status');
                        $covClass = komodo_coverage_badge_css($covKey);

                        [$noteDisp, $noteFull, $noteHasTitle] = komodo_note_preview(isset($r['import_notes']) ? (string) $r['import_notes'] : '', 96);

                        $first = komodo_normalize_date_string($r['first_price_date'] ?? null);
                        $last = komodo_normalize_date_string($r['last_price_date'] ?? null);
                        $secEv = (int) ($r['security_event_count'] ?? 0);
                        $planEv = (int) ($r['linked_event_count'] ?? 0);
                        $events = max($secEv, $planEv);
                        $companyName = (string) ($r['company_name'] ?? ($r['legal_name'] ?? ''));
                        $ticker = (string) ($r['ticker_symbol'] ?? '');
                        $companyId = (string) ($r['company_id'] ?? '');
                        $securityId = (string) ($r['security_id'] ?? '');
                        $priceRows = (int) ($r['price_rows'] ?? 0);

                        $sector = (string) ($r['sector_name'] ?? '—');
                        $industry = (string) ($r['industry_name'] ?? '—');
                        $exchange = (string) ($r['exchange_code'] ?? '');

                        $span = '—';
                        if ($priceRows > 0 && $first && $last) {
                            $span = $first . ' → ' . $last;
                        }
                        ?>
                        <tr>
                            <td title="<?= komodo_e('company_id=' . $companyId . ' · security_id=' . $securityId) ?>">
                                <div class="label-stack">
                                    <span class="label-primary">
                                        <a class="companies-link" href="index.php?page=company&company_id=<?= komodo_e($companyId) ?>"><?= komodo_e($companyName) ?></a>
                                    </span>
                                    <span class="label-secondary">
                                        <code class="inline-code"><?= komodo_e($ticker) ?></code>
                                        <?php if ($exchange !== '') { ?>
                                            <span class="compact-note"><?= komodo_e($exchange) ?></span>
                                        <?php } ?>
                                        <code class="inline-code inline-code--subtle">company_id=<?= komodo_e($companyId) ?></code>
                                        <code class="inline-code inline-code--subtle">security_id=<?= komodo_e($securityId) ?></code>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="label-stack">
                                    <span class="label-primary"><?= komodo_e($sector) ?></span>
                                    <span class="label-secondary"><?= komodo_e($industry) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="label-stack">
                                    <span class="label-primary"<?= $roleDesc ? ' title="' . komodo_e($roleDesc) . '"' : '' ?>><?= komodo_e($roleLabel) ?></span>
                                    <?php if ($roleKey !== '') { ?>
                                        <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($roleKey) ?></code></span>
                                    <?php } ?>
                                </div>
                            </td>
                            <td class="num"><?= komodo_e((string) $events) ?></td>
                            <td>
                                <div class="label-stack">
                                    <span class="label-primary">
                                        <span class="coverage-badge <?= komodo_e($covClass) ?>"<?= $covDesc ? ' title="' . komodo_e($covDesc) . '"' : '' ?>><?= komodo_e($covLabel) ?></span>
                                        <span class="compact-note">· <?= komodo_e((string) $priceRows) ?> rows</span>
                                    </span>
                                    <span class="label-secondary"><?= komodo_e($span) ?></span>
                                </div>
                            </td>
                            <td class="compact-note"><?php if ($noteDisp !== '') { ?>
                                <span<?= $noteHasTitle ? ' title="' . $noteFull . '"' : '' ?>><?= $noteDisp ?></span>
                            <?php } else { echo '—'; } ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    <?php } ?>
</section>

