import React, { useState } from 'react';
import { useI18n } from '../context/I18nContext';
import { useAuth } from '../context/AuthContext';
import {
  ClipboardListIcon,
  AlertTriangleIcon
} from '../components/Icons';

export default function RegisterPage({ navigate }) {
  const { t } = useI18n();
  const { register, loading } = useAuth();

  const [formData, setFormData] = useState({
    full_name: '',
    email: '',
    password: '',
    date_of_birth: '',
    gender: 'female',
    conditions: '',
    allergies: ''
  });

  const [error, setError] = useState('');

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (formData.password.length < 6) {
      setError(t('register.err_password'));
      return;
    }

    try {
      await register(formData);
      navigate('/check');
    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <div className="ez-page ez-narrow">
      <div className="ez-page-head" style={{ textAlign: 'center', marginBottom: '24px' }}>
        <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '8px' }}>
          <div style={{ background: '#e0f2fe', width: '48px', height: '48px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <ClipboardListIcon size={24} color="#0284c7" />
          </div>
        </div>
        <h2 className="ez-page-title">{t('register.heading')}</h2>
        <p className="ez-page-desc">{t('register.sub')}</p>
      </div>

      <div className="ez-card" style={{ borderRadius: '18px', padding: '24px' }}>
        {error && (
          <div className="alert alert-danger" style={{ marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <AlertTriangleIcon size={18} color="#dc2626" />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label className="form-label">{t('register.name')} *</label>
            <input
              type="text"
              name="full_name"
              className="form-control"
              placeholder="e.g. Eleanor Vance"
              value={formData.full_name}
              onChange={handleChange}
              required
            />
          </div>

          <div className="form-group" style={{ marginTop: '16px' }}>
            <label className="form-label">{t('login.email')} *</label>
            <input
              type="email"
              name="email"
              className="form-control"
              placeholder="e.g. eleanor@example.com"
              value={formData.email}
              onChange={handleChange}
              required
            />
          </div>

          <div className="form-group" style={{ marginTop: '16px' }}>
            <label className="form-label">{t('register.password')} *</label>
            <input
              type="password"
              name="password"
              className="form-control"
              placeholder="At least 6 characters"
              value={formData.password}
              onChange={handleChange}
              required
            />
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '16px', marginTop: '16px' }}>
            <div className="form-group">
              <label className="form-label">{t('register.dob')}</label>
              <input
                type="date"
                name="date_of_birth"
                className="form-control"
                value={formData.date_of_birth}
                onChange={handleChange}
              />
            </div>

            <div className="form-group">
              <label className="form-label">{t('register.gender')}</label>
              <select
                name="gender"
                className="form-control"
                value={formData.gender}
                onChange={handleChange}
              >
                <option value="female">{t('register.female')}</option>
                <option value="male">{t('register.male')}</option>
                <option value="other">{t('register.other')}</option>
              </select>
            </div>
          </div>

          <div className="form-group" style={{ marginTop: '16px' }}>
            <label className="form-label">{t('register.conditions')}</label>
            <input
              type="text"
              name="conditions"
              className="form-control"
              placeholder="e.g. Diabetes, Hypertension, Asthma"
              value={formData.conditions}
              onChange={handleChange}
            />
            <small style={{ color: '#64748b', marginTop: '4px', display: 'block' }}>
              Used to check contraindications with prescribed medicines.
            </small>
          </div>

          <div className="form-group" style={{ marginTop: '16px' }}>
            <label className="form-label">{t('register.allergies')}</label>
            <input
              type="text"
              name="allergies"
              className="form-control"
              placeholder="e.g. Penicillin, Sulfa, Aspirin"
              value={formData.allergies}
              onChange={handleChange}
            />
          </div>

          <div style={{ marginTop: '24px' }}>
            <button
              type="submit"
              className="ez-big-btn ez-big-btn--primary"
              style={{ width: '100%', justifyContent: 'center' }}
              disabled={loading}
            >
              <span>{loading ? 'Creating Account...' : t('register.submit')}</span>
            </button>
          </div>
        </form>

        <div style={{ marginTop: '20px', textAlign: 'center', fontSize: '14.5px', color: '#64748b' }}>
          {t('register.have_account')}{' '}
          <a
            href="/login"
            onClick={(e) => { e.preventDefault(); navigate('/login'); }}
            style={{ fontWeight: 700, color: '#0284c7' }}
          >
            {t('register.sign_in')}
          </a>
        </div>
      </div>
    </div>
  );
}
