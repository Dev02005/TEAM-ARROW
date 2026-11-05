<?php
/**
 * Citizen Registration - DoseWise
 * Medication Safety for Everyday Citizens
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/User.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'my_prescriptions.php');
}

$pageTitle = t_plain('register.title');
$errors = [];
$formData = [
    'full_name'     => '',
    'email'         => '',
    'password'      => '',
    'date_of_birth' => '',
    'gender'        => 'female',
    'conditions'    => '',
    'allergies'     => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        die(h(t_plain('common.security_failed')));
    }

    $allowed = array_keys($formData);
    $formData = array_merge($formData, array_intersect_key(array_map(fn($v) => is_string($v) ? $v : '', $_POST), array_flip($allowed)));
    $errors = array_map(fn($key) => t_plain($key), User::validateRegistration($formData));

    $users = new User();
    if (empty($errors['email']) && $users->findByEmail($formData['email'])) {
        $errors['email'] = t_plain('register.err_exists');
    }

    if (empty($errors)) {
        try {
            loginUser($users->registerCitizen($formData));
            setFlash('success', t_plain('flash.registered', ['name' => trim($formData['full_name'])]));
            redirect(BASE_URL . 'attach_prescription.php');
        } catch (PDOException $e) {
            error_log('Registration failed: ' . $e->getMessage());
            $errors['general'] = t_plain('register.err_general');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="ez-page ez-narrow">
    <div class="ez-page-head ez-center">
        <h2 class="ez-page-title"><?= t('register.heading') ?></h2>
        <p class="ez-page-sub"><?= t('register.sub') ?></p>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger" role="alert"><?= h($errors['general']) ?></div>
    <?php endif; ?>

    <section class="ez-section ez-form-card">
        <form method="POST" action="<?= BASE_URL ?>register.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="regName"><?= t('register.name') ?> <span class="required">*</span></label>
                <input type="text" id="regName" name="full_name" maxlength="<?= User::MAX_NAME ?>" class="form-control ez-input-xl" autocomplete="name" autocapitalize="words" value="<?= h($formData['full_name']) ?>" required>
                <?php if (isset($errors['full_name'])): ?>
                    <div class="form-error"><?= h($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="regEmail"><?= t('login.email') ?> <span class="required">*</span></label>
                <input type="email" id="regEmail" name="email" maxlength="<?= User::MAX_EMAIL ?>" class="form-control ez-input-xl" inputmode="email" autocomplete="email" placeholder="name@example.com" value="<?= h($formData['email']) ?>" required>
                <?php if (isset($errors['email'])): ?>
                    <div class="form-error"><?= h($errors['email']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="regPassword"><?= t('register.password') ?> <span class="required">*</span></label>
                <input type="password" id="regPassword" name="password" class="form-control ez-input-xl" autocomplete="new-password" placeholder="<?= t_attr('register.password_ph') ?>" minlength="6" required>
                <label class="ez-check ez-check--small">
                    <input type="checkbox" onclick="document.getElementById('regPassword').type = this.checked ? 'text' : 'password'">
                    <span><?= t('login.show_password') ?></span>
                </label>
                <?php if (isset($errors['password'])): ?>
                    <div class="form-error"><?= h($errors['password']) ?></div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label class="form-label" for="regDob"><?= t('register.dob') ?></label>
                    <input type="date" id="regDob" max="<?= date('Y-m-d') ?>" name="date_of_birth" class="form-control" value="<?= h($formData['date_of_birth']) ?>">
                    <?php if (isset($errors['date_of_birth'])): ?>
                        <div class="form-error"><?= h($errors['date_of_birth']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label" for="regGender"><?= t('register.gender') ?></label>
                    <select id="regGender" name="gender" class="form-control">
                        <option value="female" <?= ($formData['gender'] === 'female') ? 'selected' : '' ?>><?= t('register.female') ?></option>
                        <option value="male" <?= ($formData['gender'] === 'male') ? 'selected' : '' ?>><?= t('register.male') ?></option>
                        <option value="other" <?= ($formData['gender'] === 'other') ? 'selected' : '' ?>><?= t('register.other') ?></option>
                    </select>
                </div>
            </div>

            <fieldset class="ez-fieldset">
                <legend>🛡️ <?= t('register.health_title') ?></legend>
                <div class="form-group">
                    <label class="form-label" for="regConditions"><?= t('register.conditions') ?></label>
                    <input type="text" id="regConditions" name="conditions" class="form-control" placeholder="<?= t_attr('register.conditions_ph') ?>" value="<?= h($formData['conditions']) ?>" maxlength="<?= User::MAX_HEALTH_TEXT ?>">
                    <?php if (isset($errors['conditions'])): ?>
                        <div class="form-error"><?= h($errors['conditions']) ?></div>
                    <?php endif; ?>
                    <div class="form-hint"><?= t('register.conditions_hint') ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="regAllergies"><?= t('register.allergies') ?></label>
                    <input type="text" id="regAllergies" name="allergies" class="form-control" placeholder="<?= t_attr('register.allergies_ph') ?>" value="<?= h($formData['allergies']) ?>" maxlength="<?= User::MAX_HEALTH_TEXT ?>">
                    <?php if (isset($errors['allergies'])): ?>
                        <div class="form-error"><?= h($errors['allergies']) ?></div>
                    <?php endif; ?>
                </div>
            </fieldset>

            <button type="submit" class="ez-big-btn ez-big-btn--primary ez-big-btn--submit">
                <span class="ez-big-btn__icon">✨</span><span><?= t('register.submit') ?></span>
            </button>
        </form>

        <p class="ez-center ez-form-foot">
            <?= t('register.have_account') ?>
            <a href="<?= BASE_URL ?>login.php"><?= t('register.sign_in') ?> &rarr;</a>
        </p>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
