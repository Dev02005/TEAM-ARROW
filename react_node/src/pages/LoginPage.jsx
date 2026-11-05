import React, { useState } from 'react';
import { useI18n } from '../context/I18nContext';
import { useAuth } from '../context/AuthContext';
import {
  KeyIcon,
  AlertTriangleIcon,
  ZapIcon,
  UserIcon,
  StethoscopeIcon
} from '../components/Icons';

export default function LoginPage({ navigate }) {
  const { t } = useI18n();
  const { login, loading } = useAuth();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    try {
      const user = await login(email, password);
      if (user.role === 'admin') {
        navigate('/patients');
      } else {
        navigate('/my-prescriptions');
      }
    } catch (err) {
      setError(err.message);
    }
  };

  const handleDemoLogin = async (demoEmail) => {
    setError('');
    try {
      const user = await login(demoEmail, 'citizen123', demoEmail);
      if (user.role === 'admin') {
        navigate('/patients');
      } else {
        navigate('/my-prescriptions');
      }
    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <div className="ez-page ez-narrow">
      <div className="ez-page-head" style={{ textAlign: 'center', marginBottom: '24px' }}>
        <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '8px' }}>
          <div style={{ background: '#e0f2fe', width: '48px', height: '48px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <KeyIcon size={24} color="#0284c7" />
          </div>
        </div>
        <h2 className="ez-page-title">{t('login.heading')}</h2>
        <p className="ez-page-desc">{t('login.sub')}</p>
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
            <label className="form-label">{t('login.email')}</label>
            <input
              type="email"
              className="form-control"
              placeholder="e.g. citizen1@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </div>

          <div className="form-group" style={{ marginTop: '16px' }}>
            <label className="form-label">{t('login.password')}</label>
            <input
              type="password"
              className="form-control"
              placeholder="••••••••"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>

          <div style={{ marginTop: '24px' }}>
            <button
              type="submit"
              className="ez-big-btn ez-big-btn--primary"
              style={{ width: '100%', justifyContent: 'center' }}
              disabled={loading}
            >
              <span>{loading ? 'Signing in...' : t('login.submit')}</span>
            </button>
          </div>
        </form>

        <div style={{ marginTop: '20px', textAlign: 'center', fontSize: '14.5px', color: '#64748b' }}>
          {t('login.new')}{' '}
          <a
            href="/register"
            onClick={(e) => { e.preventDefault(); navigate('/register'); }}
            style={{ fontWeight: 700, color: '#0284c7' }}
          >
            {t('login.create')}
          </a>
        </div>
      </div>

      {/* 1-Click Demo Accounts for Judges & Demonstrations */}
      <div className="ez-card" style={{ marginTop: '24px', background: '#f8fafc', border: '1px dashed #cbd5e1', borderRadius: '18px', padding: '20px' }}>
        <h4 style={{ margin: '0 0 14px', fontSize: '15px', fontWeight: 800, color: '#334155', display: 'flex', alignItems: 'center', gap: '8px' }}>
          <ZapIcon size={18} color="#0284c7" />
          <span>1-Click Demo Accounts (Instant Judge Login)</span>
        </h4>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
          <button
            type="button"
            className="btn btn-secondary btn-sm"
            style={{ textAlign: 'left', justifyContent: 'flex-start', padding: '10px 14px', display: 'flex', alignItems: 'center', gap: '10px' }}
            onClick={() => handleDemoLogin('eleanor@example.com')}
          >
            <UserIcon size={18} color="#0284c7" />
            <div>
              <strong>Eleanor Vance</strong> (eGFR 28, diabetes + BP)
              <div style={{ fontSize: '12px', color: '#64748b' }}>Triggers 3 clinical warnings (Metformin, Brufen, Telma)</div>
            </div>
          </button>

          <button
            type="button"
            className="btn btn-secondary btn-sm"
            style={{ textAlign: 'left', justifyContent: 'flex-start', padding: '10px 14px', display: 'flex', alignItems: 'center', gap: '10px' }}
            onClick={() => handleDemoLogin('robert@example.com')}
          >
            <UserIcon size={18} color="#0284c7" />
            <div>
              <strong>Robert Chen</strong> (Warfarin + Aspirin interaction)
              <div style={{ fontSize: '12px', color: '#64748b' }}>Blood thinner conflict from two prescribers</div>
            </div>
          </button>

          <button
            type="button"
            className="btn btn-secondary btn-sm"
            style={{ textAlign: 'left', justifyContent: 'flex-start', padding: '10px 14px', display: 'flex', alignItems: 'center', gap: '10px' }}
            onClick={() => handleDemoLogin('grace@example.com')}
          >
            <UserIcon size={18} color="#0284c7" />
            <div>
              <strong>Grace Hopper</strong> (Normal regimen, eGFR 95)
              <div style={{ fontSize: '12px', color: '#64748b' }}>Clean baseline with no warnings</div>
            </div>
          </button>

          <button
            type="button"
            className="btn btn-primary btn-sm"
            style={{ textAlign: 'left', justifyContent: 'flex-start', padding: '10px 14px', display: 'flex', alignItems: 'center', gap: '10px' }}
            onClick={() => handleDemoLogin('admin@aushadhseva.in')}
          >
            <StethoscopeIcon size={18} color="#ffffff" />
            <div>
              <strong>Dr. Alistair Sharma</strong> (Hospital Clinical Admin)
              <div style={{ fontSize: '12px', color: '#bae6fd' }}>Full cohort management, search, and audit charts</div>
            </div>
          </button>
        </div>
      </div>
    </div>
  );
}
