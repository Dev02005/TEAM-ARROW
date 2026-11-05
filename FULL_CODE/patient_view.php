<?php
/**
 * Comprehensive Patient Chart & Medication Safety Review
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/Patient.php';
require_once __DIR__ . '/models/Medication.php';
require_once __DIR__ . '/models/SafetyEngine.php';

$patientId = (int)($_GET['id'] ?? 0);
if ($patientId <= 0) {
    setFlash('danger', 'Invalid patient ID specified.');
    redirect(isCitizen() ? BASE_URL . 'my_prescriptions.php' : BASE_URL . 'patients.php');
}

// Strict patient authorization:
// Citizens can ONLY view their own patient chart!
if (!canViewPatient($patientId)) {
    setFlash('danger', 'Access denied: You can only view and manage your own medication profile.');
    redirect(BASE_URL . 'my_prescriptions.php');
}

$patientModel = new Patient();
$patient = $patientModel->getById($patientId);

if (!$patient) {
    setFlash('danger', 'Patient record not found.');
    redirect(isCitizen() ? BASE_URL . 'my_prescriptions.php' : BASE_URL . 'patients.php');
}

$medicationModel = new Medication();
$activeMedications = $medicationModel->getByPatientId($patientId, 'active');
$historicalMedications = $medicationModel->getByPatientId($patientId, 'discontinued');
$timelineMedications = $medicationModel->getTimelineByPatientId($patientId);
$referenceMedicines = $medicationModel->getReferenceMedicines();

// Run explainable safety review
$safetyEngine = new SafetyEngine();
$safetyAlerts = $safetyEngine->evaluatePatient($patientId);

$egfrStage = getEgfrStage($patient['egfr'] !== null ? (float)$patient['egfr'] : null);
$pageTitle = 'Patient: ' . $patient['full_name'];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Patient Profile Header -->
<div class="patient-profile-header">
    <div class="profile-top-row">
        <div class="profile-main-info">
            <div class="profile-avatar">
                <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h2 class="profile-name"><?= h($patient['full_name']) ?></h2>
                    <span class="badge badge-neutral" style="font-family: 'JetBrains Mono', monospace; font-size: 13px;">
                        Record ID: <?= h($patient['patient_mrn']) ?>
                    </span>
                    <?php if (!empty($patient['is_near_miss_case'])): ?>
                        <span class="badge badge-warning" title="Synthetic test record designed to demonstrate clinical conflict detection">
                            🎯 Practice / Demonstration Case
                        </span>
                    <?php endif; ?>
                </div>

                <div class="profile-meta-chips">
                    <span>Age: <strong><?= calculateAge($patient['date_of_birth']) ?></strong></span>
                    <span>&bull;</span>
                    <span>Birthdate: <strong><?= formatDate($patient['date_of_birth']) ?></strong></span>
                    <span>&bull;</span>
                    <span>Sex: <strong><?= ucfirst(h($patient['gender'])) ?></strong></span>
                    <span>&bull;</span>
                    <span>Care team: <strong><?= (int)$patient['distinct_doctors'] ?> Doctor<?= ((int)$patient['distinct_doctors'] !== 1) ? 's' : '' ?></strong></span>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <?php if (canManagePatient($patientId)): ?>
                <a href="<?= BASE_URL ?>patient_edit.php?id=<?= $patientId ?>" class="btn btn-secondary">
                    <span>✏️</span> Edit profile
                </a>
            <?php endif; ?>
            <a href="#safetyResultsSection" id="btnCheckSafety" class="btn btn-teal">
                <span>🛡️</span> Check medication safety (<?= count($safetyAlerts) ?>)
            </a>
            <?php if (isAdmin()): ?>
                <a href="<?= BASE_URL ?>attach_prescription.php?patient_id=<?= $patientId ?>" class="btn btn-secondary">
                    <span>🔍</span> <?= t('nav.check') ?>
                </a>
            <?php endif; ?>
            <?php if (canManagePatient($patientId)): ?>
                <button type="button" class="btn btn-primary" onclick="openModal('addMedicationModal')">
                    <span>➕</span> Add prescription
                </button>
            <?php elseif (!isLoggedIn()): ?>
                <a href="<?= BASE_URL ?>login.php" class="btn btn-secondary">
                    <span>🔑</span> Sign in to add prescription
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Clinical Context Highlights (Kidney, Conditions, Allergies) -->
    <div class="grid grid-cols-3">
        <!-- Kidney Health Card -->
        <div style="background: var(--bg-subtle); padding: 16px 20px; border-radius: var(--radius-md); border: 1px solid var(--border);">
            <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; display: flex; align-items: center; justify-content: space-between;">
                <span>🫘 Kidney Health</span>
                <span title="eGFR (estimated Glomerular Filtration Rate) measures how efficiently the kidneys filter waste products from the blood." style="cursor:help; color:var(--primary);">ⓘ Info</span>
            </div>
            <div style="margin-top: 4px;">
                <span class="badge <?= $egfrStage['class'] ?>" style="font-size: 13.5px;">
                    <?= ($patient['egfr'] !== null) ? h((string)$patient['egfr']) . ' mL/min &bull; ' . h($egfrStage['plain_name']) : 'Not recorded' ?>
                </span>
            </div>
            <p style="font-size: 12.5px; color: var(--text-secondary); margin-top: 6px; line-height: 1.4;">
                <?= h($egfrStage['description'] ?? 'No laboratory kidney test recorded. Renal clearance risks cannot be ruled out.') ?>
            </p>
        </div>

        <!-- Known Medical Diagnoses -->
        <div style="background: var(--bg-subtle); padding: 16px 20px; border-radius: var(--radius-md); border: 1px solid var(--border);">
            <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">
                🩺 Known Medical Diagnoses
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                <?php if (!empty($patient['conditions'])): ?>
                    <?php foreach (explode(',', $patient['conditions']) as $cond): ?>
                        <span class="badge badge-neutral" style="background: white; border-color: #cbd5e1;">
                            <?= h(trim($cond)) ?>
                        </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span style="font-size: 13px; color: var(--text-muted); font-style: italic;">No medical conditions documented.</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Allergies & Sensitivities -->
        <div style="background: var(--bg-subtle); padding: 16px 20px; border-radius: var(--radius-md); border: 1px solid var(--border);">
            <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">
                ⚠️ Documented Allergies
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                <?php if (!empty($patient['allergies']) && !in_array(strtolower(trim($patient['allergies'])), ['none', 'nkda'], true)): ?>
                    <?php foreach (explode(',', $patient['allergies']) as $allergy): ?>
                        <span class="badge badge-danger">
                            <?= h(trim($allergy)) ?>
                        </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="badge badge-success">No known drug allergies (NKDA)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MEDICATION SAFETY REVIEW SECTION (Requirement Section 6) -->
<section id="safetyResultsSection" class="card" style="border: 2px solid #bae6fd; background: #ffffff;">
    <div class="card-header" style="border-bottom-color: #e0f2fe;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <h3 class="card-title" style="font-size: 19px; color: var(--text-main);">
                    <span>🛡️</span> Medication Safety Review
                </h3>
                <?php if (!empty($safetyAlerts)): ?>
                    <span class="badge badge-danger" style="font-size: 13px;">
                        ⚠️ <?= count($safetyAlerts) ?> Potential Risk<?= (count($safetyAlerts) !== 1) ? 's' : '' ?> Found
                    </span>
                <?php else: ?>
                    <span class="badge badge-success" style="font-size: 13px;">
                        ✓ All Clear
                    </span>
                <?php endif; ?>
            </div>
            <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px;">
                Independent safety analysis evaluating active medicines across all doctors against drug interactions, kidney function, duplicate therapies, and clinical contraindications.
            </p>
        </div>
        <div>
            <span style="font-size: 12px; color: var(--text-muted);">
                Checked against <strong><?= count($activeMedications) ?></strong> active prescriptions
            </span>
        </div>
    </div>

    <?php if (empty($safetyAlerts)): ?>
        <!-- Reassurance Safe State -->
        <div class="safe-state-card">
            <div class="safe-state-icon">🛡️</div>
            <div class="safe-state-title">No high-risk medication conflicts detected</div>
            <div class="safe-state-desc">
                Based on current active prescription records and recorded kidney biomarkers, no major drug interactions, duplicate therapies, or contraindicated doses were found.
            </div>
            <p style="font-size: 12px; color: #166534; margin-top: 8px;">
                <em>Always verify over-the-counter medicines and dietary supplements directly with the patient.</em>
            </p>
        </div>
    <?php else: ?>
        <!-- Structured Alerts List (Exact 6-part structure) -->
        <div class="safety-alerts-wrapper">
            <?php foreach ($safetyAlerts as $idx => $alert): ?>
                <article class="alert-card <?= h($alert['severity']) ?>" aria-labelledby="alert-title-<?= $idx ?>">
                    <!-- 1. Risk Severity & Medicines Involved -->
                    <div class="alert-card-header">
                        <div class="alert-title-group">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="alert-severity-badge <?= h($alert['severity']) ?>">
                                    <?= ucfirst(h($alert['severity'])) ?> Risk
                                </span>
                                <span style="font-size: 12.5px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">
                                    Rule: <?= h($alert['rule_code']) ?>
                                </span>
                            </div>
                            <h4 id="alert-title-<?= $idx ?>" style="font-size: 16px; font-weight: 700; color: var(--text-main); margin-top: 6px;">
                                Medicines Involved: <span style="color: var(--primary);"><?= h($alert['medicines_involved']) ?></span>
                            </h4>
                        </div>
                    </div>

                    <!-- 3. What the Potential Problem Is -->
                    <div class="alert-section">
                        <div class="alert-section-title">
                            <span>⚠️</span> What the potential problem is
                        </div>
                        <div class="alert-section-body" style="font-weight: 600; color: var(--text-main);">
                            <?= h($alert['problem']) ?>
                        </div>
                    </div>

                    <!-- 4. Why the System Flagged It -->
                    <div class="alert-section">
                        <div class="alert-section-title">
                            <span>🔍</span> Why the system flagged it
                        </div>
                        <div class="alert-section-body">
                            <?= h($alert['why_flagged']) ?>
                        </div>
                    </div>

                    <!-- 5. Recommended Next Step for Clinician Review -->
                    <div class="alert-action-box">
                        <div class="action-title">
                            <span>📋</span> Recommended next step for clinician review:
                        </div>
                        <div class="action-text">
                            <?= h($alert['recommended_action']) ?>
                        </div>
                    </div>

                    <!-- 6. Confidence, Evidence and Limitations -->
                    <div class="alert-footer-meta">
                        <div>
                            <strong>Clinical Evidence:</strong> <?= h($alert['evidence']) ?>
                        </div>
                        <div>
                            <strong>Confidence:</strong> <?= h($alert['confidence']) ?>
                        </div>
                        <div style="width: 100%; margin-top: 4px; color: #64748b; font-style: italic;">
                            <strong>Limitations:</strong> <?= h($alert['limitations']) ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Mandatory Clinical Decision Support Disclaimer -->
    <div style="margin-top: 20px; padding: 12px 16px; background: #f8fafc; border-radius: var(--radius-sm); border: 1px solid var(--border); font-size: 12.5px; color: var(--text-muted); text-align: center;">
        ⚕️ <strong>Decision-Support Notice:</strong> <?= h(CLINICAL_DISCLAIMER) ?>
    </div>
</section>

<!-- Active Recorded Medicines -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Recorded Active Medicines (<?= count($activeMedications) ?>)</h3>
            <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 2px;">
                Current medications prescribed across all consulting clinics and specialists.
            </p>
        </div>
        <?php if (canManagePatient($patientId)): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addMedicationModal')">
                <span>➕</span> Add prescription
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($activeMedications)): ?>
        <div class="empty-state" style="padding: 36px 20px;">
            <div class="empty-state-icon">💊</div>
            <div class="empty-state-title">No active medications on record</div>
            <div class="empty-state-desc">This patient currently has no active prescriptions recorded in the system.</div>
            <?php if (canManagePatient($patientId)): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addMedicationModal')">
                    Add first prescription
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="patient-list">
            <?php foreach ($activeMedications as $med): ?>
                <div class="patient-list-item" style="padding: 16px 20px;">
                    <div class="patient-list-main" style="flex: 2;">
                        <div style="width: 40px; height: 40px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                            💊
                        </div>
                        <div>
                            <div style="font-size: 16px; font-weight: 700; color: var(--text-main);">
                                <?= h($med['raw_medicine_name']) ?>
                            </div>
                            <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
                                Active ingredient: <strong><?= !empty($med['matched_generic']) ? h(ucwords($med['matched_generic'])) : 'Unmatched' ?></strong>
                                <?php if (!empty($med['therapeutic_class'])): ?>
                                    &bull; Class: <?= h($med['therapeutic_class']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="patient-middle-details" style="flex: 3;">
                        <div class="detail-block">
                            <span class="detail-block-label">Directions & Dose</span>
                            <div class="detail-block-value">
                                <?= h($med['dosage']) ?> &bull; <?= h($med['frequency']) ?>
                            </div>
                        </div>

                        <div class="detail-block">
                            <span class="detail-block-label">Prescribing Doctor</span>
                            <div class="detail-block-value">
                                <span class="doctor-pill"><?= h($med['prescribing_doctor']) ?></span>
                            </div>
                        </div>

                        <div class="detail-block">
                            <span class="detail-block-label">Prescription Date</span>
                            <div class="detail-block-value">
                                <?= formatDate($med['prescription_date']) ?>
                            </div>
                        </div>

                        <?php if (!empty($med['indication'])): ?>
                            <div class="detail-block" style="max-width: 180px;">
                                <span class="detail-block-label">Reason (Indication)</span>
                                <div class="detail-block-value" style="font-size: 12.5px;">
                                    <?= h($med['indication']) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (canManagePatient($patientId)): ?>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <form method="POST" action="<?= BASE_URL ?>medication_delete.php" style="display:inline-block;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="medication_id" value="<?= (int)$med['id'] ?>">
                                <input type="hidden" name="patient_id" value="<?= $patientId ?>">
                                <input type="hidden" name="action" value="discontinue">
                                <button type="submit" class="btn btn-secondary btn-sm" data-confirm="Mark '<?= h($med['raw_medicine_name']) ?>' as stopped? This updates the record to discontinued while preserving patient history.">
                                    Stop medication
                                </button>
                            </form>
                            <form method="POST" action="<?= BASE_URL ?>medication_delete.php" style="display:inline-block;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="medication_id" value="<?= (int)$med['id'] ?>">
                                <input type="hidden" name="patient_id" value="<?= $patientId ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-secondary btn-sm" style="color: #dc2626;" data-confirm="Remove prescription entry for '<?= h($med['raw_medicine_name']) ?>'?">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Prescribing History (Multi-Doctor Timeline) -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Prescribing History (Multi-Doctor Timeline)</h3>
            <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 2px;">
                Chronological sequence showing when different doctors prescribed or changed medicines over the years.
            </p>
        </div>
    </div>

    <div class="timeline">
        <?php if (empty($timelineMedications)): ?>
            <p style="color: var(--text-muted); padding: 10px 0;">No prescription history recorded.</p>
        <?php else: ?>
            <?php foreach ($timelineMedications as $tMed): ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <strong style="font-size: 15px; color: var(--text-main);">
                                    <?= h($tMed['raw_medicine_name']) ?> (<?= h($tMed['dosage']) ?>)
                                </strong>
                                <span class="badge <?= ($tMed['status'] === 'active') ? 'badge-success' : 'badge-neutral' ?>" style="margin-left: 8px;">
                                    <?= ($tMed['status'] === 'active') ? 'Active' : 'Discontinued' ?>
                                </span>
                                <div style="font-size: 13.5px; color: var(--text-secondary); margin-top: 4px;">
                                    How taken: <strong><?= h($tMed['frequency']) ?></strong> &bull; Route: <?= ucfirst(h($tMed['route'])) ?>
                                    <?php if (!empty($tMed['indication'])): ?>
                                        &bull; Prescribed for: <em><?= h($tMed['indication']) ?></em>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 700; color: var(--primary); font-size: 13px;">
                                    📅 <?= formatDate($tMed['prescription_date']) ?>
                                </div>
                                <div style="margin-top: 4px;">
                                    <span class="doctor-pill" style="font-size: 12px;">
                                        👨‍⚕️ <?= h($tMed['prescribing_doctor']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (canManagePatient($patientId)): ?>
<!-- Modal: Add New Prescription -->
<div id="addMedicationModal" class="modal-backdrop" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:white; border-radius:12px; max-width:700px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid var(--border); padding-bottom:12px;">
            <h3 style="font-size:18px; font-weight:700; color:var(--text-main);">Add Prescription for <?= h($patient['full_name']) ?></h3>
            <button type="button" class="btn-close" onclick="closeModal('addMedicationModal')">&times;</button>
        </div>

        <form method="POST" action="<?= BASE_URL ?>medication_add.php">
            <?= csrf_field() ?>
            <input type="hidden" name="patient_id" value="<?= $patientId ?>">

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label class="form-label">Medicine name <span class="required">*</span></label>
                    <input type="text" name="raw_medicine_name" class="form-control" placeholder="e.g. Lipitor 20mg or Motrin" required>
                    <div class="form-hint">Enter the brand or generic name as written on the bottle or prescription.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Match to clinical drug catalog (Optional)</label>
                    <select name="medicine_id" class="form-control">
                        <option value="">-- Auto-detect from medicine name --</option>
                        <?php foreach ($referenceMedicines as $refMed): ?>
                            <option value="<?= (int)$refMed['id'] ?>">
                                <?= h(ucwords($refMed['generic_name'])) ?> (<?= h($refMed['therapeutic_class']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-3">
                <div class="form-group">
                    <label class="form-label">Dose description <span class="required">*</span></label>
                    <input type="text" name="dosage" class="form-control" placeholder="e.g. 20 mg" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Dose in milligrams (mg)</label>
                    <input type="number" step="0.01" name="dose_amount_mg" class="form-control" placeholder="e.g. 20.00">
                </div>

                <div class="form-group">
                    <label class="form-label">Route</label>
                    <select name="route" class="form-control">
                        <option value="oral">Oral (by mouth)</option>
                        <option value="subcutaneous">Subcutaneous injection</option>
                        <option value="intravenous">Intravenous (IV)</option>
                        <option value="inhalation">Inhalation (inhaler)</option>
                        <option value="topical">Topical (skin cream)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label class="form-label">How often to take <span class="required">*</span></label>
                    <input type="text" name="frequency" class="form-control" placeholder="e.g. Once daily at bedtime, Twice daily" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Prescribing doctor & specialty <span class="required">*</span></label>
                    <input type="text" name="prescribing_doctor" class="form-control" placeholder="e.g. Dr. Marcus Vance (Cardiology)" required>
                </div>
            </div>

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label class="form-label">Prescription date <span class="required">*</span></label>
                    <input type="date" name="prescription_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Medical reason (Indication)</label>
                    <input type="text" name="indication" class="form-control" placeholder="e.g. High blood pressure, Atrial fibrillation">
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid var(--border); padding-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addMedicationModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <span>💾</span> Save prescription
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
