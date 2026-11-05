import React, { useState, useEffect } from 'react';
import { useI18n } from '../context/I18nContext';
import {
  PrinterIcon,
  AlertTriangleIcon,
  CheckCircleIcon
} from '../components/Icons';

export default function PrintSafetyCardPage({ patientId, navigate }) {
  const { t } = useI18n();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch(`/api/patients/${patientId}`)
      .then(res => res.json())
      .then(d => {
        setData(d);
        setLoading(false);
      })
      .catch(() => setLoading(false));
  }, [patientId]);

  if (loading) {
    return <div style={{ textAlign: 'center', padding: '40px' }}>Loading card...</div>;
  }

  if (!data || !data.patient) {
    return <div style={{ padding: '20px' }}>Patient not found.</div>;
  }

  const { patient, activeMedications, alerts, scoreData, egfrStage } = data;

  return (
    <div style={{ background: '#f8fafc', minHeight: '100vh', padding: '24px 16px' }}>
      {/* Screen-only action toolbar */}
      <div className="no-print" style={{ maxWidth: '860px', margin: '0 auto 16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <button type="button" onClick={() => navigate(-1)} className="btn btn-secondary btn-sm">
          &larr; Back
        </button>
        <button
          type="button"
          onClick={() => window.print()}
          className="btn btn-primary btn-sm"
          style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
        >
          <PrinterIcon size={16} />
          <span>Print Safety Card</span>
        </button>
      </div>

      <div className="passport-card" style={{ maxWidth: '860px', margin: '0 auto', background: 'white', border: '2px solid #cbd5e1', borderRadius: '12px', padding: '32px', boxShadow: '0 4px 12px rgba(0,0,0,0.05)' }}>
        {/* Header */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', borderBottom: '3px solid #0284c7', paddingBottom: '16px', marginBottom: '24px', flexWrap: 'wrap', gap: '12px' }}>
          <div>
            <div style={{ fontSize: '13px', fontWeight: 800, color: '#0284c7', letterSpacing: '0.05em', textTransform: 'uppercase' }}>
              Chandrapuri District Hospital &bull; Clinical Safety Passport
            </div>
            <h1 style={{ margin: '4px 0 0', fontSize: '26px', color: '#0f172a' }}>
              {t('card.title')} <small style={{ fontSize: '14px', color: '#64748b' }}>/ Doctor Visit Safety Card</small>
            </h1>
          </div>
          <div style={{ textAlign: 'right' }}>
            <div style={{ fontSize: '22px', fontWeight: 800, color: scoreData.severity === 'clean' ? '#16a34a' : '#ea580c' }}>
              {scoreData.score === 'NA' ? 'Score: NA' : `Safety Score: ${scoreData.score}%`}
            </div>
            <div style={{ fontSize: '12px', color: '#64748b' }}>Deterministic CDS Review</div>
          </div>
        </div>

        {/* Patient Summary */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '16px', background: '#f1f5f9', padding: '16px', borderRadius: '8px', marginBottom: '24px' }}>
          <div>
            <small style={{ color: '#64748b', textTransform: 'uppercase', fontSize: '11px', fontWeight: 700 }}>Patient Name</small>
            <div style={{ fontSize: '16px', fontWeight: 700, color: '#0f172a' }}>{patient.full_name}</div>
          </div>
          <div>
            <small style={{ color: '#64748b', textTransform: 'uppercase', fontSize: '11px', fontWeight: 700 }}>Record ID / MRN</small>
            <div style={{ fontSize: '15px', fontWeight: 600, fontFamily: 'monospace' }}>{patient.patient_mrn || patient.patient_id}</div>
          </div>
          <div>
            <small style={{ color: '#64748b', textTransform: 'uppercase', fontSize: '11px', fontWeight: 700 }}>Kidney Function (eGFR)</small>
            <div style={{ fontSize: '15px', fontWeight: 700 }}>
              {patient.egfr !== null ? `${patient.egfr} mL/min (Stage ${egfrStage.stage})` : 'Not recorded'}
            </div>
          </div>
          <div>
            <small style={{ color: '#64748b', textTransform: 'uppercase', fontSize: '11px', fontWeight: 700 }}>Known Allergies</small>
            <div style={{ fontSize: '15px', fontWeight: 600, color: patient.allergies ? '#dc2626' : '#16a34a' }}>
              {patient.allergies || 'NKDA'}
            </div>
          </div>
        </div>

        {/* Active Medications Table */}
        <div style={{ marginBottom: '24px', overflowX: 'auto' }}>
          <h3 style={{ fontSize: '17px', fontWeight: 800, marginBottom: '12px', borderBottom: '1px solid #e2e8f0', paddingBottom: '6px' }}>
            Current Active Medications ({activeMedications.length})
          </h3>
          <table style={{ width: '100%', minWidth: '550px', borderCollapse: 'collapse', fontSize: '14px' }}>
            <thead>
              <tr style={{ background: '#f8fafc', borderBottom: '2px solid #cbd5e1', textAlign: 'left' }}>
                <th style={{ padding: '8px' }}>Medication Name</th>
                <th style={{ padding: '8px' }}>Generic Equivalent</th>
                <th style={{ padding: '8px' }}>Dosage & Freq</th>
                <th style={{ padding: '8px' }}>Prescribing Clinician</th>
              </tr>
            </thead>
            <tbody>
              {activeMedications.map((m, idx) => (
                <tr key={idx} style={{ borderBottom: '1px solid #e2e8f0' }}>
                  <td style={{ padding: '8px', fontWeight: 700 }}>{m.raw_medicine_name}</td>
                  <td style={{ padding: '8px', textTransform: 'capitalize' }}>{m.matched_generic || m.prescribed_generic_name || '-'}</td>
                  <td style={{ padding: '8px' }}>{m.dosage} {m.frequency}</td>
                  <td style={{ padding: '8px', color: '#64748b' }}>{m.prescribing_doctor || '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Clinical Safety Review */}
        {alerts && alerts.length > 0 ? (
          <div style={{ marginBottom: '24px' }}>
            <h3 style={{ fontSize: '17px', fontWeight: 800, marginBottom: '12px', borderBottom: '1px solid #e2e8f0', paddingBottom: '6px', color: '#b91c1c', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <AlertTriangleIcon size={18} color="#dc2626" />
              <span>Prescribing Safety Review & Flagged Clinical Alerts ({alerts.length})</span>
            </h3>
            {alerts.map((a, idx) => (
              <div key={idx} style={{ background: '#fff7ed', borderLeft: '4px solid #ea580c', padding: '12px', borderRadius: '4px', marginBottom: '10px' }}>
                <div style={{ fontWeight: 700, color: '#9a3412', fontSize: '15px' }}>
                  [{a.severity.toUpperCase()}] {a.problem}
                </div>
                <div style={{ margin: '4px 0', fontSize: '13.5px', color: '#1e293b' }}>
                  <strong>Action:</strong> {a.recommended_action}
                </div>
                <div style={{ fontSize: '12.5px', color: '#64748b' }}>
                  {a.why_flagged}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div style={{ background: '#f0fdf4', borderLeft: '4px solid #16a34a', padding: '12px', borderRadius: '4px', marginBottom: '24px', display: 'flex', alignItems: 'flex-start', gap: '10px' }}>
            <CheckCircleIcon size={20} color="#16a34a" />
            <div>
              <strong style={{ color: '#166534' }}>No critical interactions or duplicate therapies found in verified records.</strong>
              <p style={{ margin: '4px 0 0', fontSize: '13px', color: '#15803d' }}>
                Patient may continue verified regimen. Note: Unrecorded OTC medicines, supplements or unentered labs are not covered.
              </p>
            </div>
          </div>
        )}

        {/* Disclaimer footer */}
        <div style={{ borderTop: '2px solid #e2e8f0', paddingTop: '12px', fontSize: '12px', color: '#64748b', display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: '8px' }}>
          <div>
            Generated by AushadhSeva Clinical Decision Support Engine.
          </div>
          <div>
            Confidential Medical Document &bull; For Clinical Reference Only
          </div>
        </div>
      </div>
    </div>
  );
}
