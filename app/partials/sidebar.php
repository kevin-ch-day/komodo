<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */
/** @var string $current_page */

$nav = [
    'dashboard' => 'Dashboard',
    'companies' => 'Companies',
    'dataset' => 'Dataset',
    'events' => 'Events',
    'market-data' => 'Market Data',
    'research-quality' => 'Research Quality',
    'data-gaps' => 'Data Gaps',
    'pipeline' => 'Pipeline',
];

$badge = $ctx['primary_status_badge'];
$caption = $ctx['sidebar_mode_caption'];

?>
<aside class="sidebar" aria-label="Komodo sections">
    <div class="sidebar-brand-block">
        <a class="sidebar-brand" href="index.php?page=dashboard">Komodo</a>
        <p class="sidebar-tagline">Cyber event-study readiness</p>
    </div>
    <div class="sidebar-connect">
        <span class="<?= komodo_e($badge['class']) ?> sidebar-mode-pill"><?= komodo_e($badge['text']) ?></span>
        <p class="sidebar-mode-caption"><?= komodo_e($caption) ?></p>
    </div>
    <nav class="sidebar-nav" aria-label="Pages">
        <ul class="sidebar-nav-list">
            <?php foreach ($nav as $key => $label) {
                $isCurrent = $current_page === $key;
                ?>
                <li>
                    <a class="sidebar-nav-link" href="index.php?page=<?= komodo_e($key) ?>"
                        <?= $isCurrent ? ' aria-current="page"' : '' ?>><?= komodo_e($label) ?></a>
                </li>
            <?php } ?>
        </ul>
    </nav>

    <details class="sidebar-legend-collapsed">
        <summary class="sidebar-legend-summary">Badge key</summary>
        <div class="sidebar-legend" aria-labelledby="sidebar-legend-title">
            <p id="sidebar-legend-title" class="sidebar-legend-title visually-hidden">Badge key</p>
            <dl class="legend-list">
                <div class="legend-row"><dt><span class="badge badge--ready">Ready</span></dt><dd>Live count confirms rows.</dd></div>
                <div class="legend-row"><dt><span class="badge badge--placeholder">Placeholder</span></dt><dd>Offline reference snapshot.</dd></div>
                <div class="legend-row"><dt><span class="badge badge--missing">Missing</span></dt><dd>Query failed — see “—” in tables.</dd></div>
                <div class="legend-row"><dt><span class="badge badge--zero-muted">Zero rows</span></dt><dd>Expected empty lane for current phase.</dd></div>
            </dl>
        </div>
    </details>

    <p class="sidebar-note">Komodo v<?= komodo_e(KOMODO_APP_VERSION) ?> · multi-page read-only shell · corpus under active construction.</p>
</aside>
