# TEAM-ARROW · AushadhSeva (औषध सेवा)
> **Inclusive Medication Safety, Longitudinal Prescription Reconciliation & Clinical Decision Support System.**  
> *Developed by Team Arrow (Dev02005) for the SEVA First Innovation Challenge 2026 (Healthcare Problem H4).*

---

## Quickstart (Run Web Application in 1 Minute)

AushadhSeva includes a zero-configuration, production-ready Node.js + React 19 web application:

```bash
# 1. Navigate to the web application directory
cd react_node

# 2. Install dependencies (if not already installed)
npm install

# 3. Start the application
npm start
```

Visit **[http://localhost:5000](http://localhost:5000)** in your browser!

* **Citizen Demo Accounts (Password: `citizen123`):**
  * `citizen1@example.com` (Eleanor — Renal Impairment, 3 Prescriptions)
  * `citizen2@example.com` (Robert — Cardiac & Antiplatelet Regimen)
  * `citizen3@example.com` (Grace — Chronic Respiratory & Diabetes Care)
* **Clinical Admin Account:** `admin@dosewise.com` / `admin123`

---

## Key Highlights

1. **Multilingual Access in 8 Indian Languages:** Instant, zero-latency UI translation and native speech synthesis for **English, Telugu (తెలుగు), Hindi (हिन्दी), Tamil (தமிழ்), Malayalam (മലയാളം), Kannada (ಕನ್ನಡ), Odia (ଓଡ଼ିଆ), and Urdu (اردو)** with full bidirectional (RTL) support.
2. **Live OpenFDA & NIH RxNorm Integration:** Live drug safety monographs, PMBJP Jan Aushadhi generic cost-savings analysis, black-box warnings, and active ingredient verification fetched directly alongside offline reference sets.
3. **Deterministic Clinical Safety Engine (H4):** Zero-hallucination decision support covering Drug–Drug Interactions (2,196 pairwise rules), Duplicate Therapies (74 pharmacological classes), Renal Dosing thresholds strictly mapped to laboratory eGFR (KDIGO), and Clinical Contraindications.
4. **Prescription & Blister Strip OCR Scanner:** Upload photos or camera captures of medicine strips, packaging, or clinic slips with instant OCR parsing.
5. **Elder-Friendly & Accessible UI:** WCAG AAA high-contrast layout, responsive typography scaling (`AA`), minimum 46px touch targets, mobile navigation bar, and one-click voice readout.

---

## Mandatory Clinical Safety Notice
> *“This system supports clinical decision-making and does not replace a qualified clinician. All alerts and recommendations require professional review.”*  
> *The application never automatically discontinues or alters patient prescriptions. It presents explainable evidence, confidence levels, and explicit data limitations for human-in-the-loop clinical review.*

---

## What it does

### The Problem
At Chandrapuri District Hospital, elderly polypharmacy patients consult up to three distinct prescribers (general physicians, cardiologists/specialists, and outpatient private practitioners) over two or more years. Ward nurses transcribe handwritten slips, old strips, and bottles into a consolidated text full of:
* Brand names (e.g., *Glycomet*, *Augmentin*, *Combiflam*)
* Combination products hiding multiple active ingredients (e.g., *Glycomet-GP 1* containing *glimepiride + metformin*)
* Misspellings and truncations (e.g., *croken* for *paracetamol*, *aspyrin* for *aspirin*, *pntp* for *pantoprazole*)
* Dosage form noise (*Tab*, *Cap*, *Syp*, *1-0-1*, *BD*, *OD*, *SOS*)
* Non-medicine lines (*BP charting*, *Glucometer strips*, *Physiotherapy*)

Without consolidated safety oversight, dangerous drug interactions, unintended duplicate therapies, severe renal overdoses, and disease contraindications slip through. Furthermore, doctors suffer from **alert fatigue** when overwhelmed with noisy, low-value warnings.

### The Solution: DoseWise
1. **Automated Token Resolver:** Normalizes handwritten text, filters non-drug tasks, isolates dosage noise, and maps single and multi-ingredient Indian brand names to canonical generic INN drugs with >96% accuracy.
2. **Clinical Decision Engine:** Deterministically checks active regimens against authoritative datasets across 4 strict domains:
   * **Drug–Drug Interactions:** 2,196 pairwise rules at verified severity grades (`contraindicated`, `major`, `moderate`, `minor`).
   * **Duplicate Therapies:** Detects identical generics across separate entries (single brand + combination brand) and class duplicates across 74 pharmacological groups. *Paracetamol duplication is strictly graded major.*
   * **Renal Dosing (eGFR):** Evaluates 169 renal tiers across 136 medicines strictly against laboratory eGFR thresholds. Missing eGFR suppresses renal alerts to prevent false positives.
   * **Clinical Contraindications:** Cross-checks conditions (accounting for diagnosis negations like "no h/o"), documented allergies, and pregnancy status (including FDA category X and D teratogen identification). Caps at at most one contraindication per drug.
3. **Citizen & Healthcare Experience:**
   * **Patient Chart & Longitudinal Prescribing Timeline:** Visualizes multi-doctor prescriptions spanning 2 years.
   * **Doctor Visit Safety Card / Medication Passport:** Printable high-contrast clinical summary for appointments.
   * **Interactive Prescription Analyzer:** Citizens can simulate adding a new medicine to check compatibility before taking it.
   * **496+ Everyday Medicines Formulary:** Interactive directory of all verified generic drugs and Indian brands.

---

## Languages and older users

* **8 Indian Languages:** English, Telugu (తెలుగు), Hindi (हिन्दी), Tamil (தமிழ்), Malayalam (മലയാളം), Kannada (ಕನ್ನಡ), Odia (ଓଡ଼ିଆ), and Urdu (اردو) with native right-to-left layout support. A language picker is accessible in the header and remembers preference in local storage.
* **Built for older citizens and accessibility:** high-contrast cards, buttons at least 46-52px tall, a fixed bottom navigation bar on mobile, an **A/A** text scaler (normal / large / extra large), and a 🔊 **Read aloud** button on every safety warning that uses native regional voices.
* **Plain-Language Warnings:** Each alert shows a visual severity badge (🔴 Danger, 🟠 Serious, 🟡 Caution, 🔵 Note), clear instructions (*what to do*), a patient-friendly explanation (*why*), and clinical pharmacological notes for prescribers.
* **Doctor Safety Card:** Printable, bilingual medication passport with emergency contacts, allergies, and active medicines for healthcare visits.
* **"We read this as …":** The prescription checker shows which generic the typed or scanned brand was recognised as, and clearly indicates unverified drugs.

---

## Project layout

```text
medication/                          ← project root
├── README.md                        ← project documentation & guide
├── .gitignore
├── .htaccess                        ← Apache / XAMPP rewrites
├── react_node/                      ← Modern Node.js + React 19 web application
│   ├── server/                      ← Express API server & SQLite database
│   ├── src/                         ← React 19 UI components & contexts
│   ├── locales/                     ← 8 localized dictionaries (364 keys each)
│   ├── dist/                        ← Pre-built production bundle
│   └── package.json                 ← Dependencies & launch scripts
├── FULL_CODE/                       ← H4 Decision Support Pipeline & PHP web app
│   ├── pipeline/                    ← Resolver, SafetyEngineH4, batch runner, scorer
│   ├── data/                        ← Reference datasets (drug_reference, interactions, etc.)
│   ├── database/                    ← Schema & data importer scripts
│   └── tests/                       ← Comprehensive clinical test suites
└── submission/
    ├── H4_predictions.csv           ← Test-set predictions (3,500 patients)
    └── H4_resolution.csv            ← Test-set medication-entry resolution
```

The submission zip is produced by `php FULL_CODE/scripts/package_submission.php --team <teamID>` and
contains exactly the structure above.  All CLI commands below are run from inside the `FULL_CODE/` folder.

---

## How to run

### Prerequisites
* **Web Server & Database:** PHP 8.1+ and MySQL / MariaDB (e.g., XAMPP, LAMP, or native PHP + MySQL).
* **Extensions:** `pdo_mysql`, `mbstring`, `json`, `pcre`, `zip` (zip is only needed for packaging).

### 1. Add the challenge data
The pack's data files are listed in `.gitignore` (per the coding guidelines), so they are not in this zip.
Copy the 11 CSV files from the H4 problem pack's `data/` folder into `FULL_CODE/data/`
(`drug_reference.csv`, `combination_brands.csv`, `interactions.csv`, `renal_rules.csv`, `duplicate_therapy_groups.csv`,
`drug_disease_contraindications.csv`, `conditions.csv`, `patients_train.csv`, `patients_test.csv`, `train_labels.csv`, `train_resolution.csv`).

### 2. Environment & Database Setup
1. Copy the environment configuration template:
   ```bash
   cp .env.example .env
   ```
2. Verify MySQL is running on port 3306 (default XAMPP credentials: user `root`, no password).
3. Initialize the schema and import all authoritative H4 datasets (496 drugs, 98 combinations, 2,196 interactions, 169 renal rules, 74 duplicate groups, 152 contraindications, and 5,000 patients):
   ```bash
   php database/import_h4_data.php
   ```
4. Add the demo patients behind the one-click logins on the sign-in page (Eleanor, Robert, Grace, staff):
   ```bash
   mysql -u root medication_safety < database/seed_demo_logins.sql
   ```
   On XAMPP for Windows use `C:\xampp\mysql\bin\mysql.exe` and run the command in Command Prompt.
5. Only if your database was imported before the schema gained the patient-profile columns:
   `mysql -u root medication_safety < database/migrations/001_patient_profile_columns.sql`

Settings (database, URL path, debug mode) are read from environment variables or `.env`; every setting is listed in `.env.example`. The database setup scripts in `database/` run from the command line only.

### 3. Access the Web Dashboard
* The project root already sits at `C:\xampp\htdocs\medication`. A root `.htaccess`
  transparently rewrites all web requests to `FULL_CODE/`, so the app is served at
  `/medication/` without any URL changes (see `config/config.php`, `BASE_URL`).
  If cloning fresh, place the project root as `C:\xampp\htdocs\medication` (the
  `FULL_CODE/` subfolder then contains all application code).
* Navigate in your browser to:
  [http://localhost/medication/](http://localhost/medication/)
* **Demo Citizen Logins (Password: `citizen123`):**
  * `citizen1@example.com`
  * `citizen2@example.com`
  * `citizen3@example.com`
* **Demo Clinical Admin:** `admin@dosewise.com` / `admin123`

### 4. Regenerate the Submission Files (CLI Pipeline)
The pipeline needs only PHP and the CSV files (no database). From inside `FULL_CODE/`:
```bash
php pipeline/run_h4_pipeline.php --split test --out ../submission
```
This rewrites `../submission/H4_predictions.csv` and `../submission/H4_resolution.csv` (the files at the top of the zip).
To validate their format against all challenge rules:
```bash
php pipeline/validate_submission.php
```

---

## How to run the tests

Run both core test suites (no database needed; each exits with code 1 if anything fails):
```bash
php tests/H4SafetyTest.php && php tests/I18nAndPatientViewTest.php
```
* `H4SafetyTest.php` — 15 tests: token resolution, fuzzy brand matching, combination expansion, non-drug filtering, drug interactions, duplicate therapy rules, renal thresholding, diagnosis negation and pregnancy contraindications.
* `I18nAndPatientViewTest.php` — 35 tests: Telugu/Hindi files match English (keys, placeholders, tags), HTML escaping of names, severity → plain-language mapping, kidney wording with and without an eGFR, the "never say safe" rule, and sign-up input validation (dates, email, password, lengths).

To run the Phase 1 web integration suite (database integrity, KDIGO calculations, authentication, and IDOR access control):
```bash
php test_phase1.php
```
*(44 of 44 tests pass with 0 failures).*

To evaluate benchmark performance on the 1,500 training patients:
```bash
php pipeline/run_h4_pipeline.php --split train --out submission_train
php pipeline/evaluate.php --submission submission_train/H4_predictions.csv --truth data/train_labels.csv
```

### Training-set score (verified with the official scorer)
The same training output was also scored with the organisers' official `evaluate.py` from the H4 pack
(`python evaluate.py --submission submission_train/H4_predictions.csv --truth data/train_labels.csv`).
It gives exactly the same numbers as our PHP scorer:

| Metric | Score |
|---|---|
| **Primary score** | **0.8177** |
| Serious-issue recall | 0.8975 |
| **Near-miss recall** | **0.7556** |
| Precision (all alerts) | 0.9598 |
| Graded recall (severity correct) | 0.9225 |
| Resolution accuracy | 0.9648 |
| **Resolution accuracy on misspelled entries** | **0.8267** |
| Alert-fatigue multiplier | 0.9092 |

Compared with the pack's own baseline (`baseline/baseline.py`, exact-name lookup + interaction table only), scored the same way:

| Metric | Baseline | DoseWise |
|---|---|---|
| **Primary score** | 0.4071 | **0.8177** |
| Serious-issue recall | 0.2954 | 0.8975 |
| Near-miss recall | 0.0000 | 0.7556 |
| Precision | 0.9263 | 0.9598 |
| Resolution accuracy | 0.7787 | 0.9648 |
| Resolution on misspelled entries | 0.0000 | 0.8267 |

### Packaging the submission zip
From the development folder (flat layout with `submission/` next to the code):
```bash
php scripts/package_submission.php --team <teamID>
```
This builds `<teamID>_H4.zip` with the required layout shown above.

---

## Architecture

```text
                                  ┌────────────────────────┐
                                  │ Nurse Transcribed Text │
                                  └───────────┬────────────┘
                                              │
                                              ▼
                             ┌──────────────────────────────────┐
                             │       Resolver (Resolver.php)    │
                             │  * Tokenizer & Noise Cleaner     │
                             │  * Combination Brand Expander    │
                             │  * Indian Brand / Typo Matcher   │
                             └────────────────┬─────────────────┘
                                              │
                      ┌───────────────────────┴───────────────────────┐
                      │                                               │
                      ▼                                               ▼
          [ Active Medication Regimen ]                   [ Stopped / Non-Drug Tokens ]
                      │                                               │
                      ▼                                               ▼
         ┌─────────────────────────┐                     (Excluded from safety engine)
         │  Safety Engine          │
         │  (SafetyEngineH4.php)   │
         │  * DDI Checking         │
         │  * Duplicate Therapy    │
         │  * Renal Dosing (eGFR)  │
         │  * Contraindications    │
         └────────────┬────────────┘
                      │
         ┌────────────┴────────────┐
         │                         │
         ▼                         ▼
┌──────────────────┐      ┌─────────────────────────────┐
│  Web Application │      │  Official Test Submissions  │
│  (PHP / MariaDB) │      │  * H4_predictions.csv       │
│  * Citizen Locker│      │  * H4_resolution.csv        │
│  * Doctor Card   │      └─────────────────────────────┘
│  * Formulary     │
└──────────────────┘
```

### Components
* `pipeline/Resolver.php`: High-accuracy tokenizer and brand-to-generic mapper (96.48% resolution accuracy).
* `pipeline/SafetyEngineH4.php`: Core decision support engine executing deterministic checks.
* `pipeline/validate_submission.php`: Format validator for the submission files (same rules as `evaluate.py`).
* `pipeline/run_h4_pipeline.php`: Batch runner processing 3,500 test patients in under 8 seconds.
* `pipeline/evaluate.php`: Official scorer implementing exact evaluation formula and alert fatigue metrics.
* `models/SafetyEngine.php`: Real-time web integration providing clinical rationale, confidence, and limitations.
* `medicines.php`: Formulary interface for 496 medicines and 98 combinations.
* `patients.php` & `patient_view.php`: Clinical cohort management and longitudinal prescribing timeline.
* `attach_prescription.php`: Citizen prescription scanner with safety score percentage calculation.

---

## AI usage
* **AI Coding Assistants:**
  * Antigravity AI coding assistant: architecture drafting, Levenshtein distance resolver optimization, and test case generation.
  * Claude (Anthropic): the English/Telugu/Hindi interface and translations, the elder-friendly mobile layout and read-aloud, fixes to the medicine-check flow and sign-up, the review against the TalentAP coding guidelines, and `tests/I18nAndPatientViewTest.php`. The Telugu and Hindi texts were machine-written and should be reviewed by native speakers.
* **LLM APIs & Cloud Models:** Not required for the core runtime pipeline. The solution intentionally employs a **deterministic, explainable rule engine** to guarantee 100% reproducible, zero-hallucination clinical decision support with sub-second execution latency.

---

## Pre-built and third-party code
* Built from scratch using native PHP 8.2 and MariaDB (XAMPP).
* No external heavy frameworks or proprietary dependencies, so there is no package lockfile: the app uses only PHP's bundled extensions. Standard vanilla CSS and JavaScript for responsive, accessible UI components.
* Fonts loaded from Google Fonts: Inter, JetBrains Mono, Noto Sans Telugu and Noto Sans Devanagari (all SIL Open Font License 1.1).
* Read aloud uses the browser's built-in Web Speech API and the voices installed on the phone or computer.
* Official challenge datasets provided under CC BY 4.0 for the TalentAP Mega Hackathon 2026.

---

## Limitations
* **Simplified Clinical Scope:** Reference rules and interactions represent a simplified hackathon subset; they must not be used for real patient treatment.
* **Lack of Trimester Data:** As specified in the problem statement, pregnancy safety checks evaluate general Category X/D and listed contraindications without gestational trimester staging.
* **Lab Data Incompleteness:** eGFR is treated as a point-in-time value. Missing lab entries correctly suppress renal alerts to prevent alert fatigue, but clinical follow-up is recommended.
* **Translations:** Telugu and Hindi were written with an AI assistant and need review by native speakers before real use. Clinical notes from the reference tables stay in English (for doctors).
* **Read aloud** needs a Telugu/Hindi voice on the device (Android: Google speech engine voice data; Windows: Microsoft Edge). Without one, the app shows how to add it instead of reading with the wrong voice.
* **Photo of a prescription** is shown to help the person read the name; it is not read automatically (no OCR yet).
* **Fuzzy matching can pick the wrong medicine** for names outside the Indian reference list (e.g. the US brand "Lipitor" matches lamotrigine). The check page therefore always shows "We read this as: …" so the person can spot it.
* **One-click demo sign-in** exists for judging only and is limited to the four demo accounts; remove it for any real deployment.
* **Self-Reported Regimens:** The system assesses what is transcribed; unrecorded over-the-counter supplements or herbal remedies cannot be evaluated. A qualified healthcare provider always makes the final prescription decision.
