<?php
/**
 * H4 Medication Safety Core Logic Test Suite
 * Follows TalentAP Hackathon Rule 6:
 * Tests the parts that matter: calculations, rules, parsers, and thresholds.
 * Asserts real outputs on core clinical edge cases.
 */

declare(strict_types=1);

require_once __DIR__ . '/../pipeline/SafetyEngineH4.php';
require_once __DIR__ . '/../pipeline/Resolver.php';

class H4SafetyTestSuite
{
    private SafetyEngineH4 $engine;
    private Resolver $resolver;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $dataDir = __DIR__ . '/../data';
        $this->engine = new SafetyEngineH4($dataDir);
        $this->resolver = $this->engine->getResolver();
    }

    private function assert(string $name, bool $condition, string $detail = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$name}" . ($detail ? " -- {$detail}" : "") . PHP_EOL;
        } else {
            $this->failed++;
            echo " [FAIL] {$name}" . ($detail ? " -- {$detail}" : "") . PHP_EOL;
        }
    }

    public function run(): int
    {
        echo "========================================================\n";
        echo "  TALENTAP H4 CLINICAL SAFETY LOGIC TEST SUITE          \n";
        echo "========================================================\n";

        // 1. RESOLVER TESTS
        echo "\n-- Section 1: Token Resolution Engine --\n";
        
        $resExactGeneric = $this->resolver->resolve('tab paracetamol 650 if pain');
        $this->assert('Resolve exact generic name', $resExactGeneric === ['paracetamol'], implode(';', $resExactGeneric));

        $resExactBrand = $this->resolver->resolve('T. Pantodac 20mg OD 30 min before bf');
        $this->assert('Resolve Indian brand to generic (Pantodac -> pantoprazole)', $resExactBrand === ['pantoprazole'], implode(';', $resExactBrand));

        $resComboBrand = $this->resolver->resolve('Tab Glycomet-GP 1 1-0-1');
        $this->assert('Resolve combination brand (Glycomet-GP 1 -> glimepiride;metformin)', $resComboBrand === ['glimepiride', 'metformin'], implode(';', $resComboBrand));

        $resGenericPlus = $this->resolver->resolve('tab sitagliptin + metformin 1-0-1');
        $this->assert('Resolve generic combo with plus sign', $resGenericPlus === ['metformin', 'sitagliptin'], implode(';', $resGenericPlus));

        $resMisspelledBrand = $this->resolver->resolve('T. croken 500 mg SOS');
        $this->assert('Resolve misspelled Indian brand (croken -> paracetamol)', $resMisspelledBrand === ['paracetamol'], implode(';', $resMisspelledBrand));

        $resNonDrug = $this->resolver->resolve('Glucometer strips');
        $this->assert('Non-drug token resolves to empty set', empty($resNonDrug));

        $resDiet = $this->resolver->resolve('Diet advice - low salt');
        $this->assert('Dietary instruction resolves to empty set', empty($resDiet));

        // 2. DRUG-DRUG INTERACTIONS
        echo "\n-- Section 2: Drug-Drug Interaction Rules --\n";

        $alertsIx = $this->engine->evaluatePatient(
            'TEST-IX-01',
            'Tab Warfarin 5mg OD; Tab Aspirin 75mg OD',
            80,
            'Hypertension',
            '',
            'N'
        );
        $hasWarfAsp = false;
        foreach ($alertsIx as $a) {
            if ($a['issue_type'] === 'interaction' && $a['drugs_involved'] === 'aspirin;warfarin') {
                $hasWarfAsp = true;
                $this->assert('Warfarin + Aspirin interaction flagged', true, "Severity: {$a['severity']}");
                break;
            }
        }
        if (!$hasWarfAsp) $this->assert('Warfarin + Aspirin interaction flagged', false);

        // 3. DUPLICATE THERAPY
        echo "\n-- Section 3: Duplicate Therapy Rules --\n";

        // Paracetamol duplicate -> major severity
        $alertsDupPcm = $this->engine->evaluatePatient(
            'TEST-DUP-01',
            'Crocin 650 SOS; Combiflam 1-0-1',
            90,
            'Fever',
            '',
            'N'
        );
        $hasPcmDup = false;
        foreach ($alertsDupPcm as $a) {
            if ($a['issue_type'] === 'duplicate_therapy' && $a['drugs_involved'] === 'paracetamol') {
                $hasPcmDup = true;
                $this->assert('Paracetamol duplicate therapy flagged as major', $a['severity'] === 'major', "Severity: {$a['severity']}");
                break;
            }
        }
        if (!$hasPcmDup) $this->assert('Paracetamol duplicate therapy flagged as major', false);

        // Combo internal components should NOT be flagged as duplicate
        $alertsComboSingle = $this->engine->evaluatePatient(
            'TEST-DUP-02',
            'Tab Glycomet-GP 1 1-0-1',
            90,
            'Type 2 Diabetes',
            '',
            'N'
        );
        $hasFalseDup = false;
        foreach ($alertsComboSingle as $a) {
            if ($a['issue_type'] === 'duplicate_therapy') $hasFalseDup = true;
        }
        $this->assert('Single combination product does not trigger internal duplicate therapy', !$hasFalseDup);

        // 4. RENAL IMPAIRMENT
        echo "\n-- Section 4: Renal Dosing Rules --\n";

        // Metformin at eGFR 25 (< 30)
        $alertsRenalLow = $this->engine->evaluatePatient(
            'TEST-REN-01',
            'Tab Metformin 500mg BD',
            25,
            'Type 2 Diabetes',
            '',
            'N'
        );
        $hasMetforminRenal = false;
        foreach ($alertsRenalLow as $a) {
            if ($a['issue_type'] === 'renal_dose' && $a['drugs_involved'] === 'metformin') {
                $hasMetforminRenal = true;
                $this->assert('Metformin eGFR < 30 triggers renal alert', true, "Severity: {$a['severity']}");
                break;
            }
        }
        if (!$hasMetforminRenal) $this->assert('Metformin eGFR < 30 triggers renal alert', false);

        // If eGFR is missing -> NO renal issue should be labelled
        $alertsRenalMissing = $this->engine->evaluatePatient(
            'TEST-REN-02',
            'Tab Metformin 500mg BD',
            null, // missing eGFR
            'CKD Stage 3',
            '',
            'N'
        );
        $hasRenalWhenMissing = false;
        foreach ($alertsRenalMissing as $a) {
            if ($a['issue_type'] === 'renal_dose') $hasRenalWhenMissing = true;
        }
        $this->assert('Missing eGFR suppresses renal alert (per H4 problem definition)', !$hasRenalWhenMissing);

        // 5. CONTRAINDICATIONS & PREGNANCY
        echo "\n-- Section 5: Clinical Contraindications & Pregnancy --\n";

        // Propranolol in Asthma
        $alertsAsthma = $this->engine->evaluatePatient(
            'TEST-CONTRA-01',
            'Tab Propranolol 40mg BD',
            90,
            'Bronchial Asthma; Hypertension',
            '',
            'N'
        );
        $hasPropranololAsthma = false;
        foreach ($alertsAsthma as $a) {
            if ($a['issue_type'] === 'contraindication' && $a['drugs_involved'] === 'propranolol') {
                $hasPropranololAsthma = true;
                $this->assert('Propranolol in Asthma contraindication flagged', $a['severity'] === 'contraindicated', "Severity: {$a['severity']}");
                break;
            }
        }
        if (!$hasPropranololAsthma) $this->assert('Propranolol in Asthma contraindication flagged', false);

        // Negated condition should NOT trigger contraindication
        $alertsNegated = $this->engine->evaluatePatient(
            'TEST-CONTRA-02',
            'Tab Propranolol 40mg BD',
            90,
            'No h/o Asthma; Hypertension',
            '',
            'N'
        );
        $hasNegatedContra = false;
        foreach ($alertsNegated as $a) {
            if ($a['issue_type'] === 'contraindication' && $a['drugs_involved'] === 'propranolol') {
                $hasNegatedContra = true;
            }
        }
        $this->assert('Negated diagnosis ("no h/o Asthma") does not trigger contraindication', !$hasNegatedContra);

        // Pregnancy Category X (Atorvastatin in pregnancy)
        $alertsPregX = $this->engine->evaluatePatient(
            'TEST-PREG-01',
            'Tab Atorvastatin 20mg HS',
            90,
            'Dyslipidemia',
            '',
            'Y' // pregnant = Y
        );
        $hasAtorvaPreg = false;
        foreach ($alertsPregX as $a) {
            if ($a['issue_type'] === 'contraindication' && $a['drugs_involved'] === 'atorvastatin') {
                $hasAtorvaPreg = true;
                $this->assert('Atorvastatin (Category X) in pregnancy flagged as contraindicated', $a['severity'] === 'contraindicated');
                break;
            }
        }
        if (!$hasAtorvaPreg) $this->assert('Atorvastatin (Category X) in pregnancy flagged as contraindicated', false);

        echo "\n========================================================\n";
        echo "SUMMARY: {$this->passed} Passed, {$this->failed} Failed.\n";
        if ($this->failed === 0) {
            echo "[SUCCESS] ALL CLINICAL CORE LOGIC ASSERTIONS PASSED!\n";
        }
        echo "========================================================\n";
        return $this->failed === 0 ? 0 : 1;
    }
}

$suite = new H4SafetyTestSuite();
exit($suite->run());

