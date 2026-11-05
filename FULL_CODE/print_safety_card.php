<?php
/**
 * Doctor Visit Safety Card / Medication Passport - DoseWise
 * Clean, high-contrast printable document for citizens to bring to appointments
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/Patient.php';
require_once __DIR__ . '/models/Medication.php';
require_once __DIR__ . '/models/SafetyEngine.php';

$citizen = currentUser();
// If logged in as a citizen, strictly lock to their own patient ID
if ($citizen && !isAdmin()) {
    $patientId = (int)$citizen['patient_id'];
} else {
    $patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : ($citizen ? (int)$citizen['patient_id'] : 1);
}

if (!canViewPatient($patientId)) {
    die(h(t_plain('card.denied')));
}

$patientModel = new Patient();
$patient = $patientModel->getById($patientId);

if (!$patient) {
    die(h(t_plain('card.not_found')));
}

$medicationModel = new Medication();
$activeMeds = $medicationModel->getByPatientId($patientId, 'active');
$discontinuedMeds = $medicationModel->getByPatientId($patientId, 'discontinued');

$safetyEngine = new SafetyEngine();
$alerts = $safetyEngine->evaluatePatient($patientId);
$scoreData = calculateSafetyScore($alerts);
$egfrStage = getEgfrStage($patient['egfr'] !== null ? (float)$patient['egfr'] : null);
?>
<!DOCTYPE html>
<html lang="<?= html_lang() ?>" data-speech-lang="<?= speech_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t_attr('card.title') ?> &mdash; <?= h($patient['full_name']) ?> &mdash; DoseWise</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Telugu", "Noto Sans Devanagari", Arial, sans-serif;
            color: #0f172a;
        }
        body {
            background: #f8fafc;
            padding: 24px;
        }
        .passport-card {
            max-width: 860px;
            margin: 0 auto;
            background: white;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #0284c7;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }
        .brand-title {
            font-size: 26px;
            font-weight: 900;
            color: #0369a1;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .brand-subtitle {
            font-size: 13px;
            color: #64748b;
            margin-top: 3px;
        }
        .print-btn-bar {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 860px;
            margin: 0 auto 20px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }
        .btn-primary { background: #0284c7; color: white; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            background: #f1f5f9;
            padding: 18px;
            border-radius: 6px;
            margin-bottom: 24px;
        }
        .info-block-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.05em;
        }
        .info-block-val {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 4px;
        }
        .score-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 9999px;
            font-weight: 800;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        th {
            background: #f8fafc;
            border-bottom: 2px solid #cbd5e1;
            padding: 10px 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13.5px;
            vertical-align: top;
        }
        .alert-box {
            border-left: 5px solid #dc2626;
            background: #fef2f2;
            padding: 14px 18px;
            border-radius: 4px;
            margin-bottom: 14px;
        }
        .alert-title {
            font-size: 14.5px;
            font-weight: 800;
            color: #991b1b;
            margin-bottom: 4px;
        }
        .alert-desc {
            font-size: 13px;
            color: #7f1d1d;
            line-height: 1.4;
        }
        .alert-doctor-question {
            margin-top: 8px;
            padding: 8px 12px;
            background: white;
            border: 1px dashed #f87171;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 700;
            color: #991b1b;
        }
        .disclaimer-box {
            margin-top: 28px;
            padding-top: 16px;
            border-top: 1px dashed #cbd5e1;
            font-size: 11.5px;
            color: #64748b;
            line-height: 1.4;
        }
        .bi-en { font-weight: 500; color: #64748b; font-size: 0.85em; }
        .patient-note {
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px;
            padding: 12px 16px; margin-bottom: 20px; font-size: 15px; line-height: 1.6; color: #1e3a8a;
        }
        .sev-local { display: block; font-size: 14px; font-weight: 700; margin-top: 4px; }
        /* Phones: one column, bigger text, table rows become cards */
        @media (max-width: 700px) {
            body { padding: 10px; }
            .passport-card { padding: 16px; }
            .header-bar { flex-direction: column; gap: 10px; }
            .header-bar > div:last-child { text-align: left !important; }
            .info-grid { grid-template-columns: 1fr; }
            .info-grid > div { grid-column: auto !important; }
            .print-btn-bar { flex-direction: column; gap: 10px; align-items: stretch; }
            .btn { justify-content: center; min-height: 52px; font-size: 17px; }
            table, thead, tbody, tr, td { display: block; width: 100% !important; }
            thead { display: none; }
            tr { border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 10px; padding: 6px; }
            td { border: none; font-size: 15px; padding: 4px 8px; }
            .alert-desc, .alert-doctor-question { font-size: 14.5px; }
        }
        @media print {
            body { background: white; padding: 0; }
            .passport-card { border: none; padding: 0; box-shadow: none; max-width: 100%; }
            .print-btn-bar { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="print-btn-bar">
        <a href="<?= BASE_URL ?>my_prescriptions.php" class="btn btn-secondary">
            &larr; <?= t('card.back') ?>
        </a>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn btn-primary">
                🖨️ <?= t('card.print') ?>
            </button>
        </div>
    </div>

    <div class="passport-card">
        <!-- Header -->
        <div class="header-bar">
            <div>
                <div class="brand-title">
                    <span>💊</span>
                    <span>DoseWise</span>
                    <span style="font-size: 16px; font-weight: 500; color: #64748b;">| <?= t_bi('card.passport') ?></span>
                </div>
                <div class="brand-subtitle">
                    <?= t_bi('card.passport_sub') ?>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 12px; font-weight: 700; color: #64748b;"><?= t_bi('card.date') ?></div>
                <div style="font-size: 14px; font-weight: 800; color: #0f172a;"><?= formatDate(date('Y-m-d')) ?> &bull; <?= date('g:i A') ?></div>
            </div>
        </div>

        <?php if (current_lang() !== 'en'): ?>
            <div class="patient-note">📋 <?= t('card.patient_note') ?></div>
        <?php endif; ?>

        <!-- Patient Demographics & Baseline Health Profile -->
        <div class="info-grid">
            <div>
                <div class="info-block-label"><?= t_bi('card.patient_name') ?></div>
                <div class="info-block-val"><?= h($patient['full_name']) ?></div>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                    <?= t('card.age') ?>: <?= !empty($patient['date_of_birth']) ? calculateAge($patient['date_of_birth']) : (!empty($patient['age']) ? t('common.years', ['n' => (int)$patient['age']]) : t('common.unknown')) ?> &bull; <?= t('register.' . (in_array($patient['gender'] ?? '', ['male', 'female'], true) ? $patient['gender'] : 'other')) ?>
                </div>
            </div>

            <div>
                <div class="info-block-label"><?= t_bi('health.kidney') ?> (eGFR)</div>
                <div class="info-block-val">
                    <?= ($patient['egfr'] !== null) ? h((string)$patient['egfr']) . ' mL/min' : t('health.not_recorded') ?>
                </div>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                    <?= h($egfrStage['plain_name']) ?>
                </div>
            </div>

            <div>
                <div class="info-block-label"><?= t_bi('common.safety_score') ?></div>
                <div style="margin-top: 4px;">
                    <?php if (($scoreData['score'] ?? '') === 'NA'): ?>
                        <span class="score-pill" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">
                            NA &bull; <?= h($scoreData['label']) ?>
                        </span>
                    <?php else: ?>
                        <span class="score-pill" style="background: <?= $scoreData['bg_color'] ?>; color: <?= $scoreData['text_color'] ?>; border: 1px solid <?= $scoreData['color'] ?>;">
                            <?= $scoreData['score'] ?>% &bull; <?= h($scoreData['label']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="grid-column: span 2;">
                <div class="info-block-label"><?= t_bi('health.conditions') ?></div>
                <div class="info-block-val" style="font-size: 13.5px;">
                    <?= !empty($patient['conditions']) ? h($patient['conditions']) : t('health.none_recorded') ?>
                </div>
            </div>

            <div>
                <div class="info-block-label"><?= t_bi('health.allergies') ?></div>
                <div class="info-block-val" style="font-size: 13.5px; color: <?= (!empty($patient['allergies']) && !in_array(strtolower($patient['allergies']), ['none', 'nkda'])) ? '#b91c1c' : '#15803d' ?>;">
                    <?= !empty($patient['allergies']) ? h($patient['allergies']) : t('health.no_allergies') . ' (NKDA)' ?>
                </div>
            </div>
        </div>

        <!-- Active Prescriptions Table -->
        <div style="margin-bottom: 24px;">
            <div style="font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                <span><?= t_bi('card.active_meds') ?> (<?= count($activeMeds) ?>)</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30%;"><?= t_bi('card.th_name') ?></th>
                        <th style="width: 20%;"><?= t_bi('card.th_dose') ?></th>
                        <th style="width: 25%;"><?= t_bi('med.doctor') ?></th>
                        <th style="width: 15%;"><?= t_bi('med.date') ?></th>
                        <th style="width: 10%;"><?= t_bi('card.th_status') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activeMeds)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;"><?= t('check.none_on_file') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($activeMeds as $med): ?>
                            <tr>
                                <td>
                                    <strong><?= h($med['raw_medicine_name']) ?></strong>
                                    <?php if (!empty($med['generic_name']) && strtolower($med['generic_name']) !== strtolower($med['raw_medicine_name'])): ?>
                                        <div style="font-size: 11.5px; color: #64748b;">(<?= t('card.generic') ?>: <?= h($med['generic_name']) ?>)</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= h($med['dosage']) ?></strong><br>
                                    <span style="font-size: 12px; color: #475569;"><?= h($med['frequency']) ?></span>
                                </td>
                                <td>
                                    <strong><?= h($med['prescribing_doctor']) ?></strong>
                                </td>
                                <td><?= formatDate($med['prescription_date']) ?></td>
                                <td><span style="background: #ecfdf5; color: #065f46; font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 4px;"><?= t('card.active') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Detected Safety Warnings & Doctor Discussion Points -->
        <div style="margin-bottom: 24px;">
            <div style="font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">
                <span>⚠️ <?= t_bi('card.alerts_title') ?></span>
            </div>

            <?php if (empty($alerts)): ?>
                <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; padding: 14px 18px; color: #166534; font-size: 14px; font-weight: 600;">
                    ✓ <?= t('cabinet.ok_title') ?> <?= t('cabinet.ok_desc') ?>
                </div>
            <?php else: ?>
                <?php foreach ($alerts as $a): 
                    $isCrit = ($a['severity'] === 'critical' || $a['severity'] === 'contraindicated');
                    $isMajor = ($a['severity'] === 'high' || $a['severity'] === 'major');
                    $boxColor = $isCrit ? '#dc2626' : ($isMajor ? '#ea580c' : ($a['severity'] === 'moderate' ? '#d97706' : '#64748b'));
                    $bgColor = $isCrit ? '#fef2f2' : ($isMajor ? '#fff7ed' : ($a['severity'] === 'moderate' ? '#fffbeb' : '#f8fafc'));
                ?>
                    <div class="alert-box" style="border-left-color: <?= $boxColor ?>; background: <?= $bgColor ?>;">
                        <div class="alert-title" style="color: <?= $boxColor ?>;">
                            [<?= strtoupper(h($a['severity'])) ?>] <?= h($a['medicines_involved']) ?>
                            <?php if (current_lang() !== 'en'): $pv = patientAlertView($a, $patient['egfr'] !== null ? (float)$patient['egfr'] : null); ?>
                                <span class="sev-local"><?= $pv['icon'] ?> <?= $pv['badge'] ?> &mdash; <?= $pv['title'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="alert-desc" style="color: #1e293b;" lang="en">
                            <strong>Concern:</strong> <?= h($a['problem']) ?><br>
                            <strong>Rationale:</strong> <?= h($a['why_flagged']) ?>
                        </div>
                        <?php if (!empty($a['confidence']) || !empty($a['limitations'])): ?>
                            <div style="font-size: 11.5px; color: #64748b; margin: 6px 0;" lang="en">
                                <strong>Confidence:</strong> <?= h($a['confidence'] ?? '95%') ?> &bull; 
                                <strong>Limitations:</strong> <?= h($a['limitations'] ?? 'Based on reviewed records. Clinician decides.') ?>
                            </div>
                        <?php endif; ?>
                        <div class="alert-doctor-question" lang="en">
                            💬 <strong>Prescriber Action:</strong> <?= h($a['recommended_action']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Medical Disclaimer -->
        <div class="disclaimer-box">
            <?php if (current_lang() !== 'en'): ?>
                <p style="font-size: 13px; color: #334155; margin-bottom: 6px;"><strong><?= t('disclaimer.title') ?></strong> <?= t('disclaimer.short') ?></p>
            <?php endif; ?>
            <strong>IMPORTANT CLINICAL NOTICE:</strong> <?= h(CLINICAL_DISCLAIMER) ?>
            This report is generated by DoseWise to support clinical communication between patients and prescribers across health systems. It does not replace independent professional medical evaluation, clinical judgment, or pharmacist dispensing checks.
        </div>
    </div>

</body>
</html>
