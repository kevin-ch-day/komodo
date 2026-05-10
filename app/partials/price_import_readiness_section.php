<?php

declare(strict_types=1);

/**
 * Price import readiness cards + recommended next action (shared by Market data and Price coverage).
 *
 * @var array<string, mixed>|null $komodo_pir
 */

$techLine = static function (array $r): string {
    $t = $r['technical'] ?? [];
    if (!is_array($t) || $t === []) {
        return '';
    }
    $codes = array_map(static fn ($c) => '<code class="inline-code inline-code--subtle">' . komodo_e((string) $c) . '</code>', $t);

    return implode(' · ', $codes);
};

$pir = $komodo_pir ?? null;
?>
<section class="panel-nested panel-phase--inset price-import-readiness" aria-labelledby="price-readiness-heading">
    <h3 id="price-readiness-heading" class="subsection-heading subsection-heading-tight">Price import readiness</h3>
    <p class="section-lead price-import-readiness__lead"><strong>Readiness</strong> reflects whether rows exist and suggested windows are covered in telemetry — not whether benchmarks are daily-complete for event studies.</p>
    <?php if ($pir === null) { ?>
        <p class="compact-note" role="status">Price import readiness is unavailable until summaries load.</p>
    <?php } else {
        /** @var array<string, mixed> $ov */
        $ov = $pir['overall'];
        /** @var array<string, mixed> $bm */
        $bm = $pir['benchmark'];
        /** @var array<string, mixed> $el */
        $el = $pir['event_linked'];
        /** @var array<string, mixed> $cp */
        $cp = $pir['comparison'];
        $notesCount = (int) ($pir['notes_count'] ?? 0);
        $nextAction = (string) ($pir['next_action'] ?? '');
        ?>
    <div class="price-readiness-overall">
        <span class="compact-note">Overall</span>
        <span class="coverage-badge <?= komodo_e((string) ($ov['badge_class'] ?? 'coverage-badge--unknown')) ?>"><?= komodo_e((string) ($ov['label'] ?? '—')) ?></span>
    </div>

    <div class="market-summary-grid price-readiness-cards" aria-label="Price import readiness by area">
        <article class="stat-card market-summary-card price-readiness-card">
            <h4 class="stat-card__title">Benchmark indexes</h4>
            <p class="stat-card__value"><span class="coverage-badge <?= komodo_e((string) ($bm['badge_class'] ?? '')) ?>"><?= komodo_e((string) ($bm['label'] ?? '—')) ?></span></p>
            <p class="compact-note stat-card__dek"><?= komodo_e((string) ($bm['dek'] ?? '')) ?></p>
            <p class="compact-note price-readiness-card__tech"><?= $techLine($bm) ?></p>
        </article>
        <article class="stat-card market-summary-card price-readiness-card">
            <h4 class="stat-card__title">Event-linked securities</h4>
            <p class="stat-card__value"><span class="coverage-badge <?= komodo_e((string) ($el['badge_class'] ?? '')) ?>"><?= komodo_e((string) ($el['label'] ?? '—')) ?></span></p>
            <p class="compact-note stat-card__dek"><?= komodo_e((string) ($el['dek'] ?? '')) ?></p>
            <p class="compact-note price-readiness-card__tech"><?= $techLine($el) ?></p>
        </article>
        <article class="stat-card market-summary-card price-readiness-card">
            <h4 class="stat-card__title">Comparison / unlinked</h4>
            <p class="stat-card__value"><span class="coverage-badge <?= komodo_e((string) ($cp['badge_class'] ?? '')) ?>"><?= komodo_e((string) ($cp['label'] ?? '—')) ?></span></p>
            <p class="compact-note stat-card__dek"><?= komodo_e((string) ($cp['dek'] ?? '')) ?></p>
            <p class="compact-note price-readiness-card__tech"><?= $techLine($cp) ?></p>
        </article>
        <article class="stat-card market-summary-card price-readiness-card">
            <h4 class="stat-card__title">Special import notes</h4>
            <p class="stat-card__value"><?= komodo_e((string) $notesCount) ?></p>
            <p class="compact-note stat-card__dek"><?= $notesCount === 0
                ? 'None in plan.'
                : 'See the import queue for ticker-level notes.'; ?></p>
        </article>
    </div>

    <div class="price-readiness-next" role="region" aria-label="Recommended next action">
        <p class="price-readiness-next__label">Recommended next action</p>
        <p class="price-readiness-next__body"><?= komodo_e($nextAction) ?></p>
    </div>
    <?php } ?>
</section>
