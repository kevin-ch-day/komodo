<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

/** @var array<string, mixed> */
$ev = $ctx['events'] ?? [
    'available' => false,
    'partial' => false,
    'mode' => 'offline',
    'message' => 'Events context was not loaded.',
    'errors' => [],
    'summary' => [
        'total_events' => 0,
        'events_with_disclosure' => 0,
        'events_with_first_trading_day' => 0,
        'events_with_security_links' => 0,
        'events_with_sources' => 0,
        'events_missing_sources' => 0,
        'events_needing_impact_review' => 0,
        'events_with_overlap_or_cluster_flags' => 0,
        'research_ready_metadata' => 0,
        'cyber_event_securities_rows' => 0,
    ],
    'distributions' => ['event_type' => [], 'severity' => [], 'confidence' => []],
    'attention' => [
        'missing_source_provenance' => [],
        'needs_impact_quantification' => [],
        'overlap_or_cluster_review' => [],
        'research_ready_metadata' => [],
    ],
    'rows' => [],
    'trace_sources' => [],
];

$sum = is_array($ev['summary'] ?? null) ? $ev['summary'] : null;
$att = is_array($ev['attention'] ?? null) ? $ev['attention'] : [];
$dist = is_array($ev['distributions'] ?? null) ? $ev['distributions'] : [];

$eventsPageParam = $_GET['events_page'] ?? null;
$perPageParam = $_GET['per_page'] ?? null;

$eventsPage = 1;
if (is_string($eventsPageParam) && ctype_digit($eventsPageParam)) {
    $p = (int) $eventsPageParam;
    $eventsPage = $p > 0 ? $p : 1;
}

$allowedPerPage = [10, 15, 20];
$perPage = 15;
if (is_string($perPageParam) && ctype_digit($perPageParam)) {
    $pp = (int) $perPageParam;
    if (in_array($pp, $allowedPerPage, true)) {
        $perPage = $pp;
    }
}

$buildEventsUrl = static function (int $page, int $perPage): string {
    $page = $page > 0 ? $page : 1;
    $qs = http_build_query([
        'page' => 'events',
        'events_page' => $page,
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

$eventsExampleLine = static function (array $it): string {
    $id = (int) ($it['cyber_event_id'] ?? 0);
    $name = trim((string) ($it['event_name'] ?? ''));
    if ($name !== '' && strlen($name) > 44) {
        $name = substr($name, 0, 41) . '…';
    }

    return $name !== '' ? '#' . $id . ' · ' . $name : '#' . $id;
};

$types = (array) ($dist['event_type'] ?? []);
$sevs = (array) ($dist['severity'] ?? []);
$confs = (array) ($dist['confidence'] ?? []);
$typeMax = 1;
foreach ($types as $row) {
    $typeMax = max($typeMax, (int) ($row['count'] ?? 0));
}
$sevMax = 1;
foreach ($sevs as $row) {
    $sevMax = max($sevMax, (int) ($row['count'] ?? 0));
}
$confMax = 1;
foreach ($confs as $row) {
    $confMax = max($confMax, (int) ($row['count'] ?? 0));
}

/** @var list<array<string, mixed>> $allRows */
$allRows = (array) ($ev['rows'] ?? []);
$pg = $paginate($allRows, $eventsPage, $perPage);

?>
<section class="panel shell-section companies-page events-page" aria-labelledby="events-heading">
    <div class="companies-hero" aria-label="Events listing header">
        <div class="companies-hero__left">
            <h2 id="events-heading" class="companies-hero__title">Events</h2>
            <p class="companies-hero__subtitle">Cyber event catalog and readiness snapshot (read-only). Use the table for per-event detail; use <a class="footer-top-link" href="index.php?page=research-quality#rq-heading">Research quality</a> and <a class="footer-top-link" href="index.php?page=data-gaps#dg-cards">Data gaps</a> for full review queues.</p>
            <?php if (!$ev['available']) { ?>
                <span class="badge badge--primary badge--offline">Offline</span>
            <?php } else { ?>
                <?php if (!empty($ev['partial'])) { ?>
                    <span class="badge badge--primary badge--degraded">Partial</span>
                <?php } else { ?>
                    <span class="badge badge--primary badge--live">Live</span>
                <?php } ?>
            <?php } ?>
        </div>
        <div class="companies-hero__right" aria-label="Events data signal">
            <?php if ($ev['available'] && $sum) { ?>
                <p class="companies-hero__signal"><?= komodo_e((string) $ev['message']) ?></p>
            <?php } else { ?>
                <p class="companies-hero__signal"><?= komodo_e((string) $ev['message']) ?></p>
            <?php } ?>
        </div>
    </div>

    <?php if (!$ev['available']) { ?>
        <section class="panel-nested panel-phase--inset" aria-label="Events offline state">
            <p class="section-lead"><?= komodo_e((string) $ev['message']) ?></p>
            <p class="compact-note">Connect MariaDB via <code class="inline-code">app/config/local.php</code> to load the event list and readiness flags.</p>
        </section>
    <?php } else { ?>
        <?php if (!empty($ev['errors'])) { ?>
            <ul class="market-md-error-list compact-note" role="list" aria-label="Events warnings">
                <?php foreach ((array) $ev['errors'] as $err) { ?>
                    <li class="compact-note"><?= komodo_e((string) $err) ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <?php
        $tEv = $sum ? (int) ($sum['total_events'] ?? 0) : 0;
        $tDisc = $sum ? (int) ($sum['events_with_disclosure'] ?? 0) : 0;
        $tFtd = $sum ? (int) ($sum['events_with_first_trading_day'] ?? 0) : 0;
        $tSec = $sum ? (int) ($sum['events_with_security_links'] ?? 0) : 0;
        $tMissSrc = $sum ? (int) ($sum['events_missing_sources'] ?? 0) : 0;
        $tImpact = $sum ? (int) ($sum['events_needing_impact_review'] ?? 0) : 0;
        $tOverlap = $sum ? (int) ($sum['events_with_overlap_or_cluster_flags'] ?? 0) : 0;
        $exImpact = array_slice((array) ($att['needs_impact_quantification'] ?? []), 0, 3);
        $exOverlap = array_slice((array) ($att['overlap_or_cluster_review'] ?? []), 0, 3);
        $exReady = array_slice((array) ($att['research_ready_metadata'] ?? []), 0, 3);
        $tReady = $sum ? (int) ($sum['research_ready_metadata'] ?? 0) : 0;
        ?>
        <section class="panel-nested panel-phase--inset events-readiness-panel" aria-labelledby="events-snapshot-heading">
            <h3 id="events-snapshot-heading" class="subsection-heading subsection-heading-tight">Dataset snapshot</h3>
            <ul class="events-readiness-conclusion" role="list">
                <li><strong><?= komodo_e((string) $tEv) ?></strong> cyber events in scope</li>
                <li><strong><?= komodo_e((string) $tDisc) ?></strong> have disclosure dates</li>
                <li><strong><?= komodo_e((string) $tFtd) ?></strong> have first trading-day dates</li>
                <li><strong><?= komodo_e((string) $tSec) ?></strong> have event-security links</li>
                <li><strong><?= komodo_e((string) $tMissSrc) ?></strong> missing source provenance</li>
                <li><strong><?= komodo_e((string) $tImpact) ?></strong> need impact review</li>
                <li><strong><?= komodo_e((string) $tOverlap) ?></strong> need overlap/cluster review</li>
            </ul>
        </section>

        <div class="companies-distribution" aria-label="Event attribute distributions">
            <section class="panel-nested panel-muted companies-distribution__panel" aria-labelledby="ev-type-dist">
                <h3 id="ev-type-dist" class="subsection-heading subsection-heading-tight">Event type</h3>
                <?php if ($types === []) { ?>
                    <p class="compact-note">—</p>
                <?php } else { ?>
                    <ul class="companies-dist-list">
                        <?php foreach ($types as $row) {
                            $label = (string) ($row['label'] ?? '—');
                            $count = (int) ($row['count'] ?? 0);
                            $pct = (int) round(100 * ($count / $typeMax));
                            ?>
                            <li class="companies-dist-row">
                                <span class="companies-dist-row__label" title="<?= komodo_e((string) ($row['raw'] ?? '')) ?>"><?= komodo_e($label) ?></span>
                                <span class="companies-dist-row__bar" aria-hidden="true"><span class="companies-dist-row__barFill" style="width: <?= komodo_e((string) $pct) ?>%"></span></span>
                                <span class="companies-dist-row__count"><?= komodo_e((string) $count) ?></span>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </section>
            <section class="panel-nested panel-muted companies-distribution__panel" aria-labelledby="ev-sev-dist">
                <h3 id="ev-sev-dist" class="subsection-heading subsection-heading-tight">Severity</h3>
                <?php if ($sevs === []) { ?>
                    <p class="compact-note">—</p>
                <?php } else { ?>
                    <ul class="companies-dist-list">
                        <?php foreach ($sevs as $row) {
                            $label = (string) ($row['label'] ?? '—');
                            $count = (int) ($row['count'] ?? 0);
                            $pct = (int) round(100 * ($count / $sevMax));
                            ?>
                            <li class="companies-dist-row">
                                <span class="companies-dist-row__label" title="<?= komodo_e((string) ($row['raw'] ?? '')) ?>"><?= komodo_e($label) ?></span>
                                <span class="companies-dist-row__bar" aria-hidden="true"><span class="companies-dist-row__barFill" style="width: <?= komodo_e((string) $pct) ?>%"></span></span>
                                <span class="companies-dist-row__count"><?= komodo_e((string) $count) ?></span>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </section>
            <section class="panel-nested panel-muted companies-distribution__panel" aria-labelledby="ev-conf-dist">
                <h3 id="ev-conf-dist" class="subsection-heading subsection-heading-tight">Confidence</h3>
                <?php if ($confs === []) { ?>
                    <p class="compact-note">—</p>
                <?php } else { ?>
                    <ul class="companies-dist-list">
                        <?php foreach ($confs as $row) {
                            $label = (string) ($row['label'] ?? '—');
                            $count = (int) ($row['count'] ?? 0);
                            $pct = (int) round(100 * ($count / $confMax));
                            ?>
                            <li class="companies-dist-row">
                                <span class="companies-dist-row__label" title="<?= komodo_e((string) ($row['raw'] ?? '')) ?>"><?= komodo_e($label) ?></span>
                                <span class="companies-dist-row__bar" aria-hidden="true"><span class="companies-dist-row__barFill" style="width: <?= komodo_e((string) $pct) ?>%"></span></span>
                                <span class="companies-dist-row__count"><?= komodo_e((string) $count) ?></span>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </section>
        </div>

        <h3 class="subsection-heading" id="events-readiness-cards-heading">Readiness signals</h3>
        <p class="compact-note">Counts only — not a work queue. Sample rows (max 3) are shortcuts; full lists live elsewhere.</p>
        <div class="events-readiness-grid" role="region" aria-labelledby="events-readiness-cards-heading">
            <article class="stat-card stat-card--missing">
                <div class="stat-card__head">
                    <h4 class="stat-card__title">Source provenance</h4>
                </div>
                <p class="stat-card__value"><?= $tEv > 0 ? komodo_e((string) $tMissSrc . ' / ' . (string) $tEv . ' missing') : '—' ?></p>
                <p class="compact-note stat-card__dek"><?php if ($tEv > 0 && $tMissSrc === $tEv) { ?>
                    All events currently lack <code class="inline-code">cyber_event_sources</code> rows. Populate source provenance before treating event metadata as research-ready.
                <?php } else { ?>
                    Events without <code class="inline-code">cyber_event_sources</code> rows lack source provenance — populate before treating metadata as research-ready.
                <?php } ?></p>
            </article>
            <article class="stat-card">
                <div class="stat-card__head">
                    <h4 class="stat-card__title">Impact review</h4>
                </div>
                <p class="stat-card__value"><?= komodo_e((string) $tImpact) ?></p>
                <p class="compact-note stat-card__dek">Events with high/critical severity but weak or missing quantified impact evidence.</p>
                <?php if ($exImpact !== []) { ?>
                    <ul class="events-readiness-examples" aria-label="Sample events for impact review">
                        <?php foreach ($exImpact as $it) { ?>
                            <li><code class="inline-code inline-code--subtle"><?= komodo_e($eventsExampleLine($it)) ?></code></li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </article>
            <article class="stat-card">
                <div class="stat-card__head">
                    <h4 class="stat-card__title">Overlap / cluster review</h4>
                </div>
                <p class="stat-card__value"><?= komodo_e((string) $tOverlap) ?></p>
                <p class="compact-note stat-card__dek">Events close to other cyber events; review independence before event-study use.</p>
                <?php if ($exOverlap !== []) { ?>
                    <ul class="events-readiness-examples" aria-label="Sample events for overlap or cluster review">
                        <?php foreach ($exOverlap as $it) { ?>
                            <li><code class="inline-code inline-code--subtle"><?= komodo_e($eventsExampleLine($it)) ?></code></li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </article>
            <article class="stat-card">
                <div class="stat-card__head">
                    <h4 class="stat-card__title">Research-ready metadata candidates</h4>
                </div>
                <p class="stat-card__value"><?= komodo_e((string) $tReady) ?></p>
                <p class="compact-note stat-card__dek">Requires source provenance plus clean review flags.</p>
                <?php if ($tReady > 0 && $exReady !== []) { ?>
                    <ul class="events-readiness-examples" aria-label="Sample research-ready candidates">
                        <?php foreach ($exReady as $it) { ?>
                            <li><code class="inline-code inline-code--subtle"><?= komodo_e($eventsExampleLine($it)) ?></code></li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </article>
        </div>
        <p class="compact-note events-readiness-more-link">Full review workflows: <a class="footer-top-link" href="index.php?page=research-quality#rq-heading">Research quality</a> · <a class="footer-top-link" href="index.php?page=data-gaps#dg-cards">Data gaps (gap cards)</a></p>

        <h3 class="subsection-heading" id="events-table-heading">Event catalog</h3>
        <p class="compact-note">One row per event. Multiple securities show as <strong>+N securities</strong>. Sort order follows disclosure date (newest first). Event drilldown is not wired yet.</p>
        <?php if ($pg['total'] > 0) { ?>
            <div class="companies-table-controls" aria-label="Events table controls">
                <p class="compact-note">Showing <?= komodo_e((string) $pg['start']) ?>–<?= komodo_e((string) $pg['end']) ?> of <?= komodo_e((string) $pg['total']) ?> events.</p>
                <div class="companies-pager">
                    <?php if ($pg['page'] > 1) { ?>
                        <a class="footer-top-link" href="<?= komodo_e($buildEventsUrl($pg['page'] - 1, $pg['per_page'])) ?>" aria-label="Previous page">Prev</a>
                    <?php } else { ?>
                        <span class="companies-pager__disabled" aria-hidden="true">Prev</span>
                    <?php } ?>
                    <span class="companies-pager__meta">Page <?= komodo_e((string) $pg['page']) ?> of <?= komodo_e((string) $pg['total_pages']) ?></span>
                    <?php if ($pg['page'] < $pg['total_pages']) { ?>
                        <a class="footer-top-link" href="<?= komodo_e($buildEventsUrl($pg['page'] + 1, $pg['per_page'])) ?>" aria-label="Next page">Next</a>
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
                            <a class="footer-top-link" href="<?= komodo_e($buildEventsUrl(1, $pp)) ?>"><?= komodo_e((string) $pp) ?></a>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
        <div class="table-scroll">
            <table class="data-table data-table--sticky data-table--dense data-table--sticky-left data-table--labeled-mobile" aria-labelledby="events-table-heading">
                <thead>
                    <tr>
                        <th scope="col">Event / company</th>
                        <th scope="col">Type</th>
                        <th scope="col">Severity / confidence</th>
                        <th scope="col">Dates</th>
                        <th scope="col">Readiness</th>
                        <th scope="col">Review flags</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array) ($pg['rows'] ?? []) as $r) {
                        $eid = (string) ($r['cyber_event_id'] ?? '');
                        $evName = (string) ($r['event_name'] ?? '');
                        $coName = (string) ($r['company_name'] ?? '');
                        $ticker = (string) ($r['display_ticker_symbol'] ?? '');
                        $nSec = (int) ($r['security_link_count'] ?? 0);
                        $typeKey = (string) ($r['event_type'] ?? '');
                        $disc = komodo_normalize_date_string($r['disclosure_date'] ?? null) ?? '—';
                        $ftd = komodo_normalize_date_string($r['first_trading_day'] ?? null) ?? '—';
                        $src = (int) ($r['source_count'] ?? 0);
                        $prim = (string) ($r['primary_readiness_label'] ?? '—');
                        $primKey = (string) ($r['primary_readiness_key'] ?? '');
                        $primDesc = $primKey !== '' ? komodo_describe($primKey, 'readiness_flag') : null;
                        $badges = (array) ($r['review_badge_keys'] ?? []);
                        ?>
                        <tr title="<?= komodo_e('cyber_event_id=' . $eid) ?>">
                            <td data-label="Event / company">
                                <div class="label-stack">
                                    <span class="label-primary"><?= komodo_e($evName) ?></span>
                                    <span class="label-secondary"><?= komodo_e($coName) ?></span>
                                    <span class="label-secondary">
                                        <?php if ($ticker !== '') { ?>
                                            <code class="inline-code"><?= komodo_e($ticker) ?></code>
                                        <?php } ?>
                                        <?php if ($nSec > 1) { ?>
                                            <span class="compact-note">+<?= komodo_e((string) ($nSec - 1)) ?> securities</span>
                                        <?php } ?>
                                        <code class="inline-code inline-code--subtle">cyber_event_id=<?= komodo_e($eid) ?></code>
                                    </span>
                                </div>
                            </td>
                            <td data-label="Type">
                                <div class="label-stack">
                                    <span class="label-primary"><?= komodo_e(komodo_label_safe($typeKey, 'generic')) ?></span>
                                    <?php if ($typeKey !== '') { ?>
                                        <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($typeKey) ?></code></span>
                                    <?php } ?>
                                </div>
                            </td>
                            <td data-label="Severity / confidence">
                                <div class="label-stack">
                                    <span class="label-primary"><?= komodo_e(komodo_label_safe((string) ($r['severity_level'] ?? ''), 'generic')) ?></span>
                                    <span class="label-secondary"><?= komodo_e(komodo_label_safe((string) ($r['confidence_level'] ?? ''), 'generic')) ?></span>
                                </div>
                            </td>
                            <td class="compact-note" data-label="Dates">
                                <div class="label-stack">
                                    <span><strong>disclosure</strong> <?= komodo_e($disc) ?></span>
                                    <span><strong>first_trading_day</strong> <?= komodo_e($ftd) ?></span>
                                </div>
                            </td>
                            <td data-label="Readiness">
                                <div class="label-stack">
                                    <span class="label-primary"<?= $primDesc ? ' title="' . komodo_e($primDesc) . '"' : '' ?>><?= komodo_e($prim) ?></span>
                                    <span class="label-secondary"><?= $src > 0 ? komodo_e('Provenance rows: ' . (string) $src) : komodo_e('No rows in cyber_event_sources') ?></span>
                                </div>
                            </td>
                            <td class="compact-note" data-label="Review flags">
                                <?php if ($badges === []) { ?>
                                    —
                                <?php } else { ?>
                                    <?php foreach ($badges as $bk) {
                                        $bd = komodo_describe((string) $bk, 'readiness_flag');
                                        ?>
                                        <span class="coverage-badge coverage-badge--partial events-flag-badge"<?= $bd ? ' title="' . komodo_e($bd) . '"' : '' ?>><?= komodo_e(komodo_label((string) $bk, 'readiness_flag')) ?></span>
                                    <?php } ?>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <details class="market-md-collapsible companies-tech-sources">
            <summary>Technical sources (audit)</summary>
            <p class="compact-note">Primary date columns read <code class="inline-code inline-code--subtle">cyber_event_dates</code> where <code class="inline-code inline-code--subtle">date_type</code> is <strong>disclosure</strong> or <strong>first_trading_day</strong> (explicit types, not guessed legacy column names).</p>
            <?php if ($sum && (int) ($sum['cyber_event_securities_rows'] ?? 0) > 0) { ?>
                <p class="compact-note">Bridge table row count (<code class="inline-code">cyber_event_securities</code>): <strong><?= komodo_e((string) (int) ($sum['cyber_event_securities_rows'] ?? 0)) ?></strong> (all event–security links in the database, not deduplicated by event).</p>
            <?php } ?>
            <ul class="market-insight-checklist compact-note">
                <?php foreach ((array) ($ev['trace_sources'] ?? []) as $srcName) { ?>
                    <li><span class="label-primary"><?= komodo_e(komodo_label((string) $srcName, 'db_object')) ?></span> <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e((string) $srcName) ?></code></span></li>
                <?php } ?>
            </ul>
        </details>
    <?php } ?>
</section>
