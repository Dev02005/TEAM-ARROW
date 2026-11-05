<?php
/**
 * High-Accuracy Clinical Medication Token Resolver
 * Resolves messy handwritten nurse-transcribed entries into canonical generics.
 * Handles single brands, combination brands, dosage forms, misspellings,
 * truncations, abbreviations, and non-drug entries.
 */

declare(strict_types=1);

class Resolver
{
    private array $lookup = [];
    private array $fuzzyCandidates = [];
    private array $nonDrugPatterns = [];
    private array $formWords = [];
    private array $stopWords = [];

    public function __construct(string $dataDir)
    {
        $this->formWords = array_flip([
            'tab', 'tab.', 't.', 't', 'cap', 'cap.', 'c.', 'inj', 'inj.',
            'syp', 'syp.', 'syr', 'inh', 'inh.', 'mdi', 'rotacap', 'sachet',
            'sach.', 'patch', 'susp', 'drops', 'powder', 'neb', 'respule',
            'gel', 'ointment', 'cream', 'lotion', 'syrup', 'tablet', 'capsule',
            'injection', 'inhaler', 'respules', 'x', 'x.'
        ]);

        $this->stopWords = array_flip([
            'od', 'bd', 'tds', 'hs', 'sos', 'prn', 'bid', 'tid', 'qid',
            'once', 'twice', 'thrice', 'at', 'if', 'after', 'before',
            'weekly', 'every', 'daily', 'puffs', 'puff', 'empty', 'stomach',
            'bf', 'bbf', 'food', 'meals', 'night', 'morning', 'noon', 'evening',
            'bedtime', 'pain', 'fever', 'stat', 'cont.', 'continue', 'month',
            'days', 'opd', 'phc', 'iv', 'im', 'sc', 'po', 'mg', 'mcg', 'gm',
            'g', 'iu', 'ml', 'tsp', 'tbsp', 'u', '-', '--', 'x5', 'x7', 'x10',
            'x14', 'x30', '1-0-1', '0-0-1', '1-0-0', '1-1-1', '0-1-0', '0-0-0',
            '(cont.)', 'cont', 'sos.', 'prn.', 'od.', 'bd.', 'hs.'
        ]);

        $this->nonDrugPatterns = [
            '/^\s*bp\s*charting/i',
            '/^\s*blood\s*pressure/i',
            '/^\s*glucometer/i',
            '/^\s*strips/i',
            '/^\s*physiotherapy/i',
            '/^\s*salt\s*restriction/i',
            '/^\s*diet\b/i',
            '/^\s*lifestyle/i',
            '/^\s*review\s*with/i',
            '/^\s*review\s*after/i',
            '/^\s*follow\s*up/i',
            '/^\s*nebulisation\s*sos/i',
            '/^\s*steam\s*inhalation/i',
            '/^\s*exercise/i',
            '/^\s*walking/i',
            '/^\s*weight\s*loss/i',
            '/^\s*sugar\s*charting/i',
            '/^\s*rbs\b/i',
            '/^\s*cbg\b/i',
            '/^\s*cbc\b/i',
            '/^\s*kft\b/i',
            '/^\s*lft\b/i',
            '/^\s*ecg\b/i',
            '/^\s*x-ray\b/i',
            '/^\s*ultrasound\b/i',
            '/^\s*usg\b/i'
        ];

        $this->loadDictionaries($dataDir);
    }

    public function cleanKey(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($s));
    }

    private function loadDictionaries(string $dataDir): void
    {
        // 1. drug_reference.csv
        $refPath = $dataDir . '/drug_reference.csv';
        if (file_exists($refPath)) {
            $h = fopen($refPath, 'r');
            $header = fgetcsv($h);
            $genIdx = array_search('generic_name', $header, true);
            $brandIdx = array_search('common_brands', $header, true);
            while (($row = fgetcsv($h)) !== false) {
                $gen = strtolower(trim($row[$genIdx] ?? ''));
                if ($gen === '') continue;
                $k = $this->cleanKey($gen);
                $this->lookup[$k] = [$gen];
                $this->fuzzyCandidates[$k] = [$gen];

                $brands = explode(';', $row[$brandIdx] ?? '');
                foreach ($brands as $b) {
                    $b = trim($b);
                    if ($b === '') continue;
                    $bk = $this->cleanKey($b);
                    $this->lookup[$bk] = [$gen];
                    $this->fuzzyCandidates[$bk] = [$gen];
                }
            }
            fclose($h);
        }

        // 2. combination_brands.csv
        $combosPath = $dataDir . '/combination_brands.csv';
        if (file_exists($combosPath)) {
            $h = fopen($combosPath, 'r');
            $header = fgetcsv($h);
            $brandIdx = array_search('brand_name', $header, true);
            $strIdx = array_search('strength_label', $header, true);
            $compIdx = array_search('generic_components', $header, true);
            while (($row = fgetcsv($h)) !== false) {
                $brand = trim($row[$brandIdx] ?? '');
                $str = trim($row[$strIdx] ?? '');
                $comps = array_filter(array_map('trim', explode(';', strtolower($row[$compIdx] ?? ''))));
                sort($comps);
                if (empty($comps)) continue;

                $bk = $this->cleanKey($brand);
                $this->lookup[$bk] = $comps;
                $this->fuzzyCandidates[$bk] = $comps;

                if ($str !== '') {
                    $bkWithStr = $this->cleanKey($brand . ' ' . $str);
                    $this->lookup[$bkWithStr] = $comps;
                    $this->fuzzyCandidates[$bkWithStr] = $comps;
                }
            }
            fclose($h);
        }

        // 3. Comprehensive Indian Clinical Abbreviations, Synonyms, and Misspellings
        $manualMap = [
            'pcm' => ['paracetamol'],
            'pcm650' => ['paracetamol'],
            'pcm500' => ['paracetamol'],
            'calcium' => ['calcium carbonate'],
            'd3' => ['cholecalciferol'],
            'vitd3' => ['cholecalciferol'],
            'vitamind3' => ['cholecalciferol'],
            'calcirol' => ['cholecalciferol'],
            'shelcal' => ['calcium carbonate', 'cholecalciferol'],
            'shelal' => ['calcium carbonate', 'cholecalciferol'],
            'ismn' => ['isosorbide mononitrate'],
            'isdn' => ['isosorbide dinitrate'],
            'sodabicarb' => ['sodium bicarbonate'],
            'sodamint' => ['sodium bicarbonate'],
            'sodami' => ['sodium bicarbonate'],
            'sodam' => ['sodium bicarbonate'],
            'soddiumbicarbonate' => ['sodium bicarbonate'],
            'soyumbicarbonate' => ['sodium bicarbonate'],
            'lthyroxine' => ['levothyroxine'],
            'frusemide' => ['furosemide'],
            'mecobalamin' => ['methylcobalamin'],
            'amoxyclav' => ['amoxicillin', 'clavulanic acid'],
            'amoxiclav' => ['amoxicillin', 'clavulanic acid'],
            'augmentin' => ['amoxicillin', 'clavulanic acid'],
            'croken' => ['paracetamol'],
            'dolo' => ['paracetamol'],
            'crocin' => ['paracetamol'],
            'pacimol' => ['paracetamol'],
            'aspyrin' => ['aspirin'],
            'aspir' => ['aspirin'],
            'glimeprid' => ['glimepiride'],
            'pantop' => ['pantoprazole'],
            'pntp' => ['pantoprazole'],
            'pentoprezolee' => ['pantoprazole'],
            'rozav' => ['rosuvastatin'],
            'paxi' => ['paroxetine'],
            'prazopr' => ['prazosin'],
            'minipes' => ['prazosin'],
            'minipress' => ['prazosin'],
            'dyna' => ['diclofenac'],
            'brufen' => ['ibuprofen'],
            'combiflam' => ['ibuprofen', 'paracetamol'],
            'vymada' => ['sacubitril', 'valsartan'],
            'janumet' => ['metformin', 'sitagliptin'],
            'diamicron' => ['gliclazide'],
            'foracort' => ['budesonide', 'formoterol'],
            'seroflo' => ['fluticasone', 'salmeterol'],
            'ascoril' => ['ambroxol', 'guaifenesin', 'levosalbutamol'],
            'ascorills' => ['ambroxol', 'guaifenesin', 'levosalbutamol'],
            'syndopaplus' => ['carbidopa', 'levodopa'],
            'syndopa' => ['carbidopa', 'levodopa'],
            'glyciphage' => ['metformin'],
            'glycomet' => ['metformin'],
            'glycometgp' => ['glimepiride', 'metformin'],
            'glycometgp1' => ['glimepiride', 'metformin'],
            'glycometgp2' => ['glimepiride', 'metformin'],
            'glycametgp' => ['glimepiride', 'metformin'],
            'glycametgp1' => ['glimepiride', 'metformin'],
            'glekomet' => ['metformin'],
            'glekometgp' => ['glimepiride', 'metformin'],
            'glekometgp1' => ['glimepiride', 'metformin'],
            'glyccometgp' => ['glimepiride', 'metformin'],
            'glyccometgp1' => ['glimepiride', 'metformin'],
            'zerodolp' => ['aceclofenac', 'paracetamol'],
            'zerodlp' => ['aceclofenac', 'paracetamol'],
            'zerodolsp' => ['aceclofenac', 'paracetamol', 'serratiopeptidase'],
            'zerodolesp' => ['aceclofenac', 'paracetamol', 'serratiopeptidase'],
            'zerodolmr' => ['aceclofenac', 'tizanidine'],
            'meftalspas' => ['dicyclomine', 'mefenamic acid'],
            'mftalspas' => ['dicyclomine', 'mefenamic acid'],
            'meftalforte' => ['mefenamic acid', 'paracetamol'],
            'telmah' => ['hydrochlorothiazide', 'telmisartan'],
            'telmikindh' => ['hydrochlorothiazide', 'telmisartan'],
            'telmaam' => ['amlodipine', 'telmisartan'],
            'tlmaam' => ['amlodipine', 'telmisartan'],
            'anlopres' => ['amlodipine', 'atenolol'],
            'anlopresat' => ['amlodipine', 'atenolol'],
            'amloprsat' => ['amlodipine', 'atenolol'],
            'amlopresat' => ['amlodipine', 'atenolol'],
            'amlovasat' => ['amlodipine', 'atenolol'],
            'losarh' => ['hydrochlorothiazide', 'losartan'],
            'cilacar' => ['cilnidipine'],
            'donep' => ['donepezil'],
            'topamac' => ['topiramate'],
            'forxiga' => ['dapagliflozin'],
            'lasix' => ['furosemide'],
            'atocor' => ['atorvastatin'],
            'atokr' => ['atorvastatin'],
            'asthalin' => ['salbutamol'],
            'losacar' => ['losartan'],
            'naprosyn' => ['naproxen'],
            'dytorplus' => ['spironolactone', 'torsemide'],
            'dytorplus10' => ['spironolactone', 'torsemide'],
            'dytorplus20' => ['spironolactone', 'torsemide'],
            'veltamplus' => ['dutasteride', 'tamsulosin'],
            'veltmplus' => ['dutasteride', 'tamsulosin'],
            'ureax' => ['tamsulosin'],
            'drotnm' => ['drotaverine', 'mefenamic acid'],
            'glemestarm' => ['glimepiride', 'metformin'],
            'glemestarm2' => ['glimepiride', 'metformin'],
            'insulin3070' => ['insulin isophane', 'insulin regular'],
            'insulin5050' => ['insulin isophane', 'insulin regular'],
            'huminsulin3070' => ['insulin isophane', 'insulin regular'],
            'huminsulin' => ['insulin isophane', 'insulin regular'],
            'mytard30' => ['insulin isophane', 'insulin regular'],
            'mixtard' => ['insulin isophane', 'insulin regular'],
            'monteleukastlc' => ['levocetirizine', 'montelukast'],
            'montelukastlc' => ['levocetirizine', 'montelukast'],
            'mantwirlc' => ['levocetirizine', 'montelukast'],
            'montairlc' => ['levocetirizine', 'montelukast'],
            'gausmet' => ['metformin', 'vildagliptin'],
            'galvusmet' => ['metformin', 'vildagliptin'],
            'sepfrna' => ['sulfamethoxazole', 'trimethoprim'],
            'septrin' => ['sulfamethoxazole', 'trimethoprim'],
            'septran' => ['sulfamethoxazole', 'trimethoprim'],
            'nmesuid' => ['nimesulide'],
            'ecosprinav' => ['aspirin', 'atorvastatin'],
            'ekosprinav' => ['aspirin', 'atorvastatin'],
            'ecosprnav' => ['aspirin', 'atorvastatin'],
            'nexytoplus' => ['clonazepam', 'escitalopram'],
            'nexitoplus' => ['clonazepam', 'escitalopram'],
            'clopitaba' => ['aspirin', 'clopidogrel'],
            'dyrto' => ['torsemide'],
            'dpltt' => ['clopidogrel'],
            'cralok' => ['carvedilol'],
            'mnrelukat' => ['montelukast'],
            'tranexamcacid' => ['tranexamic acid'],
            'befnotiamin' => ['benfotiamine'],
            'norrflokstz' => ['norfloxacin', 'tinidazole'],
            'orferxt' => ['ferrous ascorbate', 'folic acid'],
            'oroferxt' => ['ferrous ascorbate', 'folic acid'],
            'folycacid' => ['folic acid'],
            'potklor' => ['potassium chloride'],
            'tamsksin' => ['tamsulosin']
        ];

        foreach ($manualMap as $k => $generics) {
            sort($generics);
            $this->lookup[$k] = $generics;
            $this->fuzzyCandidates[$k] = $generics;
        }
    }

    public function resolve(string $rawToken): array
    {
        $token = trim($rawToken);
        if ($token === '') {
            return [];
        }

        // 1. Non-drug check
        foreach ($this->nonDrugPatterns as $pat) {
            if (preg_match($pat, $token)) {
                return [];
            }
        }

        // Special case: insulin mixtures
        if (preg_match('/(huminsulin|mixtard|mytard|insulin)\s*(30\/70|50\/50|30)/i', $token)) {
            return ['insulin isophane', 'insulin regular'];
        }

        // 2. Check for combination with '+' or '/' (e.g. 'budesonide/formoterol', 'calcium + vit D3')
        if (str_contains($token, '+')) {
            $parts = explode('+', $token);
            $merged = [];
            foreach ($parts as $p) {
                $subGens = $this->resolveSingle($p);
                foreach ($subGens as $sg) {
                    $merged[$sg] = true;
                }
            }
            if (!empty($merged)) {
                $out = array_keys($merged);
                sort($out);
                return $out;
            }
        }

        if (str_contains($token, '/') && !preg_match('/\b[0-9]+\/[0-9]+\b/', $token)) {
            $parts = explode('/', $token);
            $merged = [];
            foreach ($parts as $p) {
                $subGens = $this->resolveSingle($p);
                foreach ($subGens as $sg) {
                    $merged[$sg] = true;
                }
            }
            if (!empty($merged)) {
                $out = array_keys($merged);
                sort($out);
                return $out;
            }
        }

        return $this->resolveSingle($token);
    }

    private function resolveSingle(string $token): array
    {
        // Check whole token key first (keeps combo names like amlopres-at intact)
        $wholeK = $this->cleanKey($token);
        if (isset($this->lookup[$wholeK])) {
            return $this->lookup[$wholeK];
        }

        $cleanToken = str_replace(['(', ')', ',', '/', '-', '+'], ' ', $token);
        $words = preg_split('/\s+/', $cleanToken);

        // Strip leading form words
        while (!empty($words) && isset($this->formWords[strtolower($words[0])])) {
            array_shift($words);
        }

        if (empty($words)) {
            return [];
        }

        // Filter words: stop when hitting frequency
        $nameWords = [];
        foreach ($words as $w) {
            $lw = strtolower($w);
            if (isset($this->stopWords[$lw])) {
                break;
            }
            if (preg_match('/^[0-9]+(\.[0-9]+)?(mg|mcg|gm|g|ml|iu)?$/i', $lw)) {
                if (!empty($nameWords)) {
                    break;
                }
            }
            if (preg_match('/^[0-9]-[0-9]-[0-9]/', $lw)) {
                break;
            }
            $nameWords[] = $w;
        }

        if (empty($nameWords)) {
            $nameWords = $words;
        }

        // 3. Exact matching: longest candidate phrase first
        $n = count($nameWords);
        for ($len = $n; $len >= 1; $len--) {
            $candidateStr = implode(' ', array_slice($nameWords, 0, $len));
            $k = $this->cleanKey($candidateStr);
            if (isset($this->lookup[$k])) {
                return $this->lookup[$k];
            }
        }

        // 4. Prefix & Fuzzy matching
        $firstWord = $nameWords[0] ?? '';
        $fwK = $this->cleanKey($firstWord);
        if (isset($this->lookup[$fwK])) {
            return $this->lookup[$fwK];
        }

        if (strlen($fwK) >= 4) {
            $bestMatch = null;
            $bestDist = 999;

            foreach ($this->fuzzyCandidates as $dictKey => $generics) {
                if (str_starts_with($dictKey, $fwK) || (strlen($dictKey) >= 4 && str_starts_with($fwK, $dictKey))) {
                    return $generics;
                }

                $lenDiff = abs(strlen($dictKey) - strlen($fwK));
                if ($lenDiff <= 2) {
                    $dist = levenshtein($fwK, $dictKey);
                    $maxAllowed = (strlen($fwK) <= 5) ? 1 : 2;
                    if ($dist <= $maxAllowed && $dist < $bestDist) {
                        $bestDist = $dist;
                        $bestMatch = $generics;
                    }
                }
            }

            if ($bestMatch !== null) {
                return $bestMatch;
            }
        }

        return [];
    }
}
