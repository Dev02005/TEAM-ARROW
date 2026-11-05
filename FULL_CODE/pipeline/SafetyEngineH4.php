<?php
/**
 * Official H4 Medication Safety Decision Support Engine
 * Strictly follows the authoritative reference tables:
 *   1. interactions.csv
 *   2. duplicate_therapy_groups.csv & drug_reference.csv
 *   3. renal_rules.csv
 *   4. drug_disease_contraindications.csv & conditions.csv
 */

declare(strict_types=1);

require_once __DIR__ . '/Resolver.php';

class SafetyEngineH4
{
    private Resolver $resolver;
    private array $interactions = [];       // (drug_a, drug_b) -> [severity, action]
    private array $renalRules = [];         // generic -> [[egfr_below, severity, action], ...]
    private array $duplicateGroups = [];    // dup_group -> [name, severity, action, members_set]
    private array $drugToDupGroup = [];     // generic -> dup_group
    private array $contraindications = [];  // generic -> [[condition_id, cond_name, severity, action, reason], ...]
    private array $conditions = [];         // cond_id -> [name, kind, terms_regex]
    private array $drugRef = [];            // generic -> [pregnancy_cat, max_dose, dup_group]

    public function __construct(string $dataDir)
    {
        $this->resolver = new Resolver($dataDir);
        $this->loadAuthoritativeTables($dataDir);
    }

    public function getResolver(): Resolver
    {
        return $this->resolver;
    }

    private function loadAuthoritativeTables(string $dataDir): void
    {
        // 1. drug_reference.csv
        $refPath = $dataDir . '/drug_reference.csv';
        if (file_exists($refPath)) {
            $h = fopen($refPath, 'r');
            $header = fgetcsv($h);
            $genIdx = array_search('generic_name', $header, true);
            $pregIdx = array_search('pregnancy_category', $header, true);
            $dupIdx = array_search('duplicate_group', $header, true);
            $doseIdx = array_search('max_daily_dose_mg', $header, true);

            while (($row = fgetcsv($h)) !== false) {
                $gen = strtolower(trim($row[$genIdx] ?? ''));
                if ($gen === '') continue;
                $dup = trim($row[$dupIdx] ?? '');
                $this->drugRef[$gen] = [
                    'pregnancy_category' => trim($row[$pregIdx] ?? 'N'),
                    'duplicate_group' => $dup !== '' ? $dup : null,
                    'max_dose' => ($row[$doseIdx] ?? '') !== '' ? (float)$row[$doseIdx] : null
                ];
                if ($dup !== '') {
                    $this->drugToDupGroup[$gen] = $dup;
                }
            }
            fclose($h);
        }

        // 2. interactions.csv
        $ixPath = $dataDir . '/interactions.csv';
        if (file_exists($ixPath)) {
            $h = fopen($ixPath, 'r');
            $header = fgetcsv($h);
            $aIdx = array_search('drug_a', $header, true);
            $bIdx = array_search('drug_b', $header, true);
            $sevIdx = array_search('severity', $header, true);
            $actIdx = array_search('recommended_action', $header, true);

            while (($row = fgetcsv($h)) !== false) {
                $a = strtolower(trim($row[$aIdx] ?? ''));
                $b = strtolower(trim($row[$bIdx] ?? ''));
                if ($a === '' || $b === '') continue;
                if ($a > $b) {
                    $tmp = $a; $a = $b; $b = $tmp;
                }
                $this->interactions["{$a}|{$b}"] = [
                    'severity' => strtolower(trim($row[$sevIdx] ?? 'moderate')),
                    'action' => trim($row[$actIdx] ?? '')
                ];
            }
            fclose($h);
        }

        // 3. renal_rules.csv
        $rrPath = $dataDir . '/renal_rules.csv';
        if (file_exists($rrPath)) {
            $h = fopen($rrPath, 'r');
            $header = fgetcsv($h);
            $genIdx = array_search('generic_name', $header, true);
            $egfrIdx = array_search('egfr_below', $header, true);
            $sevIdx = array_search('severity', $header, true);
            $actIdx = array_search('recommended_action', $header, true);

            while (($row = fgetcsv($h)) !== false) {
                $gen = strtolower(trim($row[$genIdx] ?? ''));
                if ($gen === '') continue;
                $this->renalRules[$gen][] = [
                    'egfr_below' => (int)$row[$egfrIdx],
                    'severity' => strtolower(trim($row[$sevIdx] ?? 'moderate')),
                    'action' => trim($row[$actIdx] ?? '')
                ];
            }
            fclose($h);

            foreach ($this->renalRules as &$tiers) {
                usort($tiers, fn($t1, $t2) => $t1['egfr_below'] <=> $t2['egfr_below']);
            }
        }

        // 4. duplicate_therapy_groups.csv
        $dupPath = $dataDir . '/duplicate_therapy_groups.csv';
        if (file_exists($dupPath)) {
            $h = fopen($dupPath, 'r');
            $header = fgetcsv($h);
            $grpIdx = array_search('duplicate_group', $header, true);
            $nameIdx = array_search('group_name', $header, true);
            $sevIdx = array_search('severity', $header, true);
            $actIdx = array_search('recommended_action', $header, true);
            $memIdx = array_search('member_generics', $header, true);

            while (($row = fgetcsv($h)) !== false) {
                $grp = trim($row[$grpIdx] ?? '');
                if ($grp === '') continue;
                $members = array_filter(array_map('trim', explode(';', strtolower($row[$memIdx] ?? ''))));
                $memSet = array_flip($members);

                $this->duplicateGroups[$grp] = [
                    'name' => trim($row[$nameIdx] ?? ''),
                    'severity' => strtolower(trim($row[$sevIdx] ?? 'moderate')),
                    'action' => trim($row[$actIdx] ?? ''),
                    'members' => $memSet
                ];

                foreach ($members as $m) {
                    $this->drugToDupGroup[$m] = $grp;
                }
            }
            fclose($h);
        }

        // 5. drug_disease_contraindications.csv
        $ddcPath = $dataDir . '/drug_disease_contraindications.csv';
        if (file_exists($ddcPath)) {
            $h = fopen($ddcPath, 'r');
            $header = fgetcsv($h);
            $condIdx = array_search('condition_id', $header, true);
            $nameIdx = array_search('condition_name', $header, true);
            $genIdx = array_search('generic_name', $header, true);
            $sevIdx = array_search('severity', $header, true);
            $actIdx = array_search('recommended_action', $header, true);
            $rsnIdx = array_search('reason', $header, true);

            while (($row = fgetcsv($h)) !== false) {
                $gen = strtolower(trim($row[$genIdx] ?? ''));
                if ($gen === '') continue;
                $this->contraindications[$gen][] = [
                    'condition_id' => trim($row[$condIdx] ?? ''),
                    'condition_name' => trim($row[$nameIdx] ?? ''),
                    'severity' => strtolower(trim($row[$sevIdx] ?? 'major')),
                    'action' => trim($row[$actIdx] ?? ''),
                    'reason' => trim($row[$rsnIdx] ?? '')
                ];
            }
            fclose($h);
        }

        // 6. conditions.csv with clinical terms enrichment
        $condPath = $dataDir . '/conditions.csv';
        if (file_exists($condPath)) {
            $h = fopen($condPath, 'r');
            $header = fgetcsv($h);
            $idIdx = array_search('condition_id', $header, true);
            $nameIdx = array_search('condition_name', $header, true);
            $kindIdx = array_search('kind', $header, true);
            $termIdx = array_search('example_terms', $header, true);

            $clinicalSynonyms = [
                'heart_failure' => ['heart failure', 'chf', 'hfref', 'hfpef', 'ccf', 'lvef\s*[0-9]+%?', 'lv\s*dysfunction', 'dilated\s*cardiomyopathy', 'dcm'],
                'peptic_ulcer' => ['pud', 'h\/o\s*ugi\s*bleed', 'ugi\s*bleed', 'gi\s*bleed', 'peptic\s*ulcer', 'gastric\s*ulcer', 'duodenal\s*ulcer', 'melena', 'hematemesis', 'ulcer\s*bleed', 'previous\s*gi\s*bleed'],
                'chronic_liver_disease' => ['cld', 'cirrhosis', 'liver\s*cirrhosis', 'portal\s*hypertension', 'ascites', 'hepatic\s*encephalopathy', 'end\s*stage\s*liver'],
                'penicillin_allergy' => ['penicillin', 'pcn', 'amoxicillin', 'ampicillin', 'augmentin'],
                'sulfonamide_allergy' => ['sulfa', 'sulfonamide', 'sulphonamide', 'bactrim', 'septrin', 'septran', 'cotrimoxazole'],
                'nsaid_hypersensitivity' => ['aspirin\s*allergy', 'nsaid\s*allergy', 'ibuprofen\s*allergy', 'diclofenac\s*allergy', 'nsaid\s*hypersensitivity', 'aspirin'],
                'gout' => ['gout', 'hyperuricemia', 'podagra'],
                'asthma' => ['asthma', 'bronchial\s*asthma', 'ba'],
                'copd' => ['copd', 'emphysema', 'chronic\s*bronchitis'],
                'angle_closure_glaucoma' => ['glaucoma', 'narrow\s*angle', 'closed\s*angle', 'aacg'],
                'myasthenia_gravis' => ['myasthenia', 'mg'],
                'g6pd_deficiency' => ['g6pd', 'g6pd\s*def']
            ];

            while (($row = fgetcsv($h)) !== false) {
                $cid = trim($row[$idIdx] ?? '');
                if ($cid === '') continue;
                $cname = trim($row[$nameIdx] ?? '');
                $terms = array_filter(array_map('trim', explode(';', $row[$termIdx] ?? '')));
                $terms[] = $cname;
                $terms[] = str_replace('_', ' ', $cid);

                if (isset($clinicalSynonyms[$cid])) {
                    foreach ($clinicalSynonyms[$cid] as $cs) {
                        $terms[] = $cs;
                    }
                }

                $escaped = array_map(fn($t) => preg_quote($t, '/'), array_unique($terms));
                $pattern = '/\b(' . implode('|', $escaped) . ')\b/i';

                $this->conditions[$cid] = [
                    'name' => $cname,
                    'kind' => trim($row[$kindIdx] ?? 'diagnosis'),
                    'pattern' => $pattern
                ];
            }
            fclose($h);
        }
    }

    public function evaluatePatient(
        string $patientId,
        string $medicationList,
        ?int $egfr,
        string $diagnoses,
        string $allergies,
        string $pregnant
    ): array {
        $rawTokens = preg_split('/[;\n]+/', $medicationList);
        $activeEntries = [];
        $allUniqueGenerics = [];
        $genericEntryOccurrences = [];

        $entryIdx = 0;
        foreach ($rawTokens as $tok) {
            $tok = trim($tok);
            if ($tok === '') continue;

            $gens = $this->resolver->resolve($tok);
            if (empty($gens)) continue;

            // Check if explicitly marked as stopped / discontinued
            if (preg_match('/\b(stopped|stop taking|discontinued|withheld)\b/i', $tok)) {
                continue;
            }

            $activeEntries[] = [
                'token' => $tok,
                'generics' => $gens,
                'idx' => $entryIdx
            ];

            foreach ($gens as $g) {
                $allUniqueGenerics[$g] = true;
                $genericEntryOccurrences[$g][] = $entryIdx;
            }
            $entryIdx++;
        }

        $genericsList = array_keys($allUniqueGenerics);
        sort($genericsList);

        $alerts = [];
        $seenKeys = [];

        $addAlert = function(string $type, string $severity, array $drugs, string $action, float $confidence = 0.95) use ($patientId, &$alerts, &$seenKeys) {
            sort($drugs);
            $drugsStr = implode(';', $drugs);
            $key = "{$patientId}|{$type}|{$drugsStr}";
            if (isset($seenKeys[$key])) return;
            $seenKeys[$key] = true;

            $alerts[] = [
                'patient_id' => $patientId,
                'issue_type' => $type,
                'severity' => $severity,
                'drugs_involved' => $drugsStr,
                'suggested_action' => $action ?: 'Review therapy and monitor patient.',
                'confidence' => $confidence
            ];
        };

        // 1. DRUG-DRUG INTERACTIONS
        $n = count($genericsList);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $genericsList[$i];
                $b = $genericsList[$j];
                $pairKey = ($a < $b) ? "{$a}|{$b}" : "{$b}|{$a}";
                if (isset($this->interactions[$pairKey])) {
                    $hit = $this->interactions[$pairKey];
                    $addAlert('interaction', $hit['severity'], [$a, $b], $hit['action']);
                }
            }
        }

        // 2. DUPLICATE THERAPY
        // Case A: Same generic appearing across 2+ distinct written entries
        foreach ($genericEntryOccurrences as $gen => $entries) {
            $distinctEntries = count(array_unique($entries));
            if ($distinctEntries >= 2) {
                if ($gen === 'paracetamol') {
                    $sev = 'major';
                    $act = 'Avoid double paracetamol: risk of hepatotoxicity. Consolidate into single product.';
                } else {
                    $grp = $this->drugToDupGroup[$gen] ?? null;
                    if ($grp && isset($this->duplicateGroups[$grp])) {
                        $sev = $this->duplicateGroups[$grp]['severity'];
                        $act = $this->duplicateGroups[$grp]['action'];
                    } else {
                        $sev = 'moderate';
                        $act = "Duplicate prescription of " . ucfirst($gen) . ": discontinue unintended duplicate.";
                    }
                }
                $addAlert('duplicate_therapy', $sev, [$gen], $act);
            }
        }

        // Case B: Two different generics belonging to the same duplicate group across different entries
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $genericsList[$i];
                $b = $genericsList[$j];
                $grpA = $this->drugToDupGroup[$a] ?? null;
                $grpB = $this->drugToDupGroup[$b] ?? null;

                if ($grpA !== null && $grpA === $grpB && isset($this->duplicateGroups[$grpA])) {
                    $entriesA = $genericEntryOccurrences[$a] ?? [];
                    $entriesB = $genericEntryOccurrences[$b] ?? [];

                    $hasSeparateEntry = false;
                    foreach ($entriesA as $eA) {
                        foreach ($entriesB as $eB) {
                            if ($eA !== $eB) {
                                $hasSeparateEntry = true;
                                break 2;
                            }
                        }
                    }

                    if ($hasSeparateEntry) {
                        $grpInfo = $this->duplicateGroups[$grpA];
                        $addAlert('duplicate_therapy', $grpInfo['severity'], [$a, $b], $grpInfo['action']);
                    }
                }
            }
        }

        // 3. RENAL DOSE RISKS
        // If eGFR is missing, NO renal issue is labelled.
        if ($egfr !== null) {
            foreach ($genericsList as $gen) {
                if (isset($this->renalRules[$gen])) {
                    $applicableTiers = [];
                    foreach ($this->renalRules[$gen] as $tier) {
                        if ($egfr < $tier['egfr_below']) {
                            $applicableTiers[] = $tier;
                        }
                    }

                    if (!empty($applicableTiers)) {
                        usort($applicableTiers, fn($t1, $t2) => $t1['egfr_below'] <=> $t2['egfr_below']);
                        $mostRestrictive = $applicableTiers[0];

                        $addAlert(
                            'renal_dose',
                            $mostRestrictive['severity'],
                            [$gen],
                            $mostRestrictive['action']
                        );
                    }
                }
            }
        }

        // 4. DRUG-DISEASE / ALLERGY / PREGNANCY CONTRAINDICATIONS
        $patientConditions = $this->parsePatientConditions($diagnoses);
        $patientAllergies = $this->parsePatientAllergies($allergies);
        $isPregnant = (strtoupper(trim($pregnant)) === 'Y');

        $sevRank = ['contraindicated' => 4, 'major' => 3, 'moderate' => 2, 'minor' => 1];

        foreach ($genericsList as $gen) {
            $candidateContras = [];

            if (isset($this->contraindications[$gen])) {
                foreach ($this->contraindications[$gen] as $c) {
                    $cid = $c['condition_id'];

                    if ($cid === 'pregnancy') {
                        if ($isPregnant) {
                            $candidateContras[] = $c;
                        }
                    } elseif (isset($patientConditions[$cid])) {
                        $candidateContras[] = $c;
                    } elseif (isset($patientAllergies[$cid])) {
                        $candidateContras[] = $c;
                    }
                }
            }

            if ($isPregnant && isset($this->drugRef[$gen])) {
                $pCat = $this->drugRef[$gen]['pregnancy_category'];
                if ($pCat === 'X') {
                    $candidateContras[] = [
                        'severity' => 'contraindicated',
                        'action' => 'Avoid in pregnancy; severe teratogenic risk. Stop immediately.',
                        'reason' => 'FDA Category X teratogen.'
                    ];
                } elseif ($pCat === 'D') {
                    $candidateContras[] = [
                        'severity' => 'major',
                        'action' => 'Avoid in pregnancy; positive evidence of human fetal risk. Use safe alternative.',
                        'reason' => 'FDA Category D fetal risk.'
                    ];
                }
            }

            if (!empty($candidateContras)) {
                usort($candidateContras, function($a, $b) use ($sevRank) {
                    $rA = $sevRank[$a['severity']] ?? 0;
                    $rB = $sevRank[$b['severity']] ?? 0;
                    return $rB <=> $rA;
                });
                $topContra = $candidateContras[0];
                $addAlert('contraindication', $topContra['severity'], [$gen], $topContra['action']);
            }
        }

        return $alerts;
    }

    private function parsePatientConditions(string $diagText): array
    {
        $activeConds = [];
        if (trim($diagText) === '') return [];

        $clauses = preg_split('/[;,]+/', $diagText);
        foreach ($clauses as $clause) {
            $clause = trim($clause);
            if ($clause === '') continue;

            if (preg_match('/\b(no\s*h\/o|no\s*history|ruled\s*out|denies|negative\s*for|no\b)\b/i', $clause)) {
                continue;
            }

            foreach ($this->conditions as $cid => $info) {
                if ($info['kind'] === 'diagnosis' && preg_match($info['pattern'], $clause)) {
                    $activeConds[$cid] = true;
                }
            }
        }
        return $activeConds;
    }

    private function parsePatientAllergies(string $allergyText): array
    {
        $activeAllergies = [];
        $t = strtolower(trim($allergyText));
        if ($t === '' || $t === 'none' || $t === 'nkda' || $t === 'nil' || str_contains($t, 'no known')) {
            return [];
        }

        foreach ($this->conditions as $cid => $info) {
            if ($info['kind'] === 'allergy' && preg_match($info['pattern'], $t)) {
                $activeAllergies[$cid] = true;
            }
        }
        return $activeAllergies;
    }
}
