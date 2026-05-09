<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$offlineMode = $ctx['offline_mode'];
$liveMerged = $ctx['live_merged'];
$banners = $ctx['banners'];
$workflow = $ctx['workflow'];
$phaseStatusLines = $ctx['phase_status_lines'];
$nextActions = $ctx['next_actions'];
$majorGapRows = $ctx['major_gap_rows'];
$primaryStatusBadge = $ctx['primary_status_badge'];
$sidebarCaption = $ctx['sidebar_mode_caption'];
$overviewEventsBadge = $ctx['overview_events_badge'];
$overviewCalendarBadge = $ctx['overview_calendar_badge'];
$overviewPriceBadge = $ctx['overview_price_badge'];
$overviewProvenanceBadge = $ctx['overview_provenance_badge'];
$eventCoreTables = $ctx['event_core_tables'];

?>
<section class="panel overview-panel" aria-labelledby="dash-eyebrow">
    <p id="dash-eyebrow" class="section-eyebrow">Dashboard</p>
    <div class="overview-hero-layout">
        <div class="overview-hero-intro">
            <h1 class="overview-title-h1">Komodo</h1>
            <p class="overview-subtitle">Cybersecurity–finance research portal</p>
            <p class="overview-mission">Komodo v<?= komodo_e(KOMODO_APP_VERSION) ?> is a read-only local portal for MariaDB <code class="inline-code">COUNT(*)</code> telemetry and event-study preparation — not trading or investment advice. Treat every signal as provisional while tables, views, and downstream research evolve.</p>
        </div>
        <div class="overview-hero-meta" aria-label="Connection banners">
            <div class="overview-meta-row">
                <span class="<?= komodo_e($primaryStatusBadge['class']) ?>" title="Portal mode"><?= komodo_e($primaryStatusBadge['text']) ?></span>
                <span class="overview-meta-caption"><?= komodo_e($sidebarCaption) ?></span>
            </div>
            <p class="compact-note overview-disclaimer">Research analytics only — not trading, execution, or investment advice.</p>
            <div class="banner-stack overview-banner-stack">
                <?php foreach ($banners as $banner) { ?>
                    <p class="<?= komodo_e(komodo_banner_class($banner['type'])) ?>" role="status"><?= komodo_e($banner['text']) ?></p>
                <?php } ?>
                <?php if ($offlineMode) { ?>
                    <p class="env-note env-note--muted" role="note">Offline reference placeholders stay labeled until MariaDB answers.</p>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="overview-workflow panel-nested panel-phase--inset">
        <h2 class="overview-phase-heading">Current workflow phase</h2>
        <p class="phase-title"><?= komodo_e($workflow['title']) ?></p>
        <p class="section-lead phase-lead"><?= komodo_e($workflow['rationale']) ?></p>
        <?php if ($phaseStatusLines !== []) { ?>
            <ul class="phase-status-list">
                <?php foreach ($phaseStatusLines as $line) { ?>
                    <li><?= komodo_e($line) ?></li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>

    <h2 class="subsection-heading subsection-heading-tight">Status at a glance</h2>
    <p class="metric-hint">Numeric cells rely on whitelist-only reads. Italic placeholders are offline documentation; em dash (“—”) means the query did not return a usable count.</p>
    <div class="stat-grid overview-stat-grid" aria-label="High level metrics">
        <article class="<?= komodo_e(komodo_stat_card_class($offlineMode, $liveMerged, 'cyber_events')) ?>">
            <div class="stat-card__head">
                <h3 class="stat-card__title">Cyber event metadata</h3>
                <span class="<?= komodo_e($overviewEventsBadge['class']) ?>"><?= komodo_e($overviewEventsBadge['text']) ?></span>
            </div>
            <p class="stat-card__dek">Incident records spanning bridge tables keyed to equities.</p>
            <p class="stat-card__value"><?php echo komodo_metric_html(
                $offlineMode,
                $liveMerged,
                'cyber_events',
                KOMODO_OFFLINE_TABLE_REFERENCE['cyber_events'] ?? null
            ); ?> <span class="stat-card__unit"><code class="inline-code inline-code--subtle"><?= komodo_e('cyber_events') ?></code></span></p>
            <table class="overview-mini-table" aria-label="Linked cyber event tables">
                <tbody>
                    <?php foreach ($eventCoreTables as $et) {
                        if ($et === 'cyber_events') {
                            continue;
                        }
                        $ref = KOMODO_OFFLINE_TABLE_REFERENCE[$et] ?? null;
                        $etLabel = komodo_label($et, 'db_object');
                        ?>
                        <tr>
                            <th scope="row">
                                <span class="label-primary"><?= komodo_e($etLabel) ?></span>
                                <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($et) ?></code></span>
                            </th>
                            <td class="num"><?php echo komodo_metric_html($offlineMode, $liveMerged, $et, $ref); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </article>

        <article class="<?= komodo_e(komodo_stat_card_class($offlineMode, $liveMerged, 'market_calendar')) ?>">
            <div class="stat-card__head">
                <h3 class="stat-card__title">Market calendar</h3>
                <span class="<?= komodo_e($overviewCalendarBadge['class']) ?>"><?= komodo_e($overviewCalendarBadge['text']) ?></span>
            </div>
            <p class="stat-card__dek">Anchors expected trading sessions before price QA.</p>
            <table class="overview-mini-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <span class="label-primary"><?= komodo_e(komodo_label('market_calendar', 'db_object')) ?></span>
                            <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e('market_calendar') ?></code></span>
                        </th>
                        <td class="num"><?php echo komodo_metric_html(
                            $offlineMode,
                            $liveMerged,
                            'market_calendar',
                            KOMODO_OFFLINE_TABLE_REFERENCE['market_calendar'] ?? null
                        ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <span class="label-primary"><?= komodo_e(komodo_label('vw_us_trading_days', 'db_object')) ?></span>
                            <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e('vw_us_trading_days') ?></code></span>
                        </th>
                        <td class="num"><?php echo komodo_metric_html(
                            $offlineMode,
                            $liveMerged,
                            'vw_us_trading_days',
                            KOMODO_OFFLINE_VIEW_REFERENCE['vw_us_trading_days'] ?? null
                        ); ?></td>
                    </tr>
                </tbody>
            </table>
        </article>

        <article class="<?= komodo_e(komodo_stat_card_class($offlineMode, $liveMerged, 'security_daily_prices')) ?>">
            <div class="stat-card__head">
                <h3 class="stat-card__title">Price data</h3>
                <span class="<?= komodo_e($overviewPriceBadge['class']) ?>"><?= komodo_e($overviewPriceBadge['text']) ?></span>
            </div>
            <p class="stat-card__dek">Issuer + benchmark daily bars required for event windows.</p>
            <table class="overview-mini-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <span class="label-primary"><?= komodo_e(komodo_label('security_daily_prices', 'db_object')) ?></span>
                            <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e('security_daily_prices') ?></code></span>
                        </th>
                        <td class="num"><?php echo komodo_metric_html(
                            $offlineMode,
                            $liveMerged,
                            'security_daily_prices',
                            KOMODO_OFFLINE_TABLE_REFERENCE['security_daily_prices'] ?? null
                        ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <span class="label-primary"><?= komodo_e(komodo_label('index_daily_prices', 'db_object')) ?></span>
                            <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e('index_daily_prices') ?></code></span>
                        </th>
                        <td class="num"><?php echo komodo_metric_html(
                            $offlineMode,
                            $liveMerged,
                            'index_daily_prices',
                            KOMODO_OFFLINE_TABLE_REFERENCE['index_daily_prices'] ?? null
                        ); ?></td>
                    </tr>
                </tbody>
            </table>
        </article>

        <article class="<?= komodo_e(komodo_stat_card_class($offlineMode, $liveMerged, 'cyber_event_sources')) ?>">
            <div class="stat-card__head">
                <h3 class="stat-card__title">Source provenance</h3>
                <span class="<?= komodo_e($overviewProvenanceBadge['class']) ?>"><?= komodo_e($overviewProvenanceBadge['text']) ?></span>
            </div>
            <p class="stat-card__dek">Source provenance table for narrative evidence and research traceability.</p>
            <p class="stat-card__value"><?php echo komodo_metric_html(
                $offlineMode,
                $liveMerged,
                'cyber_event_sources',
                KOMODO_OFFLINE_TABLE_REFERENCE['cyber_event_sources'] ?? null
            ); ?> <span class="stat-card__unit">rows</span></p>
        </article>
    </div>

    <h2 class="subsection-heading">Major gaps</h2>
    <p class="section-lead"><?= $offlineMode
        ? 'Top gap signals from offline reference scaffolding.'
        : 'Prioritized empties and anomalies from COUNT(*) snapshots — see Data gaps for the full queue.' ?></p>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Area</th>
                    <th scope="col">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($majorGapRows as $gap) { ?>
                    <tr>
                        <td><?= komodo_e($gap[0]) ?></td>
                        <td><?= komodo_e($gap[1]) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <section class="panel-nested panel-muted panel-nested--spaced">
        <h2 class="subsection-heading subsection-heading--flush">Next actions</h2>
        <p class="section-lead">Checklist inferred from read-only telemetry — perform data work outside Komodo.</p>
        <ul class="action-list">
            <?php foreach ($nextActions as $line) { ?>
                <li><?= komodo_e($line) ?></li>
            <?php } ?>
        </ul>
    </section>
</section>
