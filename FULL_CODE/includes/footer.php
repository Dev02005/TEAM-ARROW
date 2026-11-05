<?php
/**
 * Footer Component
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);
?>
    </main>

    <footer class="app-footer">
        <div class="footer-container">
            <div>
                <strong><?= APP_NAME ?></strong> &bull; <?= t('footer.cds') ?>
                <p style="margin-top: 4px; color: var(--text-muted);">
                    ⚕️ <em><?= t('footer.disclaimer') ?></em>
                </p>
            </div>
        </div>
    </footer>

    <script>
        window.DOSEWISE_I18N = {
            speechLang: <?= json_encode(speech_lang()) ?>,
            stop: <?= json_encode(t_plain('common.stop_reading'), JSON_UNESCAPED_UNICODE) ?>,
            readAloud: <?= json_encode(t_plain('common.read_aloud'), JSON_UNESCAPED_UNICODE) ?>,
            noVoice: <?= json_encode(t_plain('common.no_voice'), JSON_UNESCAPED_UNICODE) ?>,
            textSizes: <?= json_encode([t_plain('text.normal'), t_plain('text.large'), t_plain('text.xlarge')], JSON_UNESCAPED_UNICODE) ?>,
            confirmDefault: <?= json_encode(t_plain('common.confirm'), JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <script src="<?= BASE_URL ?>public/js/app.js"></script>
</body>
</html>
