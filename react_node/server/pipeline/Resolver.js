/**
 * High-Accuracy Clinical Medication Token Resolver in Node.js
 * Faithful port of Resolver.php with optimized Levenshtein and pre-indexed lookups
 */

const fs = require('fs');
const path = require('path');

/**
 * Memory-optimized O(min(N, M)) Levenshtein edit distance
 */
function levenshtein(a, b) {
    if (a === b) return 0;
    const an = a ? a.length : 0;
    const bn = b ? b.length : 0;
    if (an === 0) return bn;
    if (bn === 0) return an;

    // Ensure `b` is the shorter string to minimize vector allocation
    if (an < bn) {
        return levenshtein(b, a);
    }

    let v0 = new Int32Array(bn + 1);
    let v1 = new Int32Array(bn + 1);

    for (let i = 0; i <= bn; i++) {
        v0[i] = i;
    }

    for (let i = 0; i < an; i++) {
        v1[0] = i + 1;
        const charA = a.charCodeAt(i);

        for (let j = 0; j < bn; j++) {
            const cost = charA === b.charCodeAt(j) ? 0 : 1;
            v1[j + 1] = Math.min(
                v1[j] + 1,      // insertion
                v0[j + 1] + 1,  // deletion
                v0[j] + cost    // substitution
            );
        }

        // Swap buffers
        const tmp = v0;
        v0 = v1;
        v1 = tmp;
    }

    return v0[bn];
}

function parseCSV(content) {
    const lines = content.split(/\r?\n/).filter(line => line.trim().length > 0);
    if (lines.length === 0) return [];
    
    function parseLine(line) {
        const row = [];
        let inQuotes = false;
        let current = '';
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            if (char === '"') {
                if (inQuotes && line[i + 1] === '"') {
                    current += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if (char === ',' && !inQuotes) {
                row.push(current);
                current = '';
            } else {
                current += char;
            }
        }
        row.push(current);
        return row;
    }

    const header = parseLine(lines[0]);
    const rows = [];
    for (let i = 1; i < lines.length; i++) {
        const values = parseLine(lines[i]);
        const obj = {};
        for (let j = 0; j < header.length; j++) {
            obj[header[j].trim()] = (values[j] !== undefined ? values[j].trim() : '');
        }
        rows.push(obj);
    }
    return rows;
}

class Resolver {
    constructor(dataDir) {
        this.lookup = Object.create(null);
        this.fuzzyCandidates = Object.create(null);
        this.fuzzyEntries = [];

        this.formWords = new Set([
            'tab', 'tab.', 't.', 't', 'cap', 'cap.', 'c.', 'inj', 'inj.',
            'syp', 'syp.', 'syr', 'inh', 'inh.', 'mdi', 'rotacap', 'sachet',
            'sach.', 'patch', 'susp', 'drops', 'powder', 'neb', 'respule',
            'gel', 'ointment', 'cream', 'lotion', 'syrup', 'tablet', 'capsule',
            'injection', 'inhaler', 'respules', 'x', 'x.'
        ]);

        this.stopWords = new Set([
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

        this.nonDrugPatterns = [
            /^\s*bp\s*charting/i,
            /^\s*blood\s*pressure/i,
            /^\s*glucometer/i,
            /^\s*strips/i,
            /^\s*physiotherapy/i,
            /^\s*salt\s*restriction/i,
            /^\s*diet\b/i,
            /^\s*lifestyle/i,
            /^\s*review\s*with/i,
            /^\s*review\s*after/i,
            /^\s*follow\s*up/i,
            /^\s*nebulisation\s*sos/i,
            /^\s*steam\s*inhalation/i,
            /^\s*exercise/i,
            /^\s*walking/i,
            /^\s*weight\s*loss/i,
            /^\s*sugar\s*charting/i,
            /^\s*rbs\b/i,
            /^\s*cbg\b/i,
            /^\s*cbc\b/i,
            /^\s*kft\b/i,
            /^\s*lft\b/i,
            /^\s*ecg\b/i,
            /^\s*x-ray\b/i,
            /^\s*ultrasound\b/i,
            /^\s*usg\b/i
        ];

        this.loadDictionaries(dataDir);
    }

    cleanKey(s) {
        return (s || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    loadDictionaries(dataDir) {
        // 1. drug_reference.csv
        const refPath = path.join(dataDir, 'drug_reference.csv');
        if (fs.existsSync(refPath)) {
            const rows = parseCSV(fs.readFileSync(refPath, 'utf8'));
            for (const row of rows) {
                const gen = (row.generic_name || '').toLowerCase().trim();
                if (!gen) continue;
                const k = this.cleanKey(gen);
                this.lookup[k] = [gen];
                this.fuzzyCandidates[k] = [gen];

                const brands = (row.common_brands || '').split(';');
                for (let b of brands) {
                    b = b.trim();
                    if (!b) continue;
                    const bk = this.cleanKey(b);
                    this.lookup[bk] = [gen];
                    this.fuzzyCandidates[bk] = [gen];
                }
            }
        }

        // 2. combination_brands.csv
        const combosPath = path.join(dataDir, 'combination_brands.csv');
        if (fs.existsSync(combosPath)) {
            const rows = parseCSV(fs.readFileSync(combosPath, 'utf8'));
            for (const row of rows) {
                const brand = (row.brand_name || '').trim();
                const str = (row.strength_label || '').trim();
                const comps = (row.generic_components || '')
                    .toLowerCase()
                    .split(';')
                    .map(c => c.trim())
                    .filter(Boolean);
                comps.sort();
                if (comps.length === 0) continue;

                const bk = this.cleanKey(brand);
                this.lookup[bk] = comps;
                this.fuzzyCandidates[bk] = comps;

                if (str) {
                    const bkWithStr = this.cleanKey(brand + ' ' + str);
                    this.lookup[bkWithStr] = comps;
                    this.fuzzyCandidates[bkWithStr] = comps;
                }
            }
        }

        // 3. Indian Clinical Abbreviations & Common Misspellings (exact from Resolver.php)
        const manualMap = {
            'pcm': ['paracetamol'],
            'pcm650': ['paracetamol'],
            'pcm500': ['paracetamol'],
            'calcium': ['calcium carbonate'],
            'd3': ['cholecalciferol'],
            'vitd3': ['cholecalciferol'],
            'vitamind3': ['cholecalciferol'],
            'calcirol': ['cholecalciferol'],
            'shelcal': ['calcium carbonate', 'cholecalciferol'],
            'shelal': ['calcium carbonate', 'cholecalciferol'],
            'ismn': ['isosorbide mononitrate'],
            'isdn': ['isosorbide dinitrate'],
            'sodabicarb': ['sodium bicarbonate'],
            'sodamint': ['sodium bicarbonate'],
            'sodami': ['sodium bicarbonate'],
            'sodam': ['sodium bicarbonate'],
            'soddiumbicarbonate': ['sodium bicarbonate'],
            'soyumbicarbonate': ['sodium bicarbonate'],
            'lthyroxine': ['levothyroxine'],
            'frusemide': ['furosemide'],
            'mecobalamin': ['methylcobalamin'],
            'amoxyclav': ['amoxicillin', 'clavulanic acid'],
            'amoxiclav': ['amoxicillin', 'clavulanic acid'],
            'augmentin': ['amoxicillin', 'clavulanic acid'],
            'croken': ['paracetamol'],
            'dolo': ['paracetamol'],
            'crocin': ['paracetamol'],
            'pacimol': ['paracetamol'],
            'aspyrin': ['aspirin'],
            'aspir': ['aspirin'],
            'glimeprid': ['glimepiride'],
            'pantop': ['pantoprazole'],
            'pntp': ['pantoprazole'],
            'pentoprezolee': ['pantoprazole'],
            'rozav': ['rosuvastatin'],
            'paxi': ['paroxetine'],
            'prazopr': ['prazosin'],
            'minipes': ['prazosin'],
            'minipress': ['prazosin'],
            'dyna': ['diclofenac'],
            'brufen': ['ibuprofen'],
            'combiflam': ['ibuprofen', 'paracetamol'],
            'vymada': ['sacubitril', 'valsartan'],
            'janumet': ['metformin', 'sitagliptin'],
            'diamicron': ['gliclazide'],
            'foracort': ['budesonide', 'formoterol'],
            'seroflo': ['fluticasone', 'salmeterol'],
            'ascoril': ['ambroxol', 'guaifenesin', 'levosalbutamol'],
            'ascorills': ['ambroxol', 'guaifenesin', 'levosalbutamol'],
            'syndopaplus': ['carbidopa', 'levodopa'],
            'syndopa': ['carbidopa', 'levodopa'],
            'glyciphage': ['metformin'],
            'glycomet': ['metformin'],
            'glycometgp': ['glimepiride', 'metformin'],
            'glycometgp1': ['glimepiride', 'metformin'],
            'glycometgp2': ['glimepiride', 'metformin'],
            'glycametgp': ['glimepiride', 'metformin'],
            'glycametgp1': ['glimepiride', 'metformin'],
            'glekomet': ['metformin'],
            'glekometgp': ['glimepiride', 'metformin'],
            'glekometgp1': ['glimepiride', 'metformin'],
            'glyccometgp': ['glimepiride', 'metformin'],
            'glyccometgp1': ['glimepiride', 'metformin'],
            'zerodolp': ['aceclofenac', 'paracetamol'],
            'zerodlp': ['aceclofenac', 'paracetamol'],
            'zerodolsp': ['aceclofenac', 'paracetamol', 'serratiopeptidase'],
            'zerodolesp': ['aceclofenac', 'paracetamol', 'serratiopeptidase'],
            'zerodolmr': ['aceclofenac', 'tizanidine'],
            'meftalspas': ['dicyclomine', 'mefenamic acid'],
            'mftalspas': ['dicyclomine', 'mefenamic acid'],
            'meftalforte': ['mefenamic acid', 'paracetamol'],
            'telmah': ['hydrochlorothiazide', 'telmisartan'],
            'telmikindh': ['hydrochlorothiazide', 'telmisartan'],
            'telmaam': ['amlodipine', 'telmisartan'],
            'tlmaam': ['amlodipine', 'telmisartan'],
            'anlopres': ['amlodipine', 'atenolol'],
            'anlopresat': ['amlodipine', 'atenolol'],
            'amloprsat': ['amlodipine', 'atenolol'],
            'amlopresat': ['amlodipine', 'atenolol'],
            'amlovasat': ['amlodipine', 'atenolol'],
            'losarh': ['hydrochlorothiazide', 'losartan'],
            'cilacar': ['cilnidipine'],
            'donep': ['donepezil'],
            'topamac': ['topiramate'],
            'forxiga': ['dapagliflozin'],
            'lasix': ['furosemide'],
            'atocor': ['atorvastatin'],
            'atokr': ['atorvastatin'],
            'asthalin': ['salbutamol'],
            'losacar': ['losartan'],
            'naprosyn': ['naproxen'],
            'dytorplus': ['spironolactone', 'torsemide'],
            'dytorplus10': ['spironolactone', 'torsemide'],
            'dytorplus20': ['spironolactone', 'torsemide'],
            'veltamplus': ['dutasteride', 'tamsulosin'],
            'veltmplus': ['dutasteride', 'tamsulosin'],
            'ureax': ['tamsulosin'],
            'drotnm': ['drotaverine', 'mefenamic acid'],
            'glemestarm': ['glimepiride', 'metformin'],
            'glemestarm2': ['glimepiride', 'metformin'],
            'insulin3070': ['insulin isophane', 'insulin regular'],
            'insulin5050': ['insulin isophane', 'insulin regular'],
            'huminsulin3070': ['insulin isophane', 'insulin regular'],
            'huminsulin': ['insulin isophane', 'insulin regular'],
            'mytard30': ['insulin isophane', 'insulin regular'],
            'mixtard': ['insulin isophane', 'insulin regular'],
            'monteleukastlc': ['levocetirizine', 'montelukast'],
            'montelukastlc': ['levocetirizine', 'montelukast'],
            'mantwirlc': ['levocetirizine', 'montelukast'],
            'montairlc': ['levocetirizine', 'montelukast'],
            'gausmet': ['metformin', 'vildagliptin'],
            'galvusmet': ['metformin', 'vildagliptin'],
            'sepfrna': ['sulfamethoxazole', 'trimethoprim'],
            'septrin': ['sulfamethoxazole', 'trimethoprim'],
            'septran': ['sulfamethoxazole', 'trimethoprim'],
            'nmesuid': ['nimesulide'],
            'ecosprinav': ['aspirin', 'atorvastatin'],
            'ekosprinav': ['aspirin', 'atorvastatin'],
            'ecosprnav': ['aspirin', 'atorvastatin'],
            'nexytoplus': ['clonazepam', 'escitalopram'],
            'nexitoplus': ['clonazepam', 'escitalopram'],
            'clopitaba': ['aspirin', 'clopidogrel'],
            'dyrto': ['torsemide'],
            'dpltt': ['clopidogrel'],
            'cralok': ['carvedilol'],
            'mnrelukat': ['montelukast'],
            'tranexamcacid': ['tranexamic acid'],
            'befnotiamin': ['benfotiamine'],
            'norrflokstz': ['norfloxacin', 'tinidazole'],
            'orferxt': ['ferrous ascorbate', 'folic acid'],
            'oroferxt': ['ferrous ascorbate', 'folic acid'],
            'folycacid': ['folic acid'],
            'potklor': ['potassium chloride'],
            'tamsksin': ['tamsulosin']
        };

        for (const [k, generics] of Object.entries(manualMap)) {
            const sorted = [...generics].sort();
            this.lookup[k] = sorted;
            this.fuzzyCandidates[k] = sorted;
        }

        // Pre-cache entries array for high-performance iteration
        this.fuzzyEntries = Object.entries(this.fuzzyCandidates);
    }

    resolve(rawToken) {
        const token = (rawToken || '').trim();
        if (!token) return [];

        // 1. Non-drug check
        for (const pat of this.nonDrugPatterns) {
            if (pat.test(token)) return [];
        }

        // Special case: insulin mixtures
        if (/(huminsulin|mixtard|mytard|insulin)\s*(30\/70|50\/50|30)/i.test(token)) {
            return ['insulin isophane', 'insulin regular'];
        }

        // 2. Check for combination with '+' or '/'
        if (token.includes('+')) {
            const parts = token.split('+');
            const merged = new Set();
            for (const p of parts) {
                const subGens = this.resolveSingle(p);
                for (const sg of subGens) merged.add(sg);
            }
            if (merged.size > 0) {
                return Array.from(merged).sort();
            }
        }

        if (token.includes('/') && !/\b[0-9]+\/[0-9]+\b/.test(token)) {
            const parts = token.split('/');
            const merged = new Set();
            for (const p of parts) {
                const subGens = this.resolveSingle(p);
                for (const sg of subGens) merged.add(sg);
            }
            if (merged.size > 0) {
                return Array.from(merged).sort();
            }
        }

        return this.resolveSingle(token);
    }

    resolveSingle(token) {
        const wholeK = this.cleanKey(token);
        if (this.lookup[wholeK]) {
            return this.lookup[wholeK];
        }

        const cleanToken = token.replace(/[\(\),\/\-\+]/g, ' ');
        const words = cleanToken.split(/\s+/).filter(Boolean);

        while (words.length > 0 && this.formWords.has(words[0].toLowerCase())) {
            words.shift();
        }

        if (words.length === 0) return [];

        const nameWords = [];
        for (const w of words) {
            const lw = w.toLowerCase();
            if (this.stopWords.has(lw)) break;
            if (/^[0-9]+(\.[0-9]+)?(mg|mcg|gm|g|ml|iu)?$/i.test(lw)) {
                if (nameWords.length > 0) break;
            }
            if (/^[0-9]-[0-9]-[0-9]/.test(lw)) break;
            nameWords.push(w);
        }

        const candidateWords = nameWords.length > 0 ? nameWords : words;
        const n = candidateWords.length;

        // Longest candidate phrase first
        for (let len = n; len >= 1; len--) {
            const candidateStr = candidateWords.slice(0, len).join(' ');
            const k = this.cleanKey(candidateStr);
            if (this.lookup[k]) {
                return this.lookup[k];
            }
        }

        // Prefix & Fuzzy matching
        const firstWord = candidateWords[0] || '';
        const fwK = this.cleanKey(firstWord);
        if (this.lookup[fwK]) {
            return this.lookup[fwK];
        }

        if (fwK.length >= 4) {
            let bestMatch = null;
            let bestDist = 999;
            const fwLen = fwK.length;
            const maxAllowed = fwLen <= 5 ? 1 : 2;

            for (let i = 0; i < this.fuzzyEntries.length; i++) {
                const [dictKey, generics] = this.fuzzyEntries[i];
                if (dictKey.startsWith(fwK) || (dictKey.length >= 4 && fwK.startsWith(dictKey))) {
                    return generics;
                }

                const lenDiff = Math.abs(dictKey.length - fwLen);
                if (lenDiff <= maxAllowed) {
                    const dist = levenshtein(fwK, dictKey);
                    if (dist <= maxAllowed && dist < bestDist) {
                        bestDist = dist;
                        bestMatch = generics;
                        if (bestDist === 1 && maxAllowed === 1) break; // early exit for exact-enough match
                    }
                }
            }

            if (bestMatch !== null) {
                return bestMatch;
            }
        }

        return [];
    }
}

module.exports = { Resolver, parseCSV, levenshtein };
