<?php
/**
 * User Login - DoseWise
 * Medication Safety for Everyday Citizens
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/User.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'my_prescriptions.php');
}

$pageTitle = t_plain('login.title');
$errors = [];
$email = '';

/** Sign the user in and send them to the right start page. */
function completeLogin(array $user): never
{
    loginUser($user);
    if ($user['role'] === 'admin') {
        setFlash('success', t_plain('flash.welcome_admin', ['name' => $user['full_name']]));
        redirect(BASE_URL . 'patients.php');
    }
    setFlash('success', t_plain('flash.welcome', ['name' => $user['full_name']]));
    redirect(BASE_URL . 'my_prescriptions.php');
}

// One-click demo accounts shown on this page (only these may sign in without a password)
const DEMO_LOGINS = ['eleanor@example.com', 'robert@example.com', 'grace@example.com', 'admin@dosewise.com'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        die(h(t_plain('common.security_failed')));
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $demoQuick = (string)($_POST['demo_user'] ?? '');
    $users = new User();

    if ($demoQuick !== '') {
        $user = in_array($demoQuick, DEMO_LOGINS, true) ? $users->findByEmail($demoQuick) : null;
        if ($user) {
            completeLogin($user);
        }
        $errors['general'] = t_plain('login.err_demo');
    } else {
        if ($email === '') {
            $errors['email'] = t_plain('login.err_email');
        }
        if ($password === '') {
            $errors['password'] = t_plain('login.err_password');
        }
        if (empty($errors)) {
            $user = $users->verifyLogin($email, $password);
            if ($user) {
                completeLogin($user);
            }
            $errors['general'] = t_plain('login.err_invalid');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="ez-page ez-narrow">
    <div class="ez-page-head ez-center">
        <div class="ez-logo">💊</div>
        <h2 class="ez-page-title"><?= t('login.heading') ?></h2>
        <p class="ez-page-sub"><?= t('login.sub') ?></p>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger" role="alert"><?= h($errors['general']) ?></div>
    <?php endif; ?>

    <!-- Standard Login Form -->
    <section class="ez-section ez-form-card">
        <form method="POST" action="<?= BASE_URL ?>login.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="loginEmail"><?= t('login.email') ?></label>
                <input type="email" id="loginEmail" name="email" class="form-control ez-input-xl" inputmode="email" autocomplete="username" placeholder="name@example.com" value="<?= h($email) ?>" required>
                <?php if (isset($errors['email'])): ?>
                    <div class="form-error"><?= h($errors['email']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="loginPassword"><?= t('login.password') ?></label>
                <input type="password" id="loginPassword" name="password" class="form-control ez-input-xl" autocomplete="current-password" placeholder="<?= t_attr('login.password_ph') ?>" required>
                <label class="ez-check ez-check--small">
                    <input type="checkbox" onclick="document.getElementById('loginPassword').type = this.checked ? 'text' : 'password'">
                    <span><?= t('login.show_password') ?></span>
                </label>
                <?php if (isset($errors['password'])): ?>
                    <div class="form-error"><?= h($errors['password']) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="ez-big-btn ez-big-btn--primary ez-big-btn--submit">
                <span class="ez-big-btn__icon">🔑</span><span><?= t('login.submit') ?></span>
            </button>
        </form>

        <p class="ez-center ez-form-foot">
            <?= t('login.new') ?>
            <a href="<?= BASE_URL ?>register.php"><?= t('login.create') ?> &rarr;</a>
        </p>
    </section>

    <!-- 1-Click Demo Sign-in (for hackathon review) -->
    <section class="ez-section ez-demo-box">
        <h3 class="ez-section__title">⚡ <?= t('login.demo_title') ?></h3>
        <p class="ez-section__desc"><?= t('login.demo_desc') ?> <code>citizen123</code></p>
        <form method="POST" action="<?= BASE_URL ?>login.php" class="ez-demo-list">
            <?= csrf_field() ?>
            <button type="submit" name="demo_user" value="eleanor@example.com" class="ez-demo-btn">
                <span>👤 <strong>Eleanor Vance</strong><small><?= t('login.demo_eleanor') ?></small></span>
                <span class="badge badge-warning"><?= t('login.has_alerts') ?></span>
            </button>
            <button type="submit" name="demo_user" value="robert@example.com" class="ez-demo-btn">
                <span>👤 <strong>Robert Chen</strong><small><?= t('login.demo_robert') ?></small></span>
                <span class="badge badge-warning"><?= t('login.has_alerts') ?></span>
            </button>
            <button type="submit" name="demo_user" value="grace@example.com" class="ez-demo-btn">
                <span>👤 <strong>Grace Hopper</strong><small><?= t('login.demo_grace') ?></small></span>
                <span class="badge badge-success"><?= t('login.no_alerts') ?></span>
            </button>
            <button type="submit" name="demo_user" value="admin@dosewise.com" class="ez-demo-btn">
                <span>⚕️ <strong>Dr. Sarah Jenkins</strong><small><?= t('login.demo_admin') ?></small></span>
                <span class="badge badge-info"><?= t('login.all_patients') ?></span>
            </button>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
