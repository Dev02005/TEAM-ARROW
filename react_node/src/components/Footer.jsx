import React from 'react';
import { useI18n } from '../context/I18nContext';
import { StethoscopeIcon } from './Icons';

export default function Footer() {
  const { t } = useI18n();

  return (
    <footer className="app-footer" style={{ borderTop: '1px solid #e2e8f0', background: '#ffffff', padding: '24px 16px', marginTop: 'auto' }}>
      <div className="footer-container" style={{ textAlign: 'center' }}>
        <div>
          <strong style={{ color: '#0f172a' }}>AushadhSeva</strong> &bull; {t('footer.cds')}
          <p style={{ marginTop: '6px', color: 'var(--text-muted)', fontSize: '13.5px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px' }}>
            <StethoscopeIcon size={15} color="#0284c7" />
            <em>{t('footer.disclaimer')}</em>
          </p>
        </div>
      </div>
    </footer>
  );
}
