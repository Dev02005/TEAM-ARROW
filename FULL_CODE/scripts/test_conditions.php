<?php
/**
 * Test that the condition patterns now match the missed diagnosis strings.
 */
require __DIR__ . '/../pipeline/SafetyEngineH4.php';
$engine = new SafetyEngineH4(__DIR__ . '/../data');

// Use reflection to read the private $conditions
$ref = new ReflectionClass($engine);
$prop = $ref->getProperty('conditions');
$prop->setAccessible(true);
$conditions = $prop->getValue($engine);

$testDiagnoses = [
    'LVEF 30%'                      => 'heart_failure',
    'DCMP'                          => 'heart_failure',
    'cardiac failure'               => 'heart_failure',
    'CHF'                           => 'heart_failure',
    'HF'                            => 'heart_failure',
    'lv dysfunction'                => 'heart_failure',
    'heart falure'                  => 'heart_failure',
    'duodenal ulcer'                => 'peptic_ulcer',
    'ulcer stomach'                 => 'peptic_ulcer',
    'acid peptic disease'           => 'peptic_ulcer',
    'gastric ulcer (2023)'          => 'peptic_ulcer',
    'bleeding DU (2022)'            => 'peptic_ulcer',
    'PUD'                           => 'peptic_ulcer',
    'gastritis'                     => 'peptic_ulcer',
    'melwna 2023 - PUD'             => 'peptic_ulcer',
    'cirrhosis'                     => 'chronic_liver_disease',
    'alcoholic liver disease'       => 'chronic_liver_disease',
    'chronic hepatitis - cirrhotic' => 'chronic_liver_disease',
    'cirrrhosis'                    => 'chronic_liver_disease',
    'reactive airway dsiease'       => 'asthma',
    'B. asthma'                     => 'asthma',
    'asthma since childhood'        => 'asthma',
    'paralysis agitans'             => 'parkinson_disease',
    'Graves'                        => 'hyperthyroidism',
    'gouty arthritis'               => 'gout',
    'hyperuricemia'                 => 'gout',
    'dysspepsia'                    => 'peptic_ulcer',
    'dysppesia'                     => 'peptic_ulcer',
    'DKD'                           => null,  // diabetic kidney disease - not a condition we track
    'PCOS'                          => null,
];

echo "=== CONDITION MATCHING TEST ===\n";
$pass = 0; $fail = 0;
foreach ($testDiagnoses as $diagText => $expectedCid) {
    $matched = [];
    foreach ($conditions as $cid => $cdata) {
        if (preg_match($cdata['pattern'], $diagText)) {
            $matched[] = $cid;
        }
    }
    
    $ok = ($expectedCid === null && empty($matched)) || 
          in_array($expectedCid, $matched, true);
    
    $status = $ok ? 'PASS' : 'FAIL';
    if (!$ok) $fail++; else $pass++;
    
    $matchStr = empty($matched) ? '(none)' : implode(', ', $matched);
    $expect = $expectedCid ?? '(none)';
    echo "  [$status] \"$diagText\" => matched: $matchStr (expected: $expect)\n";
}

echo "\n$pass PASS, $fail FAIL out of " . count($testDiagnoses) . " tests\n";

