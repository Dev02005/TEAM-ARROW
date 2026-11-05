<?php
/**
 * Medication Model
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Database.php';

class Medication
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Get all medications for a specific patient
     */
    public function getByPatientId(int $patientId, ?string $status = null): array
    {
        $sql = "SELECT pm.*, 
                       m.generic_name AS matched_generic,
                       m.therapeutic_class,
                       m.renal_dose_adjustment_required,
                       m.renal_rule AS renal_threshold_egfr
                FROM patient_medications pm
                LEFT JOIN medicines m ON pm.medicine_id = m.id
                WHERE pm.patient_id = :patient_id";

        $params = [':patient_id' => $patientId];

        if (!empty($status)) {
            $sql .= " AND pm.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY pm.prescription_date DESC, pm.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get multi-doctor prescription history grouped / formatted for timeline analysis
     */
    public function getTimelineByPatientId(int $patientId): array
    {
        $sql = "SELECT pm.*, 
                       m.generic_name,
                       m.therapeutic_class
                FROM patient_medications pm
                LEFT JOIN medicines m ON pm.medicine_id = m.id
                WHERE pm.patient_id = :patient_id
                ORDER BY pm.prescription_date ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':patient_id' => $patientId]);
        return $stmt->fetchAll();
    }

    /**
     * Get single medication record
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT pm.*, m.generic_name, m.therapeutic_class
                FROM patient_medications pm
                LEFT JOIN medicines m ON pm.medicine_id = m.id
                WHERE pm.id = :id LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * List all reference medicines for prescription dropdowns / auto-linking
     */
    public function getReferenceMedicines(): array
    {
        $sql = "SELECT id, generic_name, brand_names, therapeutic_class, standard_dosage_range 
                FROM medicines 
                ORDER BY generic_name ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Full reference catalogue for the public medicine list, A-Z.
     */
    public function getCatalog(): array
    {
        $sql = "SELECT id, drug_id, generic_name, common_brands, drug_class, therapeutic_group,
                       usual_adult_daily_dose_range, max_daily_dose_mg,
                       renal_dose_adjustment_required, renal_rule, pregnancy_category, notes
                FROM medicines
                ORDER BY generic_name ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Combination brands (one brand name, several generics), A-Z.
     */
    public function getCombinationBrands(): array
    {
        $sql = "SELECT brand_name, dose_form, generic_components FROM combination_brands ORDER BY brand_name ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Add new patient medication record
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO patient_medications (
                    patient_id, medicine_id, raw_medicine_name, prescribed_generic_name,
                    dosage, dose_amount_mg, frequency, route,
                    prescribing_doctor, prescription_date, start_date, end_date,
                    status, indication
                ) VALUES (
                    :patient_id, :medicine_id, :raw_medicine_name, :prescribed_generic_name,
                    :dosage, :dose_amount_mg, :frequency, :route,
                    :prescribing_doctor, :prescription_date, :start_date, :end_date,
                    :status, :indication
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':patient_id'              => (int)$data['patient_id'],
            ':medicine_id'            => !empty($data['medicine_id']) ? (int)$data['medicine_id'] : null,
            ':raw_medicine_name'      => trim($data['raw_medicine_name']),
            ':prescribed_generic_name'=> !empty($data['prescribed_generic_name']) ? trim($data['prescribed_generic_name']) : null,
            ':dosage'                 => trim($data['dosage']),
            ':dose_amount_mg'         => !empty($data['dose_amount_mg']) ? (float)$data['dose_amount_mg'] : null,
            ':frequency'              => trim($data['frequency']),
            ':route'                  => !empty($data['route']) ? trim($data['route']) : 'oral',
            ':prescribing_doctor'     => trim($data['prescribing_doctor']),
            ':prescription_date'      => $data['prescription_date'],
            ':start_date'             => $data['start_date'] ?? $data['prescription_date'],
            ':end_date'               => !empty($data['end_date']) ? $data['end_date'] : null,
            ':status'                 => $data['status'] ?? 'active',
            ':indication'             => !empty($data['indication']) ? trim($data['indication']) : null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update medication status (e.g. discontinue or mark completed)
     */
    public function updateStatus(int $id, string $status, ?string $endDate = null): bool
    {
        $validStatuses = ['active', 'discontinued', 'completed', 'unknown'];
        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        $sql = "UPDATE patient_medications SET status = :status";
        $params = [':status' => $status, ':id' => $id];

        if ($endDate !== null) {
            $sql .= ", end_date = :end_date";
            $params[':end_date'] = $endDate;
        } elseif ($status === 'discontinued' && $endDate === null) {
            $sql .= ", end_date = CURRENT_DATE()";
        }

        $sql .= " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete medication record
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM patient_medications WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Validate medication form input
     */
    public function validate(array $data): array
    {
        $errors = [];

        if (empty(trim($data['raw_medicine_name'] ?? ''))) {
            $errors['raw_medicine_name'] = 'Prescribed medicine name is required.';
        }

        if (empty(trim($data['dosage'] ?? ''))) {
            $errors['dosage'] = 'Dosage (e.g., 20mg or 500mg) is required.';
        }

        if (empty(trim($data['frequency'] ?? ''))) {
            $errors['frequency'] = 'Frequency (e.g., Once daily, BID) is required.';
        }

        if (empty(trim($data['prescribing_doctor'] ?? ''))) {
            $errors['prescribing_doctor'] = 'Prescribing doctor and specialty is required.';
        }

        if (empty($data['prescription_date']) || !strtotime($data['prescription_date'])) {
            $errors['prescription_date'] = 'A valid prescription date is required.';
        }

        return $errors;
    }
}
