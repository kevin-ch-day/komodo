<?php

declare(strict_types=1);

/** @var array<string, mixed> $ctx */

$workflow = $ctx['workflow'];
$phaseStatusLines = $ctx['phase_status_lines'];
$pipelineNarrative = $ctx['pipeline_narrative'];

?>
<section class="panel shell-section panel-muted" aria-labelledby="pipe-heading">
    <h2 id="pipe-heading">Pipeline</h2>
    <p class="section-lead">Roadmap and workflow narrative for the event-study database build-out.</p>

    <div class="overview-workflow overview-workflow--spaced panel-nested panel-phase--inset">
        <h3 class="overview-phase-heading">Current phase</h3>
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

    <h3 class="subsection-heading">Roadmap checkpoints</h3>
    <ul class="pipeline-list">
        <?php foreach ($pipelineNarrative as $nar) { ?>
            <li><?= komodo_e($nar) ?></li>
        <?php } ?>
    </ul>
</section>
