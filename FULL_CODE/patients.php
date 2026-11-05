<?php
/**
 * Patient Directory & Search
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/Patient.php';

// Patient records are for clinical staff only (checked here on the server, not just hidden in the menu).
requireLogin();
$citizen = currentUser();
if (!isAdmin()) {
    setFlash('info', 'Citizen accounts only have access to their personal medication profile.');
    redirect(BASE_URL . 'my_prescriptions.php');
}

$pageTitle = 'Patients';
$patientModel = new Patient();

// Search & Filter parameters
$search = trim($_GET['search'] ?? '');
$riskFilter = trim($_GET['risk'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$totalPatients = $patientModel->countAll($search, $riskFilter);
$patients = $patientModel->getAll($search, $riskFilter, $limit, $offset);
$totalPages = ceil($totalPatients / $limit);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Patients</h2>
        <p class="page-subtitle">Find and review patients with prescriptions from multiple doctors or clinics.</p>
    </div>
    <div>
        <?php if (isAdmin()): ?>
            <a href="<?= BASE_URL ?>patient_add.php" class="btn btn-primary btn-lg">
                <span>➕</span> Add patient
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Prominent Search & Filter Card -->
<div class="search-filter-card">
    <form method="GET" action="<?= BASE_URL ?>patients.php" style="display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end;">
        <div class="search-input-wrapper">
            <label class="form-label">Search patients</label>
            <span class="search-input-icon">🔍</span>
            <input type="text" name="search" class="form-control" placeholder="Search by patient name, record ID, or medical condition (e.g. Eleanor, Diabetes, Asthma)..." value="<?= h($search) ?>">
        </div>

        <div style="min-width: 260px;">
            <label class="form-label">Filter by clinical focus</label>
            <select name="risk" class="form-control">
                <option value="">All patient records</option>
                <option value="renal_impaired" <?= ($riskFilter === 'renal_impaired') ? 'selected' : '' ?>>Kidney function concerns (eGFR &lt; 60)</option>
                <option value="severe_renal" <?= ($riskFilter === 'severe_renal') ? 'selected' : '' ?>>Severely reduced kidney function (eGFR &lt; 30)</option>
                <option value="near_miss" <?= ($riskFilter === 'near_miss') ? 'selected' : '' ?>>Practice / test cases only</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">
                Search
            </button>
            <?php if (!empty($search) || !empty($riskFilter)): ?>
                <a href="<?= BASE_URL ?>patients.php" class="btn btn-secondary">
                    Clear filter
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Results Header -->
<div style="margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <span style="font-size: 14px; color: var(--text-secondary); font-weight: 500;">
        Showing <strong><?= count($patients) ?></strong> of <strong><?= number_format($totalPatients) ?></strong> patients
    </span>
    <?php if (!empty($riskFilter)): ?>
        <span class="badge badge-info">Active filter: <?= h(ucwords(str_replace('_', ' ', $riskFilter))) ?></span>
    <?php endif; ?>
</div>

<!-- Clean, Readable Patient List -->
<div class="patient-list">
    <?php if (empty($patients)): ?>
        <div class="card empty-state">
            <div class="empty-state-icon">🔍</div>
            <div class="empty-state-title">No matching patients found</div>
            <div class="empty-state-desc">
                We couldn't find any patient matching "<strong><?= h($search) ?></strong>". Try searching with a different name or clear the filter.
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?= BASE_URL ?>patients.php" class="btn btn-secondary">Clear search</a>
                <a href="<?= BASE_URL ?>patient_add.php" class="btn btn-primary">Add new patient</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($patients as $pt): 
            $egfrStage = getEgfrStage($pt['egfr'] !== null ? (float)$pt['egfr'] : null);
        ?>
            <div class="patient-list-item">
                <div class="patient-list-main">
                    <div class="patient-avatar-circle">
                        <?= strtoupper(substr($pt['full_name'], 0, 1)) ?>
                    </div>
                    <div class="patient-identity">
                        <a href="<?= BASE_URL ?>patient_view.php?id=<?= (int)$pt['id'] ?>" class="patient-name-link">
                            <?= h($pt['full_name']) ?>
                        </a>
                        <div class="patient-sub-meta">
                            Record ID: <strong><?= h($pt['patient_mrn']) ?></strong> &bull;
                            <?= calculateAge($pt['date_of_birth']) ?> &bull;
                            <?= ucfirst(h($pt['gender'])) ?>
                        </div>
                    </div>
                </div>

                <div class="patient-middle-details">
                    <!-- Kidney Function -->
                    <div class="detail-block" style="min-width: 180px;">
                        <span class="detail-block-label">Kidney function (eGFR)</span>
                        <div class="detail-block-value">
                            <span class="badge <?= $egfrStage['class'] ?>" title="<?= h($egfrStage['description'] ?? '') ?>">
                                <?= ($pt['egfr'] !== null) ? h((string)$pt['egfr']) . ' mL/min &bull; ' . h($egfrStage['plain_name'] ?? $egfrStage['stage']) : 'Not recorded' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Medical Conditions -->
                    <div class="detail-block" style="max-width: 240px; min-width: 180px;">
                        <span class="detail-block-label">Medical conditions</span>
                        <div class="detail-block-value" style="font-size: 13px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= h($pt['conditions'] ?? 'None recorded') ?>">
                            <?= !empty($pt['conditions']) ? h($pt['conditions']) : '<span style="color:var(--text-muted); font-weight:normal;">None recorded</span>' ?>
                        </div>
                    </div>

                    <!-- Care Providers -->
                    <div class="detail-block" style="min-width: 140px;">
                        <span class="detail-block-label">Doctors involved</span>
                        <div class="detail-block-value">
                            <span class="doctor-pill">
                                👨‍⚕️ <?= (int)$pt['distinct_doctors'] ?> Doctor<?= ((int)$pt['distinct_doctors'] !== 1) ? 's' : '' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Active Prescriptions -->
                    <div class="detail-block" style="min-width: 130px;">
                        <span class="detail-block-label">Active medicines</span>
                        <div class="detail-block-value">
                            <span class="badge badge-neutral">
                                💊 <?= (int)$pt['active_prescriptions'] ?> active
                            </span>
                        </div>
                    </div>

                    <!-- Test Case Indicator -->
                    <div class="detail-block">
                        <span class="detail-block-label">Case type</span>
                        <div class="detail-block-value">
                            <?php if (!empty($pt['is_near_miss_case'])): ?>
                                <span class="badge badge-warning" title="Synthetic test record with high-risk medication conflict for practice">🎯 Practice case</span>
                            <?php else: ?>
                                <span class="badge badge-neutral">Standard patient</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div>
                    <a href="<?= BASE_URL ?>patient_view.php?id=<?= (int)$pt['id'] ?>" class="btn btn-secondary btn-sm" style="font-weight: 600;">
                        View patient &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div style="margin-top: 24px; display: flex; justify-content: center; gap: 8px;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="<?= BASE_URL ?>patients.php?page=<?= $i ?>&search=<?= urlencode($search) ?>&risk=<?= urlencode($riskFilter) ?>" 
               class="btn btn-sm <?= ($i === $page) ? 'btn-primary' : 'btn-secondary' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
