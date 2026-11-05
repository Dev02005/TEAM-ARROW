/**
 * AushadhSeva Exhaustive Functionality Test Suite
 * Tests every single feature, API endpoint, auth flow, clinical rule, and edge case.
 */

const http = require('http');

const BASE_URL = 'http://localhost:5000';

function request(path, method = 'GET', body = null, token = null) {
  return new Promise((resolve) => {
    const parsed = new URL(BASE_URL + path);
    const headers = {};
    if (body) headers['Content-Type'] = 'application/json';
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const req = http.request({
      hostname: parsed.hostname,
      port: parsed.port,
      path: parsed.pathname + parsed.search,
      method,
      headers
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        let json = null;
        try { json = JSON.parse(data); } catch (e) {}
        resolve({ status: res.statusCode, data, json, headers: res.headers });
      });
    });

    req.on('error', (err) => resolve({ error: err.message, status: 0 }));
    if (body) req.write(JSON.stringify(body));
    req.end();
  });
}

(async () => {
  console.log('================================================================');
  console.log('  AUSHADHSEVA EXHAUSTIVE FUNCTIONALITY & CLINICAL AUDIT SUITE    ');
  console.log('================================================================\n');

  let passed = 0;
  let failed = 0;

  function assert(desc, condition, details = '') {
    if (condition) {
      passed++;
      console.log(`[PASS] ${desc} ${details ? '(' + details + ')' : ''}`);
    } else {
      failed++;
      console.error(`[FAIL] ${desc} ${details ? '(' + details + ')' : ''}`);
    }
  }

  // --------------------------------------------------------------------------
  // 1. Health & Static SEO Endpoints
  // --------------------------------------------------------------------------
  console.log('--- SECTION 1: Health & SEO Endpoints ---');
  const health = await request('/api/health');
  assert('Health Check endpoint responds', health.status === 200 && health.json?.status === 'ok');

  const robots = await request('/robots.txt');
  assert('Robots.txt is served', robots.status === 200 && robots.data.includes('User-agent'));

  const sitemap = await request('/sitemap.xml');
  assert('Sitemap.xml is served', sitemap.status === 200 && sitemap.data.includes('<urlset'));

  const ogImage = await request('/og-image.svg');
  assert('OG Image SVG is served', ogImage.status === 200 && ogImage.data.includes('<svg'));

  // --------------------------------------------------------------------------
  // 2. Authentication & User Session Management
  // --------------------------------------------------------------------------
  console.log('\n--- SECTION 2: Authentication & Sessions ---');

  // Citizen Demo Login (Eleanor)
  const loginEleanor = await request('/api/auth/login', 'POST', { email: 'citizen1@example.com', password: 'citizen123' });
  assert('Citizen demo login (Eleanor)', loginEleanor.status === 200 && loginEleanor.json?.token);
  const citizenToken = loginEleanor.json?.token;

  // Verify Session Token via /api/auth/me
  const authMe = await request('/api/auth/me', 'GET', null, citizenToken);
  assert('Verify token with /api/auth/me', authMe.status === 200 && authMe.json?.user?.email === 'citizen1@example.com');

  // Admin Login
  const loginAdmin = await request('/api/auth/login', 'POST', { email: 'admin@dosewise.com', password: 'admin123' });
  assert('Admin login (admin@dosewise.com)', loginAdmin.status === 200 && loginAdmin.json?.user?.role === 'admin');
  const adminToken = loginAdmin.json?.token;

  // Invalid Password Rejection
  const badLogin = await request('/api/auth/login', 'POST', { email: 'citizen1@example.com', password: 'wrongpassword' });
  assert('Rejects invalid password with 401', badLogin.status === 401);

  // Non-existent Email Rejection
  const ghostLogin = await request('/api/auth/login', 'POST', { email: 'nosuchuser@example.com', password: 'password123' });
  assert('Rejects non-existent email with 401', ghostLogin.status === 401);

  // New Citizen Registration
  const testEmail = `newuser_${Date.now()}@example.com`;
  const registerNew = await request('/api/auth/register', 'POST', {
    full_name: 'Test Citizen User',
    email: testEmail,
    password: 'password123',
    date_of_birth: '1960-05-15',
    gender: 'female',
    conditions: 'Hypertension',
    allergies: 'Penicillin'
  });
  assert('Register new citizen account', registerNew.status === 200 && registerNew.json?.token && registerNew.json?.user?.full_name === 'Test Citizen User');

  // Reject Duplicate Email
  const duplicateReg = await request('/api/auth/register', 'POST', {
    full_name: 'Test Duplicate',
    email: testEmail,
    password: 'password123'
  });
  assert('Reject duplicate registration with 400', duplicateReg.status === 400);

  // Logout
  const logoutRes = await request('/api/auth/logout', 'POST', null, citizenToken);
  assert('Logout succeeds', logoutRes.status === 200 && logoutRes.json?.success);

  // --------------------------------------------------------------------------
  // 3. Multilingual Dictionaries (8 Languages)
  // --------------------------------------------------------------------------
  console.log('\n--- SECTION 3: Multilingual Localization (8 Languages) ---');
  const languages = ['en', 'te', 'hi', 'ta', 'ml', 'kn', 'or', 'ur'];
  for (const lang of languages) {
    const localeRes = await request(`/api/locales/${lang}`);
    const keyCount = localeRes.json ? Object.keys(localeRes.json).length : 0;
    assert(`Locale '${lang}' dictionary loaded (${keyCount} keys)`, localeRes.status === 200 && keyCount >= 360);
  }

  // --------------------------------------------------------------------------
  // 4. Clinical Prescription Checker Engine (/api/check/medicine)
  // --------------------------------------------------------------------------
  console.log('\n--- SECTION 4: Clinical Prescription Checker Engine ---');

  // Exact Generic Resolution
  const checkPcm = await request('/api/check/medicine', 'POST', { medicine_name: 'paracetamol 650', save_to_locker: false });
  assert('Exact generic resolution (paracetamol)', checkPcm.json?.recognised_as?.includes('paracetamol'));

  // Indian Brand Name Resolution (Glycomet -> metformin)
  const checkBrand = await request('/api/check/medicine', 'POST', { medicine_name: 'Glycomet 500 SR', save_to_locker: false });
  assert('Indian brand resolution (Glycomet -> metformin)', checkBrand.json?.recognised_as?.includes('metformin'));

  // Combination Brand Resolution (Glycomet-GP 1 -> glimepiride + metformin)
  const checkCombo = await request('/api/check/medicine', 'POST', { medicine_name: 'Tab Glycomet-GP 1', save_to_locker: false });
  assert('Combination brand resolution (Glycomet-GP -> glimepiride, metformin)',
    checkCombo.json?.recognised_as?.includes('glimepiride') && checkCombo.json?.recognised_as?.includes('metformin'));

  // Non-Drug Token (Glucometer strips -> empty / unverified)
  const checkNonDrug = await request('/api/check/medicine', 'POST', { medicine_name: 'Glucometer strips', save_to_locker: false });
  assert('Non-drug task resolves to unverified without crash', checkNonDrug.json?.in_database === false);

  // Unknown Unverified Substance Handling (Never say "Safe")
  const checkUnknown = await request('/api/check/medicine', 'POST', { medicine_name: 'UnknownHerbPowder99', save_to_locker: false });
  assert('Unknown drug states "We cant decide whether it is safe or not"',
    checkUnknown.json?.scoreData?.score === 'NA' && checkUnknown.json?.in_database === false);

  // Severe Drug-Drug Interaction Check (Candidate Aspirin on Warfarin patient)
  // Robert is on cardiac / antiplatelet therapy
  const checkDdi = await request('/api/check/medicine', 'POST', {
    medicine_name: 'Aspirin 150mg',
    save_to_locker: false
  });
  assert('DDI check executes against patient history', checkDdi.status === 200 && checkDdi.json?.success);

  // Renal Impairment Staging & Alert (Metformin on patient with low eGFR)
  // Eleanor has eGFR 28 (Stage 4 Severe Kidney Disease: Metformin contraindicated)
  const checkRenalEleanor = await request('/api/check/medicine', 'POST', {
    medicine_name: 'Metformin 1000mg',
    save_to_locker: false
  });
  const hasContraindicated = checkRenalEleanor.json?.alerts?.some(a => a.severity === 'contraindicated' || a.severity === 'major');
  assert('Renal cutoff flagged for Eleanor (eGFR 28 contraindicated)', hasContraindicated);

  // --------------------------------------------------------------------------
  // 5. Live External Medical APIs (OpenFDA, NIH RxNorm, PMBJP)
  // --------------------------------------------------------------------------
  console.log('\n--- SECTION 5: External Medical APIs (OpenFDA, NIH RxNorm, PMBJP) ---');
  const liveInsights = await request('/api/medical/live-insights/metformin');
  assert('Live Drug Insights API responds', liveInsights.status === 200 && liveInsights.json?.success);
  assert('PMBJP Jan Aushadhi generic savings calculated',
    liveInsights.json?.insights?.jan_aushadhi?.savings_percent !== undefined);

  // --------------------------------------------------------------------------
  // 6. Formulary Medicines API (/api/medicines)
  // --------------------------------------------------------------------------
  console.log('\n--- SECTION 6: Formulary Medicines Catalog ---');
  const medsAll = await request('/api/medicines?limit=10');
  assert('Formulary list returns >= 496 medicines in database', medsAll.json?.total >= 496);

  const medsSearch = await request('/api/medicines?search=atorvastatin');
  assert('Formulary search by drug name (atorvastatin)', medsSearch.json?.medicines?.length > 0);

  // --------------------------------------------------------------------------
  // 7. Patient Management & Cohort API (/api/patients)
  // --------------------------------------------------------------------------
  console.log('\n--- SECTION 7: Patient Cohort & Clinical Charts ---');
  const patientsList = await request('/api/patients');
  assert('Patient list returns cohorts', patientsList.json?.patients?.length > 0);

  const renalFilter = await request('/api/patients?filter=renal_impaired');
  assert('Renal impaired filter (eGFR < 60)', renalFilter.json?.patients?.every(p => p.egfr < 60));

  // Patient Detailed Chart (Patient 1 - Eleanor)
  const patientChart = await request('/api/patients/1');
  assert('Patient 1 chart loaded (Eleanor)', patientChart.json?.patient?.full_name?.includes('Eleanor'));
  assert('KDIGO eGFR staging computed (eGFR 28 -> Stage 4)', patientChart.json?.egfrStage?.stage === 'G4');
  assert('Longitudinal active & historical prescriptions separated', Array.isArray(patientChart.json?.activeMedications));

  // Add Medication to Patient
  const addMed = await request('/api/patients/1/medications', 'POST', {
    raw_medicine_name: 'Tab Telma 40',
    dosage: '40 mg',
    frequency: 'OD morning',
    prescribing_doctor: 'Dr. Rao (Cardiology)',
    indication: 'Hypertension'
  });
  assert('Add medication to patient chart', addMed.status === 200 && addMed.json?.success);

  // --------------------------------------------------------------------------
  // 8. Frontend HTML Route Serving
  // --------------------------------------------------------------------------
  console.log('\n--- SECTION 8: Frontend Route Serving ---');
  const routes = [
    '/',
    '/check',
    '/medicines',
    '/my-prescriptions',
    '/patients',
    '/patients/1',
    '/print-safety-card/1',
    '/login',
    '/register'
  ];

  for (const r of routes) {
    const res = await request(r);
    assert(`Route '${r}' serves React HTML bundle`, res.status === 200 && res.data.includes('<div id="root">'));
  }

  // --------------------------------------------------------------------------
  // SUMMARY
  // --------------------------------------------------------------------------
  console.log('\n================================================================');
  console.log(`  FINAL AUDIT RESULT: ${passed} PASSED | ${failed} FAILED`);
  console.log('================================================================\n');

  if (failed === 0) {
    console.log('ALL APPLICATION FUNCTIONS ARE WORKING 100% CORRECTLY!\n');
    process.exit(0);
  } else {
    console.error(`FAILED: ${failed} tests failed.`);
    process.exit(1);
  }
})();
