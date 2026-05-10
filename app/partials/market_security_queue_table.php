<?php

declare(strict_types=1);

/**
 * Shared security import queue / plan row table (read-only).
 *
 * @var list<array<string, mixed>> $komodo_msq_rows
 * @var string $komodo_msq_aria_label
 * @var string $komodo_msq_empty_html escaped short message when no rows
 * @var (callable(array<string, mixed>): string)|null $komodo_msq_row_class_cb optional CSS class per row
 */

$komodo_msq_rows = $komodo_msq_rows ?? [];
$komodo_msq_aria_label = $komodo_msq_aria_label ?? 'Security import rows';
$komodo_msq_empty_html = $komodo_msq_empty_html ?? '—';
$komodo_msq_row_class_cb = $komodo_msq_row_class_cb ?? null;

if ($komodo_msq_rows === []) {
    echo '<p class="compact-note">' . $komodo_msq_empty_html . '</p>';

    return;
}

?>
<div class="table-scroll">
    <table class="data-table data-table--sticky data-table--dense data-table--labeled-mobile" aria-label="<?= komodo_e($komodo_msq_aria_label) ?>">
        <thead>
            <tr>
                <th scope="col">Ticker</th>
                <th scope="col">Company</th>
                <th scope="col">Role</th>
                <th scope="col" class="num">Events</th>
                <th scope="col">Plan window</th>
                <th scope="col" class="num">Price rows</th>
                <th scope="col">First / last bar</th>
                <th scope="col">Status</th>
                <th scope="col">Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($komodo_msq_rows as $fw) {
                $fst = (string) ($fw['coverage_status'] ?? '');
                $fstLabel = komodo_label($fst, 'coverage_status');
                $fstDesc = komodo_describe($fst, 'coverage_status');
                [$nDisp, $nFull, $hasTtl] = komodo_note_preview(isset($fw['import_notes']) ? (string) $fw['import_notes'] : '', 100);
                $wf = komodo_normalize_date_string($fw['first_price_date'] ?? null);
                $wl = komodo_normalize_date_string($fw['last_price_date'] ?? null);
                $barSpanHtml = komodo_html_date_window_stack(
                    $fw['first_price_date'] ?? null,
                    $fw['last_price_date'] ?? null
                );
                $roleKey = (string) ($fw['price_import_role'] ?? '');
                $roleLabel = komodo_label($roleKey, 'role');
                $roleDesc = $roleKey !== '' ? komodo_describe($roleKey, 'role') : null;
                $trClass = '';
                if (is_callable($komodo_msq_row_class_cb)) {
                    $trClass = trim((string) $komodo_msq_row_class_cb($fw));
                }
                ?>
                <tr<?= $trClass !== '' ? ' class="' . komodo_e($trClass) . '"' : '' ?>>
                    <td data-label="Ticker"><code class="inline-code"><?= komodo_e((string) ($fw['ticker_symbol'] ?? '')) ?></code></td>
                    <td data-label="Company"><?= komodo_e((string) ($fw['display_name'] ?: ($fw['security_name'] ?? ''))) ?></td>
                    <td data-label="Role">
                        <div class="label-stack">
                            <span class="label-primary"<?= $roleDesc ? ' title="' . komodo_e($roleDesc) . '"' : '' ?>><?= komodo_e($roleLabel) ?></span>
                            <?php if ($roleKey !== '') { ?>
                                <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($roleKey) ?></code></span>
                            <?php } ?>
                        </div>
                    </td>
                    <td class="num" data-label="Events"><?= isset($fw['linked_event_count']) ? komodo_e((string) $fw['linked_event_count']) : '—' ?></td>
                    <td class="triage-date-window-stack" data-label="Plan window"><?= komodo_html_date_window_stack(
                        $fw['suggested_import_start_date'] ?? null,
                        $fw['suggested_import_end_date'] ?? null
                    ) ?></td>
                    <td class="num" data-label="Price rows"><?= komodo_e((string) ($fw['price_rows'] ?? 0)) ?></td>
                    <td class="triage-date-window-stack" data-label="First / last bar"><?php echo $barSpanHtml; ?></td>
                    <td data-label="Status"><span class="coverage-badge <?= komodo_e(komodo_coverage_badge_css($fst)) ?>"<?= $fstDesc ? ' title="' . komodo_e($fstDesc) . '"' : '' ?>><?= komodo_e($fstLabel) ?></span></td>
                    <td class="compact-note" data-label="Notes"><?php if ($nDisp !== '') { ?>
                        <span<?= $hasTtl ? ' title="' . $nFull . '"' : '' ?>><?= $nDisp ?></span>
                    <?php } else {
                        echo '—';
                    } ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
