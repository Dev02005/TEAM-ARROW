import React from 'react';
import { useI18n } from '../context/I18nContext';
import { useAuth } from '../context/AuthContext';
import {
  HomeIcon,
  PillIcon,
  UsersIcon,
  BookOpenIcon,
  SearchIcon,
  UserIcon,
  LogInIcon
} from './Icons';

export default function BottomNav({ currentPath, navigate }) {
  const { t } = useI18n();
  const { user, isAdmin } = useAuth();

  return (
    <nav className="ez-bottom-nav" aria-label="Quick mobile navigation">
      <a
        href="/"
        onClick={(e) => { e.preventDefault(); navigate('/'); }}
        className={`ez-bottom-nav__item ${currentPath === '/' ? 'is-active' : ''}`}
      >
        <span className="ez-bottom-nav__icon"><HomeIcon size={22} /></span>
        <span>{t('nav.home')}</span>
      </a>

      {user && !isAdmin ? (
        <a
          href="/my-prescriptions"
          onClick={(e) => { e.preventDefault(); navigate('/my-prescriptions'); }}
          className={`ez-bottom-nav__item ${currentPath === '/my-prescriptions' ? 'is-active' : ''}`}
        >
          <span className="ez-bottom-nav__icon"><PillIcon size={22} /></span>
          <span>{t('nav.my_medicines')}</span>
        </a>
      ) : isAdmin ? (
        <a
          href="/patients"
          onClick={(e) => { e.preventDefault(); navigate('/patients'); }}
          className={`ez-bottom-nav__item ${currentPath.startsWith('/patient') ? 'is-active' : ''}`}
        >
          <span className="ez-bottom-nav__icon"><UsersIcon size={22} /></span>
          <span>{t('nav.patient_records')}</span>
        </a>
      ) : (
        <a
          href="/medicines"
          onClick={(e) => { e.preventDefault(); navigate('/medicines'); }}
          className={`ez-bottom-nav__item ${currentPath === '/medicines' ? 'is-active' : ''}`}
        >
          <span className="ez-bottom-nav__icon"><BookOpenIcon size={22} /></span>
          <span>{t('nav.medicines_list')}</span>
        </a>
      )}

      <a
        href="/check"
        onClick={(e) => { e.preventDefault(); navigate('/check'); }}
        className={`ez-bottom-nav__item ${currentPath === '/check' ? 'is-active' : ''}`}
      >
        <span className="ez-bottom-nav__icon"><SearchIcon size={22} /></span>
        <span>{t('nav.check')}</span>
      </a>

      {user ? (
        <a
          href={isAdmin ? '/patients' : '/my-prescriptions'}
          onClick={(e) => { e.preventDefault(); navigate(isAdmin ? '/patients' : '/my-prescriptions'); }}
          className={`ez-bottom-nav__item ${(isAdmin && currentPath.startsWith('/patient')) || (!isAdmin && currentPath === '/my-prescriptions') ? 'is-active' : ''}`}
        >
          <span className="ez-bottom-nav__icon"><UserIcon size={22} /></span>
          <span>{t('nav.my_account')}</span>
        </a>
      ) : (
        <a
          href="/login"
          onClick={(e) => { e.preventDefault(); navigate('/login'); }}
          className={`ez-bottom-nav__item ${currentPath === '/login' ? 'is-active' : ''}`}
        >
          <span className="ez-bottom-nav__icon"><LogInIcon size={22} /></span>
          <span>{t('nav.login')}</span>
        </a>
      )}
    </nav>
  );
}
