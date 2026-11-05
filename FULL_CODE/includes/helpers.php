<?php
/**
 * Clinical Helper Functions & Security Utilities
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

// HTML escaping shorthand for XSS prevention
function h(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Generate CSRF token and inject into session
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Generate hidden CSRF input field
function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . h($token) . '">';
}

// Verify CSRF token from POST
function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Flash messaging
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Citizen Authentication Session Helpers
function isLoggedIn(): bool
{
    return !empty($_SESSION['citizen_user']);
}

function currentUser(): ?array
{
    return $_SESSION['citizen_user'] ?? null;
}

function loginUser(array $user): void
{
    $_SESSION['citizen_user'] = [
        'id'         => (int)$user['id'],
        'patient_id' => !empty($user['patient_id']) ? (int)$user['patient_id'] : null,
        'email'      => (string)$user['email'],
        'full_name'  => (string)$user['full_name'],
        'role'       => (string)($user['role'] ?? 'citizen')
    ];
}

function logoutUser(): void
{
    unset($_SESSION['citizen_user']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('warning', t_plain('flash.please_sign_in'));
        redirect(BASE_URL . 'login.php');
    }
}

function isAdmin(): bool
{
    $user = currentUser();
    return $user && ($user['role'] ?? '') === 'admin';
}

function isCitizen(): bool
{
    $user = currentUser();
    return $user && ($user['role'] ?? '') === 'citizen';
}

function currentPatientId(): ?int
{
    $user = currentUser();
    return !empty($user['patient_id']) ? (int)$user['patient_id'] : null;
}

/**
 * Authorization check: determine if the active session has permission to
 * add, edit, discontinue, or delete medication records for a specific patient.
 * - Admin/Clinician: Authorized for all patients.
 * - Citizen account: Strictly authorized ONLY for their own linked patient ID.
 * - Guests/Unauthenticated: Unauthorized (cannot modify database records).
 */
function canManagePatient(int $patientId): bool
{
    if ($patientId <= 0 || !isLoggedIn()) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }
    return currentPatientId() === $patientId;
}

/**
 * Authorization check: determine if the active session can view a patient's
 * confidential medical chart and prescription history.
 * - Admin/Clinician: Authorized for all patients.
 * - Citizen account: Authorized ONLY for their own linked patient ID.
 * - Guests/Unauthenticated: Not allowed (sign in with a demo account to explore).
 */
function canViewPatient(int $patientId): bool
{
    if ($patientId <= 0) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }
    if (isCitizen()) {
        return currentPatientId() === $patientId;
    }
    return false;
}

// Calculate intuitive percentage safety score for citizens
function calculateSafetyScore(array $alerts): array
{
    if (empty($alerts)) {
        return [
            'score'       => 100,
            'label'       => t_plain('score.clean.label'),
            'summary'     => t_plain('score.clean.summary'),
            'badge_class' => 'badge-success',
            'color'       => '#10b981',
            'bg_color'    => '#ecfdf5',
            'text_color'  => '#065f46',
            'severity'    => 'clean'
        ];
    }

    $hasContraindicated = false;
    $hasMajor = false;
    $hasModerate = false;

    foreach ($alerts as $a) {
        $sev = strtolower($a['severity'] ?? '');
        if ($sev === 'contraindicated' || $sev === 'critical') $hasContraindicated = true;
        elseif ($sev === 'major' || $sev === 'high') $hasMajor = true;
        elseif ($sev === 'moderate') $hasModerate = true;
    }

    if ($hasContraindicated) {
        $score = max(15, 28 - (count($alerts) * 4));
        return [
            'score'       => $score,
            'label'       => t_plain('score.danger.label'),
            'summary'     => t_plain('score.danger.summary'),
            'badge_class' => 'badge-critical',
            'color'       => '#dc2626',
            'bg_color'    => '#fef2f2',
            'text_color'  => '#991b1b',
            'severity'    => 'contraindicated'
        ];
    }

    if ($hasMajor) {
        $score = max(35, 52 - (count($alerts) * 4));
        return [
            'score'       => $score,
            'label'       => t_plain('score.serious.label'),
            'summary'     => t_plain('score.serious.summary'),
            'badge_class' => 'badge-warning',
            'color'       => '#ea580c',
            'bg_color'    => '#fff7ed',
            'text_color'  => '#9a3412',
            'severity'    => 'major'
        ];
    }

    if ($hasModerate) {
        $score = max(60, 78 - (count($alerts) * 4));
        return [
            'score'       => $score,
            'label'       => t_plain('score.caution.label'),
            'summary'     => t_plain('score.caution.summary'),
            'badge_class' => 'badge-info',
            'color'       => '#0284c7',
            'bg_color'    => '#f0f9ff',
            'text_color'  => '#0369a1',
            'severity'    => 'moderate'
        ];
    }

    $score = max(75, 90 - (count($alerts) * 3));
    return [
        'score'       => $score,
        'label'       => t_plain('score.note.label'),
        'summary'     => t_plain('score.note.summary'),
        'badge_class' => 'badge-neutral',
        'color'       => '#64748b',
        'bg_color'    => '#f8fafc',
        'text_color'  => '#334155',
        'severity'    => 'minor'
    ];
}

// Display flash message banner
function renderFlash(): string
{
    $flash = getFlash();
    if (!$flash) {
        return '';
    }
    $type = h($flash['type']);
    $msg = h($flash['message']);
    return '<div class="alert alert-' . $type . ' alert-dismissible" role="alert">
                <span>' . $msg . '</span>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
            </div>';
}

// Safe redirect
function redirect(string $url): void
{
    header("Location: " . $url);
    exit;
}

// Format date nicely
function formatDate(?string $date): string
{
    if (!$date || $date === '0000-00-00') {
        return t_plain('common.na');
    }
    try {
        $d = new DateTime($date);
        // e.g. "14 Mar 2025" / "14 మార్చి 2025" / "14 मार्च 2025"
        return $d->format('j') . ' ' . t_month((int)$d->format('n')) . ' ' . $d->format('Y');
    } catch (Exception $e) {
        return h($date);
    }
}

// Calculate age from DOB
function calculateAge(?string $dob): string
{
    if (!$dob) return t_plain('common.unknown');
    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        return t_plain('common.years', ['n' => $birthDate->diff($today)->y]);
    } catch (Exception $e) {
        return t_plain('common.unknown');
    }
}

// Determine Kidney / eGFR Stage according to KDIGO guidelines with plain-language healthcare descriptions
function getEgfrStage(?float $egfr): array
{
    if ($egfr === null) {
        return [
            'stage' => 'Unknown',
            'plain_name' => t_plain('kidney.unknown.name'),
            'description' => t_plain('kidney.unknown.desc'),
            'label' => 'Unrecorded (Renal risk cannot be ruled out)',
            'class' => 'badge-neutral',
            'severity' => 'unknown'
        ];
    }
    if ($egfr >= 90) {
        return [
            'stage' => 'G1',
            'plain_name' => t_plain('kidney.g1.name'),
            'description' => t_plain('kidney.g1.desc'),
            'label' => 'Normal / High (≥90 mL/min/1.73m²)',
            'class' => 'badge-success',
            'severity' => 'normal'
        ];
    }
    if ($egfr >= 60) {
        return [
            'stage' => 'G2',
            'plain_name' => t_plain('kidney.g2.name'),
            'description' => t_plain('kidney.g2.desc'),
            'label' => 'Mildly Decreased (60-89 mL/min/1.73m²)',
            'class' => 'badge-info',
            'severity' => 'mild'
        ];
    }
    if ($egfr >= 45) {
        return [
            'stage' => 'G3a',
            'plain_name' => t_plain('kidney.g3a.name'),
            'description' => t_plain('kidney.g3a.desc'),
            'label' => 'Mild-Moderate Impairment (45-59 mL/min/1.73m²)',
            'class' => 'badge-warning',
            'severity' => 'moderate'
        ];
    }
    if ($egfr >= 30) {
        return [
            'stage' => 'G3b',
            'plain_name' => t_plain('kidney.g3b.name'),
            'description' => t_plain('kidney.g3b.desc'),
            'label' => 'Moderate-Severe Impairment (30-44 mL/min/1.73m²)',
            'class' => 'badge-warning-dark',
            'severity' => 'moderate_high'
        ];
    }
    if ($egfr >= 15) {
        return [
            'stage' => 'G4',
            'plain_name' => t_plain('kidney.g4.name'),
            'description' => t_plain('kidney.g4.desc'),
            'label' => 'Severely Decreased (15-29 mL/min/1.73m²)',
            'class' => 'badge-danger',
            'severity' => 'severe'
        ];
    }
    return [
        'stage' => 'G5',
        'plain_name' => t_plain('kidney.g5.name'),
        'description' => t_plain('kidney.g5.desc'),
        'label' => 'Kidney Failure (<15 mL/min/1.73m²)',
        'class' => 'badge-critical',
        'severity' => 'critical'
    ];
}

// Render plain-language tooltip explaining clinical or technical terms
function explainTerm(string $term, string $explanation): string
{
    return '<span class="term-explainer" tabindex="0" title="' . h($explanation) . '">
                ' . h($term) . '
                <span class="info-bubble" aria-label="' . h($explanation) . '">ⓘ</span>
            </span>';
}


/* ---------------------------------------------------------------------------
 * Patient-friendly alert presentation (translated)
 * ------------------------------------------------------------------------- */

/** Map engine severities to 4 plain levels: danger / serious / caution / note. */
function severityLevel(string $severity): string
{
    return match (strtolower($severity)) {
        'contraindicated', 'critical' => 'danger',
        'major', 'high'               => 'serious',
        'moderate'                    => 'caution',
        default                       => 'note',
    };
}

function severityIcon(string $level): string
{
    return match ($level) {
        'danger'  => '🔴',
        'serious' => '🟠',
        'caution' => '🟡',
        default   => '🔵',
    };
}

/**
 * Turn an engine alert into translated, plain-language parts for patients.
 * Clinical wording from the reference tables (recommended_action) stays in English
 * as a note for the doctor; everything around it is translated.
 */
function patientAlertView(array $a, ?float $egfr = null): array
{
    $level = severityLevel((string)($a['severity'] ?? ''));
    $type  = (string)($a['alert_type'] ?? '');
    $drugs = (string)($a['medicines_involved'] ?? '');

    $known = in_array($type, ['interaction', 'duplicate_therapy', 'renal_dose', 'contraindication'], true);
    if ($known) {
        $title = t('alert.title.' . $type, ['drugs' => $drugs]);
        $why   = ($type === 'renal_dose' && $egfr === null)
            ? t('alert.why.renal_dose_unknown')
            : t('alert.why.' . $type, ['drugs' => $drugs, 'egfr' => $egfr !== null ? (string)round($egfr) : '']);
    } else {
        $title = h((string)($a['problem'] ?? ''));
        $why   = h((string)($a['why_flagged'] ?? ''));
    }

    $pct = null;
    if (!empty($a['confidence']) && preg_match('/(\d{1,3})\s*%/', (string)$a['confidence'], $m)) {
        $pct = (int)$m[1];
    }

    return [
        'level'       => $level,
        'icon'        => severityIcon($level),
        'badge'       => t('sev.' . $level . '.badge'),
        'todo'        => t('sev.' . $level . '.todo'),
        'title'       => $title,
        'why'         => $why,
        'drugs'       => $drugs,
        'doctor_note' => (string)($a['recommended_action'] ?? ''),
        'confidence'  => $pct !== null ? t('alert.confidence', ['pct' => $pct]) : t('alert.confidence_rule'),
        'limits'      => t('alert.limits'),
    ];
}

/** Render one alert card for patients (used on My Medicines and Check Medicine pages). */
function renderPatientAlert(array $a, ?float $egfr = null): string
{
    $v = patientAlertView($a, $egfr);
    ob_start(); ?>
    <article class="ez-alert ez-alert--<?= $v['level'] ?>">
        <div class="ez-alert__level">
            <span class="ez-alert__icon" aria-hidden="true"><?= $v['icon'] ?></span>
            <span><?= $v['badge'] ?></span>
        </div>
        <h4 class="ez-alert__title"><?= $v['title'] ?></h4>
        <div class="ez-alert__drugs">💊 <?= h($v['drugs']) ?></div>
        <div class="ez-alert__todo">
            <div class="ez-alert__todo-label"><?= t('alert.what_to_do') ?></div>
            <div><?= $v['todo'] ?></div>
        </div>
        <p class="ez-alert__why"><strong><?= t('alert.why_label') ?></strong> <?= $v['why'] ?></p>
        <button type="button" class="ez-speak" data-speak-target="closest:.ez-alert" aria-label="<?= t_attr('common.read_aloud') ?>">
            🔊 <?= t('common.read_aloud') ?>
        </button>
        <details class="ez-alert__more">
            <summary><?= t('alert.more_for_doctor') ?></summary>
            <?php if ($v['doctor_note'] !== ''): ?>
                <p class="ez-alert__doctor" lang="en"><strong><?= t('alert.doctor_note') ?></strong> <?= h($v['doctor_note']) ?></p>
            <?php endif; ?>
            <p class="ez-alert__meta"><strong><?= t('alert.confidence_label') ?></strong> <?= $v['confidence'] ?></p>
            <p class="ez-alert__meta"><strong><?= t('alert.limits_label') ?></strong> <?= $v['limits'] ?></p>
        </details>
    </article>
    <?php
    return (string)ob_get_clean();
}
