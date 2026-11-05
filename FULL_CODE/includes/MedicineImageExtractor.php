<?php
/**
 * Medicine Image & OCR Text Extractor
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../pipeline/Resolver.php';

class MedicineImageExtractor
{
    private PDO $pdo;
    private Resolver $resolver;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->resolver = new Resolver(__DIR__ . '/../data');
    }

    private function cleanMedicineName(string $name): string
    {
        $name = trim(preg_replace('/\b(?:1-0-1|0-1-0|0-0-1|1-0-0|1-1-1|od|bd|bid|tds|tid|qid|sos|prn|daily|stat)\b/i', '', $name));
        $name = trim(preg_replace('/\s+/', ' ', $name));
        return $name;
    }

    public function extractFromText(string $rawText, ?string $fileName = null): array
    {
        $text = trim($rawText);
        $dosage = $this->extractDosage($text);
        $frequency = $this->extractFrequency($text);

        // 1. Try to find known medicines/brands in the text
        $matched = $this->findKnownMedicine($text);
        if ($matched) {
            $displayName = $this->cleanMedicineName($matched['name']);
            if ($dosage && !preg_match('/\b\d+\s*(?:mg|g|mcg|ml)?\b/i', $displayName)) {
                $displayName .= ' ' . $dosage;
            }
            return [
                'success'       => true,
                'medicine_name' => $displayName,
                'dosage'        => $dosage ?: ($matched['dosage'] ?? ''),
                'frequency'     => $frequency,
                'in_database'   => true,
                'generic'       => $matched['generic'] ?? '',
                'source'        => 'database_match'
            ];
        }

        // 2. If no known DB medicine, extract the most plausible unlisted medicine name
        $plausible = $this->findPlausibleMedicineToken($text);
        if ($plausible) {
            return [
                'success'       => true,
                'medicine_name' => $this->cleanMedicineName($plausible),
                'dosage'        => $dosage,
                'frequency'     => $frequency,
                'in_database'   => false,
                'generic'       => '',
                'source'        => 'unlisted_text_extraction'
            ];
        }

        // 3. Fallback: check filename if provided
        if (!empty($fileName)) {
            $fromFile = $this->extractFromFilename($fileName);
            if ($fromFile) {
                return $fromFile;
            }
        }

        return [
            'success'       => false,
            'message'       => 'No medicine name could be clearly recognized from the image text.',
            'medicine_name' => '',
            'dosage'        => '',
            'frequency'     => '',
            'in_database'   => false
        ];
    }

    public function extractDosage(string $text): string
    {
        if (preg_match('/\b(\d+(?:\.\d+)?\s*(?:mg|mcg|g|gm|ml|iu))\b/i', $text, $m)) {
            return strtolower(trim($m[1]));
        }
        return '';
    }

    public function extractFrequency(string $text): string
    {
        if (preg_match('/\b(1-0-1|bid|twice\s*daily|bd)\b/i', $text)) return 'Twice daily';
        if (preg_match('/\b(1-0-0|0-1-0|0-0-1|od|once\s*daily)\b/i', $text)) return 'Once daily';
        if (preg_match('/\b(1-1-1|tid|thrice\s*daily)\b/i', $text)) return 'Three times daily';
        if (preg_match('/\b(sos|prn|as\s*needed|when\s*needed)\b/i', $text)) return 'When needed';
        return '';
    }

    private function findKnownMedicine(string $text): ?array
    {
        // 1. Check if known brands or combinations exist as distinct words in the text
        // Check combination_brands first (multi-ingredient)
        $combos = $this->pdo->query("SELECT brand_name, generic_components FROM combination_brands ORDER BY CHAR_LENGTH(brand_name) DESC")->fetchAll();
        foreach ($combos as $cb) {
            $b = preg_quote($cb['brand_name'], '/');
            if (preg_match('/\b' . $b . '(?:\s*[\-\+]?\s*\d+)?\b/i', $text, $m)) {
                return [
                    'name'    => ucwords(trim($m[0])),
                    'generic' => $cb['generic_components']
                ];
            }
        }

        // Check medicine_aliases
        $aliases = $this->pdo->query("SELECT a.alias_name, m.generic_name FROM medicine_aliases a JOIN medicines m ON a.medicine_id = m.id ORDER BY CHAR_LENGTH(a.alias_name) DESC")->fetchAll();
        foreach ($aliases as $al) {
            $aName = preg_quote($al['alias_name'], '/');
            if (preg_match('/\b' . $aName . '(?:\s*[\-\+]?\s*\d+)?\b/i', $text, $m)) {
                return [
                    'name'    => ucwords(trim($m[0])),
                    'generic' => $al['generic_name']
                ];
            }
        }

        // Check generic names directly
        $generics = $this->pdo->query("SELECT generic_name FROM medicines ORDER BY CHAR_LENGTH(generic_name) DESC")->fetchAll();
        foreach ($generics as $g) {
            $gName = preg_quote($g['generic_name'], '/');
            if (preg_match('/\b' . $gName . '(?:\s*[\-\+]?\s*\d+)?\b/i', $text, $m)) {
                return [
                    'name'    => ucwords(trim($m[0])),
                    'generic' => $g['generic_name']
                ];
            }
        }

        $lines = preg_split('/[\r\n]+/', $text);
        
        $cleanedLines = [];
        foreach ($lines as $l) {
            $cl = trim(preg_replace('/^(?:rx|tab|tab\.|cap|cap\.|syp|inj)\s+/i', '', trim($l)));
            if ($cl !== '' && !preg_match('/(?:mfg|exp|batch|b\.no|m\.r\.p|schedule|warning|reach of children|store in|manufactured)/i', $cl)) {
                $cleanedLines[] = $cl;
            }
        }

        $allTokens = array_merge($cleanedLines, [preg_replace('/\s+/', ' ', $text)]);

        foreach ($allTokens as $token) {
            $words = preg_split('/[^a-zA-Z0-9\-]+/', $token);
            $words = array_values(array_filter($words, fn($w) => strlen($w) > 2));
            for ($len = min(3, count($words)); $len >= 1; $len--) {
                for ($i = 0; $i <= count($words) - $len; $i++) {
                    $chunk = implode(' ', array_slice($words, $i, $len));
                    $res = $this->resolver->resolve($chunk);
                    if (!empty($res)) {
                        $bestName = $this->getDisplayBrandOrGeneric($chunk, $res[0]);
                        return [
                            'name'    => $bestName,
                            'generic' => implode(';', $res)
                        ];
                    }
                }
            }
        }

        return null;
    }

    private function getDisplayBrandOrGeneric(string $matchedPhrase, string $generic): string
    {
        $cleanPhrase = trim(preg_replace('/[^a-zA-Z0-9\s\-]/', '', $matchedPhrase));
        
        $stmt = $this->pdo->prepare("SELECT alias_name FROM medicine_aliases WHERE LOWER(alias_name) = LOWER(:p) LIMIT 1");
        $stmt->execute([':p' => $cleanPhrase]);
        $alias = $stmt->fetchColumn();
        if ($alias) {
            return $alias;
        }

        $stmt = $this->pdo->prepare("SELECT brand_name FROM combination_brands WHERE LOWER(brand_name) = LOWER(:p) LIMIT 1");
        $stmt->execute([':p' => $cleanPhrase]);
        $combo = $stmt->fetchColumn();
        if ($combo) {
            return $combo;
        }

        $stmt = $this->pdo->prepare("SELECT generic_name FROM medicines WHERE LOWER(generic_name) = LOWER(:p) LIMIT 1");
        $stmt->execute([':p' => $cleanPhrase]);
        $gen = $stmt->fetchColumn();
        if ($gen) {
            return ucfirst($gen);
        }

        return ucwords($cleanPhrase);
    }

    private function findPlausibleMedicineToken(string $text): ?string
    {
        $lines = preg_split('/[\r\n]+/', $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/(?:mfg|exp|batch|b\.no|m\.r\.p|schedule|warning|reach of children|store in|manufactured|contains|tablet|capsule|direct|physician)/i', $line)) {
                continue;
            }
            $line = trim(preg_replace('/^(?:rx|tab|tab\.|cap|cap\.|syp|inj)\s+/i', '', $line));
            if (preg_match('/^[A-Za-z][A-Za-z0-9\-\s]{2,25}(?:\s+\d+(?:\.\d+)?\s*(?:mg|mcg|g|ml)?)?$/i', $line)) {
                return trim($line);
            }
        }
        return null;
    }

    public function extractFromFilename(string $fileName): ?array
    {
        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $clean = trim(preg_replace('/[_\-\.\+]+/', ' ', $base));
        $clean = preg_replace('/\b(?:img|image|photo|pic|prescription|scan|file|whatsapp|shot|camera|screenshot|strip|bottle|box|pack|tab|tablet)\b/i', '', $clean);
        $clean = trim($clean);

        if (strlen($clean) >= 3) {
            return $this->extractFromText($clean);
        }
        return null;
    }
}
