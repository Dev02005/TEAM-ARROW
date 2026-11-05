<?php
/**
 * Medication Add Handler
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/Patient.php';
require_once __DIR__ . '/models/Medication.php';

// Authentication required to add prescriptions
requireLogin();

$fallbackRedirect = isCitizen() ? BASE_URL . 'my_prescriptions.php' : BASE_URL . 'patients.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($fallbackRedirect);
}

if (!verify_csrf()) {
    die(h(t_plain('common.security_failed')));
}

$patientId = (int)($_POST['patient_id'] ?? 0);
if ($patientId <= 0) {
    setFlash('danger', 'Invalid patient identifier.');
    redirect($fallbackRedirect);
}

// Strict ownership / authorization check:
// Citizens can ONLY add medications to their own profile; admins can add for any patient
if (!canManagePatient($patientId)) {
    setFlash('danger', 'Access denied: You are not authorized to add medications to another person\'s medication list.');
    redirect($fallbackRedirect);
}

$patientModel = new Patient();
$patient = $patientModel->getById($patientId);
if (!$patient) {
    setFlash('danger', 'Patient profile not found.');
    redirect($fallbackRedirect);
}

$medicationModel = new Medication();
$postData = $_POST;
$postData['patient_id'] = $patientId;

$errors = $medicationModel->validate($postData);

if (!empty($errors)) {
    $firstError = reset($errors);
    setFlash('danger', 'Validation error: ' . $firstError);
    if (isCitizen()) {
        redirect(BASE_URL . 'my_prescriptions.php');
    } else {
        redirect(BASE_URL . 'patient_view.php?id=' . $patientId);
    }
}

try {
    $medicationId = $medicationModel->create($postData);
    setFlash('success', t_plain('flash.saved', ['name' => $postData['raw_medicine_name']]));
} catch (Exception $e) {
    error_log('medication_add failed: ' . $e->getMessage());
    setFlash('danger', t_plain('flash.action_failed'));
}

if (isCitizen()) {
    redirect(BASE_URL . 'my_prescriptions.php');
} else {
    redirect(BASE_URL . 'patient_view.php?id=' . $patientId);
}
