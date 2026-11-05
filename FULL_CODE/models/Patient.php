<?php
/**
 * Patient Model
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Database.php';

class Patient
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Retrieve paginated patient list with optional search and clinical risk filter
     */
    public function getAll(?string $search = null, ?string $riskFilter = null, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT p.*, 
                       COUNT(pm.id) AS total_prescriptions,
                       SUM(CASE WHEN pm.status = 'active' THEN 1 ELSE 0 END) AS active_prescriptions,
                       COUNT(DISTINCT pm.prescribing_doctor) AS distinct_doctors
                FROM patients p
                LEFT JOIN patient_medications pm ON p.id = pm.patient_id";

        $conditions = [];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(p.patient_mrn LIKE :search OR p.full_name LIKE :search OR p.conditions LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        if ($riskFilter === 'renal_impaired') {
            $conditions[] = "p.egfr IS NOT NULL AND p.egfr < 60.00";
        } elseif ($riskFilter === 'severe_renal') {
            $conditions[] = "p.egfr IS NOT NULL AND p.egfr < 30.00";
        } elseif ($riskFilter === 'near_miss') {
            $conditions[] = "p.is_near_miss_case = 1";
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY p.id ORDER BY p.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count total patients matching filters for pagination
     */
    public function countAll(?string $search = null, ?string $riskFilter = null): int
    {
        $sql = "SELECT COUNT(*) FROM patients p";
        $conditions = [];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(p.patient_mrn LIKE :search OR p.full_name LIKE :search OR p.conditions LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        if ($riskFilter === 'renal_impaired') {
            $conditions[] = "p.egfr IS NOT NULL AND p.egfr < 60.00";
        } elseif ($riskFilter === 'severe_renal') {
            $conditions[] = "p.egfr IS NOT NULL AND p.egfr < 30.00";
        } elseif ($riskFilter === 'near_miss') {
            $conditions[] = "p.is_near_miss_case = 1";
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Retrieve single patient by ID with medication counts
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT p.*,
                       COUNT(pm.id) AS total_prescriptions,
                       SUM(CASE WHEN pm.status = 'active' THEN 1 ELSE 0 END) AS active_prescriptions,
                       COUNT(DISTINCT pm.prescribing_doctor) AS distinct_doctors
                FROM patients p
                LEFT JOIN patient_medications pm ON p.id = pm.patient_id
                WHERE p.id = :id
                GROUP BY p.id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Retrieve patient by MRN
     */
    public function getByMrn(string $mrn): ?array
    {
        $sql = "SELECT * FROM patients WHERE patient_mrn = :mrn LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':mrn' => trim($mrn)]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Create new patient
     */
    public function create(array $data): int
    {
        $mrn = trim($data['patient_mrn'] ?? '');
        $pid = trim($data['patient_id'] ?? $mrn);

        $dob = !empty($data['date_of_birth']) ? $data['date_of_birth'] : null;
        $age = 50;
        if ($dob) {
            try {
                $bDate = new DateTime($dob);
                $age = $bDate->diff(new DateTime('today'))->y;
            } catch (Exception $e) {
                // Unreadable date of birth: keep the default age and record why.
                error_log('Invalid date_of_birth "' . $dob . '": ' . $e->getMessage());
            }
        }
        $pregnant = in_array(strtoupper(trim((string)($data['pregnant'] ?? ''))), ['Y', 'N'], true) ? strtoupper(trim((string)$data['pregnant'])) : 'NA';
        $conditions = !empty($data['conditions']) ? trim($data['conditions']) : (!empty($data['diagnoses']) ? trim($data['diagnoses']) : null);

        $sql = "INSERT INTO patients (
                    patient_id, patient_mrn, full_name, date_of_birth, age, gender, 
                    egfr, egfr_ml_min_1_73m2, serum_creatinine, pregnant, conditions, diagnoses, allergies, 
                    is_near_miss_case, notes
                ) VALUES (
                    :patient_id, :patient_mrn, :full_name, :date_of_birth, :age, :gender, 
                    :egfr, :egfr_ml_min_1_73m2, :serum_creatinine, :pregnant, :conditions, :diagnoses, :allergies, 
                    :is_near_miss_case, :notes
                )";

        $stmt = $this->pdo->prepare($sql);
        $egfrVal = (!empty($data['egfr']) || ($data['egfr'] ?? '') === '0') ? (float)$data['egfr'] : null;

        $stmt->execute([
            ':patient_id'        => $pid,
            ':patient_mrn'       => $mrn,
            ':full_name'         => trim($data['full_name']),
            ':date_of_birth'     => $dob,
            ':age'               => $age,
            ':gender'            => $data['gender'] ?? 'other',
            ':egfr'              => $egfrVal,
            ':egfr_ml_min_1_73m2'=> $egfrVal !== null ? (int)round($egfrVal) : null,
            ':serum_creatinine'  => (!empty($data['serum_creatinine']) || ($data['serum_creatinine'] ?? '') === '0') ? (float)$data['serum_creatinine'] : null,
            ':pregnant'          => $pregnant,
            ':conditions'        => $conditions,
            ':diagnoses'         => $conditions,
            ':allergies'         => !empty($data['allergies']) ? trim($data['allergies']) : null,
            ':is_near_miss_case' => !empty($data['is_near_miss_case']) ? 1 : 0,
            ':notes'             => !empty($data['notes']) ? trim($data['notes']) : null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update existing patient
     */
    public function update(int $id, array $data): bool
    {
        $dob = !empty($data['date_of_birth']) ? $data['date_of_birth'] : null;
        $age = 50;
        if ($dob) {
            try {
                $bDate = new DateTime($dob);
                $age = $bDate->diff(new DateTime('today'))->y;
            } catch (Exception $e) {
                // Unreadable date of birth: keep the default age and record why.
                error_log('Invalid date_of_birth "' . $dob . '": ' . $e->getMessage());
            }
        }
        $pregnant = in_array(strtoupper(trim((string)($data['pregnant'] ?? ''))), ['Y', 'N'], true) ? strtoupper(trim((string)$data['pregnant'])) : 'NA';
        $conditions = !empty($data['conditions']) ? trim($data['conditions']) : (!empty($data['diagnoses']) ? trim($data['diagnoses']) : null);
        $egfrVal = (!empty($data['egfr']) || ($data['egfr'] ?? '') === '0') ? (float)$data['egfr'] : null;

        $sql = "UPDATE patients SET 
                    patient_mrn = :patient_mrn,
                    full_name = :full_name,
                    date_of_birth = :date_of_birth,
                    age = :age,
                    gender = :gender,
                    egfr = :egfr,
                    egfr_ml_min_1_73m2 = :egfr_ml_min_1_73m2,
                    serum_creatinine = :serum_creatinine,
                    pregnant = :pregnant,
                    conditions = :conditions,
                    diagnoses = :diagnoses,
                    allergies = :allergies,
                    is_near_miss_case = :is_near_miss_case,
                    notes = :notes
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':patient_mrn'       => trim($data['patient_mrn']),
            ':full_name'         => trim($data['full_name']),
            ':date_of_birth'     => $dob,
            ':age'               => $age,
            ':gender'            => $data['gender'] ?? 'other',
            ':egfr'              => $egfrVal,
            ':egfr_ml_min_1_73m2'=> $egfrVal !== null ? (int)round($egfrVal) : null,
            ':serum_creatinine'  => (!empty($data['serum_creatinine']) || ($data['serum_creatinine'] ?? '') === '0') ? (float)$data['serum_creatinine'] : null,
            ':pregnant'          => $pregnant,
            ':conditions'        => $conditions,
            ':diagnoses'         => $conditions,
            ':allergies'         => !empty($data['allergies']) ? trim($data['allergies']) : null,
            ':is_near_miss_case' => !empty($data['is_near_miss_case']) ? 1 : 0,
            ':notes'             => !empty($data['notes']) ? trim($data['notes']) : null,
            ':id'                => $id
        ]);
    }

    /**
     * Delete patient (cascades to medications and alerts)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM patients WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * System-wide statistics for the clinical dashboard
     */
    public function getStats(): array
    {
        $stats = [
            'total_patients' => 0,
            'renal_risk_patients' => 0,
            'near_miss_cases' => 0,
            'total_prescriptions' => 0,
            'active_prescriptions' => 0,
            'multi_doctor_patients' => 0
        ];

        $stats['total_patients'] = (int)$this->pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
        $stats['renal_risk_patients'] = (int)$this->pdo->query("SELECT COUNT(*) FROM patients WHERE egfr IS NOT NULL AND egfr < 60.00")->fetchColumn();
        $stats['near_miss_cases'] = (int)$this->pdo->query("SELECT COUNT(*) FROM patients WHERE is_near_miss_case = 1")->fetchColumn();
        $stats['total_prescriptions'] = (int)$this->pdo->query("SELECT COUNT(*) FROM patient_medications")->fetchColumn();
        $stats['active_prescriptions'] = (int)$this->pdo->query("SELECT COUNT(*) FROM patient_medications WHERE status = 'active'")->fetchColumn();

        // Patients with prescriptions from >= 2 distinct doctors
        $stats['multi_doctor_patients'] = (int)$this->pdo->query("
            SELECT COUNT(*) FROM (
                SELECT patient_id FROM patient_medications 
                GROUP BY patient_id 
                HAVING COUNT(DISTINCT prescribing_doctor) >= 2
            ) AS mdp
        ")->fetchColumn();

        return $stats;
    }

    /**
     * Validate patient input data
     */
    public function validate(array $data, ?int $existingId = null): array
    {
        $errors = [];

        if (empty(trim($data['patient_mrn'] ?? ''))) {
            $errors['patient_mrn'] = 'Patient MRN / Identifier is required.';
        } else {
            // Check uniqueness
            $checkSql = "SELECT id FROM patients WHERE patient_mrn = :mrn" . ($existingId ? " AND id != :id" : "") . " LIMIT 1";
            $stmt = $this->pdo->prepare($checkSql);
            $params = [':mrn' => trim($data['patient_mrn'])];
            if ($existingId) {
                $params[':id'] = $existingId;
            }
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $errors['patient_mrn'] = 'A patient with this MRN already exists.';
            }
        }

        if (empty(trim($data['full_name'] ?? ''))) {
            $errors['full_name'] = 'Full name is required.';
        }

        if (empty($data['date_of_birth'])) {
            $errors['date_of_birth'] = 'Date of birth is required.';
        } elseif (!strtotime($data['date_of_birth'])) {
            $errors['date_of_birth'] = 'Invalid date of birth format.';
        }

        if (!empty($data['egfr'])) {
            $egfr = (float)$data['egfr'];
            if ($egfr < 0 || $egfr > 200) {
                $errors['egfr'] = 'eGFR must be between 0 and 200 mL/min/1.73m².';
            }
        }

        if (!empty($data['serum_creatinine'])) {
            $creat = (float)$data['serum_creatinine'];
            if ($creat < 0 || $creat > 25) {
                $errors['serum_creatinine'] = 'Serum creatinine must be a plausible physiological value (0 - 25 mg/dL).';
            }
        }

        return $errors;
    }
}
