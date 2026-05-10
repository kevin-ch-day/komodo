<?php

declare(strict_types=1);

/**
 * Simplified triage tables for Price import triage (read-only).
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

?>
<div class="table-scroll">
    <table class="data-table data-table--sticky data-table--triage" aria-label="<?= komodo_e($komodo_triage_aria_label) ?>">
        <thead>
            <tr>
                <th scope="col">Ticker</th>
                <th scope="col">Company</th>
                <th scope="col">Suggested window</th>
                <?php if ($komodo_triage_mode === 'window') { ?>
                    <th scope="col">Loaded span</th>
                <?php } ?>
                <th scope="col">Notes</th>
                <th scope="col"><?= $komodo_triage_mode === 'historical' ? 'Issue' : ($komodo_triage_mode === 'window' ? 'Issue' : 'Status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($komodo_triage_rows as $row) {
                $st = (string) ($row['coverage_status'] ?? '');
                $stLabel = komodo_label($st, 'coverage_status');
                $stDesc = komodo_describe($st, 'coverage_status');
                $rawNote = isset($row['import_notes']) ? trim((string) $row['import_notes']) : '';
                $notePlain = $rawNote !== '' ? trim(preg_replace('/\s+/', ' ', strip_tags($rawNote))) : '';
                $noteShort = $notePlain !== '' && strlen($notePlain) > 96 ? substr($notePlain, 0, 93) . '…' : $notePlain;
                $trTitle = $notePlain !== '' && $notePlain !== $noteShort ? ' title="' . komodo_e($notePlain) . '"' : '';
                $windowLabel = komodo_format_display_date_range(
                    $row['suggested_import_start_date'] ?? null,
                    $row['suggested_import_end_date'] ?? null
                );
                $wfRaw = $row['first_price_date'] ?? null;
                $wlRaw = $row['last_price_date'] ?? null;
                $loadedLabel = komodo_format_display_date_range($wfRaw, $wlRaw);
                $trClass = komodo_triage_row_prominent_class($row);
                ?>
                <tr<?= $trClass !== '' ? ' class="' . komodo_e($trClass) . '"' : '' ?><?= $trTitle ?>>
                    <td><code class="inline-code"><?= komodo_e((string) ($row['ticker_symbol'] ?? '')) ?></code></td>
                    <td><?= komodo_e((string) ($row['display_name'] ?: ($row['security_name'] ?? ''))) ?></td>
                    <td class="triage-date-cell"><?= $windowLabel !== '' ? komodo_e($windowLabel) : '—'; ?></td>
                    <?php if ($komodo_triage_mode === 'window') { ?>
                        <td class="triage-date-cell"><?= $loadedLabel !== '' ? komodo_e($loadedLabel) : '—'; ?></td>
                    <?php } ?>
                    <td class="triage-notes-cell"><?= $noteShort !== '' ? komodo_e($noteShort) : '—'; ?></td>
                    <td><?php if ($komodo_triage_mode === 'historical') { ?>
                        <div class="triage-issue-stack">
                            <span class="coverage-badge <?= komodo_e(komodo_coverage_badge_css($st)) ?>"<?= $stDesc ? ' title="' . komodo_e($stDesc) . '"' : '' ?>><?= komodo_e($stLabel) ?></span>
                            <span class="compact-note triage-issue-hint"><?= komodo_e('Ticker continuity / historical identifier') ?></span>
                        </div>
                    <?php } else { ?>
                        <span class="coverage-badge <?= komodo_e(komodo_coverage_badge_css($st)) ?>"<?= $stDesc ? ' title="' . komodo_e($stDesc) . '"' : '' ?>><?= komodo_e($stLabel) ?></span>
                    <?php } ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
