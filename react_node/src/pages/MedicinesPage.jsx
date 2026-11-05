import React, { useState, useEffect } from 'react';
import { useI18n } from '../context/I18nContext';
import {
  BookOpenIcon,
  SearchIcon,
  AlertTriangleIcon,
  CheckCircleIcon,
  ShieldAlertIcon
} from '../components/Icons';

export default function MedicinesPage({ navigate }) {
  const { t } = useI18n();

  const [medicines, setMedicines] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);

  // Live Medical API Modal state
  const [selectedMed, setSelectedMed] = useState(null);
  const [liveInfo, setLiveInfo] = useState(null);
  const [liveLoading, setLiveLoading] = useState(false);

  useEffect(() => {
    setLoading(true);
    const params = new URLSearchParams({
      page: page.toString(),
      limit: '50'
    });
    if (search.trim()) params.append('search', search.trim());

    fetch(`/api/medicines?${params.toString()}`)
      .then(res => res.json())
      .then(data => {
        setMedicines(data.medicines || []);
        setTotal(data.total || 0);
        setLoading(false);
      })
      .catch(() => setLoading(false));
  }, [page, search]);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setPage(1);
  };

  const handleOpenLiveInfo = async (med) => {
    setSelectedMed(med);
    setLiveLoading(true);
    setLiveInfo(null);
    try {
      const res = await fetch(`/api/medical/live-insights/${encodeURIComponent(med.generic_name)}`);
      const data = await res.json();
      setLiveInfo(data.insights);
    } catch {
      // ignore network errors
    } finally {
      setLiveLoading(false);
    }
  };

  const getPregnancyBadge = (cat) => {
    const c = (cat || 'N').trim().toUpperCase();
    if (c === 'X') return <span className="badge badge-critical">Category X (Teratogen)</span>;
    if (c === 'D') return <span className="badge badge-danger">Category D (Fetal Risk)</span>;
    if (c === 'C') return <span className="badge badge-warning">Category C</span>;
    if (c === 'B') return <span className="badge badge-info">Category B</span>;
    if (c === 'A') return <span className="badge badge-success">Category A</span>;
    return <span className="badge badge-neutral">Category {c}</span>;
  };

  return (
    <div className="ez-page">
      <div className="page-header" style={{ marginBottom: '24px' }}>
        <h2 style={{ fontSize: '26px', fontWeight: 800, margin: 0, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '10px' }}>
          <BookOpenIcon size={26} color="#0284c7" />
          <span>{t('nav.medicines_list')}</span>
        </h2>
        <p style={{ margin: '4px 0 0', color: '#64748b' }}>
          Comprehensive hospital formulary of 496+ verified generic drugs, Indian brand formulations, and live OpenFDA monographs ({total} medicines total)
        </p>
      </div>

      {/* Search Bar */}
      <form onSubmit={handleSearchSubmit} className="search-form-row" style={{ display: 'flex', gap: '10px', marginBottom: '24px', alignItems: 'center' }}>
        <div style={{ position: 'relative', flex: '1 1 auto', minWidth: '220px' }}>
          <input
            type="text"
            className="form-control"
            style={{ paddingLeft: '40px', minHeight: '46px', fontSize: '15.5px' }}
            placeholder={t('meds.search_ph')}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <span style={{ position: 'absolute', left: '14px', top: '50%', transform: 'translateY(-50%)', color: '#94a3b8', display: 'flex', alignItems: 'center' }}>
            <SearchIcon size={18} />
          </span>
        </div>
        <button type="submit" className="btn btn-primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', minHeight: '46px', padding: '0 20px', whiteSpace: 'nowrap' }}>
          <SearchIcon size={16} />
          <span>Search</span>
        </button>
        {search && (
          <button type="button" className="btn btn-secondary" onClick={() => { setSearch(''); setPage(1); }} style={{ minHeight: '46px', padding: '0 16px', whiteSpace: 'nowrap' }}>
            Clear
          </button>
        )}
      </form>

      {/* Medicines Table */}
      <div className="card" style={{ borderRadius: '16px', padding: '20px' }}>
        {loading ? (
          <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b' }}>
            Loading formulary catalogue...
          </div>
        ) : medicines.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b' }}>
            No medicines found matching "{search}".
          </div>
        ) : (
          <div className="responsive-table-container">
            <table className="data-table" style={{ width: '100%', minWidth: '780px' }}>
              <thead>
                <tr>
                  <th>Generic Name</th>
                  <th>Common Indian Brands</th>
                  <th>Therapeutic Class</th>
                  <th>Standard Dosing</th>
                  <th>Renal Adjustment</th>
                  <th>Pregnancy Safety</th>
                  <th>Live Monograph</th>
                </tr>
              </thead>
              <tbody>
                {medicines.map(m => (
                  <tr key={m.id}>
                    <td>
                      <strong style={{ fontSize: '15px', color: '#0f172a', textTransform: 'capitalize' }}>
                        {m.generic_name}
                      </strong>
                    </td>
                    <td style={{ maxWidth: '240px' }}>
                      {m.common_brands ? (
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px' }}>
                          {m.common_brands.split(';').slice(0, 4).map((b, idx) => (
                            <span key={idx} className="badge badge-neutral" style={{ fontSize: '11px' }}>
                              {b.trim()}
                            </span>
                          ))}
                          {m.common_brands.split(';').length > 4 && (
                            <small style={{ color: '#64748b' }}>+{m.common_brands.split(';').length - 4} more</small>
                          )}
                        </div>
                      ) : (
                        <span style={{ color: '#94a3b8' }}>-</span>
                      )}
                    </td>
                    <td>
                      <div>{m.drug_class || m.therapeutic_group || '-'}</div>
                    </td>
                    <td>
                      <div>{m.usual_adult_daily_dose_range || '-'}</div>
                      {m.max_daily_dose_mg && (
                        <small style={{ color: '#64748b' }}>Max: {m.max_daily_dose_mg} mg/day</small>
                      )}
                    </td>
                    <td>
                      {m.renal_dose_adjustment_required ? (
                        <span className="badge badge-warning" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }} title={m.renal_rule || 'Dose adjustment required'}>
                          <AlertTriangleIcon size={13} color="#ea580c" />
                          <span>Dose Adjustment</span>
                        </span>
                      ) : (
                        <span className="badge badge-neutral" style={{ color: '#16a34a' }}>Standard</span>
                      )}
                    </td>
                    <td>
                      {getPregnancyBadge(m.pregnancy_category)}
                    </td>
                    <td>
                      <button
                        type="button"
                        onClick={() => handleOpenLiveInfo(m)}
                        className="btn btn-secondary btn-sm"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', fontSize: '12px', padding: '6px 10px' }}
                      >
                        <BookOpenIcon size={13} />
                        <span>Live Insights</span>
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Live Medical Monograph & Jan Aushadhi Modal */}
      {selectedMed && (
        <div style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background: 'rgba(15, 23, 42, 0.65)',
          backdropFilter: 'blur(3px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          zIndex: 4000,
          padding: '16px'
        }}>
          <div className="card" style={{ maxWidth: 'min(94vw, 580px)', width: '100%', maxHeight: '90vh', overflowY: 'auto', borderRadius: '18px', padding: 'clamp(18px, 4vw, 26px)', boxShadow: '0 20px 25px -5px rgba(0,0,0,0.2)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', borderBottom: '1px solid #e2e8f0', paddingBottom: '12px', marginBottom: '16px' }}>
              <div>
                <span className="badge badge-info" style={{ fontSize: '11px', textTransform: 'uppercase' }}>
                  Live Medical API Monograph
                </span>
                <h3 style={{ margin: '4px 0 0', fontSize: '20px', fontWeight: 800, textTransform: 'capitalize', color: '#0f172a' }}>
                  {selectedMed.generic_name}
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setSelectedMed(null)}
                style={{ background: 'none', border: 'none', fontSize: '24px', cursor: 'pointer', color: '#64748b' }}
              >
                &times;
              </button>
            </div>

            {liveLoading ? (
              <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b' }}>
                <p>Querying OpenFDA and NIH RxNorm servers...</p>
              </div>
            ) : liveInfo ? (
              <div>
                {/* PMBJP Seva Generic Savings Box */}
                {liveInfo.jan_aushadhi && (
                  <div style={{ background: '#f0fdf4', border: '1px solid #86efac', borderRadius: '12px', padding: '14px', marginBottom: '16px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '8px' }}>
                      <strong style={{ color: '#166534', fontSize: '14px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                        <CheckCircleIcon size={16} color="#16a34a" />
                        <span>Jan Aushadhi (PMBJP) Price: ₹{liveInfo.jan_aushadhi.pmbjp_price.toFixed(2)}</span>
                      </strong>
                      <span className="badge badge-success" style={{ fontWeight: 800 }}>
                        Save {liveInfo.jan_aushadhi.savings_percent}%
                      </span>
                    </div>
                    <p style={{ margin: '4px 0 0', fontSize: '12.5px', color: '#15803d' }}>
                      Commercial brand avg: ₹{liveInfo.jan_aushadhi.brand_avg_price.toFixed(2)} / {liveInfo.jan_aushadhi.unit}. Toll-Free 1800-180-8080.
                    </p>
                  </div>
                )}

                {/* Boxed Warning */}
                {liveInfo.boxed_warning && (
                  <div style={{ background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '10px', padding: '12px', marginBottom: '14px' }}>
                    <strong style={{ color: '#b91c1c', display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px' }}>
                      <ShieldAlertIcon size={15} color="#dc2626" />
                      <span>FDA Official Boxed Warning:</span>
                    </strong>
                    <p style={{ margin: '4px 0 0', color: '#991b1b', fontSize: '12.5px', lineHeight: 1.5 }}>
                      {liveInfo.boxed_warning}
                    </p>
                  </div>
                )}

                {/* Indications */}
                {liveInfo.indications && (
                  <div style={{ marginBottom: '12px' }}>
                    <strong style={{ fontSize: '13px', color: '#334155' }}>Approved Indications:</strong>
                    <p style={{ margin: '2px 0 0', color: '#475569', fontSize: '13px', lineHeight: 1.5 }}>
                      {liveInfo.indications}
                    </p>
                  </div>
                )}

                {/* Warnings */}
                {liveInfo.warnings && (
                  <div style={{ marginBottom: '12px' }}>
                    <strong style={{ fontSize: '13px', color: '#334155' }}>Precautions & Warnings:</strong>
                    <p style={{ margin: '2px 0 0', color: '#475569', fontSize: '13px', lineHeight: 1.5 }}>
                      {liveInfo.warnings}
                    </p>
                  </div>
                )}

                {/* Identifiers */}
                <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', marginTop: '16px', borderTop: '1px solid #e2e8f0', paddingTop: '10px' }}>
                  <span className="badge badge-neutral" style={{ fontSize: '11px' }}>
                    RxCUI: {liveInfo.rxcui || 'Assigned'}
                  </span>
                  <span className="badge badge-neutral" style={{ fontSize: '11px' }}>
                    FDA API: {liveInfo.fda_available ? 'Verified' : 'Local Reference'}
                  </span>
                </div>
              </div>
            ) : (
              <p style={{ color: '#64748b' }}>No live monograph data available for this drug.</p>
            )}

            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '20px' }}>
              <button type="button" onClick={() => setSelectedMed(null)} className="btn btn-secondary">
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
