<?php
/**
 * Explainable Medication Safety Decision-Support Engine
 * Medication Safety Second Pair of Eyes - H4 Official Engine Integration
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../pipeline/SafetyEngineH4.php';

class SafetyEngine
{
    private PDO $pdo;
    private SafetyEngineH4 $h4Engine;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->h4Engine = new SafetyEngineH4(__DIR__ . '/../data');
    }

    /**
     * Run full safety review for a patient by database ID
     * Returns structured alerts formatted for healthcare professionals and citizens
     */
    public function evaluatePatient(int $patientId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM patients WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $patientId]);
        $patient = $stmt->fetch();

        if (!$patient) {
            return [];
        }

        // Fetch active prescribed medications from database table
        $medStmt = $this->pdo->prepare("
            SELECT raw_medicine_name, status
            FROM patient_medications
            WHERE patient_id = :pid AND status = 'active'
        ");
        $medStmt->execute([':pid' => $patientId]);
        $medRows = $medStmt->fetchAll();

        // Build consolidated medication list
        if (!empty($medRows)) {
            $medList = implode('; ', array_column($medRows, 'raw_medicine_name'));
        } else {
            $medList = (string)($patient['medication_list'] ?? '');
        }

        $egfr = ($patient['egfr'] !== null && $patient['egfr'] !== '') ? (int)$patient['egfr'] : null;
        $diagnoses = (string)($patient['diagnoses'] ?? $patient['conditions'] ?? '');
        $allergies = (string)($patient['allergies'] ?? '');
        $pregnant = (string)($patient['pregnant'] ?? 'NA');

        $h4Alerts = $this->h4Engine->evaluatePatient(
            $patient['patient_id'] ?? "PAT-{$patientId}",
            $medList,
            $egfr,
            $diagnoses,
            $allergies,
            $pregnant
        );

        // Format alerts for presentation in web views
        $formatted = [];
        foreach ($h4Alerts as $a) {
            $sev = $a['severity'];
            $type = $a['issue_type'];
            $drugs = $a['drugs_involved'];

            $title = match ($type) {
                'interaction' => "Drug-Drug Interaction: " . ucwords(str_replace(';', ' + ', $drugs)),
                'duplicate_therapy' => "Duplicate Therapy: " . ucwords(str_replace(';', ' & ', $drugs)),
                'renal_dose' => "Kidney Function / Renal Dosing Risk: " . ucwords($drugs),
                'contraindication' => "Clinical Contraindication: " . ucwords($drugs),
                default => "Safety Warning: " . ucwords($drugs)
            };

            $whyFlagged = match ($type) {
                'interaction' => "Active concurrent medications ({$drugs}) interact pharmacologically per verified Chandrapuri District Hospital clinical references.",
                'duplicate_therapy' => "The patient has multiple active prescriptions sharing the same active generic identity or duplicate therapeutic class ({$drugs}).",
                'renal_dose' => "Patient's kidney filtration rate (eGFR: " . ($egfr !== null ? "{$egfr} mL/min" : "Unknown") . ") is below the safe prescribing cutoff for {$drugs}.",
                'contraindication' => "This medication ({$drugs}) is contraindicated with documented clinical conditions, pregnancy status, or patient allergy history.",
                default => "Clinical conflict flagged by deterministic safety knowledge base."
            };

            $formatted[] = [
                'severity'           => $sev, // 'contraindicated', 'major', 'moderate', 'minor'
                'alert_type'         => $type,
                'medicines_involved' => ucwords(str_replace(';', ' & ', $drugs)),
                'problem'            => $title,
                'why_flagged'        => $whyFlagged,
                'recommended_action' => $a['suggested_action'],
                'evidence'           => "TalentAP 2026 Authoritative Clinical Reference Tables (interactions, renal rules, duplicate groups, contraindications).",
                'confidence'         => number_format((float)$a['confidence'] * 100, 0) . "% (Verified rule-engine match)",
                'limitations'        => "Assessment based solely on reviewed entries and recorded patient parameters. Unrecorded OTC drugs, supplements, or recent unentered lab tests are not accounted for. A clinician must decide.",
                'rule_code'          => strtoupper(substr($type, 0, 3)) . '-' . substr(md5($drugs), 0, 6)
            ];
        }

        // Sort by clinical priority: contraindicated > major > moderate > minor
        $sevOrder = ['contraindicated' => 4, 'major' => 3, 'moderate' => 2, 'minor' => 1];
        usort($formatted, fn($x, $y) => ($sevOrder[$y['severity']] ?? 0) <=> ($sevOrder[$x['severity']] ?? 0));

        return $formatted;
    }

    /**
     * Which generic medicine(s) a written name was recognised as (empty = not recognised).
     * Shown to patients so a wrong match can be spotted ("We read this as: ...").
     */
    public function resolveMedicine(string $name): array
    {
        return $this->h4Engine->getResolver()->resolve($name);
    }

    /**
     * Evaluate a candidate new prescription against an existing patient regimen
     * Used by attach_prescription.php
     */
    public function evaluateCandidatePrescription(int $patientId, string $candidateDrugName): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM patients WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $patientId]);
        $patient = $stmt->fetch();

        if (!$patient) {
            return [];
        }

        // Fetch active medications
        $medStmt = $this->pdo->prepare("
            SELECT raw_medicine_name FROM patient_medications WHERE patient_id = :pid AND status = 'active'
        ");
        $medStmt->execute([':pid' => $patientId]);
        $activeMeds = $medStmt->fetchAll(PDO::FETCH_COLUMN);

        // Prepend candidate
        $allMeds = array_merge([$candidateDrugName], $activeMeds);
        $medList = implode('; ', $allMeds);

        $egfr = ($patient['egfr'] !== null && $patient['egfr'] !== '') ? (int)$patient['egfr'] : null;
        $diagnoses = (string)($patient['diagnoses'] ?? $patient['conditions'] ?? '');
        $allergies = (string)($patient['allergies'] ?? '');
        $pregnant = (string)($patient['pregnant'] ?? 'NA');

        $h4Alerts = $this->h4Engine->evaluatePatient(
            $patient['patient_id'] ?? "PAT-{$patientId}",
            $medList,
            $egfr,
            $diagnoses,
            $allergies,
            $pregnant
        );

        // Resolve candidate to generics to filter alerts related to this candidate
        $candidateGenerics = $this->h4Engine->getResolver()->resolve($candidateDrugName);
        $candidateGenSet = array_flip($candidateGenerics);

        $relevantAlerts = [];
        foreach ($h4Alerts as $a) {
            $alertGens = explode(';', $a['drugs_involved']);
            $involvesCandidate = false;
            foreach ($alertGens as $ag) {
                if (isset($candidateGenSet[$ag])) {
                    $involvesCandidate = true;
                    break;
                }
            }
            if ($involvesCandidate || empty($candidateGenerics)) {
                $relevantAlerts[] = [
                    'severity'           => $a['severity'],
                    'alert_type'         => $a['issue_type'],
                    'medicines_involved' => ucwords(str_replace(';', ' & ', $a['drugs_involved'])),
                    'problem'            => "Clinical Risk with {$candidateDrugName}: " . ucwords(str_replace('_', ' ', $a['issue_type'])),
                    'why_flagged'        => "Adding '{$candidateDrugName}' triggers a conflict with patient's existing regimen or health profile.",
                    'recommended_action' => $a['suggested_action'],
                    'evidence'           => "Authoritative Reference DDI / Renal / Contraindication Catalog.",
                    'confidence'         => "95% (Direct match in verified knowledge base)",
                    'limitations'        => "Clinical decision-making required. Unentered OTC items or supplements are not evaluated. A clinician decides.",
                    'rule_code'          => 'CAND-' . strtoupper(substr($a['issue_type'], 0, 3))
                ];
            }
        }

        return $relevantAlerts;
    }
}
