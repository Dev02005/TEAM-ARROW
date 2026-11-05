<?php
/**
 * Header Component with Citizen Authentication, Language Switcher & Safety Disclaimer
 * DoseWise - Medication Safety for Citizens
 *
 * Mobile-first for older users: large text, a fixed bottom menu with big buttons,
 * a language picker (English / Telugu / Hindi) and a text-size button.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$citizen = currentUser();
$lang = current_lang();
$showLangPicker = !lang_chosen();
?>
<!DOCTYPE html>
<html lang="<?= html_lang() ?>" data-speech-lang="<?= speech_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0c3558">
    <title><?= isset($pageTitle) ? h($pageTitle) . ' &mdash; ' . APP_NAME : APP_NAME . ' &mdash; ' . t('app.tagline') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Telugu:wght@400;600;700;800&family=Noto+Sans+Devanagari:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/elder.css">
    <script>
        // Apply the saved text size before the page paints (no jump).
        try { var s = localStorage.getItem('dosewise_text'); if (s) document.documentElement.setAttribute('data-text', s); } catch (e) {}
    </script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>💊</text></svg>">
</head>
<body class="lang-<?= $lang ?><?= ($citizen && isAdmin()) ? ' is-admin' : '' ?>">

    <a class="ez-skip" href="#main"><?= t('nav.skip') ?></a>

    <!-- MANDATORY CLINICAL SAFETY DISCLAIMER (Requirement Section 7 & 10) -->
    <aside class="clinical-disclaimer-bar" role="note" aria-label="<?= t_attr('disclaimer.aria') ?>">
        <span class="icon">⚕️</span>
        <span><strong><?= t('disclaimer.title') ?></strong> <?= t('disclaimer.short') ?></span>
    </aside>

    <!-- Navigation Header -->
    <header class="main-nav">
        <div class="nav-container">
            <a href="<?= BASE_URL ?>index.php" class="brand-wrapper">
                <div class="brand-logo">
                    <span style="font-size: 22px;">💊</span>
                </div>
                <div class="brand-text">
                    <h1 style="display: flex; align-items: center; gap: 8px;">
                        <span class="brand-name-highlight"><?= APP_NAME ?></span>
                        <span class="badge badge-info ez-hide-mobile" style="font-size: 11px; padding: 2px 8px; letter-spacing: 0.03em; font-weight: 700;">CareFriend</span>
                    </h1>
                </div>
            </a>

            <nav class="nav-links" aria-label="<?= t_attr('nav.main') ?>">
                <a href="<?= BASE_URL ?>index.php" class="nav-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">
                    <span>🏠</span> <?= t('nav.home') ?>
                </a>

                <a href="<?= BASE_URL ?>medicines.php" class="nav-link <?= ($currentPage === 'medicines.php') ? 'active' : '' ?>">
                    <span>📚</span> <?= t('nav.medicines_list') ?>
                </a>

                <?php if ($citizen && !isAdmin()): ?>
                    <a href="<?= BASE_URL ?>my_prescriptions.php" class="nav-link <?= ($currentPage === 'my_prescriptions.php') ? 'active' : '' ?>">
                        <span>💊</span> <?= t('nav.my_medicines') ?>
                    </a>
                    <a href="<?= BASE_URL ?>attach_prescription.php" class="btn btn-teal btn-sm" style="margin-left: 6px;">
                        <span>🔍</span> <?= t('nav.check') ?>
                    </a>
                <?php elseif (isAdmin()): ?>
                    <a href="<?= BASE_URL ?>patients.php" class="nav-link <?= ($currentPage === 'patients.php' || $currentPage === 'patient_view.php') ? 'active' : '' ?>">
                        <span>👥</span> <?= t('nav.patient_records') ?>
                    </a>
                    <a href="<?= BASE_URL ?>attach_prescription.php" class="nav-link <?= ($currentPage === 'attach_prescription.php') ? 'active' : '' ?>">
                        <span>🔍</span> <?= t('nav.check') ?>
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>attach_prescription.php" class="nav-link <?= ($currentPage === 'attach_prescription.php') ? 'active' : '' ?>">
                        <span>🔍</span> <?= t('nav.check') ?>
                    </a>
                <?php endif; ?>
            </nav>

            <div class="nav-actions ez-nav-actions">
                <!-- Language dropdown: always visible on desktop and phones -->
                <div class="lang-dropdown" style="position: relative; display: inline-block;">
                    <button type="button" class="ez-pill-btn lang-dropdown-toggle" id="langDropdownToggle" aria-haspopup="true" aria-expanded="false" title="<?= t_attr('lang.change') ?>">
                        <span aria-hidden="true">🌐</span> <span><?= h(SUPPORTED_LANGS[$lang]['native']) ?></span> <span style="font-size: 10px; margin-left: 2px;">▼</span>
                    </button>
                    <div class="lang-dropdown-menu" id="langDropdownMenu" style="display: none; position: absolute; right: 0; top: calc(100% + 6px); background: #ffffff; border: 1px solid #cbd5e1; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15), 0 8px 10px -6px rgba(0,0,0,0.1); min-width: 190px; z-index: 3000; padding: 6px 0; overflow: hidden;">
                        <?php foreach (SUPPORTED_LANGS as $code => $info): ?>
                            <a href="<?= h(lang_url($code)) ?>" class="lang-dropdown-item <?= $code === $lang ? 'is-active' : '' ?>" lang="<?= $code ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; font-size: 15px; font-weight: 700; color: #1e293b; text-decoration: none; transition: background 0.15s ease;">
                                <span style="font-family: <?= $code === 'te' ? "'Noto Sans Telugu', sans-serif" : ($code === 'hi' ? "'Noto Sans Devanagari', sans-serif" : 'inherit') ?>;">
                                    <?= h($info['native']) ?>
                                </span>
                                <?php if ($code === $lang): ?>
                                    <span style="color: #0284c7; font-weight: 800; font-size: 16px;">✓</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="button" class="ez-pill-btn" data-text-size aria-label="<?= t_attr('text.size_label') ?>" title="<?= t_attr('text.size_label') ?>">
                    <span aria-hidden="true" class="ez-aa">A<small>A</small></span>
                </button>

                <?php if ($citizen): ?>
                    <div class="ez-hide-mobile" style="display: flex; align-items: center; gap: 8px; background: <?= isAdmin() ? '#eff6ff' : '#f0fdf4' ?>; border: 1px solid <?= isAdmin() ? '#bfdbfe' : '#bbf7d0' ?>; padding: 5px 12px; border-radius: 9999px;">
                        <span style="font-size: 14px;"><?= isAdmin() ? '⚕️' : '👋' ?></span>
                        <span style="font-size: 13.5px; font-weight: 700; color: <?= isAdmin() ? '#1e40af' : '#166534' ?>;">
                            <?= h($citizen['full_name']) ?>
                            <?php if (isAdmin()): ?>
                                <span class="badge badge-info" style="font-size: 10px; margin-left: 4px;"><?= t('nav.staff') ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <a href="<?= BASE_URL ?>logout.php" class="btn btn-secondary btn-sm ez-hide-mobile">
                        <?= t('nav.sign_out') ?>
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>login.php" class="btn btn-primary ez-hide-mobile" style="font-size: 13.5px; padding: 8px 16px; font-weight: 700;">
                        <span>🔑</span> <?= t('nav.login') ?>
                    </a>
                    <a href="<?= BASE_URL ?>register.php" class="btn btn-secondary btn-sm ez-hide-mobile" style="font-size: 13px;">
                        <?= t('nav.sign_up') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- PHONE BOTTOM MENU: 4 big buttons with icon + word, always in reach of the thumb -->
    <nav class="ez-bottom-nav" aria-label="<?= t_attr('nav.main') ?>">
        <a href="<?= BASE_URL ?>index.php" class="<?= $currentPage === 'index.php' ? 'is-active' : '' ?>">
            <span class="ez-bottom-nav__icon">🏠</span><span><?= t('nav.home') ?></span>
        </a>
        <?php if (isAdmin()): ?>
            <a href="<?= BASE_URL ?>patients.php" class="<?= in_array($currentPage, ['patients.php', 'patient_view.php'], true) ? 'is-active' : '' ?>">
                <span class="ez-bottom-nav__icon">👥</span><span><?= t('nav.patients_short') ?></span>
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?><?= $citizen ? 'my_prescriptions.php' : 'login.php' ?>" class="<?= $currentPage === 'my_prescriptions.php' ? 'is-active' : '' ?>">
                <span class="ez-bottom-nav__icon">💊</span><span><?= t('nav.my_medicines') ?></span>
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>attach_prescription.php" class="ez-bottom-nav__main <?= $currentPage === 'attach_prescription.php' ? 'is-active' : '' ?>">
            <span class="ez-bottom-nav__icon">🔍</span><span><?= t('nav.check_short') ?></span>
        </a>
        <?php if ($citizen): ?>
            <a href="<?= BASE_URL ?>logout.php">
                <span class="ez-bottom-nav__icon">🚪</span><span><?= t('nav.sign_out') ?></span>
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>login.php" class="<?= in_array($currentPage, ['login.php', 'register.php'], true) ? 'is-active' : '' ?>">
                <span class="ez-bottom-nav__icon">🔑</span><span><?= t('nav.login_short') ?></span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- LANGUAGE PICKER (opens automatically on the first visit) -->
    <div class="ez-sheet" id="langSheet" role="dialog" aria-modal="true" aria-labelledby="langSheetTitle" <?= $showLangPicker ? 'data-auto-open' : '' ?> hidden>
        <div class="ez-sheet__box">
            <h2 id="langSheetTitle" class="ez-sheet__title">
                <span lang="te">భాషను ఎంచుకోండి</span><br>
                <span lang="hi">भाषा चुनें</span><br>
                <span lang="en">Choose your language</span>
            </h2>
            <div class="ez-lang-list">
                <?php foreach (SUPPORTED_LANGS as $code => $info): ?>
                    <a href="<?= h(lang_url($code)) ?>" class="ez-lang-btn <?= $code === $lang ? 'is-current' : '' ?>" lang="<?= $code ?>">
                        <span class="ez-lang-btn__name"><?= h($info['native']) ?></span>
                        <?php if ($code === $lang): ?><span class="ez-lang-btn__tick" aria-hidden="true">✓</span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <button type="button" class="ez-sheet__close" data-close-sheet><?= t('common.close') ?></button>
        </div>
    </div>

    <main class="app-container" id="main">
        <!-- Flash Notification Messages -->
        <?= renderFlash() ?>
