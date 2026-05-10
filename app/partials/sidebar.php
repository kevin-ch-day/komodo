<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/pages.php';

/** @var array<string, mixed> $ctx */
/** @var string $current_page */

$routes = komodo_page_routes();
$navGroups = komodo_sidebar_nav_groups();

$badge = $ctx['primary_status_badge'];
$caption = $ctx['sidebar_mode_caption'];

$activeSidebarGroupId = null;
foreach ($navGroups as $g) {
    foreach ($g['keys'] as $k) {
        if (!isset($routes[$k])) {
            continue;
        }
        if ($current_page === $k || ($current_page === 'company' && $k === 'companies')) {
            $activeSidebarGroupId = $g['id'];
            break 2;
        }
    }
}

?>
<aside class="sidebar" aria-label="Komodo sections">
    <div class="sidebar-top">
        <div class="sidebar-brand-block">
            <a class="sidebar-brand" href="index.php?page=dashboard">Komodo</a>
            <p class="sidebar-tagline">Cybersecurity–finance event-study research</p>
        </div>
        <div class="sidebar-connect">
            <span class="<?= komodo_e($badge['class']) ?> sidebar-mode-pill"><?= komodo_e($badge['text']) ?></span>
            <p class="sidebar-mode-caption"><?= komodo_e($caption) ?></p>
        </div>
    </div>
    <nav class="sidebar-nav" aria-label="Pages"<?= $activeSidebarGroupId !== null ? ' data-sidebar-active-group="' . komodo_e($activeSidebarGroupId) . '"' : '' ?>>
        <?php foreach ($navGroups as $group) {
            $groupKeys = array_values(array_filter(
                $group['keys'],
                static fn (string $k): bool => isset($routes[$k]),
            ));
            if ($groupKeys === []) {
                continue;
            }
            $headingId = $group['id'];
            $panelId = $headingId . '-panel';
            $toggleId = $headingId . '-toggle';
            ?>
            <section class="sidebar-nav-section" data-sidebar-group="<?= komodo_e($headingId) ?>" aria-labelledby="<?= komodo_e($headingId) ?>">
                <button type="button" class="sidebar-nav-section-toggle" id="<?= komodo_e($toggleId) ?>" tabindex="-1" aria-expanded="true" aria-controls="<?= komodo_e($panelId) ?>">
                    <span class="sidebar-nav-section-heading" id="<?= komodo_e($headingId) ?>"><?= komodo_e($group['heading']) ?></span>
                    <span class="sidebar-nav-toggle-icon" aria-hidden="true"></span>
                </button>
                <ul id="<?= komodo_e($panelId) ?>" class="sidebar-nav-list sidebar-nav-panel">
                    <?php foreach ($groupKeys as $key) {
                        $label = komodo_sidebar_route_label($key);
                        $isCurrent = $current_page === $key
                            || ($current_page === 'company' && $key === 'companies');
                        ?>
                        <li>
                            <a class="sidebar-nav-link" href="index.php?page=<?= komodo_e($key) ?>"
                                <?= $isCurrent ? ' aria-current="page"' : '' ?>><?= komodo_e($label) ?></a>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>
    </nav>

    <details class="sidebar-legend-collapsed">
        <summary class="sidebar-legend-summary">Badges</summary>
        <div class="sidebar-legend" aria-labelledby="sidebar-legend-title">
            <p id="sidebar-legend-title" class="sidebar-legend-title visually-hidden">Badge key</p>
            <dl class="legend-list">
                <div class="legend-row"><dt><span class="badge badge--ready">Count OK</span></dt><dd>Live SELECT returned a non-zero row count.</dd></div>
                <div class="legend-row"><dt><span class="badge badge--placeholder">Placeholder</span></dt><dd>Offline reference snapshot.</dd></div>
                <div class="legend-row"><dt><span class="badge badge--missing">Missing</span></dt><dd>Query failed — see “—” in tables.</dd></div>
                <div class="legend-row"><dt><span class="badge badge--zero-muted">Zero rows</span></dt><dd>Expected empty lane for current phase.</dd></div>
            </dl>
        </div>
    </details>

    <p class="sidebar-note">Komodo v<?= komodo_e(KOMODO_APP_VERSION) ?> · multi-page read-only research portal · corpus under active construction.</p>
</aside>
