<?php
/**
 * User accounts: sign-in lookups and citizen self-registration.
 * Keeps SQL out of login.php / register.php (business logic separate from pages).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/Patient.php';

class User
{
    public const MAX_NAME = 150;
    public const MAX_EMAIL = 150;
    public const MIN_PASSWORD = 6;
    public const MAX_PASSWORD = 200;
    public const MAX_HEALTH_TEXT = 500;
    public const GENDERS = ['female', 'male', 'other'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => trim($email)]);
        return $stmt->fetch() ?: null;
    }

    /** Return the user when the password matches, otherwise null. */
    public function verifyLogin(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if ($user === null || !password_verify($password, (string)$user['password_hash'])) {
            return null;
        }
        return $user;
    }

    /**
     * Check sign-up input. Returns field => translation key for every problem (empty = valid).
     * Values are trimmed; lengths and allowed values match the database columns.
     */
    public static function validateRegistration(array $data): array
    {
        $errors = [];
        $name = trim((string)($data['full_name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $dob = trim((string)($data['date_of_birth'] ?? ''));

        if ($name === '' || mb_strlen($name) > self::MAX_NAME) {
            $errors['full_name'] = 'register.err_name';
        }
        if ($email === '' || mb_strlen($email) > self::MAX_EMAIL || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'register.err_email';
        }
        if (strlen($password) < self::MIN_PASSWORD || strlen($password) > self::MAX_PASSWORD) {
            $errors['password'] = 'register.err_password';
        }
        if ($dob !== '' && !self::isPastDate($dob)) {
            $errors['date_of_birth'] = 'register.err_dob';
        }
        if (!in_array((string)($data['gender'] ?? 'other'), self::GENDERS, true)) {
            $errors['gender'] = 'register.err_gender';
        }
        foreach (['conditions', 'allergies'] as $field) {
            if (mb_strlen(trim((string)($data[$field] ?? ''))) > self::MAX_HEALTH_TEXT) {
                $errors[$field] = 'register.err_too_long';
            }
        }
        return $errors;
    }

    /**
     * Create a citizen's patient record and login together (one transaction).
     * Kidney function (eGFR) is left empty: it must come from a real lab test, never a guess.
     * Returns the session user array.
     */
    public function registerCitizen(array $data): array
    {
        $name = trim((string)$data['full_name']);
        $email = trim((string)$data['email']);
        $mrn = 'CIT-' . date('Y') . '-' . bin2hex(random_bytes(4));

        $this->pdo->beginTransaction();
        try {
            $patientId = (new Patient())->create([
                'patient_id'    => $mrn,
                'patient_mrn'   => $mrn,
                'full_name'     => $name,
                'date_of_birth' => trim((string)($data['date_of_birth'] ?? '')) ?: null,
                'gender'        => $data['gender'] ?? 'other',
                'egfr'          => null,
                'conditions'    => trim((string)($data['conditions'] ?? '')),
                'allergies'     => trim((string)($data['allergies'] ?? '')),
            ]);

            $stmt = $this->pdo->prepare("
                INSERT INTO users (patient_id, email, password_hash, full_name, role)
                VALUES (:patient_id, :email, :password_hash, :full_name, 'citizen')
            ");
            $stmt->execute([
                ':patient_id'    => $patientId,
                ':email'         => $email,
                ':password_hash' => password_hash((string)$data['password'], PASSWORD_DEFAULT),
                ':full_name'     => $name,
            ]);
            $userId = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['id' => $userId, 'patient_id' => $patientId, 'email' => $email, 'full_name' => $name, 'role' => 'citizen'];
    }

    private static function isPastDate(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value && $date <= new DateTime('today');
    }
}
