<?php
/**
 * Edit Patient Profile
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/Patient.php';

requireLogin();

$fallbackRedirect = isCitizen() ? BASE_URL . 'my_prescriptions.php' : BASE_URL . 'patients.php';

$patientId = (int)($_GET['id'] ?? 0);
if ($patientId <= 0) {
    setFlash('danger', 'Invalid patient ID specified.');
    redirect($fallbackRedirect);
}

if (!canManagePatient($patientId)) {
    setFlash('danger', 'Access denied: You are not authorized to edit another person\'s patient profile.');
    redirect($fallbackRedirect);
}

$patientModel = new Patient();
$patient = $patientModel->getById($patientId);

if (!$patient) {
    setFlash('danger', 'Patient record not found.');
    redirect($fallbackRedirect);
}

$pageTitle = 'Edit: ' . $patient['full_name'];
$errors = [];
$formData = $patient;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        die('CSRF security verification failed.');
    }

    $formData = array_merge($formData, $_POST);
    $errors = $patientModel->validate($formData, $patientId);

    if (empty($errors)) {
        try {
            $patientModel->update($patientId, $formData);
            setFlash('success', 'Patient record updated successfully.');
            redirect(isCitizen() ? BASE_URL . 'my_prescriptions.php' : BASE_URL . 'patient_view.php?id=' . $patientId);
        } catch (Exception $e) {
            error_log('patient_edit failed: ' . $e->getMessage());
            $errors['general'] = t_plain('flash.action_failed');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Edit Patient: <?= h($patient['full_name']) ?></h2>
        <p class="page-subtitle">Update kidney function measurements or add new medical diagnoses.</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>patient_view.php?id=<?= $patientId ?>" class="btn btn-secondary">
            &larr; Back to patient profile
        </a>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= h($errors['general']) ?></div>
<?php endif; ?>

<div class="card" style="max-width: 820px; margin: 0 auto; padding: 28px;">
    <form method="POST" action="<?= BASE_URL ?>patient_edit.php?id=<?= $patientId ?>">
        <?= csrf_field() ?>

        <h3 style="font-size: 16px; font-weight: 700; color: var(--text-main); margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            1. Patient Identification
        </h3>

        <div class="grid grid-cols-2">
            <div class="form-group">
                <label class="form-label">Patient ID / Record number <span class="required">*</span></label>
                <input type="text" name="patient_mrn" class="form-control" value="<?= h($formData['patient_mrn']) ?>" required>
                <?php if (isset($errors['patient_mrn'])): ?>
                    <div class="form-error"><?= h($errors['patient_mrn']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Patient full name <span class="required">*</span></label>
                <input type="text" name="full_name" class="form-control" value="<?= h($formData['full_name']) ?>" required>
                <?php if (isset($errors['full_name'])): ?>
                    <div class="form-error"><?= h($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-2">
            <div class="form-group">
                <label class="form-label">Date of birth <span class="required">*</span></label>
                <input type="date" name="date_of_birth" class="form-control" value="<?= h($formData['date_of_birth']) ?>" required>
                <?php if (isset($errors['date_of_birth'])): ?>
                    <div class="form-error"><?= h($errors['date_of_birth']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-control">
                    <option value="female" <?= ($formData['gender'] === 'female') ? 'selected' : '' ?>>Female</option>
                    <option value="male" <?= ($formData['gender'] === 'male') ? 'selected' : '' ?>>Male</option>
                    <option value="other" <?= ($formData['gender'] === 'other') ? 'selected' : '' ?>>Other / Unspecified</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Pregnancy Status</label>
                <select name="pregnant" class="form-control">
                    <option value="N" <?= (($formData['pregnant'] ?? 'N') === 'N') ? 'selected' : '' ?>>Not pregnant (N)</option>
                    <option value="Y" <?= (($formData['pregnant'] ?? '') === 'Y') ? 'selected' : '' ?>>Currently pregnant (Y)</option>
                    <option value="NA" <?= (($formData['pregnant'] ?? '') === 'NA') ? 'selected' : '' ?>>Not applicable (NA)</option>
                </select>
            </div>
        </div>

        <h3 style="font-size: 16px; font-weight: 700; color: var(--text-main); margin: 24px 0 16px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding-top: 18px; padding-bottom: 8px;">
            2. Kidney Function Biomarkers
        </h3>

        <div class="grid grid-cols-2">
            <div class="form-group">
                <label class="form-label">Kidney filtration rate (eGFR in mL/min)</label>
                <input type="number" step="0.1" name="egfr" class="form-control" value="<?= h((string)$formData['egfr']) ?>">
                <div class="form-hint">Normal is ≥90. Values under 60 indicate reduced kidney capacity.</div>
                <?php if (isset($errors['egfr'])): ?>
                    <div class="form-error"><?= h($errors['egfr']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Serum creatinine (mg/dL)</label>
                <input type="number" step="0.01" name="serum_creatinine" class="form-control" value="<?= h((string)$formData['serum_creatinine']) ?>">
                <?php if (isset($errors['serum_creatinine'])): ?>
                    <div class="form-error"><?= h($errors['serum_creatinine']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <h3 style="font-size: 16px; font-weight: 700; color: var(--text-main); margin: 24px 0 16px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding-top: 18px; padding-bottom: 8px;">
            3. Medical History & Documented Allergies
        </h3>

        <div class="form-group">
            <label class="form-label">Known medical conditions (separated by commas)</label>
            <input type="text" name="conditions" class="form-control" value="<?= h($formData['conditions']) ?>">
            <div class="form-hint">e.g. Type 2 Diabetes, High blood pressure, Asthma, Peptic ulcer</div>
        </div>

        <div class="form-group">
            <label class="form-label">Documented drug allergies</label>
            <input type="text" name="allergies" class="form-control" value="<?= h($formData['allergies']) ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Clinical notes</label>
            <textarea name="notes" class="form-control"><?= h($formData['notes']) ?></textarea>
        </div>

        <div class="form-group" style="margin-top: 16px; background: var(--bg-subtle); padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border);">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size: 13.5px; font-weight: 600;">
                <input type="checkbox" name="is_near_miss_case" value="1" <?= (!empty($formData['is_near_miss_case'])) ? 'checked' : '' ?>>
                <span>Mark as practice / demonstration test case</span>
            </label>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px; border-top:1px solid var(--border); padding-top:18px;">
            <a href="<?= BASE_URL ?>patient_view.php?id=<?= $patientId ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg">
                <span>💾</span> Update patient record
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
