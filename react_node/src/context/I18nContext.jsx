import React, { createContext, useContext, useState, useEffect, useCallback, useRef } from 'react';
import enDict from '../../locales/en.json';
import teDict from '../../locales/te.json';
import hiDict from '../../locales/hi.json';
import taDict from '../../locales/ta.json';
import mlDict from '../../locales/ml.json';
import knDict from '../../locales/kn.json';
import orDict from '../../locales/or.json';
import urDict from '../../locales/ur.json';

const dictionaries = {
  en: enDict,
  te: teDict,
  hi: hiDict,
  ta: taDict,
  ml: mlDict,
  kn: knDict,
  or: orDict,
  ur: urDict
};

export const SUPPORTED_LANGS = {
  en: { name: 'English', native: 'English', bcp47: 'en-IN', dir: 'ltr' },
  te: { name: 'Telugu', native: 'తెలుగు', bcp47: 'te-IN', dir: 'ltr' },
  hi: { name: 'Hindi', native: 'हिन्दी', bcp47: 'hi-IN', dir: 'ltr' },
  ta: { name: 'Tamil', native: 'தமிழ்', bcp47: 'ta-IN', dir: 'ltr' },
  ml: { name: 'Malayalam', native: 'മലയാളം', bcp47: 'ml-IN', dir: 'ltr' },
  kn: { name: 'Kannada', native: 'ಕನ್ನಡ', bcp47: 'kn-IN', dir: 'ltr' },
  or: { name: 'Odia', native: 'ଓଡ଼ିଆ', bcp47: 'or-IN', dir: 'ltr' },
  ur: { name: 'Urdu', native: 'اردو', bcp47: 'ur-IN', dir: 'rtl' }
};

const I18nContext = createContext(null);

export const I18nProvider = ({ children }) => {
  const [lang, setLangState] = useState(() => {
    return localStorage.getItem('dosewise_lang') || 'en';
  });

  const [textSize, setTextSizeState] = useState(() => {
    return localStorage.getItem('dosewise_text') || 'normal';
  });

  const [isSpeaking, setIsSpeaking] = useState(false);
  const voicesRef = useRef([]);

  // Load and cache voices when speech synthesis is available
  useEffect(() => {
    if ('speechSynthesis' in window) {
      const loadVoices = () => {
        voicesRef.current = window.speechSynthesis.getVoices();
      };
      loadVoices();
      window.speechSynthesis.onvoiceschanged = loadVoices;
    }
  }, []);

  // Update HTML tag attributes on language change
  useEffect(() => {
    const langInfo = SUPPORTED_LANGS[lang] || SUPPORTED_LANGS.en;
    document.documentElement.lang = lang;
    document.documentElement.dir = langInfo.dir || 'ltr';
    document.documentElement.setAttribute('data-speech-lang', langInfo.bcp47 || 'en-IN');
    document.body.className = `lang-${lang}`;
  }, [lang]);

  useEffect(() => {
    document.documentElement.setAttribute('data-text', textSize);
  }, [textSize]);

  const setLang = useCallback((newLang) => {
    if (dictionaries[newLang]) {
      setLangState(newLang);
      localStorage.setItem('dosewise_lang', newLang);
    }
  }, []);

  const cycleTextSize = useCallback(() => {
    setTextSizeState(prev => {
      const next = prev === 'normal' ? 'large' : prev === 'large' ? 'xlarge' : 'normal';
      localStorage.setItem('dosewise_text', next);
      return next;
    });
  }, []);

  const t = useCallback((key, params = {}) => {
    const dict = dictionaries[lang] || dictionaries.en;
    let template = dict[key] || dictionaries.en[key] || key;

    for (const [k, val] of Object.entries(params)) {
      template = template.replace(new RegExp(`\\{${k}\\}`, 'g'), val);
    }
    return template;
  }, [lang]);

  const speak = useCallback((text, forcedLang = null) => {
    if (!('speechSynthesis' in window)) {
      alert(t('common.no_voice'));
      return;
    }

    window.speechSynthesis.cancel();
    if (isSpeaking) {
      setIsSpeaking(false);
      return;
    }

    const cleanText = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    if (!cleanText) return;

    const targetBcp47 = forcedLang || SUPPORTED_LANGS[lang]?.bcp47 || 'en-IN';
    const utterance = new SpeechSynthesisUtterance(cleanText);
    utterance.lang = targetBcp47;
    utterance.rate = 0.88; // clear, comfortable cadence for elderly listeners
    utterance.pitch = 1.0;

    // Pick best matching native voice
    const voices = voicesRef.current.length > 0 ? voicesRef.current : window.speechSynthesis.getVoices();
    const langPrefix = targetBcp47.split('-')[0].toLowerCase();
    const targetLangName = SUPPORTED_LANGS[lang]?.name?.toLowerCase() || '';

    const matchedVoice = voices.find(v => {
      const vl = (v.lang || '').replace('_', '-').toLowerCase();
      return vl === targetBcp47.toLowerCase();
    }) || voices.find(v => {
      const vl = (v.lang || '').replace('_', '-').toLowerCase();
      return vl.startsWith(langPrefix);
    }) || voices.find(v => {
      const vn = (v.name || '').toLowerCase();
      return targetLangName && vn.includes(targetLangName);
    });

    if (matchedVoice) {
      utterance.voice = matchedVoice;
    }

    utterance.onstart = () => setIsSpeaking(true);
    utterance.onend = () => setIsSpeaking(false);
    utterance.onerror = () => setIsSpeaking(false);

    window.speechSynthesis.speak(utterance);
  }, [lang, isSpeaking, t]);

  // Localized alert speech builder: answers purely in the selected Indian language
  const speakAlert = useCallback((alert) => {
    if (!alert) return;

    const typeKey = alert.alert_type || 'interaction';
    const localizedTitle = t(`alert.title.${typeKey}`) !== `alert.title.${typeKey}`
      ? t(`alert.title.${typeKey}`)
      : (alert.problem || '');

    const localizedWhy = t(`alert.why.${typeKey}`, {
      drugs: alert.medicines_involved || '',
      egfr: alert.egfr || ''
    }) !== `alert.why.${typeKey}`
      ? t(`alert.why.${typeKey}`, { drugs: alert.medicines_involved || '', egfr: alert.egfr || '' })
      : (alert.why_flagged || '');

    let speech = '';
    if (lang === 'te') {
      speech = `హెచ్చరిక. ${localizedTitle}. మందులు: ${alert.medicines_involved}. ${localizedWhy} దయచేసి ఈ మందులు తీసుకునే ముందు మీ డాక్టర్‌ను లేదా ఫార్మసిస్ట్‌ను సంప్రదించండి.`;
    } else if (lang === 'hi') {
      speech = `चिकित्सा चेतावनी. ${localizedTitle}। दवाएं: ${alert.medicines_involved}। ${localizedWhy} कृपया यह दवाएं लेने से पहले अपने डॉक्टर या फार्मासिस्ट से परामर्श लें।`;
    } else if (lang === 'ta') {
      speech = `மருத்துவ எச்சரிக்கை. ${localizedTitle}. மருந்துகள்: ${alert.medicines_involved}. ${localizedWhy} தயவுசெய்து உங்கள் மருத்துவரிடம் ஆலோசிக்கவும்.`;
    } else if (lang === 'ml') {
      speech = `മെഡിക്കൽ മുന്നറിയിപ്പ്. ${localizedTitle}. മരുന്നുകൾ: ${alert.medicines_involved}. ${localizedWhy} ദയവായി നിങ്ങളുടെ ഡോക്ടറോട് സംസാരിക്കുക.`;
    } else if (lang === 'kn') {
      speech = `ವೈದ್ಯಕೀಯ ಎಚ್ಚರಿಕೆ. ${localizedTitle}. ಔಷಧಗಳು: ${alert.medicines_involved}. ${localizedWhy} ದಯವಿಟ್ಟು ನಿಮ್ಮ ವೈದ್ಯರನ್ನು ಸಂಪರ್ಕಿಸಿ.`;
    } else if (lang === 'or') {
      speech = `ଚିକିତ୍ସା ଚେତାବନୀ। ${localizedTitle}। ଔଷଧଗୁଡ଼ିକ: ${alert.medicines_involved}। ${localizedWhy} ଦୟାକରି ଆପଣଙ୍କ ଡାକ୍ତରଙ୍କ ସହିତ ପରାମର୍ଶ କରନ୍ତୁ।`;
    } else if (lang === 'ur') {
      speech = `طبی انتباہ۔ ${localizedTitle}۔ ادویات: ${alert.medicines_involved}۔ ${localizedWhy} برائے مہربانی اپنے معالج یا فارماسسٹ سے رجوع کریں۔`;
    } else {
      speech = `Safety Alert. ${localizedTitle}. Medications involved: ${alert.medicines_involved}. ${localizedWhy} Please consult your physician or pharmacist.`;
    }

    speak(speech);
  }, [lang, t, speak]);

  const stopSpeaking = useCallback(() => {
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
    }
    setIsSpeaking(false);
  }, []);

  return (
    <I18nContext.Provider value={{
      lang,
      setLang,
      textSize,
      cycleTextSize,
      t,
      speak,
      speakAlert,
      stopSpeaking,
      isSpeaking,
      supportedLangs: SUPPORTED_LANGS
    }}>
      {children}
    </I18nContext.Provider>
  );
};

export const useI18n = () => useContext(I18nContext);
