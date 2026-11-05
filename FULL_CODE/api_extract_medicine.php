<?php
/**
 * API Endpoint: Extract & Match Medicine from OCR Text / Uploaded Prescription Photo
 * DoseWise - Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/MedicineImageExtractor.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$rawText = trim((string)($_POST['ocr_text'] ?? ''));
$fileName = trim((string)($_POST['filename'] ?? ''));

// If an image file was uploaded directly in the request
if (!empty($_FILES['prescription_file']['name'])) {
    $uploadedName = $_FILES['prescription_file']['name'];
    if (empty($fileName)) {
        $fileName = $uploadedName;
    }
}

$extractor = new MedicineImageExtractor();
$result = $extractor->extractFromText($rawText, $fileName);

echo json_encode($result);

