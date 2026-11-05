/**
 * Embedded SQLite Database Layer for DoseWise using node:sqlite (DatabaseSync)
 * Includes pre-compiled statements and structured repository methods
 */

const { DatabaseSync } = require('node:sqlite');
const fs = require('fs');
const path = require('path');
const { parseCSV } = require('../pipeline/Resolver');

const DB_PATH = path.resolve(__dirname, 'dosewise.sqlite');
const db = new DatabaseSync(DB_PATH);

// Pre-compiled statement cache to avoid recompiling SQL strings on every request
const statementCache = new Map();

function getStatement(sql) {
    let stmt = statementCache.get(sql);
    if (!stmt) {
        stmt = db.prepare(sql);
        statementCache.set(sql, stmt);
    }
    return stmt;
}

function initDatabase(dataDir) {
    db.exec(`
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id INTEGER NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            full_name TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'citizen',
            phone TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS patients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id TEXT UNIQUE,
            patient_mrn TEXT,
            full_name TEXT NOT NULL,
            cohort TEXT DEFAULT 'train',
            age INTEGER,
            date_of_birth TEXT,
            sex TEXT,
            gender TEXT,
            weight_kg REAL,
            egfr REAL,
            pregnant TEXT DEFAULT 'NA',
            diagnoses TEXT,
            conditions TEXT,
            allergies TEXT,
            medication_list TEXT,
            prescriber_count INTEGER DEFAULT 1,
            is_near_miss_case INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS patient_medications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id INTEGER NOT NULL,
            medicine_id INTEGER,
            raw_medicine_name TEXT NOT NULL,
            prescribed_generic_name TEXT,
            dosage TEXT,
            dose_amount_mg REAL,
            frequency TEXT,
            route TEXT DEFAULT 'oral',
            prescribing_doctor TEXT,
            prescription_date TEXT,
            start_date TEXT,
            status TEXT DEFAULT 'active',
            indication TEXT,
            instructions TEXT
        );

        CREATE TABLE IF NOT EXISTS medicines (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            drug_id TEXT,
            generic_name TEXT NOT NULL,
            common_brands TEXT,
            drug_class TEXT,
            therapeutic_group TEXT,
            usual_adult_daily_dose_range TEXT,
            max_daily_dose_mg REAL,
            renal_dose_adjustment_required INTEGER DEFAULT 0,
            renal_rule TEXT,
            pregnancy_category TEXT,
            notes TEXT
        );

        CREATE TABLE IF NOT EXISTS combination_brands (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            brand_name TEXT NOT NULL,
            generic_components TEXT NOT NULL,
            strength_label TEXT
        );

        CREATE INDEX IF NOT EXISTS idx_patients_mrn ON patients (patient_mrn);
        CREATE INDEX IF NOT EXISTS idx_patients_egfr ON patients (egfr);
        CREATE INDEX IF NOT EXISTS idx_meds_patient_id ON patient_medications (patient_id, status);
        CREATE INDEX IF NOT EXISTS idx_medicines_generic ON medicines (generic_name);
    `);

    // Check if medicines are loaded
    const medCount = db.prepare('SELECT COUNT(*) as cnt FROM medicines').get();
    if (medCount.cnt === 0 && dataDir) {
        console.log('Populating medicines reference database...');
        const refPath = path.join(dataDir, 'drug_reference.csv');
        if (fs.existsSync(refPath)) {
            const rows = parseCSV(fs.readFileSync(refPath, 'utf8'));
            const stmt = db.prepare(`
                INSERT INTO medicines (drug_id, generic_name, common_brands, drug_class, therapeutic_group,
                    usual_adult_daily_dose_range, max_daily_dose_mg, renal_dose_adjustment_required, renal_rule, pregnancy_category, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            `);
            for (const r of rows) {
                stmt.run(
                    r.drug_id || '',
                    r.generic_name || '',
                    r.common_brands || '',
                    r.drug_class || '',
                    r.therapeutic_group || '',
                    r.usual_adult_daily_dose_range || '',
                    r.max_daily_dose_mg ? parseFloat(r.max_daily_dose_mg) : null,
                    r.renal_dose_adjustment_required === '1' || r.renal_dose_adjustment_required === 'true' ? 1 : 0,
                    r.renal_rule || '',
                    r.pregnancy_category || 'N',
                    r.notes || ''
                );
            }
        }

        // Populating combination brands
        const comboPath = path.join(dataDir, 'combination_brands.csv');
        if (fs.existsSync(comboPath)) {
            const rows = parseCSV(fs.readFileSync(comboPath, 'utf8'));
            const stmt = db.prepare('INSERT INTO combination_brands (brand_name, generic_components, strength_label) VALUES (?, ?, ?)');
            for (const r of rows) {
                stmt.run(r.brand_name || '', r.generic_components || '', r.strength_label || '');
            }
        }
    }

    // Check if demo patients are loaded
    const eleanorCheck = db.prepare("SELECT id FROM patients WHERE patient_id = 'DEMO-ELEANOR'").get();
    if (!eleanorCheck) {
        console.log('Seeding demo patients and user accounts...');
        
        // 1. Eleanor
        const insPat = db.prepare(`
            INSERT INTO patients (patient_id, patient_mrn, full_name, cohort, age, date_of_birth, sex, gender, weight_kg,
                egfr, pregnant, diagnoses, conditions, allergies, medication_list, prescriber_count, is_near_miss_case)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `);

        insPat.run('DEMO-ELEANOR', 'DEMO-ELEANOR', 'Eleanor Vance', 'train', 78, '1948-03-12', 'F', 'female', 58.0,
            28.0, 'NA', 'Type 2 diabetes; Hypertension', 'Type 2 diabetes; Hypertension', 'Penicillin',
            'Glycomet 500; Brufen 400; Telma 40', 2, 1);
        const eleanorId = db.prepare("SELECT id FROM patients WHERE patient_id = 'DEMO-ELEANOR'").get().id;

        const insMed = db.prepare(`
            INSERT INTO patient_medications (patient_id, raw_medicine_name, prescribed_generic_name, dosage, dose_amount_mg,
                frequency, route, prescribing_doctor, prescription_date, start_date, status, indication)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `);

        insMed.run(eleanorId, 'Glycomet 500', 'metformin', '500 mg', 500, 'Twice daily after food', 'oral', 'Dr. Rao (Diabetes clinic)', '2025-01-10', '2025-01-10', 'active', 'Sugar');
        insMed.run(eleanorId, 'Brufen 400', 'ibuprofen', '400 mg', 400, 'When needed for pain', 'oral', 'Dr. Reddy (Orthopaedics)', '2025-06-02', '2025-06-02', 'active', 'Knee pain');
        insMed.run(eleanorId, 'Telma 40', 'telmisartan', '40 mg', 40, 'Once daily morning', 'oral', 'Dr. Rao (Diabetes clinic)', '2025-01-10', '2025-01-10', 'active', 'BP');

        // 2. Robert
        insPat.run('DEMO-ROBERT', 'DEMO-ROBERT', 'Robert Chen', 'train', 71, '1955-08-20', 'M', 'male', 72.0,
            72.0, 'NA', 'Atrial fibrillation', 'Atrial fibrillation', '',
            'Warf 5; Ecosprin 75; Atorva 10', 2, 1);
        const robertId = db.prepare("SELECT id FROM patients WHERE patient_id = 'DEMO-ROBERT'").get().id;

        insMed.run(robertId, 'Warf 5', 'warfarin', '5 mg', 5, 'Once daily at night', 'oral', 'Dr. Iyer (Cardiology)', '2024-11-20', '2024-11-20', 'active', 'Irregular heartbeat');
        insMed.run(robertId, 'Ecosprin 75', 'aspirin', '75 mg', 75, 'Once daily', 'oral', 'Dr. Khan (Family doctor)', '2025-04-15', '2025-04-15', 'active', 'Heart');
        insMed.run(robertId, 'Atorva 10', 'atorvastatin', '10 mg', 10, 'Once daily at night', 'oral', 'Dr. Iyer (Cardiology)', '2024-11-20', '2024-11-20', 'active', 'Cholesterol');

        // 3. Grace
        insPat.run('DEMO-GRACE', 'DEMO-GRACE', 'Grace Hopper', 'train', 66, '1960-12-09', 'F', 'female', 61.0,
            95.0, 'NA', 'Hypertension', 'Hypertension', '',
            'Telma 40; Atorva 10', 1, 0);
        const graceId = db.prepare("SELECT id FROM patients WHERE patient_id = 'DEMO-GRACE'").get().id;

        insMed.run(graceId, 'Telma 40', 'telmisartan', '40 mg', 40, 'Once daily morning', 'oral', 'Dr. Khan (Family doctor)', '2025-02-01', '2025-02-01', 'active', 'BP');
        insMed.run(graceId, 'Atorva 10', 'atorvastatin', '10 mg', 10, 'Once daily at night', 'oral', 'Dr. Khan (Family doctor)', '2025-02-01', '2025-02-01', 'active', 'Cholesterol');

        // Users
        const insUser = db.prepare(`
            INSERT INTO users (patient_id, email, password_hash, full_name, role)
            VALUES (?, ?, ?, ?, ?)
        `);

        insUser.run(eleanorId, 'eleanor@example.com', 'citizen123', 'Eleanor Vance', 'citizen');
        insUser.run(robertId, 'robert@example.com', 'citizen123', 'Robert Chen', 'citizen');
        insUser.run(graceId, 'grace@example.com', 'citizen123', 'Grace Hopper', 'citizen');
        insUser.run(null, 'admin@aushadhseva.in', 'admin123', 'Dr. Alistair Sharma', 'admin');
        insUser.run(null, 'admin@dosewise.com', 'admin123', 'Dr. Alistair Sharma', 'admin');
        insUser.run(eleanorId, 'citizen1@example.com', 'citizen123', 'Eleanor Vance', 'citizen');
        insUser.run(robertId, 'citizen2@example.com', 'citizen123', 'Robert Chen', 'citizen');
        insUser.run(graceId, 'citizen3@example.com', 'citizen123', 'Grace Hopper', 'citizen');

        // Seed cohort patients from patients_train.csv if available
        if (dataDir) {
            const patCsv = path.join(dataDir, 'patients_train.csv');
            if (fs.existsSync(patCsv)) {
                const pRows = parseCSV(fs.readFileSync(patCsv, 'utf8'));
                for (let i = 0; i < Math.min(100, pRows.length); i++) {
                    const r = pRows[i];
                    try {
                        insPat.run(
                            r.patient_id,
                            r.patient_id,
                            `Patient ${r.patient_id}`,
                            'train',
                            r.age ? parseInt(r.age, 10) : 65,
                            `19${60 - (parseInt(r.age, 10) || 60) + 40}-05-15`,
                            r.sex || 'M',
                            r.sex === 'F' ? 'female' : 'male',
                            r.weight_kg ? parseFloat(r.weight_kg) : 65.0,
                            r.egfr_ml_min_1_73m2 ? parseFloat(r.egfr_ml_min_1_73m2) : null,
                            r.pregnant || 'NA',
                            r.diagnoses || '',
                            r.diagnoses || '',
                            r.allergies || '',
                            r.medication_list || '',
                            1,
                            0
                        );
                    } catch {
                        // ignore duplicates
                    }
                }
            }
        }
        console.log('Database seeded successfully.');
    }
}

module.exports = { db, initDatabase, getStatement };
