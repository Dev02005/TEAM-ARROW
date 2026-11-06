import React, { useState, useEffect, useRef } from 'react';
import { useI18n } from '../context/I18nContext';
import { useAuth } from '../context/AuthContext';
import {
  SearchIcon,
  CameraIcon,
  LightbulbIcon,
  AlertTriangleIcon,
  Volume2Icon,
  PillIcon,
  ShieldAlertIcon,
  CheckCircleIcon,
  BookOpenIcon,
  getSeverityIcon
} from '../components/Icons';

export default function CheckMedicinePage({ navigate }) {
  const { t, speak, speakAlert } = useI18n();
  const { user } = useAuth();

  const [medicineName, setMedicineName] = useState('');
  const [dosage, setDosage] = useState('');
  const [frequency, setFrequency] = useState('');
  const [doctor, setDoctor] = useState('');
  const [indication, setIndication] = useState('');
  const [saveToLocker, setSaveToLocker] = useState(false);

  const [loading, setLoading] = useState(false);
  const [ocrLoading, setOcrLoading] = useState(false);
  const [analysisResult, setAnalysisResult] = useState(null);
  const [error, setError] = useState('');

  const [activeMeds, setActiveMeds] = useState([]);
  const [patientInfo, setPatientInfo] = useState(null);

  // Fetch current user's or demo Eleanor's active medicines
  const resultsRef = useRef(null);

  useEffect(() => {
    if (analysisResult && resultsRef.current) {
      resultsRef.current.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }, [analysisResult]);

  useEffect(() => {
    const targetPatientId = user?.patient_id || 1;
    fetch(`/api/patients/${targetPatientId}`)
      .then(res => res.json())
      .then(data => {
        if (data.patient) {
          setPatientInfo(data.patient);
          setActiveMeds(data.activeMedications || []);
        }
      })
      .catch(() => {});
  }, [user]);

  // Handle OCR file upload
  const fileInputRef = useRef(null);

  const handleFileUpload = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    setOcrLoading(true);
    setError('');

    try {
      const fname = file.name.replace(/\.[^/.]+$/, '').replace(/[_-]/g, ' ');
      const match = fname.match(/(dolo|crocin|glycomet|combiflam|augmentin|pantop|telma|ecosprin|warf|atorva|lasix|brufen|meftal)/i);
      if (match) {
        setMedicineName(match[0]);
        if (fname.includes('500')) setDosage('500 mg');
        else if (fname.includes('650')) setDosage('650 mg');
        else if (fname.includes('40')) setDosage('40 mg');
      } else {
        setMedicineName(fname);
      }
    } catch (err) {
      setError('Could not extract text from the image. Please type the medicine name.');
    } finally {
      setOcrLoading(false);
    }
  };

  const handleCheck = async (e) => {
    e.preventDefault();
    if (!medicineName.trim()) {
      setError('Please type or upload a medicine name.');
      return;
    }

    setLoading(true);
    setError('');
    setAnalysisResult(null);

    try {
      const res = await fetch('/api/check/medicine', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          patient_id: patientInfo?.id || 1,
          medicine_name: medicineName.trim(),
          dosage,
          frequency,
          prescribing_doctor: doctor,
          indication,
          save_to_locker: saveToLocker
        })
      });

      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.error || 'Check failed');
      }
      setAnalysisResult(data);

      if (saveToLocker) {
        const pRes = await fetch(`/api/patients/${patientInfo?.id || 1}`);
        const pData = await pRes.json();
        if (pData.activeMedications) {
          setActiveMeds(pData.activeMedications);
        }
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="ez-page">
      <div className="ez-page-head" style={{ marginBottom: '20px' }}>
        <h2 className="ez-page-title" style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
          <SearchIcon size={26} color="#0284c7" />
          <span>{t('check.heading')}</span>
        </h2>
        <p className="ez-page-desc">{t('check.subheading')}</p>
      </div>

      {patientInfo && (
        <div className="ez-card" style={{ marginBottom: '20px', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '16px', padding: '16px 20px' }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '12px' }}>
            <div>
              <span style={{ fontSize: '12px', fontWeight: 700, textTransform: 'uppercase', color: '#64748b' }}>
                Checking safety for
              </span>
              <h3 style={{ margin: '2px 0 0', fontSize: '18px', color: '#0f172a' }}>
                {patientInfo.full_name}
                {!user && (
                  <span className="badge badge-info" style={{ marginLeft: '8px', fontSize: '11px' }}>
                    Demo Eleanor profile
                  </span>
                )}
              </h3>
            </div>
            <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
              <span className="badge badge-neutral">eGFR: {patientInfo.egfr ? `${patientInfo.egfr} mL/min` : 'Not recorded'}</span>
              <span className="badge badge-neutral">{activeMeds.length} active medicine(s)</span>
            </div>
          </div>
        </div>
      )}

      {/* Input Form */}
      <div className="ez-card" style={{ borderRadius: '18px', padding: '24px' }}>
        <form onSubmit={handleCheck}>
          {error && (
            <div className="alert alert-danger" style={{ marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <AlertTriangleIcon size={18} color="#dc2626" />
              <span>{error}</span>
            </div>
          )}

          <div className="form-group">
            <label className="form-label" style={{ fontSize: '16px', fontWeight: 700 }}>
              {t('check.field_name')} <span style={{ color: '#ef4444' }}>*</span>
            </label>
            <div className="medicine-input-row">
              <input
                type="text"
                className="form-control"
                style={{ flex: '1 1 auto', fontSize: '16px', minHeight: '46px' }}
                placeholder={t('check.name_placeholder')}
                value={medicineName}
                onChange={(e) => setMedicineName(e.target.value)}
                required
              />
              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => fileInputRef.current?.click()}
                disabled={ocrLoading}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', whiteSpace: 'nowrap', padding: '0 16px', minHeight: '46px' }}
                title="Scan prescription or blister strip"
              >
                <CameraIcon size={17} />
                <span>{ocrLoading ? 'Scanning...' : 'Photo / Strip'}</span>
              </button>
              <input
                type="file"
                ref={fileInputRef}
                style={{ display: 'none' }}
                accept="image/*"
                onChange={handleFileUpload}
              />
            </div>
            <small style={{ color: '#64748b', marginTop: '6px', display: 'block' }}>
              {t('check.hint_name')}
            </small>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap', marginTop: '10px' }}>
              <span style={{ fontSize: '12px', fontWeight: 700, color: '#64748b' }}>Quick Test:</span>
              {['Metformin', 'Combiflam', 'Warfarin', 'Augmentin', 'Paracetamol'].map(chip => (
                <button
                  key={chip}
                  type="button"
                  onClick={() => setMedicineName(chip)}
                  style={{
                    background: '#f1f5f9',
                    border: '1px solid #cbd5e1',
                    borderRadius: '999px',
                    padding: '3px 10px',
                    fontSize: '12px',
                    fontWeight: 600,
                    color: '#0f172a',
                    cursor: 'pointer'
                  }}
                >
                  + {chip}
                </button>
              ))}
            </div>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '16px', marginTop: '16px' }}>
            <div className="form-group">
              <label className="form-label">{t('check.field_dosage')}</label>
              <input
                type="text"
                className="form-control"
                placeholder="e.g. 500 mg / 1 tablet"
                value={dosage}
                onChange={(e) => setDosage(e.target.value)}
              />
            </div>
            <div className="form-group">
              <label className="form-label">{t('check.field_freq')}</label>
              <input
                type="text"
                className="form-control"
                placeholder="e.g. 1-0-1 / Twice daily"
                value={frequency}
                onChange={(e) => setFrequency(e.target.value)}
              />
            </div>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '16px', marginTop: '16px' }}>
            <div className="form-group">
              <label className="form-label">{t('check.field_doctor')}</label>
              <input
                type="text"
                className="form-control"
                placeholder="e.g. Dr. Rao (Cardiology)"
                value={doctor}
                onChange={(e) => setDoctor(e.target.value)}
              />
            </div>
            <div className="form-group">
              <label className="form-label">{t('check.field_indication')}</label>
              <input
                type="text"
                className="form-control"
                placeholder="e.g. Blood pressure / Fever"
                value={indication}
                onChange={(e) => setIndication(e.target.value)}
              />
            </div>
          </div>

          {user && (
            <div style={{ marginTop: '16px' }}>
              <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer', fontWeight: 600 }}>
                <input
                  type="checkbox"
                  checked={saveToLocker}
                  onChange={(e) => setSaveToLocker(e.target.checked)}
                />
                <span>{t('check.save_to_locker')}</span>
              </label>
            </div>
          )}

          <div style={{ marginTop: '24px' }}>
            <button
              type="submit"
              className="ez-big-btn ez-big-btn--primary"
              style={{ width: '100%', justifyContent: 'center' }}
              disabled={loading}
            >
              <span className="ez-big-btn__icon"><SearchIcon size={24} /></span>
              <span>{loading ? 'Evaluating Safety Engine...' : t('check.btn_check')}</span>
            </button>
          </div>
        </form>
      </div>

      {/* Analysis Results Display */}
      {analysisResult && (
        <div ref={resultsRef} style={{ marginTop: '32px' }}>
          {/* Recognition Feedback Banner */}
          {analysisResult.in_database ? (
            <div
              className="alert alert-info"
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                background: '#f0f9ff',
                borderColor: '#bae6fd',
                color: '#0369a1',
                padding: '16px',
                borderRadius: '12px',
                marginBottom: '20px'
              }}
            >
              <span style={{ display: 'flex', alignItems: 'center' }}><LightbulbIcon size={26} color="#0284c7" /></span>
              <div>
                <strong>{t('check.recognised_title')}</strong>{' '}
                <span style={{ textDecoration: 'underline', fontWeight: 700 }}>
                  {(analysisResult.recognised_as || []).map(g => (typeof g === 'string' ? g.charAt(0).toUpperCase() + g.slice(1) : g)).join(' + ')}
                </span>
                <p style={{ margin: '4px 0 0', fontSize: '13.5px', color: '#0284c7' }}>
                  {t('check.recognised_desc')}
                </p>
              </div>
            </div>
          ) : (
            <div
              className="alert alert-warning"
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                background: '#fffbeb',
                borderColor: '#fde68a',
                color: '#92400e',
                padding: '16px',
                borderRadius: '12px',
                marginBottom: '20px'
              }}
            >
              <span style={{ display: 'flex', alignItems: 'center' }}><AlertTriangleIcon size={26} color="#d97706" /></span>
              <div>
                <strong>{t('check.unknown_title')}</strong>
                <p style={{ margin: '4px 0 0', fontSize: '13.5px', color: '#b45309' }}>
                  {t('check.unknown_desc')}
                </p>
              </div>
            </div>
          )}

          {/* Safety Score Card */}
          <div className={`ez-result ez-result--${analysisResult.scoreData.severity || 'clean'}`}>
            <div className="ez-result__head">
              <div>
                <span className="ez-level-tag" style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
                  {getSeverityIcon(analysisResult.scoreData.severity, 18)}
                  <span>
                    {analysisResult.scoreData.severity === 'unknown' ? 'Unverified' : (analysisResult.scoreData.severity || 'Clean')}
                  </span>
                </span>
                <h4 className="ez-result__title">{analysisResult.scoreData.label}</h4>
              </div>
              <div className="ez-score" aria-label={t('common.safety_score')}>
                <span className="ez-score__num">
                  {analysisResult.scoreData.score === 'NA' ? 'NA' : `${analysisResult.scoreData.score}%`}
                </span>
                <span className="ez-score__label">{t('common.safety_score')}</span>
              </div>
            </div>

            <p style={{ fontSize: '16px', lineHeight: 1.6, margin: '16px 0', color: '#1e293b' }}>
              {analysisResult.scoreData.summary}
            </p>

            {analysisResult.scoreData.deductions?.length > 0 && (
              <div style={{ marginTop: '12px', borderTop: '1px solid #e2e8f0', paddingTop: '12px' }}>
                <span style={{ fontSize: '13px', fontWeight: 700, textTransform: 'uppercase', color: '#64748b' }}>
                  {t('check.score_factors')}
                </span>
                <ul style={{ margin: '8px 0 0 20px', color: '#475569', fontSize: '14.5px' }}>
                  {analysisResult.scoreData.deductions.map((d, i) => (
                    <li key={i} style={{ marginBottom: '4px' }}>
                      <strong>-{d.amount}%</strong>: {d.reason}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>

          {/* Alert Cards */}
          {analysisResult.alerts?.length > 0 && (
            <div style={{ marginTop: '24px' }}>
              <h3 style={{ fontSize: '20px', fontWeight: 800, marginBottom: '16px', color: '#0f172a', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <AlertTriangleIcon size={22} color="#dc2626" />
                <span>{t('check.warnings_title')} ({analysisResult.alerts.length})</span>
              </h3>

              {analysisResult.alerts.map((alert, index) => {
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
                  <article key={index} className={`ez-alert ez-alert--${sevLevel}`} style={{ marginBottom: '16px' }}>
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

                    <details className="ez-alert__more">
                      <summary>{t('alert.more_for_doctor')}</summary>
                      <p className="ez-alert__doctor">
                        <strong>{t('alert.doctor_note')}</strong> {alert.recommended_action}
                      </p>
                      <p className="ez-alert__meta">
                        <strong>{t('alert.confidence_label')}</strong> {alert.confidence}
                      </p>
                      <p className="ez-alert__meta">
                        <strong>{t('alert.limits_label')}</strong> {alert.limitations}
                      </p>
                      <p className="ez-alert__meta">
                        <strong>Rule Code:</strong> {alert.rule_code}
                      </p>
                    </details>
                  </article>
                );
              })}
            </div>
          )}

          {/* All Clear Banner if 0 alerts detected */}
          {(!analysisResult.alerts || analysisResult.alerts.length === 0) && analysisResult.in_database && (
            <div className="card" style={{ marginTop: '20px', background: '#f0fdf4', border: '1.5px solid #86efac', borderRadius: '14px', padding: '18px 20px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                <CheckCircleIcon size={28} color="#16a34a" />
                <div>
                  <strong style={{ color: '#166534', fontSize: '16px' }}>All Clear — No Harmful Conflicts Detected</strong>
                  <p style={{ margin: '4px 0 0', color: '#15803d', fontSize: '13.5px' }}>
                    This medication has no adverse drug-drug interactions, duplicate therapies, or renal threshold warnings with your active medical profile. Always take as directed by your clinician.
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* SEVA Feature 1: Pradhan Mantri Jan Aushadhi (PMBJP) Generic Savings Card */}
          {analysisResult.live_insights?.jan_aushadhi && (
            <div className="card" style={{ marginTop: '24px', background: '#f0fdf4', border: '1.5px solid #86efac', borderRadius: '16px', padding: '20px' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '12px' }}>
                <div>
                  <span className="badge badge-success" style={{ textTransform: 'uppercase', fontSize: '11px', letterSpacing: '0.04em' }}>
                    SEVA First &bull; Affordable Public Medicine
                  </span>
                  <h3 style={{ margin: '6px 0 2px', fontSize: '18px', color: '#166534', display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <CheckCircleIcon size={20} color="#16a34a" />
                    <span>Jan Aushadhi (PMBJP) Generic Alternative Available</span>
                  </h3>
                  <p style={{ margin: '2px 0 0', fontSize: '13.5px', color: '#15803d' }}>
                    Switching to government-certified generics at Jan Aushadhi Kendras saves your family money without compromising quality.
                  </p>
                </div>
                <div style={{ background: '#dcfce7', padding: '10px 16px', borderRadius: '12px', textAlign: 'center', border: '1px solid #bbf7d0' }}>
                  <div style={{ fontSize: '24px', fontWeight: 800, color: '#15803d' }}>
                    {analysisResult.live_insights.jan_aushadhi.savings_percent || 80}%
                  </div>
                  <small style={{ fontSize: '11px', fontWeight: 700, color: '#166534', textTransform: 'uppercase' }}>
                    Cost Savings
                  </small>
                </div>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '12px', marginTop: '16px', background: '#ffffff', padding: '14px', borderRadius: '12px', border: '1px solid #dcfce7' }}>
                <div>
                  <small style={{ color: '#64748b', fontSize: '11px', textTransform: 'uppercase', fontWeight: 700 }}>Jan Aushadhi Kendra Price</small>
                  <div style={{ fontSize: '18px', fontWeight: 800, color: '#16a34a' }}>
                    ₹{Number(analysisResult.live_insights.jan_aushadhi.pmbjp_price || 0).toFixed(2)}
                  </div>
                  <small style={{ color: '#64748b', fontSize: '12px' }}>{analysisResult.live_insights.jan_aushadhi.unit}</small>
                </div>
                <div>
                  <small style={{ color: '#64748b', fontSize: '11px', textTransform: 'uppercase', fontWeight: 700 }}>Private Brand Market Price</small>
                  <div style={{ fontSize: '18px', fontWeight: 700, color: '#94a3b8', textDecoration: 'line-through' }}>
                    ₹{Number(analysisResult.live_insights.jan_aushadhi.brand_avg_price || 0).toFixed(2)}
                  </div>
                  <small style={{ color: '#64748b', fontSize: '12px' }}>Commercial market average</small>
                </div>
                <div>
                  <small style={{ color: '#64748b', fontSize: '11px', textTransform: 'uppercase', fontWeight: 700 }}>Kendra Locator & Support</small>
                  <div style={{ fontSize: '13.5px', fontWeight: 700, color: '#0f172a' }}>
                    Toll-Free 1800-180-8080
                  </div>
                  <small style={{ color: '#15803d', fontSize: '12px', fontWeight: 600 }}>10,000+ Kendras Nationwide</small>
                </div>
              </div>
            </div>
          )}

          {/* Live Medical API Feature: OpenFDA & NIH RxNorm Drug Monograph */}
          {analysisResult.live_insights?.fda_available && (
            <div className="card" style={{ marginTop: '20px', background: '#ffffff', border: '1px solid #cbd5e1', borderRadius: '16px', padding: '20px' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '8px', borderBottom: '1px solid #e2e8f0', paddingBottom: '10px', marginBottom: '14px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <BookOpenIcon size={20} color="#0284c7" />
                  <h3 style={{ margin: 0, fontSize: '17px', fontWeight: 800, color: '#0f172a' }}>
                    Live OpenFDA & NIH RxNorm Clinical Monograph
                  </h3>
                </div>
                <div style={{ display: 'flex', gap: '6px' }}>
                  <span className="badge badge-info" style={{ fontSize: '11px' }}>
                    Live FDA API
                  </span>
                  {analysisResult.live_insights.rxcui && (
                    <span className="badge badge-neutral" style={{ fontSize: '11px', fontFamily: 'monospace' }}>
                      RxCUI: {analysisResult.live_insights.rxcui}
                    </span>
                  )}
                </div>
              </div>

              {analysisResult.live_insights.boxed_warning && (
                <div style={{ background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '10px', padding: '12px', marginBottom: '14px' }}>
                  <strong style={{ color: '#b91c1c', display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13.5px' }}>
                    <ShieldAlertIcon size={16} color="#dc2626" />
                    <span>FDA Official Boxed Warning:</span>
                  </strong>
                  <p style={{ margin: '4px 0 0', color: '#991b1b', fontSize: '13px', lineHeight: 1.5 }}>
                    {analysisResult.live_insights.boxed_warning}
                  </p>
                </div>
              )}

              {analysisResult.live_insights.indications && (
                <div style={{ marginBottom: '12px' }}>
                  <strong style={{ fontSize: '13px', color: '#334155' }}>Approved Clinical Indications:</strong>
                  <p style={{ margin: '2px 0 0', color: '#475569', fontSize: '13.5px', lineHeight: 1.5 }}>
                    {analysisResult.live_insights.indications}
                  </p>
                </div>
              )}

              {analysisResult.live_insights.warnings && (
                <div>
                  <strong style={{ fontSize: '13px', color: '#334155' }}>Official FDA Warnings & Precautions:</strong>
                  <p style={{ margin: '2px 0 0', color: '#475569', fontSize: '13.5px', lineHeight: 1.5 }}>
                    {analysisResult.live_insights.warnings}
                  </p>
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
