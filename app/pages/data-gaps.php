<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$offlineMode = $ctx['offline_mode'];
$gapRows = $ctx['gap_rows'];

?>
<section class="panel shell-section" aria-labelledby="gaps-heading">
    <h2 id="gaps-heading">Data gaps</h2>
    <p class="section-lead"><?= $offlineMode
        ? 'Expected gaps compared to offline reference scaffolding.'
        : 'Derived from interpreted table counts (not a substitute for QA views).' ?></p>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Area</th>
                    <th scope="col">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gapRows as $gap) { ?>
                    <tr>
                        <td><?= komodo_e($gap[0]) ?></td>
                        <td><?= komodo_e($gap[1]) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
