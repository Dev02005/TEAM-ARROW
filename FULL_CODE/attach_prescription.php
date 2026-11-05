<?php
/**
 * Check a Medicine (Attach Prescription & Safety Percentage Analyzer)
 * DoseWise - Medication Safety for Citizens
 *
 * The typed medicine is added to the person's current medicines and the whole list is
 * checked by the H4 engine (SafetyEngine::evaluateCandidatePrescription). Only warnings
 * that involve the new medicine are shown. The page also says what the name was
 * recognised as, so a wrong match can be noticed.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/Patient.php';
require_once __DIR__ . '/models/Medication.php';
require_once __DIR__ . '/models/SafetyEngine.php';

$pageTitle = t_plain('check.page_title');
$citizen = currentUser();

const DEMO_PATIENT_MRN = 'DEMO-ELEANOR'; // synthetic example person for visitors who are not signed in

$patientModel = new Patient();

// Citizens: only their own record. Staff: the chosen patient. Visitors: the synthetic demo patient.
if ($citizen && !isAdmin()) {
    $patientId = (int)$citizen['patient_id'];
} elseif (isAdmin()) {
    $patientId = (int)($_GET['patient_id'] ?? 0);
} else {
    $demo = $patientModel->getByMrn(DEMO_PATIENT_MRN);
    $patientId = $demo ? (int)$demo['id'] : 0;
}

$patient = $patientId > 0 ? $patientModel->getById($patientId) : null;

$medicationModel = new Medication();
$activeMeds = $medicationModel->getByPatientId($patientId, 'active');
$referenceMedicines = $medicationModel->getReferenceMedicines();

$safetyEngine = new SafetyEngine();

// Form variables
$submittedDrug = trim($_POST['medicine_name'] ?? ($_GET['drug'] ?? ''));
$submittedDosage = trim($_POST['dosage'] ?? '');
$submittedFrequency = trim($_POST['frequency'] ?? '');
$submittedDoctor = trim($_POST['prescribing_doctor'] ?? '');
$submittedIndication = trim($_POST['indication'] ?? '');
$saveToLocker = !empty($_POST['save_to_locker']);

$analysisPerformed = false;
$recognisedAs = [];
$drugSpecificAlerts = [];
$scoreData = null;
$egfr = ($patient && $patient['egfr'] !== null) ? (float)$patient['egfr'] : null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' || !empty($_GET['drug'])) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !verify_csrf()) {
        die(h(t_plain('common.security_failed')));
    }

    if (!empty($submittedDrug) && $patient) {
        $analysisPerformed = true;

        // 1. What did we recognise the written name as?
        $recognisedAs = $safetyEngine->resolveMedicine($submittedDrug);

        if (!empty($recognisedAs)) {
            // 2. Check the new medicine TOGETHER with everything already being taken,
            //    plus kidney function, conditions, allergies and pregnancy.
            $drugSpecificAlerts = $safetyEngine->evaluateCandidatePrescription($patientId, $submittedDrug);

            // 3. Extra plain-language safety nets (only when the engine found nothing)
            if (empty($drugSpecificAlerts)) {
                $drugLower = strtolower($submittedDrug . ' ' . implode(' ', $recognisedAs));
                if ($egfr !== null && $egfr < 30.0 && str_contains($drugLower, 'metformin')) {
                    $drugSpecificAlerts[] = [
                        'severity' => 'contraindicated',
                        'alert_type' => 'renal_dose',
                        'medicines_involved' => ucwords(implode(' & ', $recognisedAs)),
                        'recommended_action' => 'Metformin is contraindicated below eGFR 30 mL/min (lactic acidosis risk). Prescriber to review for a kidney-appropriate alternative.',
                        'confidence' => '95%',
                    ];
                } elseif ($egfr !== null && $egfr < 60.0 && preg_match('/ibuprofen|naproxen|diclofenac/', $drugLower)) {
                    $drugSpecificAlerts[] = [
                        'severity' => ($egfr < 30.0 ? 'major' : 'moderate'),
                        'alert_type' => 'renal_dose',
                        'medicines_involved' => ucwords(implode(' & ', $recognisedAs)),
                        'recommended_action' => 'NSAIDs reduce renal blood flow in reduced kidney function. Prescriber/pharmacist to consider an alternative analgesic.',
                        'confidence' => '90%',
                    ];
                }

                $conds = strtolower((string)($patient['conditions'] ?? ''));
                if (str_contains($conds, 'asthma') && str_contains($drugLower, 'propranolol')) {
                    $drugSpecificAlerts[] = [
                        'severity' => 'contraindicated',
                        'alert_type' => 'contraindication',
                        'medicines_involved' => 'Propranolol',
                        'recommended_action' => 'Non-selective beta blocker with asthma: risk of bronchospasm. Prescriber to choose an alternative.',
                        'confidence' => '95%',
                    ];
                }
            }

            // Most serious first
            $rank = ['contraindicated' => 4, 'critical' => 4, 'major' => 3, 'high' => 3, 'moderate' => 2, 'minor' => 1];
            usort($drugSpecificAlerts, fn($x, $y) => ($rank[$y['severity']] ?? 0) <=> ($rank[$x['severity']] ?? 0));

            $scoreData = calculateSafetyScore($drugSpecificAlerts);

            // Save to "My medicines" only when asked, allowed, and the name was recognised
            if ($saveToLocker && canManagePatient($patientId)) {
                $medicineId = null;
                foreach ($referenceMedicines as $ref) {
                    if (in_array(strtolower($ref['generic_name']), $recognisedAs, true)) {
                        $medicineId = $ref['id'];
                        break;
                    }
                }
                $medicationModel->create([
                    'patient_id' => $patientId,
                    'medicine_id' => $medicineId,
                    'raw_medicine_name' => $submittedDrug,
                    'prescribed_generic_name' => implode(';', $recognisedAs),
                    'dosage' => $submittedDosage,
                    'dose_amount_mg' => (float)filter_var($submittedDosage, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                    'frequency' => $submittedFrequency,
                    'route' => 'oral',
                    'prescribing_doctor' => $submittedDoctor,
                    'prescription_date' => date('Y-m-d'),
                    'indication' => $submittedIndication,
                    'status' => 'active'
                ]);
                setFlash('success', t_plain('flash.saved', ['name' => $submittedDrug]));
                redirect(BASE_URL . 'my_prescriptions.php');
            }
        }
    }
}

$level = $scoreData ? ($drugSpecificAlerts ? severityLevel($scoreData['severity']) : 'clean') : 'unknown';

// Example medicines with Indian brand names the reference data recognises
$examples = [
    ['Brufen 400', '400 mg'],
    ['Glycomet 500', '500 mg'],
    ['Ecosprin 75', '75 mg'],
    ['Atorva 10', '10 mg'],
    ['Combiflam', '1 tablet'],
    ['Dolo 650', '650 mg'],
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="ez-page">

    <div class="ez-page-head">
        <p class="ez-eyebrow"><?= t('check.eyebrow') ?></p>
        <h2 class="ez-page-title"><?= t('check.title') ?></h2>
        <?php if ($citizen && $patient): ?>
            <p class="ez-page-sub"><?= t('check.for_person', ['name' => $patient['full_name'], 'n' => count($activeMeds)]) ?></p>
        <?php elseif ($patient): ?>
            <p class="ez-demo-note">💡 <?= t('check.demo_note', ['name' => $patient['full_name']]) ?>
                <a href="<?= BASE_URL ?>login.php"><?= t('check.demo_login') ?> &rarr;</a></p>
        <?php elseif (isAdmin()): ?>
            <p class="ez-demo-note">👥 <?= t('check.pick_patient') ?> <a href="<?= BASE_URL ?>patients.php"><?= t('nav.patient_records') ?> &rarr;</a></p>
        <?php else: ?>
            <p class="ez-demo-note">🔑 <?= t('check.no_profile') ?> <a href="<?= BASE_URL ?>login.php"><?= t('nav.login') ?> &rarr;</a></p>
        <?php endif; ?>
    </div>

    <!-- RESULTS FIRST (when a check was done), so nobody has to scroll to find them -->
    <?php if ($analysisPerformed): ?>
        <section id="safetyAnalysisResults" class="ez-status ez-status--<?= $level ?>">
            <p class="ez-eyebrow"><?= t('check.result_for') ?></p>
            <h3 class="ez-status__drug"><?= h($submittedDrug) ?></h3>

            <?php if (empty($recognisedAs)): ?>
                <div class="ez-status__top">
                    <div class="ez-status__icon" aria-hidden="true">❓</div>
                    <div class="ez-status__text">
                        <div class="ez-status__label">We cant decide whether it is safe or not</div>
                        <div class="ez-status__count"><?= t('check.not_in_database') ?></div>
                    </div>
                    <div class="ez-score" style="border-color: #cbd5e1; background: #f8fafc;">
                        <span class="ez-score__num" style="color: #64748b; font-size: 28px;">NA</span>
                        <span class="ez-score__label"><?= t('common.safety_score') ?></span>
                    </div>
                </div>
                <p class="ez-status__summary">
                    We cant decide whether it is safe or not. "<?= h($submittedDrug) ?>" is not available in our verified database of 496+ clinical medicines and safety guidelines. Because active ingredients and clinical safety thresholds cannot be confirmed, no safety score can be calculated and potential drug conflicts cannot be verified.
                </p>
            <?php else: ?>
                <p class="ez-recognised">🔎 <?= t('check.recognised_as') ?>
                    <strong><?= h(ucwords(implode(' + ', $recognisedAs))) ?></strong>
                    <span class="ez-muted"><?= t('check.recognised_hint') ?></span>
                </p>

                <div class="ez-status__top">
                    <div class="ez-status__icon" aria-hidden="true"><?= $drugSpecificAlerts ? severityIcon($level) : '🛡️' ?></div>
                    <div class="ez-status__text">
                        <div class="ez-status__label"><?= h($scoreData['label']) ?></div>
                        <div class="ez-status__count">
                            <?= $drugSpecificAlerts ? t('cabinet.n_issues', ['n' => count($drugSpecificAlerts)]) : t('cabinet.no_issues') ?>
                        </div>
                    </div>
                    <div class="ez-score">
                        <span class="ez-score__num"><?= (int)$scoreData['score'] ?>%</span>
                        <span class="ez-score__label"><?= t('common.safety_score') ?></span>
                    </div>
                </div>
                <p class="ez-status__summary"><?= h($scoreData['summary']) ?></p>
                <p class="ez-muted"><?= t('check.checked_against', ['n' => count($activeMeds)]) ?></p>
            <?php endif; ?>

            <button type="button" class="ez-speak" data-speak-target="#safetyAnalysisResults">🔊 <?= t('common.read_aloud') ?></button>
        </section>

        <?php if (!empty($drugSpecificAlerts)): ?>
            <section class="ez-section">
                <h3 class="ez-section__title">⚠️ <?= t('check.alerts_title') ?></h3>
                <div class="ez-alert-list">
                    <?php foreach ($drugSpecificAlerts as $a): ?>
                        <?= renderPatientAlert($a, $egfr) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php elseif (!empty($recognisedAs)): ?>
            <section class="ez-ok">
                <div class="ez-ok__icon">🛡️</div>
                <div class="ez-ok__title"><?= t('check.ok_title') ?></div>
                <p><?= t('check.ok_desc', ['name' => $submittedDrug, 'n' => count($activeMeds)]) ?></p>
            </section>
        <?php else: ?>
            <section class="ez-section">
                <div class="ez-alert ez-alert--note" style="border-left-color: #64748b; background: #f8fafc;">
                    <h4 class="ez-alert__title" style="color: #334155;">⚠️ Medicine Not Found in Safety Knowledge Base (Safety Score: NA)</h4>
                    <p style="margin: 8px 0 6px; font-size: 16.5px; line-height: 1.6;">
                        <strong>"<?= h($submittedDrug) ?>"</strong> could not be matched with any of the 496+ approved medicines, 98 combinations, or known brand aliases in our system.
                    </p>
                    <p style="margin: 0 0 10px; font-size: 15.5px; color: var(--ez-text-2); line-height: 1.5;">
                        We cannot decide whether it is safe or not because its chemical active ingredients, kidney clearance guidelines, and interaction warnings are not on file.
                    </p>
                    <ul style="margin: 8px 0 0 20px; font-size: 15.5px; line-height: 1.6; color: var(--ez-text-2);">
                        <li>Please check the spelling printed on the strip, box, or prescription slip.</li>
                        <li>Try searching by the generic chemical salt name printed in smaller brackets (e.g. <em>Paracetamol</em>, <em>Metformin</em>, <em>Atorvastatin</em>).</li>
                        <li>Always ask your doctor or licensed pharmacist before starting or combining an unverified medicine.</li>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <div class="ez-actions ez-actions--row">
            <a href="<?= BASE_URL ?>attach_prescription.php" class="ez-big-btn ez-big-btn--light">
                <span class="ez-big-btn__icon">🔄</span><span><?= t('check.another') ?></span>
            </a>
            <?php if ($citizen && !isAdmin()): ?>
                <a href="<?= BASE_URL ?>my_prescriptions.php" class="ez-big-btn ez-big-btn--teal">
                    <span class="ez-big-btn__icon">💊</span><span><?= t('home.btn_my_meds') ?></span>
                </a>
            <?php elseif (!$citizen): ?>
                <a href="<?= BASE_URL ?>register.php" class="ez-big-btn ez-big-btn--primary">
                    <span class="ez-big-btn__icon">✨</span><span><?= t('check.create_account') ?></span>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- THE FORM -->
    <section class="ez-section ez-form-card" <?= $analysisPerformed ? 'id="checkAgain"' : '' ?>>
        <form method="POST" action="<?= BASE_URL ?>attach_prescription.php<?= isAdmin() ? '?patient_id=' . $patientId : '' ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- Step 1: photo (optional) -->
            <div class="ez-form-step">
                <div class="ez-form-step__head"><span class="ez-step__num">1</span> <?= t('check.step_photo') ?></div>
                <label class="ez-photo-btn" id="photoDropArea">
                    <input type="file" id="prescriptionFile" name="prescription_file" accept="image/*" capture="environment" onchange="handleFileSelected(this)">
                    <span class="ez-photo-btn__icon">📷</span>
                    <span><?= t('check.photo_btn') ?><small><?= t('check.photo_sub') ?></small></span>
                </label>
                <div id="photoPreview" class="ez-photo-preview" hidden>
                    <img id="photoPreviewImg" alt="Prescription preview">
                    <p><?= t('check.photo_attached') ?></p>
                </div>

                <!-- OCR Scanning & Extraction Progress Box -->
                <div id="ocrStatusBox" style="display: none; margin-top: 14px; padding: 14px 18px; border-radius: 14px; background: #f0f9ff; border: 2px solid #bae6fd; align-items: center; gap: 14px;">
                    <div id="ocrSpinner" style="width: 26px; height: 26px; border: 3px solid #0284c7; border-top-color: transparent; border-radius: 50%; display: inline-block; animation: ez-spin 0.8s linear infinite; flex-shrink: 0;"></div>
                    <div style="flex: 1;">
                        <div id="ocrStatusTitle" style="font-weight: 800; color: #0369a1; font-size: 15.5px;">Scanning image for medicine name...</div>
                        <div id="ocrStatusMsg" style="font-size: 14px; color: #0284c7; margin-top: 2px;">Extracting and matching medicine with clinical safety database...</div>
                    </div>
                </div>
            </div>

            <!-- Step 2: medicine name -->
            <div class="ez-form-step">
                <label class="ez-form-step__head" for="medicineNameInput"><span class="ez-step__num">2</span> <?= t('check.step_name') ?> <span class="required">*</span></label>
                <input type="text" id="medicineNameInput" name="medicine_name" class="form-control ez-input-xl" autocomplete="off" autocapitalize="words"
                       placeholder="<?= t_attr('check.name_ph') ?>" value="<?= h($submittedDrug) ?>" required>
                <div id="ocrDetectedBadge" style="display: none; margin-top: 6px; font-size: 14.5px; color: #059669; font-weight: 700;">
                    ✨ Automatically recognized &amp; printed from image
                </div>
                <p class="form-hint"><?= t('check.examples_hint') ?></p>
                <div class="ez-chip-row">
                    <?php foreach ($examples as [$name, $dose]): ?>
                        <button type="button" class="ez-chip" onclick="setMedicine(<?= h(json_encode($name)) ?>, <?= h(json_encode($dose)) ?>)"><?= h($name) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Optional details -->
            <details class="ez-more" <?= ($submittedDosage || $submittedFrequency || $submittedDoctor) ? 'open' : '' ?>>
                <summary><?= t('check.more_details') ?></summary>
                <div class="form-group">
                    <label class="form-label" for="dosageInput"><?= t('check.dose') ?></label>
                    <input type="text" id="dosageInput" name="dosage" class="form-control" placeholder="<?= t_attr('check.dose_ph') ?>" value="<?= h($submittedDosage) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="frequencyInput"><?= t('check.how_often') ?></label>
                    <input type="text" id="frequencyInput" name="frequency" class="form-control" placeholder="<?= t_attr('check.how_often_ph') ?>" value="<?= h($submittedFrequency) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="doctorInput"><?= t('check.doctor') ?></label>
                    <input type="text" id="doctorInput" name="prescribing_doctor" class="form-control" placeholder="<?= t_attr('check.doctor_ph') ?>" value="<?= h($submittedDoctor) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="indicationInput"><?= t('check.condition') ?></label>
                    <input type="text" id="indicationInput" name="indication" class="form-control" placeholder="<?= t_attr('check.condition_ph') ?>" value="<?= h($submittedIndication) ?>">
                </div>
            </details>

            <?php if ($citizen && !isAdmin()): ?>
                <label class="ez-check">
                    <input type="checkbox" name="save_to_locker" value="1" <?= $saveToLocker ? 'checked' : '' ?>>
                    <span>💾 <?= t('check.save') ?></span>
                </label>
            <?php endif; ?>

            <button type="submit" class="ez-big-btn ez-big-btn--primary ez-big-btn--submit">
                <span class="ez-big-btn__icon">🔍</span><span><?= t('check.submit') ?></span>
            </button>
        </form>
    </section>

    <!-- MEDICINES ALREADY ON FILE -->
    <section class="ez-section">
        <h3 class="ez-section__title">💊 <?= t('check.on_file', ['n' => count($activeMeds)]) ?></h3>
        <p class="ez-section__desc"><?= t('check.on_file_desc') ?></p>
        <?php if (empty($activeMeds)): ?>
            <p class="ez-muted"><?= t('check.none_on_file') ?></p>
        <?php else: ?>
            <ul class="ez-simple-list">
                <?php foreach ($activeMeds as $am): ?>
                    <li><strong><?= h($am['raw_medicine_name']) ?></strong>
                        <span class="ez-muted"><?= h(trim($am['dosage'] . ' · ' . $am['frequency'], ' ·')) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<script src="<?= BASE_URL ?>public/js/tesseract.min.js"></script>
<script>
function setMedicine(name, dose) {
    document.getElementById('medicineNameInput').value = name;
    if (dose) document.getElementById('dosageInput').value = dose;
    document.getElementById('medicineNameInput').focus();
}

async function handleFileSelected(input) {
    const file = input && input.files ? input.files[0] : input;
    if (!file) return;

    const previewBox = document.getElementById('photoPreview');
    const previewImg = document.getElementById('photoPreviewImg');
    const ocrBox = document.getElementById('ocrStatusBox');
    const ocrTitle = document.getElementById('ocrStatusTitle');
    const ocrMsg = document.getElementById('ocrStatusMsg');
    const ocrSpinner = document.getElementById('ocrSpinner');
    const detectedBadge = document.getElementById('ocrDetectedBadge');
    const medInput = document.getElementById('medicineNameInput');

    // 1. Show image preview
    if (file.type && file.type.indexOf('image/') === 0) {
        previewImg.src = URL.createObjectURL(file);
        previewImg.hidden = false;
    }
    previewBox.hidden = false;

    // 2. Show OCR scanning state
    ocrBox.style.display = 'flex';
    ocrBox.style.background = '#f0f9ff';
    ocrBox.style.borderColor = '#bae6fd';
    ocrSpinner.style.display = 'inline-block';
    ocrTitle.style.color = '#0369a1';
    ocrTitle.innerText = 'Analyzing image...';
    ocrMsg.style.color = '#0284c7';
    ocrMsg.innerText = 'Scanning text with OCR and matching medicine name...';
    if (detectedBadge) detectedBadge.style.display = 'none';

    let ocrText = '';

    // 3. Client OCR via Tesseract.js if available
    try {
        if (typeof Tesseract !== 'undefined') {
            ocrMsg.innerText = 'Reading text from image with OCR engine...';
            const worker = await Tesseract.createWorker('eng', 1, {
                workerPath: '<?= BASE_URL ?>public/js/worker.min.js',
                langPath: '<?= BASE_URL ?>public/js',
                logger: m => {
                    if (m && m.status === 'recognizing text' && m.progress) {
                        ocrMsg.innerText = 'Reading image text: ' + Math.round(m.progress * 100) + '%';
                    }
                }
            });
            const ret = await worker.recognize(file);
            ocrText = (ret && ret.data && ret.data.text) ? ret.data.text : '';
            await worker.terminate();
        }
    } catch (e) {
        console.warn('Client-side OCR notice:', e);
    }

    // 4. Request API extraction & clinical matching
    try {
        const formData = new FormData();
        formData.append('ocr_text', ocrText);
        formData.append('filename', file.name || '');
        formData.append('prescription_file', file);

        const resp = await fetch('<?= BASE_URL ?>api_extract_medicine.php', {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();

        if (data && data.success && data.medicine_name) {
            // Automatically print the medicine name in the section "Type the medicine name"
            medInput.value = data.medicine_name;
            if (data.dosage && document.getElementById('dosageInput')) {
                document.getElementById('dosageInput').value = data.dosage;
            }
            if (data.frequency && document.getElementById('frequencyInput')) {
                document.getElementById('frequencyInput').value = data.frequency;
            }

            // Visual feedback
            ocrSpinner.style.display = 'none';
            ocrBox.style.background = '#ecfdf5';
            ocrBox.style.borderColor = '#6ee7b7';
            ocrTitle.style.color = '#065f46';
            ocrTitle.innerText = '✅ Detected: ' + data.medicine_name;
            ocrMsg.style.color = '#047857';
            ocrMsg.innerText = 'Medicine name has been automatically printed in "Type the medicine name" below.';
            if (detectedBadge) detectedBadge.style.display = 'block';

            // Highlight glow on input field
            medInput.style.transition = 'all 0.3s ease';
            medInput.style.boxShadow = '0 0 0 4px rgba(16, 185, 129, 0.4)';
            medInput.style.borderColor = '#10b981';
            setTimeout(() => {
                medInput.style.boxShadow = '';
                medInput.style.borderColor = '';
            }, 2500);

            medInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            medInput.focus();
        } else {
            ocrSpinner.style.display = 'none';
            ocrBox.style.background = '#fefce8';
            ocrBox.style.borderColor = '#fde047';
            ocrTitle.style.color = '#854d0e';
            ocrTitle.innerText = 'Image scanned';
            ocrMsg.style.color = '#a16207';
            ocrMsg.innerText = 'Could not find a clear drug name. Please type the medicine name in Step 2.';
            medInput.focus();
        }
    } catch (err) {
        console.error('OCR API error:', err);
        ocrSpinner.style.display = 'none';
        ocrBox.style.background = '#fefce8';
        ocrBox.style.borderColor = '#fde047';
        ocrTitle.style.color = '#854d0e';
        ocrTitle.innerText = 'Photo added';
        ocrMsg.innerText = 'Please look at the strip and type the medicine name below.';
        medInput.focus();
    }
}

// Drag & drop and paste support
document.addEventListener('DOMContentLoaded', function () {
    const dropArea = document.getElementById('photoDropArea');
    if (dropArea) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropArea.style.borderColor = '#0284c7';
                dropArea.style.background = '#f0f9ff';
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropArea.style.borderColor = '';
                dropArea.style.background = '';
            }, false);
        });

        dropArea.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                const input = document.getElementById('prescriptionFile');
                input.files = files;
                handleFileSelected(input);
            }
        }, false);
    }

    // Paste image from clipboard support (Ctrl+V)
    document.addEventListener('paste', function (e) {
        const items = (e.clipboardData || (e.originalEvent && e.originalEvent.clipboardData))?.items;
        if (!items) return;
        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            if (item.kind === 'file' && item.type.indexOf('image/') === 0) {
                const blob = item.getAsFile();
                handleFileSelected(blob);
                break;
            }
        }
    });

    <?php if ($analysisPerformed): ?>
    const r = document.getElementById('safetyAnalysisResults');
    if (r) r.scrollIntoView({ block: 'start' });
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
