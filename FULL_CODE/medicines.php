<?php
/**
 * 496 Official Clinical Medicines Directory - DoseWise
 * Medication Safety for Everyday Citizens & Healthcare Professionals
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/Medication.php';

$pageTitle = t_plain('meds.page_title');

$medicationModel = new Medication();
$allMedicines = $medicationModel->getCatalog();
$totalMedicinesCount = count($allMedicines);
$combos = $medicationModel->getCombinationBrands();
$totalCombosCount = count($combos);

require_once __DIR__ . '/includes/header.php';
?>

<div class="ez-page ez-wide">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--primary); letter-spacing: 0.05em;">
                <?= t('meds.eyebrow') ?>
            </div>
            <h2 style="font-size: 28px; font-weight: 900; color: var(--text-main); letter-spacing: -0.02em;">
                <?= t('meds.title', ['n' => $totalMedicinesCount]) ?>
            </h2>
            <p style="font-size: 14.5px; color: var(--text-muted); margin-top: 4px;">
                <?= t('meds.desc') ?>
            </p>
        </div>

        <div>
            <a href="<?= BASE_URL ?>attach_prescription.php" class="btn btn-primary btn-lg" style="box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);">
                <span>🔍</span> <?= t('nav.check') ?>
            </a>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="card" style="padding: 24px; margin-bottom: 24px;">
        <div style="position: relative; margin-bottom: 16px;">
            <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 18px; color: var(--text-muted);">🔍</span>
            <input type="text" id="medSearchInput" class="form-control" style="font-size: 16px; padding: 12px 16px 12px 46px; border-radius: var(--radius-md);" placeholder="<?= t_attr('meds.search_ph') ?>" aria-label="<?= t_attr('meds.search_ph') ?>" oninput="filterMedicines()">
        </div>

        <!-- Category Filter Pills -->
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px;">
            <button type="button" class="filter-tab-pill active" onclick="setCategoryFilter('all', this)">
                <?= t('meds.cat_all') ?> (<?= $totalMedicinesCount ?>)
            </button>
            <button type="button" class="filter-tab-pill" onclick="setCategoryFilter('Cardiovascular', this)">
                <?= t('meds.cat_heart') ?> (89)
            </button>
            <button type="button" class="filter-tab-pill" onclick="setCategoryFilter('Anti-infective', this)">
                <?= t('meds.cat_infection') ?> (77)
            </button>
            <button type="button" class="filter-tab-pill" onclick="setCategoryFilter('Psychiatry', this)">
                <?= t('meds.cat_mind') ?> (49)
            </button>
            <button type="button" class="filter-tab-pill" onclick="setCategoryFilter('Gastrointestinal', this)">
                <?= t('meds.cat_stomach') ?> (41)
            </button>
            <button type="button" class="filter-tab-pill" onclick="setCategoryFilter('Neurology', this)">
                <?= t('meds.cat_nerves') ?> (38)
            </button>
            <button type="button" class="filter-tab-pill" onclick="setCategoryFilter('Analgesic & musculoskeletal', this)">
                <?= t('meds.cat_pain') ?> (31)
            </button>
            <button type="button" class="filter-tab-pill" onclick="setCategoryFilter('Diabetes & metabolic', this)">
                <?= t('meds.cat_diabetes') ?> (28)
            </button>
            <button type="button" class="filter-tab-pill" onclick="setCategoryFilter('Respiratory', this)">
                <?= t('meds.cat_breathing') ?> (24)
            </button>
        </div>

        <!-- Alphabet A-Z Jump Bar -->
        <div class="ez-hide-mobile" style="display: flex; gap: 4px; flex-wrap: wrap; align-items: center; padding: 8px 12px; background: var(--bg-subtle); border-radius: var(--radius-sm); border: 1px solid var(--border);">
            <span style="font-size: 12px; font-weight: 700; color: var(--text-muted); margin-right: 6px;"><?= t('meds.jump') ?></span>
            <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 7px; font-size: 12px;" onclick="setLetterFilter('', this)"><?= t('meds.all') ?></button>
            <?php foreach (range('A', 'Z') as $char): ?>
                <button type="button" class="btn btn-secondary btn-sm letter-btn" style="padding: 2px 7px; font-size: 12px;" onclick="setLetterFilter('<?= $char ?>', this)">
                    <?= $char ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Live Counter -->
    <div style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
        <span><?= str_replace('{shown}', '<strong id="visibleMedCount">' . $totalMedicinesCount . '</strong>', t('meds.showing', ['total' => $totalMedicinesCount])) ?></span>
        <span class="ez-hide-mobile" style="font-size: 12px; color: #166534;">⚡ <?= t('meds.dataset', ['n' => $totalMedicinesCount, 'c' => $totalCombosCount]) ?></span>
    </div>

    <!-- 496 Medicines Card Grid -->
    <div id="medicinesContainer" class="ez-med-grid">
        <?php foreach ($allMedicines as $idx => $m): 
            $firstLetter = strtoupper(substr($m['generic_name'], 0, 1));
            $hasRenalWarning = !empty($m['renal_dose_adjustment_required']);
            $pCat = $m['pregnancy_category'] ?: 'N';
            $pCatColor = match($pCat) {
                'X' => '#dc2626',
                'D' => '#ea580c',
                'C' => '#f59e0b',
                'B' => '#10b981',
                'A' => '#059669',
                default => '#64748b'
            };
        ?>
            <div class="card medicine-card-item" 
                 data-generic="<?= h(strtolower($m['generic_name'])) ?>" 
                 data-brand="<?= h(strtolower($m['common_brands'] ?? '')) ?>" 
                 data-category="<?= h($m['therapeutic_group']) ?>" 
                 data-letter="<?= $firstLetter ?>"
                 style="padding: 20px; margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.15s ease; border-left: 5px solid var(--primary);">
                
                <div>
                    <!-- Top Category & ID -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span class="badge badge-info" style="font-size: 11px;">
                            <?= h($m['drug_class'] ?: $m['therapeutic_group']) ?>
                        </span>
                        <div style="display: flex; gap: 4px; align-items: center;">
                            <span class="badge" style="background: <?= $pCatColor ?>22; color: <?= $pCatColor ?>; border: 1px solid <?= $pCatColor ?>; font-size: 10px; font-weight: 800;" title="<?= t_attr('meds.preg_title', ['cat' => $pCat]) ?>">
                                <?= t('meds.preg') ?>: <?= $pCat ?>
                            </span>
                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted);">
                                <?= h($m['drug_id']) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Medicine Generic Name -->
                    <h4 style="font-size: 17px; font-weight: 800; color: var(--text-main); margin-bottom: 3px; text-transform: capitalize;">
                        <?= h($m['generic_name']) ?>
                    </h4>

                    <!-- Indian Brands -->
                    <?php if (!empty($m['common_brands'])): ?>
                        <div style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 12px; line-height: 1.4;">
                            <span style="font-weight: 600; color: var(--text-main);"><?= t('meds.brands') ?>:</span> 
                            <span style="color: var(--primary); font-weight: 500;"><?= h(str_replace(';', ', ', $m['common_brands'])) ?></span>
                        </div>
                    <?php else: ?>
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px; font-style: italic;">
                            <?= t('meds.generic_only') ?>
                        </div>
                    <?php endif; ?>

                    <!-- Standard Dosage & Kidney Risk Information -->
                    <div style="background: var(--bg-subtle); padding: 10px 12px; border-radius: var(--radius-sm); font-size: 12.5px; margin-bottom: 12px; border: 1px solid var(--border);">
                        <?php if (!empty($m['usual_adult_daily_dose_range'])): ?>
                            <div style="margin-bottom: 4px;">
                                <strong style="color: var(--text-main);"><?= t('meds.usual_dose') ?>:</strong> <?= h($m['usual_adult_daily_dose_range']) ?>
                                <?php if ($m['max_daily_dose_mg']): ?>
                                    <span style="color: var(--text-muted);">(<?= t('meds.max') ?>: <?= $m['max_daily_dose_mg'] ?> mg/day)</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($hasRenalWarning): ?>
                            <div style="color: #b45309; font-weight: 600; display: flex; align-items: flex-start; gap: 4px; margin-top: 4px;">
                                <span>⚠️</span>
                                <span><?= t('meds.kidney_alert') ?>: <span lang="en"><?= h($m['renal_rule'] ?: 'Dose adjustment required in renal decline') ?></span></span>
                            </div>
                        <?php else: ?>
                            <div style="color: #166534; font-size: 11.5px; margin-top: 3px;">
                                ✓ <?= t('meds.no_kidney') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Button -->
                <div style="padding-top: 10px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
                    <a href="<?= BASE_URL ?>attach_prescription.php?drug=<?= urlencode($m['generic_name']) ?>" class="btn btn-secondary btn-sm" style="font-weight: 700; font-size: 12.5px;">
                        <span>🔍</span> <?= t('meds.check_me') ?> &rarr;
                    </a>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

</div>

<script>
let currentCategory = 'all';
let currentLetter = '';

function setCategoryFilter(category, button) {
    currentCategory = category;
    document.querySelectorAll('.filter-tab-pill').forEach(btn => btn.classList.remove('active'));
    button.classList.add('active');
    filterMedicines();
}

function setLetterFilter(letter, button) {
    currentLetter = letter.toUpperCase();
    document.querySelectorAll('.letter-btn').forEach(btn => btn.classList.remove('btn-primary'));
    if (letter !== '') {
        button.classList.add('btn-primary');
    }
    filterMedicines();
}

function filterMedicines() {
    const query = document.getElementById('medSearchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.medicine-card-item');
    let visibleCount = 0;

    cards.forEach(card => {
        const generic = card.getAttribute('data-generic');
        const brand = card.getAttribute('data-brand');
        const category = card.getAttribute('data-category');
        const letter = card.getAttribute('data-letter');

        const matchesQuery = !query || generic.includes(query) || brand.includes(query);
        const matchesCategory = (currentCategory === 'all') || (category === currentCategory);
        const matchesLetter = !currentLetter || (letter === currentLetter);

        if (matchesQuery && matchesCategory && matchesLetter) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    document.getElementById('visibleMedCount').textContent = visibleCount;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
