<?php
require_once __DIR__ . '/../pipeline/Resolver.php';

$resolver = new Resolver(__DIR__ . '/../data');
$h = fopen(__DIR__ . '/../data/train_resolution.csv', 'r');
$hdr = fgetcsv($h);

$total = 0;
$correct = 0;
$misTotal = 0;
$misCorrect = 0;
$nonDrugTotal = 0;
$nonDrugCorrect = 0;

$mismatches = [];

while (($r = fgetcsv($h)) !== false) {
    $tok = $r[1];
    $trueGen = strtolower(trim($r[2]));
    $kind = $r[3];

    $predGens = $resolver->resolve($tok);
    sort($predGens);
    $predGen = implode(';', $predGens);

    $isOk = ($predGen === $trueGen);
    $total++;
    if ($isOk) $correct++;

    if (str_starts_with($kind, 'misspelled')) {
        $misTotal++;
        if ($isOk) $misCorrect++;
    }

    if ($kind === 'non_drug') {
        $nonDrugTotal++;
        if ($isOk) $nonDrugCorrect++;
    }

    if (!$isOk && count($mismatches) < 20) {
        $mismatches[] = [
            'token' => $tok,
            'true' => $trueGen,
            'pred' => $predGen,
            'kind' => $kind
        ];
    }
}
fclose($h);

echo "Total tokens scored: {$total}\n";
echo "Accuracy: " . number_format($correct / $total * 100, 2) . "% ({$correct}/{$total})\n";
echo "Misspelled Accuracy: " . number_format($misCorrect / $misTotal * 100, 2) . "% ({$misCorrect}/{$misTotal})\n";
echo "Non-Drug Accuracy: " . number_format($nonDrugCorrect / $nonDrugTotal * 100, 2) . "% ({$nonDrugCorrect}/{$nonDrugTotal})\n\n";

if (!empty($mismatches)) {
    echo "Sample mismatches:\n";
    foreach ($mismatches as $m) {
        echo "  [{$m['kind']}] '{$m['token']}' -> True: '{$m['true']}' | Pred: '{$m['pred']}'\n";
    }
}

