<?php

declare(strict_types=1);

/**
 * Simplified triage tables for Price import triage (read-only).
 *
 * Window mode: company-first action columns; drill-down narrative lives on the company page + Price Audit.
 *
 * @var list<array<string, mixed>> $komodo_triage_rows
 * @var string $komodo_triage_mode needs|window|historical|special_notes
 * @var string $komodo_triage_aria_label
 * @var string $komodo_triage_empty_html escaped short message when no rows
 */

$komodo_triage_rows = $komodo_triage_rows ?? [];
$komodo_triage_mode = $komodo_triage_mode ?? 'needs';
$komodo_triage_aria_label = $komodo_triage_aria_label ?? 'Import triage';
$komodo_triage_empty_html = $komodo_triage_empty_html ?? '—';

if ($komodo_triage_rows === []) {
    echo '<p class="compact-note">' . $komodo_triage_empty_html . '</p>';

    return;
}

$isWindow = $komodo_triage_mode === 'window';
$isNeeds = $komodo_triage_mode === 'needs';
$isSpecial = $komodo_triage_mode === 'special_notes';
$isHistorical = $komodo_triage_mode === 'historical';

$tableClass = 'data-table data-table--sticky data-table--dense data-table--triage data-table--labeled-mobile'
    . ($isWindow ? ' data-table--worklist-window' : '');

?>
<div class="table-scroll">
    <table class="<?= komodo_e($tableClass) ?>" aria-label="<?= komodo_e($komodo_triage_aria_label) ?>">
        <thead>
            <tr>
                <?php if ($isWindow) { ?>
                    <th scope="col">Company / security</th>
                    <th scope="col">Problem</th>
                    <th scope="col">Missing daily range</th>
                    <th scope="col">Next action</th>
                    <th scope="col">Status</th>
                <?php } else { ?>
                    <th scope="col">Ticker</th>
                    <th scope="col">Company</th>
                    <th scope="col">Plan window</th>
                    <?php if ($isNeeds) { ?>
                        <th scope="col">Plan note</th>
                    <?php } ?>
                    <?php if ($isSpecial) { ?>
                        <th scope="col">Import note</th>
                    <?php } ?>
                    <?php if ($isHistorical) { ?>
                        <th scope="col">Lineage note</th>
                    <?php } ?>
                    <th scope="col"><?= $isHistorical ? 'Issue' : 'Status' ?></th>
                <?php } ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($komodo_triage_rows as $row) {
                $st = (string) ($row['coverage_status'] ?? '');
                $stLabel = komodo_label($st, 'coverage_status');
                $stDesc = komodo_describe($st, 'coverage_status');
                $rawNote = isset($row['import_notes']) ? trim((string) $row['import_notes']) : '';
                $notePlain = $rawNote !== '' ? trim(preg_replace('/\s+/', ' ', strip_tags($rawNote))) : '';
                $trClass = komodo_triage_row_prominent_class($row);
                $mainTrClass = trim($trClass);

                $ticker = (string) ($row['ticker_symbol'] ?? '');
                $companyName = (string) ($row['display_name'] ?: ($row['security_name'] ?? ''));

                $wl = null;
                $trTitle = '';
                if ($isWindow) {
                    $wl = komodo_triage_window_worklist_copy($row);
                    $trTitle = $wl['title'] !== '' ? ' title="' . komodo_e($wl['title']) . '"' : '';
                }
                ?>
                <tr<?= $mainTrClass !== '' ? ' class="' . komodo_e($mainTrClass) . '"' : '' ?><?= $trTitle ?>>
                    <?php if ($isWindow && $wl !== null) {
                        $cid = (int) ($row['company_id'] ?? 0);
                        $coLine = trim((string) ($row['worklist_company_name'] ?? ''));
                        if ($coLine === '') {
                            $coLine = $companyName !== '' ? $companyName : $ticker;
                        }
                        $ex = trim((string) ($row['exchange_code'] ?? ''));
                        $roleKey = (string) ($row['price_import_role'] ?? '');
                        $roleShort = $roleKey !== '' ? komodo_label_safe($roleKey, 'role') : '';
                        $contextParts = [];
                        if ($ex !== '') {
                            $contextParts[] = komodo_e($ex);
                        }
                        if ($roleShort !== '') {
                            $contextParts[] = komodo_e($roleShort);
                        }
                        $contextLine = $contextParts !== [] ? implode(' <span class="triage-worklist-meta-sep" aria-hidden="true">·</span> ', $contextParts) : '';
                        ?>
                    <td data-label="Company / security">
                        <div class="triage-worklist-identity">
                            <div class="triage-worklist-identity__name"><?php if ($cid > 0) { ?>
                                <a class="companies-link" href="index.php?page=company&company_id=<?= komodo_e((string) $cid) ?>"><?= komodo_e($coLine) ?></a>
                            <?php } else { ?>
                                <?= komodo_e($coLine) ?>
                            <?php } ?></div>
                            <?php if ($ticker !== '') { ?>
                                <div class="triage-worklist-identity__ticker label-secondary" aria-label="Ticker"><?php if ($cid > 0) { ?>
                                    <a class="companies-link triage-worklist-ticker-link" href="index.php?page=company&company_id=<?= komodo_e((string) $cid) ?>#company-securities"><code class="inline-code"><?= komodo_e($ticker) ?></code></a>
                                <?php } else { ?>
                                    <code class="inline-code"><?= komodo_e($ticker) ?></code>
                                <?php } ?></div>
                            <?php } ?>
                            <?php if ($contextLine !== '') { ?>
                                <div class="label-secondary triage-worklist-identity__context"><?= $contextLine ?></div>
                            <?php } ?>
                            <div class="triage-worklist-row-actions">
                                <?php if ($cid > 0) { ?>
                                    <a class="triage-worklist-cta triage-worklist-cta--primary" href="index.php?page=company&company_id=<?= komodo_e((string) $cid) ?>#company-securities">View company detail</a>
                                <?php } ?>
                                <a class="triage-worklist-cta triage-worklist-cta--secondary" href="index.php?page=price-audit#full-plan-heading">Price Audit<?php if ($ticker !== '') { ?> <span class="triage-worklist-cta__hint">(full plan / proof)</span><?php } ?></a>
                            </div>
                        </div>
                    </td>
                    <td data-label="Problem"><div class="triage-prose-cell"><?= komodo_e($wl['gap']) ?></div></td>
                    <td data-label="Missing daily range"><div class="triage-prose-cell"><?= komodo_e($wl['missing']) ?></div></td>
                    <td data-label="Next action"><div class="triage-prose-cell"><?= komodo_e($wl['next']) ?></div></td>
                    <td data-label="Status"><span class="coverage-badge <?= komodo_e(komodo_coverage_badge_css($st)) ?>"<?= $stDesc ? ' title="' . komodo_e($stDesc) . '"' : '' ?>><?= komodo_e($stLabel) ?></span></td>
                    <?php } else { ?>
                    <td data-label="Ticker"><code class="inline-code"><?= komodo_e($ticker) ?></code></td>
                    <td class="triage-company-cell" data-label="Company"><div class="triage-prose-cell"><?= komodo_e($companyName) ?></div></td>
                    <td class="triage-date-window-stack" data-label="Plan window"><div class="triage-date-window-inner"><?= komodo_html_date_window_stack(
                        $row['suggested_import_start_date'] ?? null,
                        $row['suggested_import_end_date'] ?? null
                    ) ?></div></td>
                    <?php if ($isNeeds) { ?>
                        <td class="triage-plan-note-cell" data-label="Plan note"><?php if ($notePlain === '') { ?>
                            —
                        <?php } else { ?>
                            <span class="triage-note-pill" title="<?= komodo_e($notePlain) ?>">Has note</span>
                        <?php } ?></td>
                    <?php } ?>
                    <?php if ($isSpecial) { ?>
                        <?php
                        $specLen = 88;
                        $specShort = $notePlain !== '' && strlen($notePlain) > $specLen ? substr($notePlain, 0, $specLen - 1) . '…' : $notePlain;
                        $specTitle = $notePlain !== '' ? ' title="' . komodo_e($notePlain) . '"' : '';
                        ?>
                        <td class="triage-notes-cell triage-notes-cell--special" data-label="Import note"><?php if ($specShort === '') { ?>
                            —
                        <?php } else { ?>
                            <span class="triage-import-note-snippet"<?= $specTitle ?>><?= komodo_e($specShort) ?></span>
                        <?php } ?></td>
                    <?php } ?>
                    <?php if ($isHistorical) { ?>
                        <?php
                        $histLen = 72;
                        $histShort = $notePlain !== '' && strlen($notePlain) > $histLen ? substr($notePlain, 0, $histLen - 1) . '…' : $notePlain;
                        $histTitle = $notePlain !== '' ? ' title="' . komodo_e($notePlain) . '"' : '';
                        ?>
                        <td class="triage-notes-cell" data-label="Lineage note"><?php if ($histShort === '') { ?>
                            —
                        <?php } else { ?>
                            <span class="triage-import-note-snippet"<?= $histTitle ?>><?= komodo_e($histShort) ?></span>
                        <?php } ?></td>
                    <?php } ?>
                    <td data-label="<?= $isHistorical ? 'Issue' : 'Status' ?>"><?php if ($isHistorical) { ?>
                        <div class="triage-issue-stack">
                            <span class="coverage-badge <?= komodo_e(komodo_coverage_badge_css($st)) ?>"<?= $stDesc ? ' title="' . komodo_e($stDesc) . '"' : '' ?>><?= komodo_e($stLabel) ?></span>
                            <span class="compact-note triage-issue-hint"><?= komodo_e('Ticker continuity / historical identifier') ?></span>
                        </div>
                    <?php } else { ?>
                        <span class="coverage-badge <?= komodo_e(komodo_coverage_badge_css($st)) ?>"<?= $stDesc ? ' title="' . komodo_e($stDesc) . '"' : '' ?>><?= komodo_e($stLabel) ?></span>
                    <?php } ?></td>
                    <?php } ?>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
