<?php
/**
 * Medication Status / Deletion Handler
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/Medication.php';

// Authentication required to modify medications
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(isCitizen() ? BASE_URL . 'my_prescriptions.php' : BASE_URL . 'patients.php');
}

if (!verify_csrf()) {
    die(h(t_plain('common.security_failed')));
}

$medicationId = (int)($_POST['medication_id'] ?? 0);
$patientId = (int)($_POST['patient_id'] ?? 0);
$action = trim($_POST['action'] ?? '');

$fallbackRedirect = isCitizen() ? BASE_URL . 'my_prescriptions.php' : BASE_URL . 'patients.php';

if ($medicationId <= 0 || $patientId <= 0) {
    setFlash('danger', 'Invalid request parameters.');
    redirect($fallbackRedirect);
}

$medicationModel = new Medication();
$medication = $medicationModel->getById($medicationId);

if (!$medication) {
    setFlash('danger', 'Medication record not found.');
    redirect($fallbackRedirect);
}

// Ensure the medication actually belongs to the submitted patient ID
if ((int)$medication['patient_id'] !== $patientId) {
    setFlash('danger', 'Security validation failed: Prescription does not match the specified patient.');
    redirect($fallbackRedirect);
}

// Strict ownership / authorization check:
// Citizens can ONLY modify their own medications; admins can manage any patient
if (!canManagePatient($patientId)) {
    setFlash('danger', t_plain('flash.not_allowed'));
    redirect(isCitizen() ? BASE_URL . 'my_prescriptions.php' : BASE_URL . 'patients.php');
}

try {
    if ($action === 'discontinue') {
        $medicationModel->updateStatus($medicationId, 'discontinued');
        setFlash('warning', t_plain('flash.stopped', ['name' => $medication['raw_medicine_name']]));
    } elseif ($action === 'delete') {
        $medicationModel->delete($medicationId);
        setFlash('info', 'Prescription entry removed successfully.');
    } else {
        setFlash('danger', 'Unrecognized medication action.');
    }
} catch (Exception $e) {
    error_log('medication_delete failed: ' . $e->getMessage());
    setFlash('danger', t_plain('flash.action_failed'));
}

if (isCitizen()) {
    redirect(BASE_URL . 'my_prescriptions.php');
} else {
    redirect(BASE_URL . 'patient_view.php?id=' . $patientId);
}
