<?php

declare(strict_types=1);

/**
 * Ordered list for Price import triage “next batch” rows (read-only).
 *
 * @var list<array<string, mixed>> $komodo_nb_rows
 */

$komodo_nb_rows = $komodo_nb_rows ?? [];
if ($komodo_nb_rows === []) {
    return;
}

?>
<ol class="triage-next-batch-list compact-note">
    <?php foreach ($komodo_nb_rows as $nb) {
        $nst = (string) ($nb['coverage_status'] ?? '');
        $nlab = komodo_label($nst, 'coverage_status');
        $evc = (int) ($nb['linked_event_count'] ?? 0);
        ?>
        <li>
            <code class="inline-code"><?= komodo_e((string) ($nb['ticker_symbol'] ?? '')) ?></code>
            <span class="triage-next-batch-sep" aria-hidden="true">·</span>
            <span class="coverage-badge <?= komodo_e(komodo_coverage_badge_css($nst)) ?>"><?= komodo_e($nlab) ?></span>
            <?php if ($evc > 0) { ?>
            <span class="triage-next-batch-sep" aria-hidden="true">·</span>
            <span class="compact-note"><?= komodo_e((string) $evc) ?> linked events</span>
            <?php } ?>
        </li>
    <?php } ?>
</ol>
