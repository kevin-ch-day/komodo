<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$offlineMode = $ctx['offline_mode'];
$tableCountsSafe = $ctx['table_counts_safe'];
$tableOrder = $ctx['table_order'];

?>
<section class="panel shell-section" aria-labelledby="dataset-heading">
    <h2 id="dataset-heading">Dataset</h2>
    <p class="section-lead">Core table inventory for <code class="inline-code">gecko_research_database_prod</code>.
        <?= $offlineMode ? ' Offline reference counts — connect for live totals.' : ' Live row counts from MariaDB COUNT(*).' ?></p>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Table</th>
                    <th scope="col" class="num">Rows</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableOrder as $tbl) {
                    $ref = KOMODO_OFFLINE_TABLE_REFERENCE[$tbl] ?? null;
                    ?>
                    <tr>
                        <td><code class="inline-code"><?= komodo_e($tbl) ?></code></td>
                        <td class="num"><?php echo komodo_metric_html($offlineMode, $tableCountsSafe, $tbl, $ref); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
