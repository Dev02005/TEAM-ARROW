<?php
/**
 * Citizen Medicine Cabinet - DoseWise
 * Medication Safety for Everyday Citizens
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/Patient.php';
require_once __DIR__ . '/models/Medication.php';
require_once __DIR__ . '/models/SafetyEngine.php';

requireLogin();
$citizen = currentUser();
$patientId = (int)($citizen['patient_id'] ?? 0);

if ($patientId <= 0) {
    if (isAdmin()) {
        redirect(BASE_URL . 'patients.php');
    }
    setFlash('danger', t_plain('flash.profile_missing'));
    redirect(BASE_URL . 'index.php');
}

$patientModel = new Patient();
$patient = $patientModel->getById($patientId);
if (!$patient) {
    setFlash('danger', t_plain('flash.profile_missing'));
    redirect(BASE_URL . 'index.php');
}

$medicationModel = new Medication();
$activeMeds = $medicationModel->getByPatientId($patientId, 'active');
$historicalMeds = $medicationModel->getByPatientId($patientId, 'discontinued');

$safetyEngine = new SafetyEngine();
$alerts = $safetyEngine->evaluatePatient($patientId);
$scoreData = calculateSafetyScore($alerts);

// Check if any active medicine is not available in the database
$unresolvedMeds = [];
foreach ($activeMeds as $med) {
    if (empty($med['matched_generic'])) {
        $resolved = $safetyEngine->resolveMedicine($med['raw_medicine_name']);
        if (empty($resolved)) {
            $unresolvedMeds[] = $med['raw_medicine_name'];
        }
    }
}

if (!empty($unresolvedMeds)) {
    $level = 'unknown';
    $scoreData['label'] = "We cant decide whether it is safe or not";
    $scoreData['score'] = 'NA';
    $scoreData['summary'] = "We cant decide whether it is safe or not. Your active medicines list includes unverified medicine(s) (" . implode(', ', $unresolvedMeds) . ") not found in our clinical database. Because active ingredients and safety parameters are unconfirmed, an overall safety score cannot be calculated.";
} else {
    $level = $alerts ? severityLevel($scoreData['severity']) : 'clean';
}

$egfrStage = getEgfrStage($patient['egfr'] !== null ? (float)$patient['egfr'] : null);
$pageTitle = t_plain('nav.my_medicines');
$egfrVal = $patient['egfr'] !== null ? (float)$patient['egfr'] : null;

require_once __DIR__ . '/includes/header.php';
?>

<div class="ez-page">

    <!-- Welcome -->
    <div class="ez-page-head">
        <p class="ez-eyebrow"><?= t('cabinet.eyebrow') ?></p>
        <h2 class="ez-page-title"><?= t('cabinet.welcome', ['name' => $citizen['full_name']]) ?></h2>
    </div>

    <!-- 1. OVERALL STATUS: one clear message first -->
    <section class="ez-status ez-status--<?= $level ?>" id="cabinetStatus">
        <div class="ez-status__top">
            <div class="ez-status__icon" aria-hidden="true"><?= $alerts ? severityIcon($level) : '🛡️' ?></div>
            <div class="ez-status__text">
                <div class="ez-status__label"><?= h($scoreData['label']) ?></div>
                <div class="ez-status__count">
                    <?= $alerts ? t('cabinet.n_issues', ['n' => count($alerts)]) : t('cabinet.no_issues') ?>
                </div>
            </div>
            <div class="ez-score" style="<?= ($scoreData['score'] ?? '') === 'NA' ? 'border-color: #cbd5e1; background: #f8fafc;' : '' ?>">
                <span class="ez-score__num" style="<?= ($scoreData['score'] ?? '') === 'NA' ? 'color: #64748b; font-size: 28px;' : '' ?>"><?= ($scoreData['score'] ?? '') === 'NA' ? 'NA' : ((int)$scoreData['score'] . '%') ?></span>
                <span class="ez-score__label"><?= t('common.safety_score') ?></span>
            </div>
        </div>
        <p class="ez-status__summary"><?= h($scoreData['summary']) ?></p>
        <?php if (!empty($unresolvedMeds)): ?>
            <div class="ez-alert ez-alert--note" style="border-left-color: #64748b; background: #fff; margin: 12px 0;">
                <strong style="color: #334155;">⚠️ Unverified Medicine in Your Cabinet:</strong>
                <p style="margin: 4px 0 0; font-size: 15.5px; color: var(--ez-text-2);">
                    The medicine(s) <strong><?= h(implode(', ', $unresolvedMeds)) ?></strong> could not be matched with our database of approved medicines. We recommend verifying the spelling or discussing with your doctor or pharmacist.
                </p>
            </div>
        <?php endif; ?>
        <button type="button" class="ez-speak" data-speak-target="#cabinetStatus">🔊 <?= t('common.read_aloud') ?></button>
        <div class="ez-actions ez-actions--row">
            <a href="<?= BASE_URL ?>print_safety_card.php" target="_blank" class="ez-big-btn ez-big-btn--light">
                <span class="ez-big-btn__icon">🖨️</span><span><?= t('cabinet.doctor_card') ?><small><?= t('cabinet.doctor_card_sub') ?></small></span>
            </a>
            <a href="<?= BASE_URL ?>attach_prescription.php" class="ez-big-btn ez-big-btn--primary">
                <span class="ez-big-btn__icon">🔍</span><span><?= t('cabinet.check_new') ?></span>
            </a>
        </div>
    </section>

    <!-- 2. THINGS TO TALK ABOUT WITH THE DOCTOR -->
    <?php if (!empty($alerts)): ?>
        <section class="ez-section">
            <h3 class="ez-section__title">⚠️ <?= t('cabinet.alerts_title', ['n' => count($alerts)]) ?></h3>
            <p class="ez-section__desc"><?= t('cabinet.alerts_desc') ?></p>
            <div class="ez-alert-list">
                <?php foreach ($alerts as $a): ?>
                    <?= renderPatientAlert($a, $egfrVal) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php else: ?>
        <section class="ez-ok">
            <div class="ez-ok__icon">🛡️</div>
            <div class="ez-ok__title"><?= t('cabinet.ok_title') ?></div>
            <p><?= t('cabinet.ok_desc') ?></p>
        </section>
    <?php endif; ?>

    <!-- 3. MEDICINES I AM TAKING -->
    <section class="ez-section">
        <div class="ez-section__row">
            <h3 class="ez-section__title">💊 <?= t('cabinet.meds_title', ['n' => count($activeMeds)]) ?></h3>
            <a href="<?= BASE_URL ?>attach_prescription.php" class="btn btn-primary">➕ <?= t('cabinet.add') ?></a>
        </div>
        <p class="ez-section__desc"><?= t('cabinet.meds_desc') ?></p>

        <?php if (empty($activeMeds)): ?>
            <div class="ez-empty">
                <div class="ez-empty__icon">💊</div>
                <div class="ez-empty__title"><?= t('cabinet.empty_title') ?></div>
                <p><?= t('cabinet.empty_desc') ?></p>
                <a href="<?= BASE_URL ?>attach_prescription.php" class="btn btn-primary btn-lg"><?= t('cabinet.empty_btn') ?></a>
            </div>
        <?php else: ?>
            <ul class="ez-med-list">
                <?php foreach ($activeMeds as $med): ?>
                    <li class="ez-med">
                        <div class="ez-med__name">💊 <?= h($med['raw_medicine_name']) ?></div>
                        <dl class="ez-med__facts">
                            <div><dt><?= t('med.dose') ?></dt><dd><?= h($med['dosage']) ?: '—' ?></dd></div>
                            <div><dt><?= t('med.how_often') ?></dt><dd><?= h($med['frequency']) ?: '—' ?></dd></div>
                            <div><dt><?= t('med.doctor') ?></dt><dd><?= h($med['prescribing_doctor']) ?: '—' ?></dd></div>
                            <div><dt><?= t('med.date') ?></dt><dd><?= formatDate($med['prescription_date']) ?></dd></div>
                        </dl>
                        <form method="POST" action="<?= BASE_URL ?>medication_delete.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="medication_id" value="<?= (int)$med['id'] ?>">
                            <input type="hidden" name="patient_id" value="<?= $patientId ?>">
                            <input type="hidden" name="action" value="discontinue">
                            <button type="submit" class="btn btn-secondary ez-med__stop" data-confirm="<?= t_attr('cabinet.stop_confirm', ['name' => $med['raw_medicine_name']]) ?>">
                                <?= t('cabinet.stopped') ?>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- 4. MY HEALTH DETAILS (used for the checks) -->
    <section class="ez-section">
        <h3 class="ez-section__title">🩺 <?= t('cabinet.health_title') ?></h3>
        <dl class="ez-info">
            <div>
                <dt>🫘 <?= t('health.kidney') ?></dt>
                <dd>
                    <?php if ($patient['egfr'] !== null): ?>
                        <?= h($egfrStage['plain_name']) ?> <span class="ez-muted">(eGFR <?= h((string)round((float)$patient['egfr'])) ?>)</span>
                    <?php else: ?>
                        <?= t('health.not_recorded') ?>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>🩺 <?= t('health.conditions') ?></dt>
                <dd><?= !empty($patient['conditions']) ? h($patient['conditions']) : t('health.none_recorded') ?></dd>
            </div>
            <div>
                <dt>⚠️ <?= t('health.allergies') ?></dt>
                <dd>
                    <?php if (!empty($patient['allergies']) && !in_array(strtolower(trim($patient['allergies'])), ['none', 'nkda'], true)): ?>
                        <strong class="ez-danger-text"><?= h($patient['allergies']) ?></strong>
                    <?php else: ?>
                        <?= t('health.no_allergies') ?>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
