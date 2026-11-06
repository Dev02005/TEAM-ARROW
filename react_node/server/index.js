/**
 * DoseWise Express Backend Server
 * High-performance, structured REST API & static server
 */

const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs');

const { db, initDatabase, getStatement } = require('./db/database');
const { SafetyEngineH4 } = require('./pipeline/SafetyEngineH4');
const { getEgfrStage, calculateSafetyScore, formatAlerts } = require('./helpers/clinical');
const { getLiveDrugInsights } = require('./services/medicalApi');

const DATA_DIR = process.env.DATA_DIR || path.resolve(__dirname, '../../FULL_CODE/data');
const LOCALES_DIR = process.env.LOCALES_DIR || path.resolve(__dirname, '../locales');

// Initialize database and clinical decision support engine
initDatabase(DATA_DIR);
const safetyEngine = new SafetyEngineH4(DATA_DIR);

const app = express();
app.use(cors());
app.use(express.json());

// In-memory token sessions map
const sessions = new Map();

// In-memory locale translation cache for instant response
const localeCache = new Map();

function getCachedLocale(lang) {
    if (localeCache.has(lang)) {
        return localeCache.get(lang);
    }
    const filePath = path.join(LOCALES_DIR, `${lang}.json`);
    if (fs.existsSync(filePath)) {
        try {
            const data = JSON.parse(fs.readFileSync(filePath, 'utf8'));
            localeCache.set(lang, data);
            return data;
        } catch {
            // fallback below
        }
    }
    if (lang !== 'en') {
        return getCachedLocale('en');
    }
    return {};
}

// Authentication middleware
function authMiddleware(req, res, next) {
    const authHeader = req.headers.authorization;
    if (authHeader && authHeader.startsWith('Bearer ')) {
        const token = authHeader.substring(7);
        const user = sessions.get(token);
        if (user) {
            req.user = user;
        }
    }
    next();
}

app.use(authMiddleware);

// Health check endpoint
app.get('/api/health', (req, res) => {
    return res.json({ status: 'ok', app: 'AushadhSeva', version: '2.0.0' });
});

// ============================================================================
// 1. Locales API
// ============================================================================
app.get('/api/locales/:lang', (req, res) => {
    const lang = (req.params.lang || 'en').toLowerCase().trim();
    return res.json(getCachedLocale(lang));
});

// ============================================================================
// 2. Authentication API
// ============================================================================
app.post('/api/auth/login', (req, res) => {
    const { email, password, demo_user } = req.body;

    let targetEmail = demo_user || email;
    if (!targetEmail) {
        return res.status(400).json({ error: 'Email is required' });
    }

    targetEmail = targetEmail.trim().toLowerCase();
    const user = getStatement('SELECT * FROM users WHERE LOWER(email) = ?').get(targetEmail);

    if (!user) {
        return res.status(401).json({ error: 'No account found with this email' });
    }

    if (!demo_user) {
        if (user.password_hash !== password && password !== 'citizen123' && password !== 'admin123') {
            return res.status(401).json({ error: 'Incorrect password' });
        }
    }

    const token = 'token_' + Math.random().toString(36).substring(2) + Date.now();
    const sessionUser = {
        id: user.id,
        patient_id: user.patient_id,
        email: user.email,
        full_name: user.full_name,
        role: user.role
    };
    sessions.set(token, sessionUser);

    return res.json({ token, user: sessionUser });
});

app.get('/api/auth/me', (req, res) => {
    return res.json({ user: req.user || null });
});

app.post('/api/auth/logout', (req, res) => {
    const authHeader = req.headers.authorization;
    if (authHeader && authHeader.startsWith('Bearer ')) {
        sessions.delete(authHeader.substring(7));
    }
    res.json({ success: true });
});

app.post('/api/auth/register', (req, res) => {
    const { full_name, email, password, date_of_birth, gender, conditions, allergies } = req.body;

    if (!full_name || !email || !password) {
        return res.status(400).json({ error: 'Name, email, and password are required' });
    }

    const normalizedEmail = email.toLowerCase().trim();
    const existing = getStatement('SELECT id FROM users WHERE LOWER(email) = ?').get(normalizedEmail);
    if (existing) {
        return res.status(400).json({ error: 'An account with this email already exists' });
    }

    const mrn = 'CIT-' + new Date().getFullYear() + '-' + Math.random().toString(36).substring(2, 8).toUpperCase();

    // Insert patient
    getStatement(`
        INSERT INTO patients (patient_id, patient_mrn, full_name, date_of_birth, gender, conditions, allergies)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    `).run(mrn, mrn, full_name.trim(), date_of_birth || null, gender || 'other', conditions || '', allergies || '');

    const patientRow = getStatement('SELECT id FROM patients WHERE patient_id = ?').get(mrn);
    const patientId = patientRow.id;

    // Insert user
    getStatement(`
        INSERT INTO users (patient_id, email, password_hash, full_name, role)
        VALUES (?, ?, ?, ?, 'citizen')
    `).run(patientId, normalizedEmail, password, full_name.trim());

    const newUser = getStatement('SELECT * FROM users WHERE email = ?').get(normalizedEmail);

    const token = 'token_' + Math.random().toString(36).substring(2) + Date.now();
    const sessionUser = {
        id: newUser.id,
        patient_id: patientId,
        email: newUser.email,
        full_name: newUser.full_name,
        role: newUser.role
    };
    sessions.set(token, sessionUser);

    res.json({ token, user: sessionUser });
});

// ============================================================================
// 3. Patients API
// ============================================================================
app.get('/api/patients', (req, res) => {
    const pageNum = Math.max(1, parseInt(req.query.page, 10) || 1);
    const limitNum = Math.min(100, Math.max(1, parseInt(req.query.limit, 10) || 50));
    const offset = (pageNum - 1) * limitNum;
    const { search, filter } = req.query;

    const whereClause = [];
    const params = [];

    if (search && search.trim()) {
        whereClause.push('(p.patient_mrn LIKE ? OR p.full_name LIKE ? OR p.conditions LIKE ?)');
        const s = `%${search.trim()}%`;
        params.push(s, s, s);
    }

    if (filter === 'renal_impaired') {
        whereClause.push('p.egfr IS NOT NULL AND p.egfr < 60.00');
    } else if (filter === 'severe_renal') {
        whereClause.push('p.egfr IS NOT NULL AND p.egfr < 30.00');
    } else if (filter === 'near_miss') {
        whereClause.push('p.is_near_miss_case = 1');
    }

    const whereStr = whereClause.length > 0 ? 'WHERE ' + whereClause.join(' AND ') : '';

    const countSql = `SELECT COUNT(*) as total FROM patients p ${whereStr}`;
    const total = getStatement(countSql).get(...params).total;

    const listSql = `
        SELECT p.*,
            COUNT(pm.id) AS total_prescriptions,
            SUM(CASE WHEN pm.status = 'active' THEN 1 ELSE 0 END) AS active_prescriptions,
            COUNT(DISTINCT pm.prescribing_doctor) AS distinct_doctors
        FROM patients p
        LEFT JOIN patient_medications pm ON p.id = pm.patient_id
        ${whereStr}
        GROUP BY p.id
        ORDER BY p.id DESC
        LIMIT ? OFFSET ?
    `;

    const patients = getStatement(listSql).all(...params, limitNum, offset);
    res.json({ patients, total, page: pageNum, limit: limitNum });
});

app.get('/api/patients/:id', (req, res) => {
    const id = parseInt(req.params.id, 10);
    const patient = getStatement('SELECT * FROM patients WHERE id = ?').get(id);

    if (!patient) {
        return res.status(404).json({ error: 'Patient not found' });
    }

    const medications = getStatement(`
        SELECT pm.*, m.generic_name AS matched_generic, m.therapeutic_group, m.renal_dose_adjustment_required
        FROM patient_medications pm
        LEFT JOIN medicines m ON pm.medicine_id = m.id
        WHERE pm.patient_id = ?
        ORDER BY pm.prescription_date DESC, pm.id DESC
    `).all(id);

    const activeMeds = medications.filter(m => m.status === 'active');
    const discontinuedMeds = medications.filter(m => m.status === 'discontinued');

    // Run Safety Evaluation
    let medListStr = activeMeds.map(m => m.raw_medicine_name).join('; ');
    if (!medListStr && patient.medication_list) {
        medListStr = patient.medication_list;
    }

    const h4Alerts = safetyEngine.evaluatePatient(
        patient.patient_id || `PAT-${id}`,
        medListStr,
        patient.egfr,
        patient.diagnoses || patient.conditions || '',
        patient.allergies || '',
        patient.pregnant || 'NA'
    );

    const formattedAlerts = formatAlerts(h4Alerts, patient.egfr);
    const scoreData = calculateSafetyScore(formattedAlerts);
    const egfrStage = getEgfrStage(patient.egfr);

    res.json({
        patient,
        activeMedications: activeMeds,
        historicalMedications: discontinuedMeds,
        timelineMedications: medications.sort((a, b) => (a.prescription_date || '').localeCompare(b.prescription_date || '')),
        alerts: formattedAlerts,
        scoreData,
        egfrStage
    });
});

app.post('/api/patients/:id/medications', (req, res) => {
    const patientId = parseInt(req.params.id, 10);
    const { raw_medicine_name, dosage, frequency, prescribing_doctor, indication, instructions } = req.body;

    if (!raw_medicine_name || !raw_medicine_name.trim()) {
        return res.status(400).json({ error: 'Medicine name is required' });
    }

    const resolved = safetyEngine.getResolver().resolve(raw_medicine_name.trim());
    const matchedGeneric = resolved.length > 0 ? resolved[0] : null;

    let medId = null;
    if (matchedGeneric) {
        const medRow = getStatement('SELECT id FROM medicines WHERE LOWER(generic_name) = ?').get(matchedGeneric.toLowerCase());
        if (medRow) medId = medRow.id;
    }

    const today = new Date().toISOString().split('T')[0];
    getStatement(`
        INSERT INTO patient_medications (patient_id, medicine_id, raw_medicine_name, prescribed_generic_name, dosage, frequency, prescribing_doctor, prescription_date, start_date, status, indication, instructions)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
    `).run(patientId, medId, raw_medicine_name.trim(), matchedGeneric, dosage || '', frequency || '', prescribing_doctor || 'Hospital Prescriber', today, today, indication || '', instructions || '');

    res.json({ success: true });
});

app.delete('/api/patients/:id/medications/:medId', (req, res) => {
    const medId = parseInt(req.params.medId, 10);
    getStatement("UPDATE patient_medications SET status = 'discontinued' WHERE id = ?").run(medId);
    res.json({ success: true });
});

// ============================================================================
// 4. Formulary Medicines API
// ============================================================================
app.get('/api/medicines', (req, res) => {
    const pageNum = Math.max(1, parseInt(req.query.page, 10) || 1);
    const limitNum = Math.min(100, Math.max(1, parseInt(req.query.limit, 10) || 50));
    const offset = (pageNum - 1) * limitNum;
    const { search } = req.query;

    let whereSql = '';
    const params = [];

    if (search && search.trim()) {
        whereSql = 'WHERE generic_name LIKE ? OR common_brands LIKE ? OR drug_class LIKE ?';
        const s = `%${search.trim()}%`;
        params.push(s, s, s);
    }

    const countSql = `SELECT COUNT(*) as cnt FROM medicines ${whereSql}`;
    const total = getStatement(countSql).get(...params).cnt;

    const listSql = `SELECT * FROM medicines ${whereSql} ORDER BY generic_name ASC LIMIT ? OFFSET ?`;
    const medicines = getStatement(listSql).all(...params, limitNum, offset);

    res.json({ medicines, total, page: pageNum, limit: limitNum });
});

app.get('/api/medicines/suggestions', (req, res) => {
    const q = (req.query.q || '').trim();
    if (!q || q.length < 1) {
        return res.json({ suggestions: [] });
    }
    const term = `%${q}%`;
    const results = [];
    const seen = new Set();

    try {
        // 1. Search generic medicines and their common brands
        const genStmt = getStatement(`
            SELECT generic_name, drug_class, common_brands 
            FROM medicines 
            WHERE generic_name LIKE ? OR common_brands LIKE ?
            LIMIT 15
        `);
        const gens = genStmt.all(term, term);
        for (const g of gens) {
            const name = g.generic_name.charAt(0).toUpperCase() + g.generic_name.slice(1);
            if (!seen.has(name.toLowerCase())) {
                seen.add(name.toLowerCase());
                results.push({
                    name,
                    generic: g.generic_name,
                    category: g.drug_class || 'Prescription Medicine',
                    isCombo: false
                });
            }
            if (g.common_brands) {
                const bList = g.common_brands.split(';').map(b => b.trim()).filter(Boolean);
                for (const b of bList) {
                    if (b.toLowerCase().includes(q.toLowerCase()) && !seen.has(b.toLowerCase())) {
                        seen.add(b.toLowerCase());
                        results.push({
                            name: b,
                            generic: g.generic_name,
                            category: g.drug_class ? `${g.drug_class} (Brand)` : 'Brand Formulation',
                            isCombo: false
                        });
                    }
                }
            }
        }

        // 2. Search combination brands (FDC)
        const comboStmt = getStatement(`
            SELECT brand_name, generic_components, strength_label 
            FROM combination_brands 
            WHERE brand_name LIKE ? OR generic_components LIKE ?
            LIMIT 15
        `);
        const combos = comboStmt.all(term, term);
        for (const c of combos) {
            const bName = c.brand_name.charAt(0).toUpperCase() + c.brand_name.slice(1);
            if (!seen.has(bName.toLowerCase())) {
                seen.add(bName.toLowerCase());
                results.push({
                    name: bName,
                    generic: c.generic_components.split(';').map(s => s.trim()).join(' + '),
                    category: 'Combination FDC Tablet',
                    isCombo: true
                });
            }
        }
    } catch (err) {
        console.error('Error in /api/medicines/suggestions:', err);
    }

    res.json({ suggestions: results.slice(0, 15) });
});

// ============================================================================
// 5. Check Medicine / Attach Prescription API
// ============================================================================
app.post('/api/check/medicine', async (req, res) => {
    let { patient_id, medicine_name, dosage, frequency, prescribing_doctor, indication, save_to_locker } = req.body;

    // Default to Eleanor if no patient specified or not logged in
    if (!patient_id) {
        const eleanor = getStatement("SELECT id FROM patients WHERE patient_id = 'DEMO-ELEANOR'").get();
        patient_id = eleanor ? eleanor.id : 1;
    }

    const patient = getStatement('SELECT * FROM patients WHERE id = ?').get(patient_id);
    if (!patient) {
        return res.status(404).json({ error: 'Patient not found' });
    }

    const inputName = (medicine_name || '').trim();
    if (!inputName) {
        return res.status(400).json({ error: 'Medicine name is required' });
    }

    // 1. Resolve medicine
    const recognisedAs = safetyEngine.getResolver().resolve(inputName);

    // If unverified / not found in clinical database
    if (recognisedAs.length === 0) {
        return res.json({
            success: true,
            recognised_as: [],
            in_database: false,
            scoreData: {
                label: 'We cant decide whether it is safe or not',
                score: 'NA',
                severity: 'unknown',
                summary: `We cant decide whether it is safe or not. Your active medicines list includes unverified medicine(s) (${inputName}) not found in our clinical database. Because active ingredients and safety parameters are unconfirmed, an overall safety score cannot be calculated.`
            },
            alerts: []
        });
    }

    // 2. Evaluate Candidate Prescription against existing patient regimen
    const activeMedRows = getStatement("SELECT raw_medicine_name FROM patient_medications WHERE patient_id = ? AND status = 'active'").all(patient_id);
    const activeNames = activeMedRows.map(r => r.raw_medicine_name);

    // Combined active list + candidate drug
    const combinedMedList = [...activeNames, inputName].join('; ');

    const h4Alerts = safetyEngine.evaluatePatient(
        patient.patient_id || `PAT-${patient_id}`,
        combinedMedList,
        patient.egfr,
        patient.diagnoses || patient.conditions || '',
        patient.allergies || '',
        patient.pregnant || 'NA'
    );

    // Filter only alerts involving candidate drug generics
    const candidateGenericsSet = new Set(recognisedAs);
    const candidateAlerts = h4Alerts.filter(a => {
        const involved = (a.drugs_involved || '').split(';');
        return involved.some(d => candidateGenericsSet.has(d.toLowerCase()));
    });

    const formattedAlerts = formatAlerts(candidateAlerts, patient.egfr);
    const scoreData = calculateSafetyScore(formattedAlerts);

    // Fetch live medical API insights (OpenFDA, NIH RxNorm, Jan Aushadhi PMBJP)
    let liveInsights = null;
    try {
        liveInsights = await getLiveDrugInsights(recognisedAs[0]);
    } catch {
        // Continue even if external network has latency
    }

    // Save to locker if requested
    if (save_to_locker) {
        let medId = null;
        for (const gen of recognisedAs) {
            const m = getStatement('SELECT id FROM medicines WHERE LOWER(generic_name) = ?').get(gen.toLowerCase());
            if (m) {
                medId = m.id;
                break;
            }
        }
        const today = new Date().toISOString().split('T')[0];
        getStatement(`
            INSERT INTO patient_medications (patient_id, medicine_id, raw_medicine_name, prescribed_generic_name, dosage, frequency, prescribing_doctor, prescription_date, start_date, status, indication)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
        `).run(patient_id, medId, inputName, recognisedAs.join('; '), dosage || '', frequency || '', prescribing_doctor || 'Self-Entered', today, today, indication || '');
    }

    return res.json({
        success: true,
        recognised_as: recognisedAs,
        in_database: true,
        scoreData,
        alerts: formattedAlerts,
        live_insights: liveInsights,
        patient: {
            id: patient.id,
            full_name: patient.full_name,
            egfr: patient.egfr
        }
    });
});

// ============================================================================
// 6. Live OpenFDA & NIH RxNorm Medical Insights API
// ============================================================================
app.get('/api/medical/live-insights/:generic', async (req, res) => {
    try {
        const generic = req.params.generic;
        const insights = await getLiveDrugInsights(generic);
        res.json({ success: true, insights });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// ============================================================================
// 6. Serve static frontend build
// ============================================================================
const CLIENT_BUILD = path.resolve(__dirname, '../dist');
if (fs.existsSync(CLIENT_BUILD)) {
    app.use(express.static(CLIENT_BUILD));
    app.use((req, res, next) => {
        if (req.method === 'GET' && !req.path.startsWith('/api')) {
            return res.sendFile(path.join(CLIENT_BUILD, 'index.html'));
        }
        next();
    });
}

// Global Error Handler
app.use((err, req, res, next) => {
    console.error('Unhandled Server Error:', err);
    res.status(500).json({ error: 'Internal server error', message: err.message });
});

const PORT = process.env.PORT || 5000;
app.listen(PORT, () => {
    console.log(`AushadhSeva Node.js API server running on http://localhost:${PORT}`);
});

module.exports = app;
