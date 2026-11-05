<?php
/**
 * Tests for the multi-language layer, patient-facing alert wording and sign-up validation.
 * Runs without a database:  php tests/I18nAndPatientViewTest.php   (exit code 1 on any failure)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../models/User.php';

final class I18nAndPatientViewTest
{
    private int $passed = 0;
    private int $failed = 0;

    private function check(string $name, bool $condition, string $detail = ''): void
    {
        $condition ? $this->passed++ : $this->failed++;
        echo ($condition ? ' [PASS] ' : ' [FAIL] ') . $name . ($detail !== '' ? " -- {$detail}" : '') . PHP_EOL;
    }

    public function run(): int
    {
        $this->languageFilesMatchEnglish();
        $this->translationBehaviour();
        $this->severityAndAlertWording();
        $this->neverCallsAListSafe();
        $this->registrationValidation();

        echo PHP_EOL . "SUMMARY: {$this->passed} Passed, {$this->failed} Failed." . PHP_EOL;
        return $this->failed === 0 ? 0 : 1;
    }

    /** Every Telugu/Hindi string exists, keeps the same {placeholders} and the same <strong> tags. */
    private function languageFilesMatchEnglish(): void
    {
        $en = lang_table('en');
        foreach (['te', 'hi'] as $code) {
            $table = lang_table($code);
            $missing = array_diff(array_keys($en), array_keys($table));
            $extra = array_diff(array_keys($table), array_keys($en));
            $this->check("{$code}: has every English key", $missing === [], implode(', ', array_slice($missing, 0, 5)));
            $this->check("{$code}: has no unknown keys", $extra === [], implode(', ', array_slice($extra, 0, 5)));

            $bad = [];
            foreach ($en as $key => $text) {
                if (!isset($table[$key])) {
                    continue;
                }
                preg_match_all('/\{\w+\}/', $text, $a);
                preg_match_all('/\{\w+\}/', $table[$key], $b);
                sort($a[0]);
                sort($b[0]);
                if ($a[0] !== $b[0] || substr_count($text, '<strong>') !== substr_count($table[$key], '<strong>')) {
                    $bad[] = $key;
                }
            }
            $this->check("{$code}: placeholders and tags match English", $bad === [], implode(', ', $bad));
            $this->check("{$code}: 12 month names", count(explode(',', $table['date.months'])) === 12);
        }
    }

    private function translationBehaviour(): void
    {
        $html = t('flash.welcome', ['name' => '<script>x</script>'], 'en');
        $this->check('t() escapes placeholder values', str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>'), $html);
        $this->check('t() fills placeholders in Telugu', t('common.years', ['n' => 72], 'te') === '72 సంవత్సరాలు');
        $this->check('Unknown key returns the key itself', t_raw('no.such.key') === 'no.such.key');
        $this->check('t_attr() strips tags for attributes', t_attr('sev.danger.todo', [], 'en') === htmlspecialchars(strip_tags(t_raw('sev.danger.todo', 'en')), ENT_QUOTES, 'UTF-8'));
        $this->check('Month name in Hindi', t_raw('date.months', 'hi') !== '' && explode(',', t_raw('date.months', 'hi'))[2] === 'मार्च');
    }

    private function severityAndAlertWording(): void
    {
        $this->check('contraindicated -> danger', severityLevel('contraindicated') === 'danger');
        $this->check('major -> serious', severityLevel('MAJOR') === 'serious');
        $this->check('moderate -> caution', severityLevel('moderate') === 'caution');
        $this->check('unknown severity -> note (never hidden)', severityLevel('???') === 'note');

        $renal = ['severity' => 'major', 'alert_type' => 'renal_dose', 'medicines_involved' => 'Metformin',
                  'recommended_action' => 'Avoid if eGFR < 30', 'confidence' => '0.95'];
        $withEgfr = patientAlertView($renal, 28.4);
        $this->check('Renal alert mentions the rounded eGFR', str_contains($withEgfr['why'], 'eGFR 28'), strip_tags($withEgfr['why']));
        $this->check('Renal alert names the medicine', str_contains($withEgfr['why'], 'Metformin'));
        $withoutEgfr = patientAlertView($renal, null);
        $this->check('Missing kidney test uses the "not recorded" wording', $withoutEgfr['why'] === t('alert.why.renal_dose_unknown'));
        $this->check('Serious alert tells the patient to keep taking medicines', str_contains(strip_tags($withEgfr['todo']), 'Keep taking'));

        $pct = patientAlertView(['severity' => 'minor', 'alert_type' => 'interaction', 'medicines_involved' => 'A & B', 'confidence' => '95% (rule)']);
        $this->check('Confidence percentage is shown', str_contains($pct['confidence'], '95%'));

        $xss = patientAlertView(['severity' => 'major', 'alert_type' => 'interaction', 'medicines_involved' => '<img src=x>']);
        $this->check('Medicine names are escaped in alert text', !str_contains($xss['why'], '<img'));
    }

    /** Problem rule: never claim a medicine list is "safe"; only "no problem found in what was checked". */
    private function neverCallsAListSafe(): void
    {
        $clean = calculateSafetyScore([]);
        $this->check('Clean result label does not say "safe"', stripos($clean['label'], 'safe') === false, $clean['label']);
        $this->check('Clean summary warns the list may be incomplete', str_contains($clean['summary'], 'may not be complete'));
        $danger = calculateSafetyScore([['severity' => 'contraindicated']]);
        $this->check('Contraindicated scores 30% or less', $danger['score'] <= 30, (string)$danger['score']);
    }

    private function registrationValidation(): void
    {
        $valid = ['full_name' => 'Lakshmi Devi', 'email' => 'lakshmi@example.com', 'password' => 'secret12',
                  'date_of_birth' => '1950-04-01', 'gender' => 'female', 'conditions' => 'BP', 'allergies' => ''];
        $this->check('Valid sign-up has no errors', User::validateRegistration($valid) === []);
        $this->check('Empty date of birth is allowed', User::validateRegistration(['date_of_birth' => ''] + $valid) === []);

        $cases = [
            'future date of birth' => [['date_of_birth' => date('Y-m-d', strtotime('+1 day'))], 'date_of_birth'],
            'impossible date'      => [['date_of_birth' => '1950-02-30'], 'date_of_birth'],
            'bad email'            => [['email' => 'not-an-email'], 'email'],
            'short password'       => [['password' => '12345'], 'password'],
            'unknown gender'       => [['gender' => 'robot'], 'gender'],
            'blank name'           => [['full_name' => '   '], 'full_name'],
            'very long allergies'  => [['allergies' => str_repeat('a', 501)], 'allergies'],
        ];
        foreach ($cases as $label => [$override, $field]) {
            $errors = User::validateRegistration($override + $valid);
            $this->check("Rejects {$label}", isset($errors[$field]), json_encode($errors));
        }
    }
}

exit((new I18nAndPatientViewTest())->run());
