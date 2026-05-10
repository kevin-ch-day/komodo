<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$offlineMode = $ctx['offline_mode'];
$tableCountsSafe = $ctx['table_counts_safe'];
$tableOrder = $ctx['table_order'];

?>
<section class="panel shell-section" aria-labelledby="dataset-heading">
    <h2 id="dataset-heading">Dataset</h2>
    <p class="section-lead">Core table inventory (read-only research portal) for <code class="inline-code">gecko_research_database_prod</code>.
        <?= $offlineMode ? ' Offline reference counts — connect for live totals.' : ' Live row counts from MariaDB COUNT(*).' ?></p>
    <div class="table-scroll">
        <table class="data-table data-table--labeled-mobile">
            <thead>
                <tr>
                    <th scope="col">Object</th>
                    <th scope="col" class="num">Rows</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableOrder as $tbl) {
                    $ref = KOMODO_OFFLINE_TABLE_REFERENCE[$tbl] ?? null;
                    $tblLabel = komodo_label($tbl, 'db_object');
                    ?>
                    <tr>
                        <td data-label="Object">
                            <div class="label-stack">
                                <span class="label-primary"><?= komodo_e($tblLabel) ?></span>
                                <span class="label-secondary"><code class="inline-code inline-code--subtle"><?= komodo_e($tbl) ?></code></span>
                            </div>
                        </td>
                        <td class="num" data-label="Rows"><?php echo komodo_metric_html($offlineMode, $tableCountsSafe, $tbl, $ref); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
