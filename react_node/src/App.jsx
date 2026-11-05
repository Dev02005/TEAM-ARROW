import React, { useState, useEffect } from 'react';
import { useI18n } from './context/I18nContext';
import { useAuth } from './context/AuthContext';
import { GlobeIcon } from './components/Icons';

import Header from './components/Header';
import BottomNav from './components/BottomNav';
import Footer from './components/Footer';

import HomePage from './pages/HomePage';
import CheckMedicinePage from './pages/CheckMedicinePage';
import MyPrescriptionsPage from './pages/MyPrescriptionsPage';
import PatientsPage from './pages/PatientsPage';
import PatientViewPage from './pages/PatientViewPage';
import PrintSafetyCardPage from './pages/PrintSafetyCardPage';
import MedicinesPage from './pages/MedicinesPage';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';

import './styles/style.css';
import './styles/elder.css';

export default function App() {
  const [currentPath, setCurrentPath] = useState(window.location.pathname || '/');
  const { lang, setLang, supportedLangs, t } = useI18n();
  const { user } = useAuth();

  // First-visit language modal check
  const [showFirstVisitLangModal, setShowFirstVisitLangModal] = useState(() => {
    return !localStorage.getItem('aushadhseva_lang_chosen') && !localStorage.getItem('dosewise_lang_chosen');
  });

  const navigate = (path) => {
    window.history.pushState({}, '', path);
    setCurrentPath(path);
    window.scrollTo(0, 0);
  };

  useEffect(() => {
    const onPopState = () => {
      setCurrentPath(window.location.pathname || '/');
    };
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  // Dynamic SEO Page Title & Description Updates
  useEffect(() => {
    let pageTitle = 'AushadhSeva — Medication Safety Second Pair of Eyes';
    let metaDesc = 'Inclusive trilingual Clinical Decision Support platform preventing adverse drug-drug interactions (DDIs), duplicate therapies, and renal dosing risks.';

    if (currentPath === '/check' || currentPath === '/attach_prescription.php') {
      pageTitle = 'Check Prescription & Medicine Safety | AushadhSeva';
      metaDesc = 'Scan or type prescription medicines to verify clinical drug-drug interactions, duplicate therapies, and renal safety warnings.';
    } else if (currentPath === '/my-prescriptions' || currentPath === '/my_prescriptions.php') {
      pageTitle = 'My Medication Locker & Safety Card | AushadhSeva';
      metaDesc = 'View your active prescriptions, comprehensive safety alerts, and generate your doctor visit safety card.';
    } else if (currentPath === '/medicines' || currentPath === '/medicines.php') {
      pageTitle = 'Hospital Formulary & Indian Drug Directory | AushadhSeva';
      metaDesc = 'Search 496+ verified generic medicines, Indian brand formulations, renal dosage cutoffs, and pregnancy safety categories.';
    } else if (currentPath === '/patients' || currentPath === '/patients.php') {
      pageTitle = 'Patient Cohort Management & Safety Surveillance | AushadhSeva';
      metaDesc = 'Multi-doctor longitudinal patient charts, KDIGO renal impairment filters, and near-miss case audits.';
    } else if (currentPath.startsWith('/patients/')) {
      pageTitle = 'Patient Clinical Chart & Longitudinal Timeline | AushadhSeva';
      metaDesc = 'Comprehensive patient medical chart, KDIGO kidney staging, and multi-prescriber history.';
    } else if (currentPath.startsWith('/print-safety-card/')) {
      pageTitle = 'Print Doctor Safety Card & Health Passport | AushadhSeva';
      metaDesc = 'Official hospital visit clinical passport for doctor review with verified medications and safety alerts.';
    } else if (currentPath === '/login' || currentPath === '/login.php') {
      pageTitle = 'Sign In | AushadhSeva Secure Access';
      metaDesc = 'Sign in to access your personal medication locker or hospital clinical administrator cohort.';
    } else if (currentPath === '/register' || currentPath === '/register.php') {
      pageTitle = 'Register Citizen Account | AushadhSeva';
      metaDesc = 'Create a secure citizen health record for longitudinal prescription safety tracking.';
    }

    document.title = pageTitle;
    const descTag = document.querySelector('meta[name="description"]');
    if (descTag) descTag.setAttribute('content', metaDesc);

    const ogTitle = document.querySelector('meta[property="og:title"]');
    if (ogTitle) ogTitle.setAttribute('content', pageTitle);

    const ogDesc = document.querySelector('meta[property="og:description"]');
    if (ogDesc) ogDesc.setAttribute('content', metaDesc);

    const ogUrl = document.querySelector('meta[property="og:url"]');
    if (ogUrl) ogUrl.setAttribute('content', window.location.href);

    const canonical = document.querySelector('link[rel="canonical"]');
    if (canonical) canonical.setAttribute('href', window.location.origin + currentPath);
  }, [currentPath]);

  const selectFirstVisitLang = (code) => {
    setLang(code);
    localStorage.setItem('aushadhseva_lang_chosen', 'true');
    localStorage.setItem('dosewise_lang_chosen', 'true');
    setShowFirstVisitLangModal(false);
  };

  // Match routes
  const renderPage = () => {
    if (currentPath === '/' || currentPath === '/index.php') {
      return <HomePage navigate={navigate} />;
    }
    if (currentPath === '/check' || currentPath === '/attach_prescription.php') {
      return <CheckMedicinePage navigate={navigate} />;
    }
    if (currentPath === '/my-prescriptions' || currentPath === '/my_prescriptions.php') {
      return <MyPrescriptionsPage navigate={navigate} />;
    }
    if (currentPath === '/patients' || currentPath === '/patients.php') {
      return <PatientsPage navigate={navigate} />;
    }
    if (currentPath.startsWith('/patients/')) {
      const id = currentPath.split('/')[2];
      return <PatientViewPage patientId={id} navigate={navigate} />;
    }
    if (currentPath.startsWith('/print-safety-card/')) {
      const id = currentPath.split('/')[2];
      return <PrintSafetyCardPage patientId={id} navigate={navigate} />;
    }
    if (currentPath === '/medicines' || currentPath === '/medicines.php') {
      return <MedicinesPage navigate={navigate} />;
    }
    if (currentPath === '/login' || currentPath === '/login.php') {
      return <LoginPage navigate={navigate} />;
    }
    if (currentPath === '/register' || currentPath === '/register.php') {
      return <RegisterPage navigate={navigate} />;
    }
    return <HomePage navigate={navigate} />;
  };

  const isPrintView = currentPath.startsWith('/print-safety-card/');

  return (
    <>
      <a className="ez-skip" href="#main">Skip to main content</a>

      {!isPrintView && <Header currentPath={currentPath} navigate={navigate} />}

      <main id="main" className="app-main-wrapper" role="main" tabIndex="-1">
        {renderPage()}
      </main>

      {!isPrintView && <Footer />}
      {!isPrintView && <BottomNav currentPath={currentPath} navigate={navigate} />}

      {/* First visit trilingual modal */}
      {showFirstVisitLangModal && (
        <div style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background: 'rgba(12, 53, 88, 0.75)',
          backdropFilter: 'blur(4px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          zIndex: 5000,
          padding: '16px'
        }}>
          <div style={{
            background: 'white',
            borderRadius: '16px',
            padding: 'clamp(20px, 5vw, 32px)',
            maxWidth: '440px',
            width: '100%',
            maxHeight: '90vh',
            overflowY: 'auto',
            textAlign: 'center',
            boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.2)'
          }}>
            <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '12px' }}>
              <div style={{ background: '#e0f2fe', width: '56px', height: '56px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <GlobeIcon size={32} color="#0284c7" />
              </div>
            </div>
            <h3 style={{ margin: '0 0 8px', fontSize: 'clamp(18px, 4vw, 21px)', fontWeight: 800, color: '#0f172a' }}>
              Choose your language / భాష / भाषा
            </h3>
            <p style={{ margin: '0 0 20px', color: '#64748b', fontSize: '14.5px' }}>
              Select your preferred language. You can change this at any time.
            </p>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
              {Object.entries(supportedLangs).map(([code, info]) => (
                <button
                  key={code}
                  type="button"
                  onClick={() => selectFirstVisitLang(code)}
                  className="ez-big-btn ez-big-btn--light"
                  style={{ justifyContent: 'center', padding: '14px', fontSize: '17px', fontWeight: 700 }}
                >
                  {info.native} ({info.name})
                </button>
              ))}
            </div>
          </div>
        </div>
      )}
    </>
  );
}
