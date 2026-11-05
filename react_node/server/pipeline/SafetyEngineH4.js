/**
 * Official H4 Medication Safety Decision Support Engine in Node.js
 * Faithful port of SafetyEngineH4.php
 */

const fs = require('fs');
const path = require('path');
const { Resolver, parseCSV } = require('./Resolver');

class SafetyEngineH4 {
    constructor(dataDir) {
        this.resolver = new Resolver(dataDir);
        this.interactions = {};
        this.renalRules = {};
        this.duplicateGroups = {};
        this.drugToDupGroup = {};
        this.contraindications = {};
        this.conditions = {};
        this.drugRef = {};

        this.loadAuthoritativeTables(dataDir);
    }

    getResolver() {
        return this.resolver;
    }

    loadAuthoritativeTables(dataDir) {
        // 1. drug_reference.csv
        const refPath = path.join(dataDir, 'drug_reference.csv');
        if (fs.existsSync(refPath)) {
            const rows = parseCSV(fs.readFileSync(refPath, 'utf8'));
            for (const r of rows) {
                const gen = (r.generic_name || '').toLowerCase().trim();
                if (!gen) continue;
                const dup = (r.duplicate_group || '').trim();
                const maxDose = r.max_daily_dose_mg ? parseFloat(r.max_daily_dose_mg) : null;
                this.drugRef[gen] = {
                    pregnancy_category: (r.pregnancy_category || 'N').trim(),
                    duplicate_group: dup || null,
                    max_dose: maxDose
                };
                if (dup) {
                    this.drugToDupGroup[gen] = dup;
                }
            }
        }

        // 2. interactions.csv
        const ixPath = path.join(dataDir, 'interactions.csv');
        if (fs.existsSync(ixPath)) {
            const rows = parseCSV(fs.readFileSync(ixPath, 'utf8'));
            for (const r of rows) {
                let a = (r.drug_a || '').toLowerCase().trim();
                let b = (r.drug_b || '').toLowerCase().trim();
                if (!a || !b) continue;
                if (a > b) {
                    const tmp = a; a = b; b = tmp;
                }
                this.interactions[`${a}|${b}`] = {
                    severity: (r.severity || 'moderate').toLowerCase().trim(),
                    action: (r.recommended_action || '').trim()
                };
            }
        }

        // 3. renal_rules.csv
        const rrPath = path.join(dataDir, 'renal_rules.csv');
        if (fs.existsSync(rrPath)) {
            const rows = parseCSV(fs.readFileSync(rrPath, 'utf8'));
            for (const r of rows) {
                const gen = (r.generic_name || '').toLowerCase().trim();
                if (!gen) continue;
                if (!this.renalRules[gen]) this.renalRules[gen] = [];
                this.renalRules[gen].push({
                    egfr_below: parseInt(r.egfr_below, 10),
                    severity: (r.severity || 'moderate').toLowerCase().trim(),
                    action: (r.recommended_action || '').trim()
                });
            }
            for (const gen in this.renalRules) {
                this.renalRules[gen].sort((t1, t2) => t1.egfr_below - t2.egfr_below);
            }
        }

        // 4. duplicate_therapy_groups.csv
        const dupPath = path.join(dataDir, 'duplicate_therapy_groups.csv');
        if (fs.existsSync(dupPath)) {
            const rows = parseCSV(fs.readFileSync(dupPath, 'utf8'));
            for (const r of rows) {
                const grp = (r.duplicate_group || '').trim();
                if (!grp) continue;
                const members = (r.member_generics || '')
                    .toLowerCase()
                    .split(';')
                    .map(m => m.trim())
                    .filter(Boolean);
                const memSet = new Set(members);

                this.duplicateGroups[grp] = {
                    name: (r.group_name || '').trim(),
                    severity: (r.severity || 'moderate').toLowerCase().trim(),
                    action: (r.recommended_action || '').trim(),
                    members: memSet
                };

                for (const m of members) {
                    this.drugToDupGroup[m] = grp;
                }
            }
        }

        // 5. drug_disease_contraindications.csv
        const ddcPath = path.join(dataDir, 'drug_disease_contraindications.csv');
        if (fs.existsSync(ddcPath)) {
            const rows = parseCSV(fs.readFileSync(ddcPath, 'utf8'));
            for (const r of rows) {
                const gen = (r.generic_name || '').toLowerCase().trim();
                if (!gen) continue;
                if (!this.contraindications[gen]) this.contraindications[gen] = [];
                this.contraindications[gen].push({
                    condition_id: (r.condition_id || '').trim(),
                    condition_name: (r.condition_name || '').trim(),
                    severity: (r.severity || 'major').toLowerCase().trim(),
                    action: (r.recommended_action || '').trim(),
                    reason: (r.reason || '').trim()
                });
            }
        }

        // 6. conditions.csv
        const condPath = path.join(dataDir, 'conditions.csv');
        if (fs.existsSync(condPath)) {
            const rows = parseCSV(fs.readFileSync(condPath, 'utf8'));
            const clinicalSynonyms = {
                'heart_failure': ['heart failure', 'chf', 'hfref', 'hfpef', 'ccf', 'lvef\\s*[0-9]+%?', 'lv\\s*dysfunction', 'dilated\\s*cardiomyopathy', 'dcm'],
                'peptic_ulcer': ['pud', 'h/o\\s*ugi\\s*bleed', 'ugi\\s*bleed', 'gi\\s*bleed', 'peptic\\s*ulcer', 'gastric\\s*ulcer', 'duodenal\\s*ulcer', 'melena', 'hematemesis', 'ulcer\\s*bleed', 'previous\\s*gi\\s*bleed'],
                'chronic_liver_disease': ['cld', 'cirrhosis', 'liver\\s*cirrhosis', 'portal\\s*hypertension', 'ascites', 'hepatic\\s*encephalopathy', 'end\\s*stage\\s*liver'],
                'penicillin_allergy': ['penicillin', 'pcn', 'amoxicillin', 'ampicillin', 'augmentin'],
                'sulfonamide_allergy': ['sulfa', 'sulfonamide', 'sulphonamide', 'bactrim', 'septrin', 'septran', 'cotrimoxazole'],
                'nsaid_hypersensitivity': ['aspirin\\s*allergy', 'nsaid\\s*allergy', 'ibuprofen\\s*allergy', 'diclofenac\\s*allergy', 'nsaid\\s*hypersensitivity', 'aspirin'],
                'gout': ['gout', 'hyperuricemia', 'podagra'],
                'asthma': ['asthma', 'bronchial\\s*asthma', 'ba'],
                'copd': ['copd', 'emphysema', 'chronic\\s*bronchitis'],
                'angle_closure_glaucoma': ['glaucoma', 'narrow\\s*angle', 'closed\\s*angle', 'aacg'],
                'myasthenia_gravis': ['myasthenia', 'mg'],
                'g6pd_deficiency': ['g6pd', 'g6pd\\s*def']
            };

            for (const r of rows) {
                const cid = (r.condition_id || '').trim();
                if (!cid) continue;
                const cname = (r.condition_name || '').trim();
                const terms = (r.example_terms || '')
                    .split(';')
                    .map(t => t.trim())
                    .filter(Boolean);
                terms.push(cname);
                terms.push(cid.replace(/_/g, ' '));

                if (clinicalSynonyms[cid]) {
                    for (const cs of clinicalSynonyms[cid]) {
                        terms.push(cs);
                    }
                }

                const uniqueTerms = Array.from(new Set(terms));
                const escaped = uniqueTerms.map(t => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
                const pattern = new RegExp('\\b(' + escaped.join('|') + ')\\b', 'i');

                this.conditions[cid] = {
                    name: cname,
                    kind: (r.kind || 'diagnosis').trim(),
                    pattern: pattern
                };
            }
        }
    }

    evaluatePatient(patientId, medicationList, egfr, diagnoses, allergies, pregnant) {
        const rawTokens = (medicationList || '').split(/[;\n]+/);
        const activeEntries = [];
        const allUniqueGenerics = new Set();
        const genericEntryOccurrences = {};

        let entryIdx = 0;
        for (const tok of rawTokens) {
            const trimmed = tok.trim();
            if (!trimmed) continue;

            const gens = this.resolver.resolve(trimmed);
            if (gens.length === 0) continue;

            if (/\b(stopped|stop taking|discontinued|withheld)\b/i.test(trimmed)) {
                continue;
            }

            activeEntries.push({
                token: trimmed,
                generics: gens,
                idx: entryIdx
            });

            for (const g of gens) {
                allUniqueGenerics.add(g);
                if (!genericEntryOccurrences[g]) genericEntryOccurrences[g] = [];
                genericEntryOccurrences[g].push(entryIdx);
            }
            entryIdx++;
        }

        const genericsList = Array.from(allUniqueGenerics).sort();
        const alerts = [];
        const seenKeys = new Set();

        const addAlert = (type, severity, drugs, action, confidence = 0.95) => {
            const sortedDrugs = [...drugs].sort();
            const drugsStr = sortedDrugs.join(';');
            const key = `${patientId}|${type}|${drugsStr}`;
            if (seenKeys.has(key)) return;
            seenKeys.add(key);

            alerts.push({
                patient_id: patientId,
                issue_type: type,
                severity: severity,
                drugs_involved: drugsStr,
                suggested_action: action || 'Review therapy and monitor patient.',
                confidence: confidence
            });
        };

        const n = genericsList.length;

        // 1. DRUG-DRUG INTERACTIONS
        for (let i = 0; i < n; i++) {
            for (let j = i + 1; j < n; j++) {
                const a = genericsList[i];
                const b = genericsList[j];
                const pairKey = a < b ? `${a}|${b}` : `${b}|${a}`;
                if (this.interactions[pairKey]) {
                    const hit = this.interactions[pairKey];
                    addAlert('interaction', hit.severity, [a, b], hit.action);
                }
            }
        }

        // 2. DUPLICATE THERAPY
        // Case A: Same generic appearing across 2+ distinct written entries
        for (const [gen, entries] of Object.entries(genericEntryOccurrences)) {
            const distinctEntries = new Set(entries).size;
            if (distinctEntries >= 2) {
                if (gen === 'paracetamol') {
                    addAlert(
                        'duplicate_therapy',
                        'major',
                        [gen],
                        'Avoid double paracetamol: risk of hepatotoxicity. Consolidate into single product.'
                    );
                } else {
                    const grp = this.drugToDupGroup[gen] || null;
                    if (grp && this.duplicateGroups[grp]) {
                        addAlert('duplicate_therapy', this.duplicateGroups[grp].severity, [gen], this.duplicateGroups[grp].action);
                    } else {
                        addAlert('duplicate_therapy', 'moderate', [gen], `Duplicate prescription of ${gen}: discontinue unintended duplicate.`);
                    }
                }
            }
        }

        // Case B: Two different generics belonging to the same duplicate group across different entries
        for (let i = 0; i < n; i++) {
            for (let j = i + 1; j < n; j++) {
                const a = genericsList[i];
                const b = genericsList[j];
                const grpA = this.drugToDupGroup[a] || null;
                const grpB = this.drugToDupGroup[b] || null;

                if (grpA && grpA === grpB && this.duplicateGroups[grpA]) {
                    const entriesA = genericEntryOccurrences[a] || [];
                    const entriesB = genericEntryOccurrences[b] || [];

                    let hasSeparateEntry = false;
                    for (const eA of entriesA) {
                        for (const eB of entriesB) {
                            if (eA !== eB) {
                                hasSeparateEntry = true;
                                break;
                            }
                        }
                        if (hasSeparateEntry) break;
                    }

                    if (hasSeparateEntry) {
                        const grpInfo = this.duplicateGroups[grpA];
                        addAlert('duplicate_therapy', grpInfo.severity, [a, b], grpInfo.action);
                    }
                }
            }
        }

        // 3. RENAL DOSE RISKS
        // If eGFR is missing (null/undefined), NO renal issue is labelled.
        if (egfr !== null && egfr !== undefined && egfr !== '') {
            const numEgfr = parseFloat(egfr);
            for (const gen of genericsList) {
                if (this.renalRules[gen]) {
                    const applicableTiers = [];
                    for (const tier of this.renalRules[gen]) {
                        if (numEgfr < tier.egfr_below) {
                            applicableTiers.push(tier);
                        }
                    }

                    if (applicableTiers.length > 0) {
                        applicableTiers.sort((t1, t2) => t1.egfr_below - t2.egfr_below);
                        const mostRestrictive = applicableTiers[0];
                        addAlert('renal_dose', mostRestrictive.severity, [gen], mostRestrictive.action);
                    }
                }
            }
        }

        // 4. DRUG-DISEASE / ALLERGY / PREGNANCY CONTRAINDICATIONS
        const patientConditions = this.parsePatientConditions(diagnoses);
        const patientAllergies = this.parsePatientAllergies(allergies);
        const isPregnant = ((pregnant || '').toString().trim().toUpperCase() === 'Y');

        const sevRank = { 'contraindicated': 4, 'major': 3, 'moderate': 2, 'minor': 1 };

        for (const gen of genericsList) {
            const candidateContras = [];

            if (this.contraindications[gen]) {
                for (const c of this.contraindications[gen]) {
                    const cid = c.condition_id;

                    if (cid === 'pregnancy') {
                        if (isPregnant) candidateContras.push(c);
                    } else if (patientConditions[cid]) {
                        candidateContras.push(c);
                    } else if (patientAllergies[cid]) {
                        candidateContras.push(c);
                    }
                }
            }

            if (isPregnant && this.drugRef[gen]) {
                const pCat = this.drugRef[gen].pregnancy_category;
                if (pCat === 'X') {
                    candidateContras.push({
                        severity: 'contraindicated',
                        action: 'Avoid in pregnancy; severe teratogenic risk. Stop immediately.',
                        reason: 'FDA Category X teratogen.'
                    });
                } else if (pCat === 'D') {
                    candidateContras.push({
                        severity: 'major',
                        action: 'Avoid in pregnancy; positive evidence of human fetal risk. Use safe alternative.',
                        reason: 'FDA Category D fetal risk.'
                    });
                }
            }

            if (candidateContras.length > 0) {
                candidateContras.sort((a, b) => {
                    const rA = sevRank[a.severity] || 0;
                    const rB = sevRank[b.severity] || 0;
                    return rB - rA;
                });
                const topContra = candidateContras[0];
                addAlert('contraindication', topContra.severity, [gen], topContra.action);
            }
        }

        return alerts;
    }

    parsePatientConditions(diagText) {
        const activeConds = {};
        if (!diagText || !diagText.trim()) return activeConds;

        const clauses = diagText.split(/[;,]+/);
        for (const clause of clauses) {
            const trimmed = clause.trim();
            if (!trimmed) continue;

            // Negation check
            if (/\b(no\s*h\/o|no\s*history|ruled\s*out|denies|negative\s*for|no\b)\b/i.test(trimmed)) {
                continue;
            }

            for (const [cid, info] of Object.entries(this.conditions)) {
                if (info.kind === 'diagnosis' && info.pattern.test(trimmed)) {
                    activeConds[cid] = true;
                }
            }
        }
        return activeConds;
    }

    parsePatientAllergies(allergyText) {
        const activeAllergies = {};
        const t = (allergyText || '').toLowerCase().trim();
        if (!t || t === 'none' || t === 'nkda' || t === 'nil' || t.includes('no known')) {
            return activeAllergies;
        }

        for (const [cid, info] of Object.entries(this.conditions)) {
            if (info.kind === 'allergy' && info.pattern.test(t)) {
                activeAllergies[cid] = true;
            }
        }
        return activeAllergies;
    }
}

module.exports = { SafetyEngineH4 };
