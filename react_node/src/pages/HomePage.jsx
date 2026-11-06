import React, { useState } from 'react';
import { useI18n } from '../context/I18nContext';
import { useAuth } from '../context/AuthContext';
import {
  PillIcon,
  SearchIcon,
  KeyIcon,
  BookOpenIcon,
  KidneyIcon,
  LungsIcon,
  AlertTriangleIcon,
  CheckCircleIcon,
  ZapIcon,
  Volume2Icon,
  PhoneCallIcon,
  getSeverityIcon
} from '../components/Icons';

export default function HomePage({ navigate }) {
  const { t, speak } = useI18n();
  const { user } = useAuth();

  const presetDefs = {
    'war-asp':    { score: 45, level: 'serious', renderIcon: () => <PillIcon size={18} color="#ea580c" /> },
    'met-ckd':    { score: 20, level: 'danger',  renderIcon: () => <KidneyIcon size={18} color="#dc2626" /> },
    'lis-ibu':    { score: 40, level: 'serious', renderIcon: () => <PillIcon size={18} color="#ea580c" /> },
    'dup-statin': { score: 35, level: 'serious', renderIcon: () => <AlertTriangleIcon size={18} color="#ea580c" /> },
    'pro-asthma': { score: 15, level: 'danger',  renderIcon: () => <LungsIcon size={18} color="#dc2626" /> },
    'safe-check': { score: 98, level: 'clean',   renderIcon: () => <CheckCircleIcon size={18} color="#16a34a" /> }
  };

  const [activePreset, setActivePreset] = useState('war-asp');

  const getPresetData = (key) => {
    const def = presetDefs[key];
    return {
      ...def,
      button: t(`preset.${key}.button`),
      badge: def.level === 'clean' ? t('preset.clean_badge') : t(`sev.${def.level}.badge`),
      title: t(`preset.${key}.title`),
      problem: t(`preset.${key}.problem`),
      why: t(`preset.${key}.why`),
      action: t(`preset.${key}.action`)
    };
  };

  const current = getPresetData(activePreset);

  const handleReadAloud = () => {
    const text = `${current.title}. ${t('home.q_problem')}: ${current.problem}. ${t('home.q_why')}: ${current.why}. ${t('home.q_ask')}: ${current.action}`;
    speak(text);
  };

  return (
    <div className="ez-page">
      {/* 1. WELCOME HERO */}
      <section className="ez-hero">
        <p className="ez-hero__tag" style={{ textTransform: 'uppercase', letterSpacing: '0.04em' }}>{t('home.tag')}</p>
        <h2 className="ez-hero__title">{t('home.hero_title')}</h2>
        <p className="ez-hero__desc">{t('home.hero_desc')}</p>

        <div className="ez-actions">
          <button
            type="button"
            onClick={() => navigate('/check')}
            className="ez-big-btn ez-big-btn--primary"
          >
            <span className="ez-big-btn__icon"><SearchIcon size={28} /></span>
            <span>{t('home.btn_check')}<small>{t('home.btn_check_sub')}</small></span>
          </button>

          {user ? (
            <button
              type="button"
              onClick={() => navigate('/my-prescriptions')}
              className="ez-big-btn ez-big-btn--teal"
            >
              <span className="ez-big-btn__icon"><PillIcon size={28} /></span>
              <span>{t('home.btn_my_meds')}<small>{t('home.btn_my_meds_sub')}</small></span>
            </button>
          ) : (
            <button
              type="button"
              onClick={() => navigate('/login')}
              className="ez-big-btn ez-big-btn--light"
            >
              <span className="ez-big-btn__icon"><KeyIcon size={28} color="#0284c7" /></span>
              <span>{t('home.btn_login')}<small>{t('home.btn_login_sub')}</small></span>
            </button>
          )}

          <button
            type="button"
            onClick={() => navigate('/medicines')}
            className="ez-big-btn ez-big-btn--light"
          >
            <span className="ez-big-btn__icon"><BookOpenIcon size={28} color="#0284c7" /></span>
            <span>{t('home.btn_list')}<small>{t('home.btn_list_sub')}</small></span>
          </button>
        </div>
      </section>

      {/* 2. HOW IT WORKS: 3 STEPS */}
      <section className="ez-section">
        <h3 className="ez-section__title">{t('home.how_title')}</h3>
        <ol className="ez-steps">
          <li className="ez-step">
            <span className="ez-step__num">1</span>
            <div>
              <h4>{t('home.step1_title')}</h4>
              <p>{t('home.step1_desc')}</p>
            </div>
          </li>
          <li className="ez-step">
            <span className="ez-step__num">2</span>
            <div>
              <h4>{t('home.step2_title')}</h4>
              <p>{t('home.step2_desc')}</p>
            </div>
          </li>
          <li className="ez-step">
            <span className="ez-step__num">3</span>
            <div>
              <h4>{t('home.step3_title')}</h4>
              <p>{t('home.step3_desc')}</p>
            </div>
          </li>
        </ol>
      </section>

      {/* 3. TRY IT: EXAMPLE COMBINATIONS */}
      <section className="ez-section">
        <h3 className="ez-section__title" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <ZapIcon size={22} color="#0284c7" />
          <span>{t('home.try_title')}</span>
        </h3>
        <p className="ez-section__desc">{t('home.try_desc')}</p>

        <div className="ez-choice-list" role="group" aria-label={t('home.try_title')}>
          {Object.keys(presetDefs).map((key) => {
            const p = getPresetData(key);
            return (
              <button
                key={key}
                type="button"
                className={`ez-choice ${key === activePreset ? 'is-active' : ''}`}
                onClick={() => setActivePreset(key)}
                aria-pressed={key === activePreset}
                style={{ display: 'flex', alignItems: 'center', gap: '10px' }}
              >
                <span>{p.renderIcon()}</span>
                <span>{p.button}</span>
              </button>
            );
          })}
        </div>

        <div
          id="quickCheckResult"
          key={activePreset}
          className={`ez-result ez-result--${current.level} animate-spring-in`}
          aria-live="polite"
        >
          <div className="ez-result__head">
            <div>
              <span id="qcSeverityBadge" className="ez-level-tag" style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
                {getSeverityIcon(current.level, 18)}
                <span>{current.badge}</span>
              </span>
              <h4 id="qcTitle" className="ez-result__title">{current.title}</h4>
            </div>
            <div className="ez-score" aria-label={t('common.safety_score')}>
              <span id="qcScore" className="ez-score__num">{current.score}%</span>
              <span className="ez-score__label">{t('common.safety_score')}</span>
            </div>
          </div>

          <div className="ez-result__block">
            <div className="ez-result__label">{t('home.q_problem')}</div>
            <div id="qcProblem" className="ez-result__strong">{current.problem}</div>
          </div>
          <div className="ez-result__block">
            <div className="ez-result__label">{t('home.q_why')}</div>
            <div id="qcWhy">{current.why}</div>
          </div>
          <div className="ez-alert__todo">
            <div className="ez-alert__todo-label" style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
              <PhoneCallIcon size={16} />
              <span>{t('home.q_ask')}</span>
            </div>
            <div id="qcAction">{current.action}</div>
          </div>

          <div className="ez-result__foot">
            <button
              type="button"
              className="ez-speak"
              onClick={handleReadAloud}
              style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
            >
              <Volume2Icon size={18} />
              <span>{t('common.read_aloud')}</span>
            </button>
            <button
              type="button"
              onClick={() => navigate('/check')}
              className="btn btn-primary"
            >
              {t('home.try_own')} &rarr;
            </button>
          </div>
        </div>
        <p className="ez-note">{t('home.example_note')}</p>
      </section>

      {/* 4. MEDICINE LIST BANNER */}
      <section className="ez-section ez-section--teal">
        <h3 className="ez-section__title" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <BookOpenIcon size={22} color="#0d9488" />
          <span>{t('home.list_title')}</span>
        </h3>
        <p className="ez-section__desc">{t('home.list_desc')}</p>
        <button
          type="button"
          onClick={() => navigate('/medicines')}
          className="ez-big-btn ez-big-btn--teal ez-big-btn--inline"
          style={{ display: 'inline-flex', alignItems: 'center', gap: '10px' }}
        >
          <BookOpenIcon size={24} />
          <span>{t('home.btn_list')}</span>
        </button>
      </section>
    </div>
  );
}
