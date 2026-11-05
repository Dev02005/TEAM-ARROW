/**
 * Clinical Decision Support & Scoring Helpers (Node.js port of helpers.php & models/SafetyEngine.php)
 */

function getEgfrStage(egfr) {
    if (egfr === null || egfr === undefined || egfr === '') {
        return {
            stage: 'Unknown',
            description: 'Kidney function (eGFR) not recorded',
            label: 'eGFR Not Recorded',
            class: 'badge-neutral',
            severity: 'none'
        };
    }
    const val = parseFloat(egfr);
    if (val >= 90) {
        return {
            stage: 'G1',
            description: 'Normal or high kidney function (≥90 mL/min/1.73m²)',
            label: 'Normal Kidney Function (≥90 mL/min/1.73m²)',
            class: 'badge-success',
            severity: 'none'
        };
    }
    if (val >= 60) {
        return {
            stage: 'G2',
            description: 'Mildly decreased kidney function (60-89 mL/min/1.73m²)',
            label: 'Mildly Decreased (60-89 mL/min/1.73m²)',
            class: 'badge-info',
            severity: 'mild'
        };
    }
    if (val >= 45) {
        return {
            stage: 'G3a',
            description: 'Mildly to moderately decreased kidney function (45-59 mL/min/1.73m²)',
            label: 'Mild-Moderate Impairment (45-59 mL/min/1.73m²)',
            class: 'badge-warning',
            severity: 'moderate'
        };
    }
    if (val >= 30) {
        return {
            stage: 'G3b',
            description: 'Moderately to severely decreased kidney function (30-44 mL/min/1.73m²)',
            label: 'Moderate-Severe Impairment (30-44 mL/min/1.73m²)',
            class: 'badge-warning',
            severity: 'moderate'
        };
    }
    if (val >= 15) {
        return {
            stage: 'G4',
            description: 'Severely decreased kidney function (15-29 mL/min/1.73m²)',
            label: 'Severe Impairment (15-29 mL/min/1.73m²)',
            class: 'badge-danger',
            severity: 'severe'
        };
    }
    return {
        stage: 'G5',
        description: 'Kidney failure (<15 mL/min/1.73m²)',
        label: 'Kidney Failure (<15 mL/min/1.73m²)',
        class: 'badge-critical',
        severity: 'critical'
    };
}

function calculateSafetyScore(alerts) {
    if (!alerts || alerts.length === 0) {
        return {
            score: 98,
            label: 'No problems found in what was checked',
            summary: 'No interaction, duplicate or kidney risk was found in the medicines checked. Keep taking them as prescribed. This check only covers what you entered, not unentered medicines or supplements.',
            severity: 'clean',
            deductions: []
        };
    }

    let score = 100;
    const deductions = [];
    const counts = { contraindicated: 0, major: 0, moderate: 0, minor: 0 };

    for (const a of alerts) {
        const sev = (a.severity || '').toLowerCase();
        if (sev === 'contraindicated' || sev === 'critical') {
            counts.contraindicated++;
        } else if (sev === 'major' || sev === 'high') {
            counts.major++;
        } else if (sev === 'moderate') {
            counts.moderate++;
        } else {
            counts.minor++;
        }
    }

    if (counts.contraindicated > 0) {
        const penalty = Math.min(80, counts.contraindicated * 70);
        score -= penalty;
        deductions.push({
            reason: `${counts.contraindicated} contraindicated risk(s) flagged`,
            amount: penalty
        });
    }

    if (counts.major > 0) {
        const penalty = Math.min(60, counts.major * 30);
        score -= penalty;
        deductions.push({
            reason: `${counts.major} major clinical conflict(s)`,
            amount: penalty
        });
    }

    if (counts.moderate > 0) {
        const penalty = Math.min(30, counts.moderate * 15);
        score -= penalty;
        deductions.push({
            reason: `${counts.moderate} moderate interaction(s)`,
            amount: penalty
        });
    }

    if (counts.minor > 0) {
        const penalty = Math.min(10, counts.minor * 5);
        score -= penalty;
        deductions.push({
            reason: `${counts.minor} minor precaution(s)`,
            amount: penalty
        });
    }

    score = Math.max(10, Math.min(95, score));

    let topSev = 'minor';
    let label = 'Minor Precautions Noted';
    let summary = 'Minor potential interactions or precautions detected. Review with your pharmacist.';

    if (counts.contraindicated > 0) {
        topSev = 'contraindicated';
        label = 'Severe Clinical Conflict (Contraindicated)';
        summary = 'One or more medications carry strict clinical contraindications with your documented health profile. Consult your doctor immediately.';
    } else if (counts.major > 0) {
        topSev = 'major';
        label = 'Major Safety Risk Detected';
        summary = 'Potentially dangerous drug interaction, duplicate therapy, or renal cutoff exceeded. Clinician review required.';
    } else if (counts.moderate > 0) {
        topSev = 'moderate';
        label = 'Moderate Clinical Precaution';
        summary = 'Moderate drug interactions or dose adjustments required. Mention to your doctor at your next visit.';
    }

    return {
        score,
        label,
        summary,
        severity: topSev,
        deductions
    };
}

function formatAlerts(h4Alerts, egfr) {
    const formatted = [];
    const sevOrder = { 'contraindicated': 4, 'major': 3, 'moderate': 2, 'minor': 1 };

    for (const a of h4Alerts) {
        const sev = a.severity;
        const type = a.issue_type;
        const drugs = a.drugs_involved;

        let title = '';
        let whyFlagged = '';

        if (type === 'interaction') {
            title = 'Drug-Drug Interaction: ' + drugs.split(';').map(d => d.charAt(0).toUpperCase() + d.slice(1)).join(' + ');
            whyFlagged = `Active concurrent medications (${drugs}) interact pharmacologically per verified Chandrapuri District Hospital clinical references.`;
        } else if (type === 'duplicate_therapy') {
            title = 'Duplicate Therapy: ' + drugs.split(';').map(d => d.charAt(0).toUpperCase() + d.slice(1)).join(' & ');
            whyFlagged = `The patient has multiple active prescriptions sharing the same active generic identity or duplicate therapeutic class (${drugs}).`;
        } else if (type === 'renal_dose') {
            title = 'Kidney Function / Renal Dosing Risk: ' + drugs.charAt(0).toUpperCase() + drugs.slice(1);
            whyFlagged = `Patient's kidney filtration rate (eGFR: ${egfr !== null && egfr !== undefined ? egfr + ' mL/min' : 'Unknown'}) is below the safe prescribing cutoff for ${drugs}.`;
        } else if (type === 'contraindication') {
            title = 'Clinical Contraindication: ' + drugs.charAt(0).toUpperCase() + drugs.slice(1);
            whyFlagged = `This medication (${drugs}) is contraindicated with documented clinical conditions, pregnancy status, or patient allergy history.`;
        } else {
            title = 'Safety Warning: ' + drugs;
            whyFlagged = 'Clinical conflict flagged by deterministic safety knowledge base.';
        }

        formatted.push({
            severity: sev,
            alert_type: type,
            medicines_involved: drugs.split(';').map(d => d.charAt(0).toUpperCase() + d.slice(1)).join(' & '),
            problem: title,
            why_flagged: whyFlagged,
            recommended_action: a.suggested_action,
            evidence: 'TalentAP 2026 Authoritative Clinical Reference Tables (interactions, renal rules, duplicate groups, contraindications).',
            confidence: Math.round((a.confidence || 0.95) * 100) + '% (Verified rule-engine match)',
            limitations: 'Assessment based solely on reviewed entries and recorded patient parameters. Unrecorded OTC drugs, supplements, or recent unentered lab tests are not accounted for. A clinician must decide.',
            rule_code: type.substring(0, 3).toUpperCase() + '-' + Buffer.from(drugs).toString('hex').substring(0, 6)
        });
    }

    formatted.sort((x, y) => (sevOrder[y.severity] || 0) - (sevOrder[x.severity] || 0));
    return formatted;
}

module.exports = { getEgfrStage, calculateSafetyScore, formatAlerts };
