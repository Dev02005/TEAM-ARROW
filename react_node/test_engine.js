const path = require('path');
const { SafetyEngineH4 } = require('./server/pipeline/SafetyEngineH4');

const dataDir = path.resolve('FULL_CODE/data');
console.log('Testing SafetyEngineH4 with dataDir:', dataDir);

const engine = new SafetyEngineH4(dataDir);
const resolver = engine.getResolver();

let passed = 0;
let failed = 0;

function assert(desc, cond, detail = '') {
    if (cond) {
        passed++;
        console.log(` [PASS] ${desc}${detail ? ' -- ' + detail : ''}`);
    } else {
        failed++;
        console.log(` [FAIL] ${desc}${detail ? ' -- ' + detail : ''}`);
    }
}

// 1. Resolver tests
const res1 = resolver.resolve('tab paracetamol 650 if pain');
assert('Resolve exact generic name', JSON.stringify(res1) === JSON.stringify(['paracetamol']), res1.join(';'));

const res2 = resolver.resolve('T. Pantodac 20mg OD 30 min before bf');
assert('Resolve Indian brand to generic (Pantodac -> pantoprazole)', JSON.stringify(res2) === JSON.stringify(['pantoprazole']), res2.join(';'));

const res3 = resolver.resolve('Tab Glycomet-GP 1 1-0-1');
assert('Resolve combination brand (Glycomet-GP 1 -> glimepiride;metformin)', JSON.stringify(res3) === JSON.stringify(['glimepiride', 'metformin']), res3.join(';'));

const res4 = resolver.resolve('Glucometer strips');
assert('Non-drug token resolves to empty set', res4.length === 0);

// 2. Interaction tests
const alertsIx = engine.evaluatePatient('TEST-IX-01', 'Tab Warfarin 5mg OD; Tab Aspirin 75mg OD', 80, 'Hypertension', '', 'N');
const hasWarfAsp = alertsIx.some(a => a.issue_type === 'interaction' && a.drugs_involved === 'aspirin;warfarin');
assert('Warfarin + Aspirin interaction flagged', hasWarfAsp);

// 3. Duplicate Paracetamol
const alertsDupPcm = engine.evaluatePatient('TEST-DUP-01', 'Crocin 650 SOS; Combiflam 1-0-1', 90, 'Fever', '', 'N');
const pcmAlert = alertsDupPcm.find(a => a.issue_type === 'duplicate_therapy' && a.drugs_involved === 'paracetamol');
assert('Paracetamol duplicate flagged as major', pcmAlert && pcmAlert.severity === 'major');

// 4. Missing eGFR suppresses renal alerts
const alertsRenalMissing = engine.evaluatePatient('TEST-REN-02', 'Tab Metformin 500mg BD', null, 'CKD Stage 3', '', 'N');
const hasRenal = alertsRenalMissing.some(a => a.issue_type === 'renal_dose');
assert('Missing eGFR suppresses renal alert', !hasRenal);

// 5. Negated condition
const alertsNeg = engine.evaluatePatient('TEST-CONTRA-02', 'Tab Propranolol 40mg BD', 90, 'No h/o Asthma; Hypertension', '', 'N');
const hasNeg = alertsNeg.some(a => a.issue_type === 'contraindication');
assert('Negated diagnosis ("no h/o Asthma") does not trigger contraindication', !hasNeg);

console.log(`\nResults: ${passed} passed, ${failed} failed`);
process.exit(failed === 0 ? 0 : 1);
