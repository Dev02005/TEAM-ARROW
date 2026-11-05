import React, { useState, useEffect } from 'react';
import { useI18n } from '../context/I18nContext';
import {
  UsersIcon,
  KidneyIcon,
  AlertTriangleIcon,
  TargetIcon,
  SearchIcon
} from '../components/Icons';

export default function PatientsPage({ navigate }) {
  const { t } = useI18n();

  const [patients, setPatients] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    const params = new URLSearchParams({
      page: page.toString(),
      limit: '50'
    });
    if (search.trim()) params.append('search', search.trim());
    if (filter) params.append('filter', filter);

    fetch(`/api/patients?${params.toString()}`)
      .then(res => res.json())
      .then(data => {
        setPatients(data.patients || []);
        setTotal(data.total || 0);
        setLoading(false);
      })
      .catch(() => setLoading(false));
  }, [page, search, filter]);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setPage(1);
  };

  const getEgfrBadge = (egfr) => {
    if (egfr === null || egfr === undefined || egfr === '') {
      return <span className="badge badge-neutral">Not recorded</span>;
    }
    const val = parseFloat(egfr);
    if (val >= 90) return <span className="badge badge-success">G1 ({val})</span>;
    if (val >= 60) return <span className="badge badge-info">G2 ({val})</span>;
    if (val >= 45) return <span className="badge badge-warning">G3a ({val})</span>;
    if (val >= 30) return <span className="badge badge-warning">G3b ({val})</span>;
    if (val >= 15) return <span className="badge badge-danger">G4 ({val})</span>;
    return <span className="badge badge-critical">G5 ({val})</span>;
  };

  return (
    <div className="ez-page">
      <div className="page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px', marginBottom: '24px' }}>
        <div>
          <h2 style={{ fontSize: '26px', fontWeight: 800, margin: 0, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '10px' }}>
            <UsersIcon size={26} color="#0284c7" />
            <span>Patient Cohort Management</span>
          </h2>
          <p style={{ margin: '4px 0 0', color: '#64748b' }}>
            Multi-doctor longitudinal patient charts and clinical safety surveillance ({total} patients total)
          </p>
        </div>
      </div>

      {/* Filter Tabs */}
      <div className="filter-tabs" style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', marginBottom: '20px' }}>
        <button
          type="button"
          className={`filter-tab ${filter === '' ? 'active' : ''}`}
          onClick={() => { setFilter(''); setPage(1); }}
        >
          All Patients
        </button>
        <button
          type="button"
          className={`filter-tab ${filter === 'renal_impaired' ? 'active' : ''}`}
          onClick={() => { setFilter('renal_impaired'); setPage(1); }}
          style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
        >
          <KidneyIcon size={16} />
          <span>Renal Impaired (eGFR &lt; 60)</span>
        </button>
        <button
          type="button"
          className={`filter-tab ${filter === 'severe_renal' ? 'active' : ''}`}
          onClick={() => { setFilter('severe_renal'); setPage(1); }}
          style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
        >
          <AlertTriangleIcon size={16} color="#ea580c" />
          <span>Severe Renal (eGFR &lt; 30)</span>
        </button>
        <button
          type="button"
          className={`filter-tab ${filter === 'near_miss' ? 'active' : ''}`}
          onClick={() => { setFilter('near_miss'); setPage(1); }}
          style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
        >
          <TargetIcon size={16} color="#0284c7" />
          <span>Demo / Practice Cases</span>
        </button>
      </div>

      {/* Search Bar */}
      <form onSubmit={handleSearchSubmit} style={{ display: 'flex', gap: '8px', marginBottom: '24px', flexWrap: 'wrap' }}>
        <div style={{ position: 'relative', flex: '1 1 220px', minWidth: 0, maxWidth: '480px' }}>
          <input
            type="text"
            className="form-control"
            placeholder="Search by MRN, patient name, or condition..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        <button type="submit" className="btn btn-primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
          <SearchIcon size={16} />
          <span>Search</span>
        </button>
        {search && (
          <button type="button" className="btn btn-secondary" onClick={() => { setSearch(''); setPage(1); }}>
            Clear
          </button>
        )}
      </form>

      {/* Patients Table */}
      <div className="card" style={{ borderRadius: '16px', padding: '20px' }}>
        {loading ? (
          <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b' }}>
            Loading patient records...
          </div>
        ) : patients.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b' }}>
            No patients match the selected filter.
          </div>
        ) : (
          <div className="responsive-table-container">
            <table className="data-table" style={{ width: '100%', minWidth: '760px' }}>
              <thead>
                <tr>
                  <th>Record ID / MRN</th>
                  <th>Patient Name</th>
                  <th>Age / Sex</th>
                  <th>Kidney Function (eGFR)</th>
                  <th>Conditions</th>
                  <th>Care Team</th>
                  <th>Active Meds</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {patients.map(p => (
                  <tr key={p.id}>
                    <td>
                      <code style={{ fontWeight: 600, color: '#0284c7' }}>{p.patient_mrn || p.patient_id}</code>
                      {p.is_near_miss_case ? (
                        <span className="badge badge-warning" style={{ display: 'block', width: 'fit-content', marginTop: '4px', fontSize: '10px' }}>
                          Near-Miss
                        </span>
                      ) : null}
                    </td>
                    <td>
                      <strong>{p.full_name}</strong>
                    </td>
                    <td>
                      {p.age || '-'} y / {p.gender ? p.gender.charAt(0).toUpperCase() : (p.sex || '-')}
                    </td>
                    <td>{getEgfrBadge(p.egfr)}</td>
                    <td style={{ maxWidth: '240px', fontSize: '13px' }}>
                      {p.conditions || p.diagnoses || <span style={{ color: '#94a3b8' }}>None documented</span>}
                    </td>
                    <td>
                      <span className="badge badge-neutral">
                        {p.distinct_doctors || 1} Doctor(s)
                      </span>
                    </td>
                    <td>
                      <span className="badge badge-info" style={{ fontWeight: 700 }}>
                        {p.active_prescriptions || 0} active
                      </span>
                    </td>
                    <td>
                      <button
                        type="button"
                        onClick={() => navigate(`/patients/${p.id}`)}
                        className="btn btn-primary btn-sm"
                      >
                        Open Chart &rarr;
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
