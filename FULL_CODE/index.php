<?php
/**
 * Citizen Homepage - DoseWise
 * Medication Safety Second Pair of Eyes for Individuals & Families
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle = t_plain('home.page_title');
$citizen = currentUser();

require_once __DIR__ . '/includes/header.php';
?>

<?php
// Example combinations for the "Try it" box. Text comes from lang/<code>.php; the
// level decides colour and icon. Scores match calculateSafetyScore() bands.
$presetDefs = [
    'war-asp'    => ['score' => 45, 'level' => 'serious', 'icon' => '💊'],
    'met-ckd'    => ['score' => 20, 'level' => 'danger',  'icon' => '🫘'],
    'lis-ibu'    => ['score' => 40, 'level' => 'serious', 'icon' => '💊'],
    'dup-statin' => ['score' => 35, 'level' => 'serious', 'icon' => '⚠️'],
    'pro-asthma' => ['score' => 15, 'level' => 'danger',  'icon' => '🫁'],
    'safe-check' => ['score' => 98, 'level' => 'clean',   'icon' => '✓'],
];
$presets = [];
foreach ($presetDefs as $key => $def) {
    $presets[$key] = $def + [
        'button'  => t_plain("preset.$key.button"),
        'badge'   => t_plain($def['level'] === 'clean' ? 'preset.clean_badge' : "sev.{$def['level']}.badge"),
        'title'   => t_plain("preset.$key.title"),
        'problem' => t_plain("preset.$key.problem"),
        'why'     => t_plain("preset.$key.why"),
        'action'  => t_plain("preset.$key.action"),
    ];
}
$first = $presets['war-asp'];
?>

<div class="ez-page">

    <!-- 1. WELCOME -->
    <section class="ez-hero">
        <p class="ez-hero__tag"><?= t('home.tag') ?></p>
        <h2 class="ez-hero__title"><?= t('home.hero_title') ?></h2>
        <p class="ez-hero__desc"><?= t('home.hero_desc') ?></p>

        <div class="ez-actions">
            <a href="<?= BASE_URL ?>attach_prescription.php" class="ez-big-btn ez-big-btn--primary">
                <span class="ez-big-btn__icon">🔍</span>
                <span><?= t('home.btn_check') ?><small><?= t('home.btn_check_sub') ?></small></span>
            </a>
            <?php if ($citizen): ?>
                <a href="<?= BASE_URL ?>my_prescriptions.php" class="ez-big-btn ez-big-btn--teal">
                    <span class="ez-big-btn__icon">💊</span>
                    <span><?= t('home.btn_my_meds') ?><small><?= t('home.btn_my_meds_sub') ?></small></span>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>login.php" class="ez-big-btn ez-big-btn--light">
                    <span class="ez-big-btn__icon">🔑</span>
                    <span><?= t('home.btn_login') ?><small><?= t('home.btn_login_sub') ?></small></span>
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>medicines.php" class="ez-big-btn ez-big-btn--light">
                <span class="ez-big-btn__icon">📚</span>
                <span><?= t('home.btn_list') ?><small><?= t('home.btn_list_sub') ?></small></span>
            </a>
        </div>
    </section>

    <!-- 2. HOW IT WORKS: 3 STEPS -->
    <section class="ez-section">
        <h3 class="ez-section__title"><?= t('home.how_title') ?></h3>
        <ol class="ez-steps">
            <li class="ez-step">
                <span class="ez-step__num">1</span>
                <div>
                    <h4><?= t('home.step1_title') ?></h4>
                    <p><?= t('home.step1_desc') ?></p>
                </div>
            </li>
            <li class="ez-step">
                <span class="ez-step__num">2</span>
                <div>
                    <h4><?= t('home.step2_title') ?></h4>
                    <p><?= t('home.step2_desc') ?></p>
                </div>
            </li>
            <li class="ez-step">
                <span class="ez-step__num">3</span>
                <div>
                    <h4><?= t('home.step3_title') ?></h4>
                    <p><?= t('home.step3_desc') ?></p>
                </div>
            </li>
        </ol>
    </section>

    <!-- 3. TRY IT: EXAMPLE COMBINATIONS -->
    <section class="ez-section">
        <h3 class="ez-section__title">⚡ <?= t('home.try_title') ?></h3>
        <p class="ez-section__desc"><?= t('home.try_desc') ?></p>

        <div class="ez-choice-list" role="group" aria-label="<?= t_attr('home.try_title') ?>">
            <?php foreach ($presets as $key => $p): ?>
                <button type="button" class="ez-choice <?= $key === 'war-asp' ? 'is-active' : '' ?>" data-preset="<?= $key ?>" aria-pressed="<?= $key === 'war-asp' ? 'true' : 'false' ?>">
                    <span aria-hidden="true"><?= $p['icon'] ?></span> <?= h($p['button']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div id="quickCheckResult" class="ez-result ez-result--<?= $first['level'] ?>" aria-live="polite">
            <div class="ez-result__head">
                <div>
                    <span id="qcSeverityBadge" class="ez-level-tag"><?= severityIcon($first['level']) ?> <?= h($first['badge']) ?></span>
                    <h4 id="qcTitle" class="ez-result__title"><?= h($first['title']) ?></h4>
                </div>
                <div class="ez-score" aria-label="<?= t_attr('common.safety_score') ?>">
                    <span id="qcScore" class="ez-score__num"><?= $first['score'] ?>%</span>
                    <span class="ez-score__label"><?= t('common.safety_score') ?></span>
                </div>
            </div>

            <div class="ez-result__block">
                <div class="ez-result__label"><?= t('home.q_problem') ?></div>
                <div id="qcProblem" class="ez-result__strong"><?= h($first['problem']) ?></div>
            </div>
            <div class="ez-result__block">
                <div class="ez-result__label"><?= t('home.q_why') ?></div>
                <div id="qcWhy"><?= h($first['why']) ?></div>
            </div>
            <div class="ez-alert__todo">
                <div class="ez-alert__todo-label">📞 <?= t('home.q_ask') ?></div>
                <div id="qcAction"><?= h($first['action']) ?></div>
            </div>

            <div class="ez-result__foot">
                <button type="button" class="ez-speak" data-speak-target="#quickCheckResult">🔊 <?= t('common.read_aloud') ?></button>
                <a href="<?= BASE_URL ?>attach_prescription.php" class="btn btn-primary"><?= t('home.try_own') ?> &rarr;</a>
            </div>
        </div>
        <p class="ez-note"><?= t('home.example_note') ?></p>
    </section>

    <!-- 4. MEDICINE LIST -->
    <section class="ez-section ez-section--teal">
        <h3 class="ez-section__title">📚 <?= t('home.list_title') ?></h3>
        <p class="ez-section__desc"><?= t('home.list_desc') ?></p>
        <a href="<?= BASE_URL ?>medicines.php" class="ez-big-btn ez-big-btn--teal ez-big-btn--inline">
            <span class="ez-big-btn__icon">📚</span><span><?= t('home.btn_list') ?></span>
        </a>
    </section>
</div>

<script>
const citizenPresets = <?= json_encode($presets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const levelIcons = { danger: '🔴', serious: '🟠', caution: '🟡', note: '🔵', clean: '🟢' };

document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('.ez-choice[data-preset]');
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            buttons.forEach(function (b) { b.classList.remove('is-active'); b.setAttribute('aria-pressed', 'false'); });
            this.classList.add('is-active');
            this.setAttribute('aria-pressed', 'true');

            const data = citizenPresets[this.getAttribute('data-preset')];
            if (!data) return;
            document.getElementById('qcTitle').textContent = data.title;
            document.getElementById('qcProblem').textContent = data.problem;
            document.getElementById('qcWhy').textContent = data.why;
            document.getElementById('qcAction').textContent = data.action;
            document.getElementById('qcScore').textContent = data.score + '%';
            document.getElementById('qcSeverityBadge').textContent = (levelIcons[data.level] || '') + ' ' + data.badge;
            document.getElementById('quickCheckResult').className = 'ez-result ez-result--' + data.level;
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
