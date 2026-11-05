<?php
/**
 * Test Suite: Medicine Verification & Image Analysis
 * 1. Unlisted/unknown medicine -> "We cant decide whether it is safe or not" & Safety Score: NA
 * 2. Image OCR analysis -> Automatically extracts and prints name to "type the medicine name" section
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/MedicineImageExtractor.php';
require_once __DIR__ . '/../models/SafetyEngine.php';

class MedicineCheckAndOcrTestSuite
{
    private int $passed = 0;
    private int $failed = 0;

    private function assert(string $desc, bool $condition, string $detail = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$desc}" . ($detail ? " ({$detail})" : "") . PHP_EOL;
        } else {
            $this->failed++;
            echo " [FAIL] {$desc}" . ($detail ? " ({$detail})" : "") . PHP_EOL;
        }
    }

    public function run(): int
    {
        echo "========================================================\n";
        echo "  MEDICINE VERIFICATION & IMAGE OCR TEST SUITE          \n";
        echo "========================================================\n\n";

        $safetyEngine = new SafetyEngine();
        $extractor = new MedicineImageExtractor();

        // ----------------------------------------------------
        // SECTION 1: Unknown Medicine Safety Score & Label
        // ----------------------------------------------------
        echo "-- Section 1: Unknown / Unlisted Medicine Handling --\n";

        $unknownDrug = 'UnlistedHerbCure99';
        $resolved = $safetyEngine->resolveMedicine($unknownDrug);
        $this->assert('Unlisted medicine resolves to empty set', empty($resolved));

        // Simulate attach_prescription.php with unlisted drug
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = csrf_token();
        $_POST['medicine_name'] = $unknownDrug;
        $_SESSION['citizen_user'] = [
            'id' => 1,
            'patient_id' => 5001,
            'email' => 'eleanor@example.com',
            'full_name' => 'Eleanor Vance',
            'role' => 'citizen'
        ];

        ob_start();
        include __DIR__ . '/../attach_prescription.php';
        $html = ob_get_clean();

        $this->assert(
            'Shows exact message: "We cant decide whether it is safe or not"',
            str_contains($html, 'We cant decide whether it is safe or not'),
            'Label displayed prominently'
        );

        $this->assert(
            'Safety score in that place displays NA',
            preg_match('/<div class="ez-score"[^>]*>[\s\S]*?<span class="ez-score__num"[^>]*>\s*NA\s*<\/span>[\s\S]*?<\/div>/', $html) === 1,
            'Score gauge contains NA instead of percentage'
        );

        $this->assert(
            'Does NOT give numeric percentage score for unlisted medicine',
            !preg_match('/<div class="ez-score"[^>]*>[\s\S]*?<span class="ez-score__num"[^>]*>\s*\d+%\s*<\/span>/', $html),
            'No percentage score assigned'
        );

        // ----------------------------------------------------
        // SECTION 2: Image OCR & Medicine Extraction
        // ----------------------------------------------------
        echo "\n-- Section 2: Image OCR Text Extraction --\n";

        // Test 2A: Indian Brand on Strip
        $res1 = $extractor->extractFromText("DOLO 650\nParacetamol Tablets IP\n650mg\nMicro Labs Ltd");
        $this->assert('Extracts DOLO 650 from strip text', $res1['success'] && str_contains(strtoupper($res1['medicine_name']), 'DOLO 650'));

        // Test 2B: Prescription Slip with Dosage & Frequency
        $res2 = $extractor->extractFromText("Rx\nTab Glycomet 500mg\n1-0-1 after food");
        $this->assert('Extracts Glycomet 500mg from prescription', $res2['success'] && str_contains($res2['medicine_name'], 'Glycomet'));
        $this->assert('Extracts dosage 500mg', $res2['dosage'] === '500mg');
        $this->assert('Extracts frequency Twice daily from 1-0-1', $res2['frequency'] === 'Twice daily');

        // Test 2C: Combination Product (Combiflam)
        $res3 = $extractor->extractFromText("Combiflam\nIbuprofen & Paracetamol\nSanofi");
        $this->assert('Extracts combination brand Combiflam', $res3['success'] && $res3['medicine_name'] === 'Combiflam');

        // Test 2D: Unlisted Medicine from Image Text
        $res4 = $extractor->extractFromText("BioCurcumin 500mg\nDaily Wellness Supplement");
        $this->assert('Extracts unlisted medicine name from image text', $res4['success'] && str_contains($res4['medicine_name'], 'BioCurcumin'));
        $this->assert('Identifies unlisted medicine as not in DB', $res4['in_database'] === false);

        // Test 2E: Filename fallback
        $res5 = $extractor->extractFromText("", "brufen_400_strip.png");
        $this->assert('Extracts Brufen from filename fallback', $res5['success'] && str_contains($res5['medicine_name'], 'Brufen'));

        // ----------------------------------------------------
        // SECTION 3: Frontend Image Analysis & Auto-Print Wiring
        // ----------------------------------------------------
        echo "\n-- Section 3: Frontend Script & Auto-Print Input Wiring --\n";

        $attachFile = file_get_contents(__DIR__ . '/../attach_prescription.php');
        $this->assert('Step 2 input has id medicineNameInput', str_contains($attachFile, 'id="medicineNameInput"'));
        $this->assert('Tesseract library included', str_contains($attachFile, 'tesseract.min.js'));
        $this->assert('Auto-print to medicineNameInput in JS', str_contains($attachFile, 'medInput.value = data.medicine_name'));
        $this->assert('Drag and drop support included', str_contains($attachFile, 'photoDropArea'));
        $this->assert('Clipboard paste support included', str_contains($attachFile, "addEventListener('paste'"));

        echo "\n========================================================\n";
        echo "SUMMARY: {$this->passed} Passed, {$this->failed} Failed.\n";
        echo "========================================================\n";

        return $this->failed === 0 ? 0 : 1;
    }
}

exit((new MedicineCheckAndOcrTestSuite())->run());

