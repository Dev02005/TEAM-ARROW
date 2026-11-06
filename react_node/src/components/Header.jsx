import React, { useState, useEffect, useRef } from 'react';
import { useI18n } from '../context/I18nContext';
import { useAuth } from '../context/AuthContext';
import {
  PillIcon,
  StethoscopeIcon,
  HomeIcon,
  BookOpenIcon,
  SearchIcon,
  UsersIcon,
  UserIcon,
  GlobeIcon,
  ChevronDownIcon,
  LogOutIcon,
  LogInIcon,
  UserPlusIcon,
  CheckIcon
} from './Icons';

export default function Header({ currentPath, navigate }) {
  const { lang, setLang, textSize, cycleTextSize, t, supportedLangs } = useI18n();
  const { user, isAdmin, logout } = useAuth();
  const [langMenuOpen, setLangMenuOpen] = useState(false);
  const langDropdownRef = useRef(null);

  // Close dropdown on click outside
  useEffect(() => {
    function handleClickOutside(event) {
      if (langDropdownRef.current && !langDropdownRef.current.contains(event.target)) {
        setLangMenuOpen(false);
      }
    }
    if (langMenuOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [langMenuOpen]);

  const handleLangSelect = (code) => {
    setLang(code);
    setLangMenuOpen(false);
  };

  return (
    <>
      {/* MANDATORY CLINICAL SAFETY DISCLAIMER */}
      <aside className="clinical-disclaimer-bar" role="note" aria-label={t('disclaimer.aria')}>
        <span className="icon" style={{ display: 'inline-flex', alignItems: 'center' }}>
          <StethoscopeIcon size={18} color="#38bdf8" />
        </span>
        <span>
          <strong>{t('disclaimer.title')}</strong> {t('disclaimer.short')}
        </span>
      </aside>

      {/* Main Navigation Header */}
      <header className="main-nav">
        <div className="nav-container">
          <a
            href="/"
            onClick={(e) => { e.preventDefault(); navigate('/'); }}
            className="brand-wrapper"
            style={{ textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '10px', flexShrink: 0 }}
          >
            <div className="brand-logo" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#e0f2fe', borderRadius: '12px', width: '40px', height: '40px', color: '#0284c7' }}>
              <PillIcon size={22} color="#0284c7" />
            </div>
            <div className="brand-text">
              <h1 style={{ display: 'flex', alignItems: 'center', gap: '8px', margin: 0, fontSize: '20px' }}>
                <span className="brand-name-highlight" style={{ color: '#0f172a', fontWeight: 800 }}>AushadhSeva</span>
                <span
                  className="badge badge-info ez-hide-mobile"
                  style={{ fontSize: '11px', padding: '2px 8px', letterSpacing: '0.03em', fontWeight: 700 }}
                >
                  SEVA First
                </span>
              </h1>
            </div>
          </a>

          <nav className="nav-links" aria-label={t('nav.main')} style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <a
              href="/"
              onClick={(e) => { e.preventDefault(); navigate('/'); }}
              className={`nav-link ${currentPath === '/' ? 'active' : ''}`}
              style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', whiteSpace: 'nowrap' }}
            >
              <HomeIcon size={16} /> {t('nav.home')}
            </a>

            <a
              href="/medicines"
              onClick={(e) => { e.preventDefault(); navigate('/medicines'); }}
              className={`nav-link ${currentPath === '/medicines' ? 'active' : ''}`}
              style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', whiteSpace: 'nowrap' }}
            >
              <BookOpenIcon size={16} /> {t('nav.medicines_list')}
            </a>

            {user && !isAdmin && (
              <a
                href="/my-prescriptions"
                onClick={(e) => { e.preventDefault(); navigate('/my-prescriptions'); }}
                className={`nav-link ${currentPath === '/my-prescriptions' ? 'active' : ''}`}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', whiteSpace: 'nowrap' }}
              >
                <PillIcon size={16} /> {t('nav.my_medicines')}
              </a>
            )}

            {isAdmin && (
              <a
                href="/patients"
                onClick={(e) => { e.preventDefault(); navigate('/patients'); }}
                className={`nav-link ${currentPath.startsWith('/patient') ? 'active' : ''}`}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', whiteSpace: 'nowrap' }}
              >
                <UsersIcon size={16} /> {t('nav.patient_records')}
              </a>
            )}

            <a
              href="/check"
              onClick={(e) => { e.preventDefault(); navigate('/check'); }}
              className="btn btn-teal btn-sm"
              style={{ marginLeft: '4px', display: 'inline-flex', alignItems: 'center', gap: '6px', whiteSpace: 'nowrap' }}
            >
              <SearchIcon size={15} /> {t('nav.check')}
            </a>
          </nav>

          <div className="nav-actions ez-nav-actions" style={{ display: 'flex', alignItems: 'center', gap: '8px', flexShrink: 0 }}>
            {/* Language dropdown */}
            <div className="lang-dropdown" ref={langDropdownRef} style={{ position: 'relative', display: 'inline-block' }}>
              <button
                type="button"
                className="ez-pill-btn lang-dropdown-toggle"
                onClick={() => setLangMenuOpen(!langMenuOpen)}
                aria-haspopup="true"
                aria-expanded={langMenuOpen}
                title={t('lang.change')}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
              >
                <GlobeIcon size={17} />
                <span>{supportedLangs[lang]?.native}</span>
                <ChevronDownIcon size={13} />
              </button>
              {langMenuOpen && (
                <div
                  className="lang-dropdown-menu autocomplete-dropdown"
                  style={{
                    display: 'block',
                    position: 'absolute',
                    right: 0,
                    top: 'calc(100% + 6px)',
                    background: '#ffffff',
                    border: '1px solid #cbd5e1',
                    borderRadius: '12px',
                    boxShadow: 'var(--shadow-popover)',
                    minWidth: '190px',
                    zIndex: 3000,
                    padding: '6px 0',
                    overflow: 'hidden'
                  }}
                >
                  {Object.entries(supportedLangs).map(([code, info]) => (
                    <button
                      key={code}
                      type="button"
                      onClick={() => handleLangSelect(code)}
                      className={`lang-dropdown-item ${code === lang ? 'is-active' : ''}`}
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        width: '100%',
                        border: 'none',
                        background: code === lang ? '#f1f5f9' : 'transparent',
                        padding: '10px 16px',
                        fontSize: '15px',
                        fontWeight: 700,
                        color: '#1e293b',
                        cursor: 'pointer',
                        textAlign: 'left'
                      }}
                    >
                      <span>{info.native}</span>
                      {code === lang && <CheckIcon size={16} color="#0284c7" />}
                    </button>
                  ))}
                </div>
              )}
            </div>

            {/* Text size button */}
            <button
              type="button"
              className={`ez-pill-btn ${textSize !== 'normal' ? 'is-active' : ''}`}
              onClick={cycleTextSize}
              aria-label={`${t('text.size_label') || 'Text size'}: ${textSize}`}
              title={`${t('text.size_label') || 'Text size'}: ${
                textSize === 'normal'
                  ? 'Normal (Click for Large)'
                  : textSize === 'large'
                  ? 'Large (+15%) (Click for Extra Large)'
                  : 'Extra Large (+30%) (Click for Normal)'
              }`}
              style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: '5px',
                background: textSize === 'xlarge' ? '#bae6fd' : textSize === 'large' ? '#e0f2fe' : '#ffffff',
                borderColor: textSize !== 'normal' ? '#0284c7' : 'var(--ez-line)',
                color: textSize !== 'normal' ? '#0284c7' : 'var(--ez-text)',
                fontWeight: 700
              }}
            >
              <span aria-hidden="true" className="ez-aa">
                <span className="ez-aa__big">A</span>
                <span className="ez-aa__small">A</span>
              </span>
              {textSize !== 'normal' && (
                <span
                  style={{
                    fontSize: '11px',
                    fontWeight: 800,
                    padding: '1px 5px',
                    borderRadius: '999px',
                    background: '#0284c7',
                    color: '#ffffff',
                    lineHeight: '1.2'
                  }}
                >
                  {textSize === 'large' ? '+15%' : '+30%'}
                </span>
              )}
            </button>

            {/* User profile / Login */}
            {user ? (
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <div
                  className="ez-hide-mobile"
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '6px',
                    background: isAdmin ? '#eff6ff' : '#f0fdf4',
                    border: `1px solid ${isAdmin ? '#bfdbfe' : '#bbf7d0'}`,
                    padding: '6px 12px',
                    borderRadius: '9999px'
                  }}
                >
                  {isAdmin ? <StethoscopeIcon size={15} color="#1e40af" /> : <UserIcon size={15} color="#166534" />}
                  <span style={{ fontSize: '13.5px', fontWeight: 700, color: isAdmin ? '#1e40af' : '#166534' }}>
                    {user.full_name}
                  </span>
                </div>
                <button
                  type="button"
                  onClick={logout}
                  className="btn btn-secondary btn-sm"
                  style={{ padding: '6px 12px', display: 'inline-flex', alignItems: 'center', gap: '5px' }}
                >
                  <LogOutIcon size={14} />
                  <span>{t('nav.sign_out')}</span>
                </button>
              </div>
            ) : (
              <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                <button
                  type="button"
                  onClick={() => navigate('/login')}
                  className="btn btn-secondary btn-sm"
                  style={{ display: 'inline-flex', alignItems: 'center', gap: '5px' }}
                >
                  <LogInIcon size={14} />
                  <span>{t('nav.login')}</span>
                </button>
                <button
                  type="button"
                  onClick={() => navigate('/register')}
                  className="btn btn-primary btn-sm ez-hide-mobile"
                  style={{ display: 'inline-flex', alignItems: 'center', gap: '5px' }}
                >
                  <UserPlusIcon size={14} />
                  <span>{t('nav.sign_up')}</span>
                </button>
              </div>
            )}
          </div>
        </div>
      </header>
    </>
  );
}
