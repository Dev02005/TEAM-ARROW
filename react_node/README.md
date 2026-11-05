# DoseWise — React & Node.js Edition
> **A clinician-assisting medication safety platform and deterministic decision-support pipeline that reconciles messy multi-doctor longitudinal prescriptions, resolves brand names to canonical generics, and flags life-threatening conflicts with zero alert fatigue.**

---

## What was done
The application has been converted from native PHP/MariaDB to modern **React 19** and **Node.js 26** while **preserving 100% of the UI design, CSS stylesheets (`style.css`, `elder.css`), features, clinical decision logic, and trilingual support (English, Telugu, Hindi)**.

### Technology Stack
* **Frontend:** React 19 + Vite + Vanilla CSS (`style.css` & `elder.css`)
* **Backend:** Node.js (Express API server)
* **Embedded Database:** SQLite via native `node:sqlite` (`DatabaseSync` - zero external database dependencies or installations required)
* **Clinical Decision Engine:** Native Node.js ports of `Resolver.js` (Levenshtein + Indian brand normalizer) and `SafetyEngineH4.js` (4-domain deterministic CDS)
* **Speech & Accessibility:** Web Speech API (TTS) in Telugu (`te-IN`), Hindi (`hi-IN`), and English (`en-IN`)

---

## How to Run

### 1. Prerequisites
* **Node.js 22.5+** (tested and verified on Node.js v26.13.0).

### 2. Quick Start
From the `react_node/` directory:

```bash
# Run the combined backend server + pre-built React frontend:
npm start
```

The application will be served at:
**[http://localhost:5000](http://localhost:5000)**

### 3. Development Mode (with Hot Module Replacement)
If you wish to develop on React with Vite hot-reload:
```bash
# Start the Express API server (terminal 1):
node server/index.js

# Start the Vite development server (terminal 2):
npm run dev
```
Open [http://localhost:3000](http://localhost:3000) (API calls are automatically proxied to port 5000).

### 4. Running the Tests
To verify all clinical assertions and application features:
```bash
node test_full_app.js
```

---

## Demo Accounts (1-Click Login Supported)
On the Sign-In page (`/login`), judges and users can sign in with one click or with password `citizen123` / `admin123`:
* **Eleanor Vance** (`eleanor@example.com` / `citizen123`): Impaired kidney function (eGFR 28), diabetes + BP. Demonstrates 3 clinical safety warnings.
* **Robert Chen** (`robert@example.com` / `citizen123`): Multi-doctor Warfarin + Aspirin interaction.
* **Grace Hopper** (`grace@example.com` / `citizen123`): Clean prescription regimen with no warnings.
* **Dr. Alistair Sharma** (`admin@dosewise.com` / `admin123`): Clinical staff account with full patient cohort management and audit access.
