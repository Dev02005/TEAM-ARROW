import React, { useState, useEffect } from 'react';
import { useI18n } from '../context/I18nContext';
import {
  PrinterIcon,
  KidneyIcon,
  ClipboardListIcon,
  AlertTriangleIcon,
  BabyIcon,
  ShieldAlertIcon,
  CalendarIcon,
  TargetIcon,
  getSeverityIcon
} from '../components/Icons';

export default function PatientViewPage({ patientId, navigate }) {
  const { t } = useI18n();

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Modal for adding a prescription
  const [showAddModal, setShowAddModal] = useState(false);
  const [newMedName, setNewMedName] = useState('');
  const [newDosage, setNewDosage] = useState('');
  const [newFreq, setNewFreq] = useState('');
  const [newDoc, setNewDoc] = useState('');
  const [newIndication, setNewIndication] = useState('');
  const [addLoading, setAddLoading] = useState(false);

  const fetchPatient = () => {
    setLoading(true);
    fetch(`/api/patients/${patientId}`)
      .then(res => {
        if (!res.ok) throw new Error('Patient not found');
        return res.json();
      })
      .then(d => {
        setData(d);
        setLoading(false);
      })
      .catch(err => {
        setError(err.message);
        setLoading(false);
      });
  };

  useEffect(() => {
    fetchPatient();
  }, [patientId]);

  const handleAddMedication = async (e) => {
    e.preventDefault();
    if (!newMedName.trim()) return;

    setAddLoading(true);
    try {
      const res = await fetch(`/api/patients/${patientId}/medications`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          raw_medicine_name: newMedName.trim(),
          dosage: newDosage,
          frequency: newFreq,
          prescribing_doctor: newDoc,
          indication: newIndication
        })
      });
      if (!res.ok) throw new Error('Failed to add medication');
      setShowAddModal(false);
      setNewMedName('');
      setNewDosage('');
      setNewFreq('');
      setNewDoc('');
      setNewIndication('');
      fetchPatient();
    } catch (err) {
      alert(err.message);
    } finally {
      setAddLoading(false);
    }
  };

  const handleDiscontinue = async (medId) => {
    if (!window.confirm('Discontinue this prescription?')) return;
    try {
      await fetch(`/api/patients/${patientId}/medications/${medId}`, { method: 'DELETE' });
      fetchPatient();
    } catch (err) {
      alert(err.message);
    }
  };

  if (loading) {
    return (
      <div className="container" style={{ padding: '60px 16px', textAlign: 'center' }}>
        <p style={{ color: '#64748b', fontSize: '18px' }}>Loading comprehensive patient chart...</p>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="container" style={{ padding: '32px 16px' }}>
        <div className="alert alert-danger" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <AlertTriangleIcon size={18} color="#dc2626" />
          <span>{error || 'Patient could not be loaded.'}</span>
        </div>
        <button className="btn btn-secondary" onClick={() => navigate('/patients')}>&larr; Back to Patients</button>
      </div>
    );
  }

  const { patient, activeMedications, timelineMedications, alerts, scoreData, egfrStage } = data;

  return (
    <div className="ez-page">
      {/* Top action bar */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', flexWrap: 'wrap', gap: '10px' }}>
        <button type="button" onClick={() => navigate('/patients')} className="btn btn-secondary btn-sm">
          &larr; Back to Patient List
        </button>
        <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
          <button
            type="button"
            onClick={() => navigate(`/print-safety-card/${patient.id}`)}
            className="btn btn-secondary btn-sm"
            style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
          >
            <PrinterIcon size={15} />
            <span>Print Doctor Safety Card</span>
          </button>
          <button type="button" onClick={() => setShowAddModal(true)} className="btn btn-primary btn-sm">
            + Prescribe Medication
          </button>
        </div>
      </div>

      {/* Profile Header */}
      <div className="patient-profile-header" style={{ marginBottom: '24px', background: '#ffffff', borderRadius: '18px', padding: '24px', border: '1px solid #e2e8f0' }}>
        <div className="profile-top-row">
          <div className="profile-main-info" style={{ display: 'flex', alignItems: 'center', gap: '18px', flexWrap: 'wrap' }}>
            <div className="profile-avatar" style={{ width: '64px', height: '64px', borderRadius: '16px', background: 'linear-gradient(135deg, #0284c7, #0d9488)', color: 'white', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '26px', fontWeight: 800 }}>
              {(patient.full_name || 'P').charAt(0).toUpperCase()}
            </div>
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }}>
                <h2 className="profile-name" style={{ margin: 0, fontSize: '24px', fontWeight: 800 }}>{patient.full_name}</h2>
                <span className="badge badge-neutral" style={{ fontFamily: 'monospace' }}>
                  Record ID: {patient.patient_mrn || patient.patient_id}
                </span>
                {patient.is_near_miss_case ? (
                  <span className="badge badge-warning" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
                    <TargetIcon size={13} color="#ea580c" />
                    <span>Demonstration Case</span>
                  </span>
                ) : null}
              </div>
              <div className="profile-meta-chips" style={{ marginTop: '6px', color: '#64748b', fontSize: '14.5px', display: 'flex', flexWrap: 'wrap', gap: '8px', alignItems: 'center' }}>
                <span>Age: <strong style={{ color: '#0f172a' }}>{patient.age || '-'}</strong></span>
                <span>&bull;</span>
                <span>Sex: <strong style={{ color: '#0f172a' }}>{patient.gender ? patient.gender.toUpperCase() : (patient.sex || '-')}</strong></span>
                <span>&bull;</span>
                <span>Care team: <strong style={{ color: '#0f172a' }}>{patient.distinct_doctors || 1} Doctor(s)</strong></span>
                <span>&bull;</span>
                <span>Weight: <strong style={{ color: '#0f172a' }}>{patient.weight_kg ? `${patient.weight_kg} kg` : '-'}</strong></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Clinical Status Grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '16px', marginBottom: '24px' }}>
        <div className="card" style={{ borderRadius: '14px', padding: '18px' }}>
          <span style={{ fontSize: '12px', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: '6px' }}>
            <KidneyIcon size={16} color="#0284c7" />
            <span>Kidney Function (KDIGO)</span>
          </span>
          <div style={{ marginTop: '8px' }}>
            <span className={`badge ${egfrStage.class}`} style={{ fontSize: '13px', padding: '4px 10px' }}>
              Stage {egfrStage.stage}
            </span>
            <div style={{ marginTop: '4px', fontSize: '13.5px', color: '#1e293b' }}>
              eGFR: <strong>{patient.egfr !== null ? `${patient.egfr} mL/min` : 'Unknown'}</strong>
            </div>
            <small style={{ color: '#64748b' }}>{egfrStage.description}</small>
          </div>
        </div>

        <div className="card" style={{ borderRadius: '14px', padding: '18px' }}>
          <span style={{ fontSize: '12px', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: '6px' }}>
            <ClipboardListIcon size={16} color="#0284c7" />
            <span>Documented Conditions</span>
          </span>
          <div style={{ marginTop: '8px', fontSize: '14px', fontWeight: 600, color: '#0f172a' }}>
            {patient.conditions || patient.diagnoses || <span style={{ color: '#94a3b8' }}>None documented</span>}
          </div>
        </div>

        <div className="card" style={{ borderRadius: '14px', padding: '18px' }}>
          <span style={{ fontSize: '12px', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: '6px' }}>
            <AlertTriangleIcon size={16} color="#dc2626" />
            <span>Known Allergies</span>
          </span>
          <div style={{ marginTop: '8px', fontSize: '14px', fontWeight: 600, color: patient.allergies ? '#dc2626' : '#16a34a' }}>
            {patient.allergies || <span>No known drug allergies (NKDA)</span>}
          </div>
        </div>

        <div className="card" style={{ borderRadius: '14px', padding: '18px' }}>
          <span style={{ fontSize: '12px', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: '6px' }}>
            <BabyIcon size={16} color="#0284c7" />
            <span>Pregnancy Status</span>
          </span>
          <div style={{ marginTop: '8px', fontSize: '14px', fontWeight: 600, color: '#0f172a' }}>
            {patient.pregnant === 'Y' ? (
              <span className="badge badge-warning">Currently Pregnant</span>
            ) : (
              <span>Not Pregnant / Not Applicable</span>
            )}
          </div>
        </div>
      </div>

      {/* Safety Evaluation Summary */}
      <div className={`ez-result ez-result--${scoreData.severity || 'clean'}`} style={{ marginBottom: '24px' }}>
        <div className="ez-result__head">
          <div>
            <span className="ez-level-tag" style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
              {getSeverityIcon(scoreData.severity, 18)}
              <span>{scoreData.severity.toUpperCase()}</span>
            </span>
            <h4 className="ez-result__title">{scoreData.label}</h4>
          </div>
          <div className="ez-score">
            <span className="ez-score__num">{scoreData.score === 'NA' ? 'NA' : `${scoreData.score}%`}</span>
            <span className="ez-score__label">{t('common.safety_score')}</span>
          </div>
        </div>
        <p style={{ margin: '14px 0', fontSize: '15.5px', color: '#1e293b' }}>
          {scoreData.summary}
        </p>
      </div>

      {/* Explainable Decision Support Alerts */}
      {alerts && alerts.length > 0 && (
        <div style={{ marginBottom: '32px' }}>
          <h3 style={{ fontSize: '20px', fontWeight: 800, marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <ShieldAlertIcon size={22} color="#dc2626" />
            <span>Detected Clinical Conflicts ({alerts.length})</span>
          </h3>
          {alerts.map((a, i) => (
            <div key={i} className="card" style={{ marginBottom: '16px', borderRadius: '14px', borderLeft: `5px solid ${a.severity === 'contraindicated' ? '#dc2626' : a.severity === 'major' ? '#ea580c' : a.severity === 'moderate' ? '#eab308' : '#3b82f6'}` }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '8px' }}>
                <div>
                  <span className={`badge ${a.severity === 'contraindicated' ? 'badge-critical' : a.severity === 'major' ? 'badge-danger' : a.severity === 'moderate' ? 'badge-warning' : 'badge-info'}`}>
                    {a.severity.toUpperCase()}
                  </span>
                  <h4 style={{ margin: '8px 0 4px', fontSize: '17px', color: '#0f172a' }}>{a.problem}</h4>
                  <div style={{ color: '#64748b', fontSize: '13px' }}>Rule Code: <code>{a.rule_code}</code> &bull; Confidence: {a.confidence}</div>
                </div>
              </div>
              <div style={{ marginTop: '12px', background: '#f8fafc', padding: '12px', borderRadius: '8px' }}>
                <strong style={{ color: '#0f172a' }}>Recommended Clinical Action:</strong>
                <p style={{ margin: '4px 0 0', color: '#334155' }}>{a.recommended_action}</p>
              </div>
              <div style={{ marginTop: '10px', fontSize: '13px', color: '#64748b' }}>
                <strong>Evidence:</strong> {a.evidence}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Longitudinal Prescribing Timeline */}
      <div className="card" style={{ marginBottom: '32px', borderRadius: '16px', padding: '24px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px', flexWrap: 'wrap', gap: '8px' }}>
          <div>
            <h3 style={{ margin: 0, fontSize: '18px', fontWeight: 800, display: 'flex', alignItems: 'center', gap: '8px' }}>
              <CalendarIcon size={20} color="#0284c7" />
              <span>Multi-Doctor Longitudinal Prescribing Timeline</span>
            </h3>
            <p style={{ margin: '2px 0 0', fontSize: '13px', color: '#64748b' }}>
              Consolidated medication history across all hospital visits and outpatient prescribers
            </p>
          </div>
          <button type="button" onClick={() => setShowAddModal(true)} className="btn btn-primary btn-sm">
            + Add Medicine
          </button>
        </div>

        {timelineMedications.length === 0 ? (
          <p style={{ color: '#64748b' }}>No prescription history recorded.</p>
        ) : (
          <div className="responsive-table-container">
            <table className="data-table" style={{ width: '100%', minWidth: '680px' }}>
              <thead>
                <tr>
                  <th>Prescription Date</th>
                  <th>Prescribing Doctor</th>
                  <th>Written Medicine Name</th>
                  <th>Resolved Generic Equivalent</th>
                  <th>Dosage / Instructions</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {timelineMedications.map(med => (
                  <tr key={med.id}>
                    <td><code>{med.prescription_date || '-'}</code></td>
                    <td><strong>{med.prescribing_doctor || 'Unknown Prescriber'}</strong></td>
                    <td>{med.raw_medicine_name}</td>
                    <td>
                      <span className="badge badge-neutral">
                        {med.matched_generic || med.prescribed_generic_name || 'Unverified'}
                      </span>
                    </td>
                    <td>
                      <div>{med.dosage || '-'}</div>
                      <small style={{ color: '#64748b' }}>{med.frequency || '-'}</small>
                    </td>
                    <td>
                      <span className={`badge ${med.status === 'active' ? 'badge-success' : 'badge-neutral'}`}>
                        {med.status.toUpperCase()}
                      </span>
                    </td>
                    <td>
                      {med.status === 'active' && (
                        <button
                          type="button"
                          onClick={() => handleDiscontinue(med.id)}
                          className="btn btn-secondary btn-sm"
                          style={{ color: '#ef4444', borderColor: '#fca5a5' }}
                        >
                          Discontinue
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Modal to Prescribe Medication (Spring Animated) */}
      {showAddModal && (
        <div
          className="modal-backdrop-spring"
          onClick={(e) => { if (e.target === e.currentTarget) setShowAddModal(false); }}
          style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            background: 'rgba(15, 23, 42, 0.65)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 4000,
            padding: '16px'
          }}
        >
          <div
            className="card modal-dialog-spring"
            style={{
              maxWidth: 'min(94vw, 540px)',
              width: '100%',
              maxHeight: '90vh',
              overflowY: 'auto',
              borderRadius: '16px',
              padding: 'clamp(16px, 4vw, 24px)',
              boxShadow: 'var(--shadow-popover)'
            }}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
              <h3 style={{ margin: 0, fontSize: '18px', fontWeight: 800 }}>Prescribe New Medication</h3>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setShowAddModal(false)}
                aria-label="Close modal"
              >
                &times;
              </button>
            </div>
            <form onSubmit={handleAddMedication}>
              <div className="form-group">
                <label className="form-label">Medicine Name (Brand or Generic) *</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="e.g. Pantodac 40mg / Warfarin 5mg"
                  value={newMedName}
                  onChange={(e) => setNewMedName(e.target.value)}
                  required
                />
              </div>
              <div className="form-group" style={{ marginTop: '12px' }}>
                <label className="form-label">Dosage</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="e.g. 500 mg"
                  value={newDosage}
                  onChange={(e) => setNewDosage(e.target.value)}
                />
              </div>
              <div className="form-group" style={{ marginTop: '12px' }}>
                <label className="form-label">Frequency / Directions</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="e.g. 1-0-1 after food"
                  value={newFreq}
                  onChange={(e) => setNewFreq(e.target.value)}
                />
              </div>
              <div className="form-group" style={{ marginTop: '12px' }}>
                <label className="form-label">Prescribing Doctor / Department</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="e.g. Dr. Verma (Nephrology)"
                  value={newDoc}
                  onChange={(e) => setNewDoc(e.target.value)}
                />
              </div>
              <div className="form-group" style={{ marginTop: '12px' }}>
                <label className="form-label">Clinical Indication</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="e.g. Hypertension control"
                  value={newIndication}
                  onChange={(e) => setNewIndication(e.target.value)}
                />
              </div>
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', marginTop: '20px' }}>
                <button type="button" onClick={() => setShowAddModal(false)} className="btn btn-secondary">Cancel</button>
                <button type="submit" className="btn btn-primary" disabled={addLoading}>
                  {addLoading ? 'Saving...' : 'Add to Regimen'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
