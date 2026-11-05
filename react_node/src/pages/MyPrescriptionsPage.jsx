import React, { useState, useEffect } from 'react';
import { useI18n } from '../context/I18nContext';
import { useAuth } from '../context/AuthContext';
import {
  PillIcon,
  PrinterIcon,
  SearchIcon,
  AlertTriangleIcon,
  Volume2Icon,
  ClockIcon,
  CheckCircleIcon,
  getSeverityIcon
} from '../components/Icons';

export default function MyPrescriptionsPage({ navigate }) {
  const { t, speak, speakAlert } = useI18n();
  const { user } = useAuth();

  const [loading, setLoading] = useState(true);
  const [data, setData] = useState(null);
  const [error, setError] = useState('');

  const patientId = user?.patient_id || 1;

  useEffect(() => {
    fetch(`/api/patients/${patientId}`)
      .then(res => {
        if (!res.ok) throw new Error('Failed to load prescriptions');
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
  }, [patientId]);

  if (loading) {
    return (
      <div className="ez-page" style={{ textAlign: 'center', padding: '60px 16px' }}>
        <p style={{ fontSize: '18px', color: '#64748b' }}>Loading your medicine cabinet...</p>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="ez-page" style={{ padding: '0 16px' }}>
        <div className="alert alert-danger" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <AlertTriangleIcon size={18} color="#dc2626" />
          <span>{error || 'Could not load your records.'}</span>
        </div>
      </div>
    );
  }

  const { patient, activeMedications, historicalMedications, alerts, scoreData } = data;

  return (
    <div className="ez-page">
      <div className="ez-page-head" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '16px', marginBottom: '20px' }}>
        <div>
          <h2 className="ez-page-title" style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            <PillIcon size={26} color="#0284c7" />
            <span>{t('nav.my_medicines')}</span>
          </h2>
          <p className="ez-page-desc">
            Personal medication locker for <strong>{patient.full_name}</strong>
          </p>
        </div>
        <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
          <button
            type="button"
            onClick={() => navigate(`/print-safety-card/${patient.id}`)}
            className="btn btn-secondary"
            style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
          >
            <PrinterIcon size={16} />
            <span>{t('card.title')}</span>
          </button>
          <button
            type="button"
            onClick={() => navigate('/check')}
            className="btn btn-primary"
            style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
          >
            <SearchIcon size={16} />
            <span>{t('nav.check')}</span>
          </button>
        </div>
      </div>

      {/* Safety Score Overview */}
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
        <p style={{ fontSize: '16px', lineHeight: 1.6, margin: '14px 0', color: '#1e293b' }}>
          {scoreData.summary}
        </p>
      </div>

      {/* Active Medications Table */}
      <div className="ez-card" style={{ marginBottom: '24px', borderRadius: '18px', padding: '24px' }}>
        <h3 style={{ fontSize: '18px', fontWeight: 800, marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px' }}>
          <CheckCircleIcon size={20} color="#16a34a" />
          <span>Active Prescriptions ({activeMedications.length})</span>
        </h3>

        {activeMedications.length === 0 ? (
          <p style={{ color: '#64748b' }}>No active medications in your locker. Click "Check a Medicine" to add one.</p>
        ) : (
          <div className="responsive-table-container">
            <table className="data-table" style={{ width: '100%', minWidth: '580px' }}>
              <thead>
                <tr>
                  <th>Medicine Name</th>
                  <th>Generic Equivalent</th>
                  <th>Dosage & Frequency</th>
                  <th>Prescriber</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                {activeMedications.map(med => (
                  <tr key={med.id}>
                    <td>
                      <strong style={{ color: '#0f172a' }}>{med.raw_medicine_name}</strong>
                      {med.indication && (
                        <div style={{ fontSize: '12px', color: '#64748b' }}>For: {med.indication}</div>
                      )}
                    </td>
                    <td>
                      <span className="badge badge-neutral" style={{ textTransform: 'capitalize' }}>
                        {med.matched_generic || med.prescribed_generic_name || 'Unverified'}
                      </span>
                    </td>
                    <td>
                      <div>{med.dosage || '-'}</div>
                      <small style={{ color: '#64748b' }}>{med.frequency || '-'}</small>
                    </td>
                    <td style={{ fontSize: '13px' }}>{med.prescribing_doctor || '-'}</td>
                    <td style={{ fontSize: '13px' }}>{med.prescription_date || '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Safety Warnings Section */}
      {alerts && alerts.length > 0 && (
        <div style={{ marginBottom: '24px' }}>
          <h3 style={{ fontSize: '18px', fontWeight: 800, marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <AlertTriangleIcon size={20} color="#dc2626" />
            <span>{t('cabinet.alerts_title')} ({alerts.length})</span>
          </h3>
          {alerts.map((alert, idx) => {
            const sevLevel = alert.severity === 'contraindicated' ? 'danger' : alert.severity === 'major' ? 'serious' : alert.severity === 'moderate' ? 'caution' : 'note';
            const typeKey = alert.alert_type || 'interaction';
            const localizedTitle = t(`alert.title.${typeKey}`) !== `alert.title.${typeKey}`
              ? `${t(`alert.title.${typeKey}`)}: ${alert.medicines_involved}`
              : alert.problem;
            const localizedWhy = t(`alert.why.${typeKey}`, { drugs: alert.medicines_involved || '', egfr: alert.egfr || '' }) !== `alert.why.${typeKey}`
              ? t(`alert.why.${typeKey}`, { drugs: alert.medicines_involved || '', egfr: alert.egfr || '' })
              : alert.why_flagged;
            const localizedBadge = t(`sev.${sevLevel}.badge`);

            return (
              <article key={idx} className={`ez-alert ez-alert--${sevLevel}`} style={{ marginBottom: '16px' }}>
                <div className="ez-alert__level" style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                  <span className="ez-alert__icon" style={{ display: 'inline-flex', alignItems: 'center' }}>
                    {getSeverityIcon(alert.severity, 18)}
                  </span>
                  <span>{localizedBadge}</span>
                </div>
                <h4 className="ez-alert__title">{localizedTitle}</h4>
                <div className="ez-alert__drugs" style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                  <PillIcon size={16} />
                  <span>{alert.medicines_involved}</span>
                </div>
                <div className="ez-alert__todo">
                  <div className="ez-alert__todo-label">{t('alert.what_to_do')}</div>
                  <div>{alert.recommended_action}</div>
                </div>
                <p className="ez-alert__why">
                  <strong>{t('alert.why_label')}</strong> {localizedWhy}
                </p>
                <button
                  type="button"
                  className="ez-speak"
                  onClick={() => speakAlert(alert)}
                  style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
                >
                  <Volume2Icon size={17} />
                  <span>{t('common.read_aloud')}</span>
                </button>
              </article>
            );
          })}
        </div>
      )}

      {/* Discontinued / Historical Medications */}
      {historicalMedications && historicalMedications.length > 0 && (
        <div className="ez-card" style={{ borderRadius: '18px', padding: '20px' }}>
          <h3 style={{ fontSize: '16px', fontWeight: 700, color: '#64748b', marginBottom: '12px', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <ClockIcon size={18} />
            <span>Past / Discontinued Medications ({historicalMedications.length})</span>
          </h3>
          <ul style={{ margin: 0, paddingLeft: '20px', color: '#64748b' }}>
            {historicalMedications.map(med => (
              <li key={med.id} style={{ marginBottom: '6px' }}>
                <span style={{ textDecoration: 'line-through' }}>{med.raw_medicine_name}</span> &mdash; stopped
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
