import React from 'react';
import { useI18n } from '../context/I18nContext';
import { PillIcon, StethoscopeIcon } from './Icons';

export default function Footer({ navigate }) {
  const { t } = useI18n();

  const handleNav = (e, path) => {
    e.preventDefault();
    if (navigate) {
      navigate(path);
    } else {
      window.location.href = path;
    }
  };

  return (
    <footer className="app-footer" role="contentinfo">
      <div className="footer-container">
        {/* Top Section: Brand & Quick Links */}
        <div className="footer-top-row">
          <div className="footer-brand-col">
            <div className="footer-brand-title">
              <div className="footer-logo-box">
                <PillIcon size={20} color="#0284c7" />
              </div>
              <span className="footer-brand-name">AushadhSeva</span>
              <span className="footer-sfic-badge">SEVA First 2026</span>
            </div>
            <p className="footer-brand-desc">
              {t('footer.cds')}
            </p>
          </div>

          <nav className="footer-nav-col" aria-label="Footer navigation">
            <a href="/" onClick={(e) => handleNav(e, '/')} className="footer-nav-link">
              {t('nav.home')}
            </a>
            <a href="/check" onClick={(e) => handleNav(e, '/check')} className="footer-nav-link">
              {t('nav.check')}
            </a>
            <a href="/medicines" onClick={(e) => handleNav(e, '/medicines')} className="footer-nav-link">
              {t('nav.medicines_list')}
            </a>
            <a href="/my-prescriptions" onClick={(e) => handleNav(e, '/my-prescriptions')} className="footer-nav-link">
              {t('nav.my_medicines')}
            </a>
            <a href="/login" onClick={(e) => handleNav(e, '/login')} className="footer-nav-link">
              {t('nav.login')}
            </a>
          </nav>
        </div>

        {/* Divider */}
        <hr className="footer-divider" />

        {/* Clinical Disclaimer Bar */}
        <div className="footer-disclaimer-box">
          <span className="footer-disclaimer-icon">
            <StethoscopeIcon size={16} color="#0284c7" />
          </span>
          <p className="footer-disclaimer-text">
            <strong>Mandatory Clinical Notice:</strong> {t('footer.disclaimer')}
          </p>
        </div>

        {/* Bottom Credits */}
        <div className="footer-bottom-row">
          <p className="footer-credits">
            &copy; 2026 Team Arrow (Dev02005) &bull; SEVA First Innovation Challenge &bull; Healthcare Problem H4
          </p>
          <p className="footer-mission">
            Theme 3: Swasth &amp; Samavesh Bharat
          </p>
        </div>
      </div>
    </footer>
  );
}
