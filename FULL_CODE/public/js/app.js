/**
 * Clinical UI JavaScript Interactions
 * Medication Safety Second Pair of Eyes
 */

document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss notification alerts after 6 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            if (alert && alert.parentElement) {
                alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-4px)';
                setTimeout(() => alert.remove(), 300);
            }
        }, 6000);
    });

    // Confirmation on clinical operations (e.g., stopping a medication)
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const message = btn.getAttribute('data-confirm') || ((window.DOSEWISE_I18N && window.DOSEWISE_I18N.confirmDefault) || 'Are you sure you want to proceed with this action?');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // Real-time live filtering on patient list
    const liveFilterInput = document.querySelector('[data-patient-filter]');
    if (liveFilterInput) {
        liveFilterInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.patient-list-item');
            let visibleCount = 0;

            items.forEach(function (item) {
                const text = item.textContent.toLowerCase();
                const matches = text.includes(query);
                item.style.display = matches ? 'flex' : 'none';
                if (matches) visibleCount++;
            });

            const countDisplay = document.getElementById('patientFilteredCount');
            if (countDisplay) {
                countDisplay.textContent = visibleCount;
            }

            const noMatchesMsg = document.getElementById('noFilterMatches');
            if (noMatchesMsg) {
                noMatchesMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
            }
        });
    }

    // Modal dialog controls
    window.openModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            const firstInput = modal.querySelector('input:not([type=hidden]), select, textarea');
            if (firstInput) firstInput.focus();
        }
    };

    window.closeModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    };

    // Close modal when clicking outside modal box
    window.addEventListener('click', function (e) {
        if (e.target.classList.contains('modal-backdrop')) {
            e.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });

    // Escape key closes open modals
    window.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal-backdrop');
            openModals.forEach(m => {
                m.style.display = 'none';
            });
            document.body.style.overflow = 'auto';
        }
    });

    // Safety review button smooth scroll & loading indicator
    const safetyCheckBtn = document.getElementById('btnCheckSafety');
    if (safetyCheckBtn) {
        safetyCheckBtn.addEventListener('click', function (e) {
            const targetSection = document.getElementById('safetyResultsSection');
            if (targetSection) {
                targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }
});

/* ==========================================================================
 * Elder-friendly helpers: language sheet, text size, read aloud
 * ========================================================================== */
(function () {
    const I18N = window.DOSEWISE_I18N || {};

    // ---- Navbar Language Dropdown ----------------------------------------
    const langToggle = document.getElementById('langDropdownToggle');
    const langMenu = document.getElementById('langDropdownMenu');
    if (langToggle && langMenu) {
        langToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const isShown = langMenu.style.display === 'block';
            langMenu.style.display = isShown ? 'none' : 'block';
            langToggle.setAttribute('aria-expanded', isShown ? 'false' : 'true');
        });

        document.addEventListener('click', function (e) {
            if (!langToggle.contains(e.target) && !langMenu.contains(e.target)) {
                langMenu.style.display = 'none';
                langToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ---- Language picker sheet (mobile & auto-open) -----------------------
    function openSheet(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.removeAttribute('hidden');
        el.hidden = false;
        el.style.display = 'flex';
        document.body.classList.add('ez-sheet-open');
        const first = el.querySelector('.ez-lang-btn');
        if (first) first.focus();
    }
    function closeSheets() {
        document.querySelectorAll('.ez-sheet').forEach(function (el) {
            el.setAttribute('hidden', '');
            el.hidden = true;
            el.style.display = 'none';
        });
        document.body.classList.remove('ez-sheet-open');
    }
    document.addEventListener('click', function (e) {
        const opener = e.target.closest('[data-open-sheet]');
        if (opener) { openSheet(opener.getAttribute('data-open-sheet')); return; }
        if (e.target.closest('[data-close-sheet]') || e.target.classList.contains('ez-sheet')) closeSheets();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSheets(); });
    document.addEventListener('DOMContentLoaded', function () {
        const auto = document.querySelector('.ez-sheet[data-auto-open]');
        if (auto) openSheet(auto.id);
    });

    // ---- Text size: Normal -> Large -> Extra large -> Normal ---------------
    const SIZES = ['', 'large', 'xlarge'];
    function currentSize() { return document.documentElement.getAttribute('data-text') || ''; }
    function setSize(size) {
        if (size) document.documentElement.setAttribute('data-text', size);
        else document.documentElement.removeAttribute('data-text');
        try { localStorage.setItem('dosewise_text', size); } catch (e) {}
    }
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-text-size]');
        if (!btn) return;
        const next = SIZES[(SIZES.indexOf(currentSize()) + 1) % SIZES.length];
        setSize(next);
        const names = I18N.textSizes || ['Normal', 'Large', 'Extra large'];
        showToast(names[SIZES.indexOf(next)]);
    });

    function showToast(text) {
        let t = document.getElementById('ezToast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'ezToast';
            t.className = 'ez-toast';
            t.setAttribute('role', 'status');
            document.body.appendChild(t);
        }
        t.textContent = text;
        t.classList.add('is-visible');
        clearTimeout(t._timer);
        t._timer = setTimeout(function () { t.classList.remove('is-visible'); }, 1600);
    }
    window.ezToast = showToast;

    // ---- Read aloud (phone's own text-to-speech; Telugu/Hindi on Android) ----
    // We never read Telugu/Hindi with an English voice (it sounds like nonsense).
    // If the phone/browser has no voice for the language, a help note is shown instead.
    const canSpeak = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
    let speakingBtn = null;
    let queue = [];

    function textFor(btn) {
        const target = btn.getAttribute('data-speak-target') || '';
        let el = null;
        if (target.indexOf('closest:') === 0) el = btn.closest(target.slice(8));
        else if (target) el = document.querySelector(target);
        if (!el) return btn.getAttribute('data-speak-text') || '';
        const clone = el.cloneNode(true);
        // Do not read buttons, the English doctor note, help notes or hidden extras.
        clone.querySelectorAll('button, details, .ez-voice-help, [data-no-speak], script, style').forEach(function (n) { n.remove(); });
        // Put a full stop after headings/labels so the voice pauses between them.
        clone.querySelectorAll('h1,h2,h3,h4,div,p,li,dt,dd').forEach(function (n) { n.appendChild(document.createTextNode('. ')); });
        return clone.textContent
            .replace(/[\u{1F300}-\u{1FAFF}☀-➿⬀-⯿️]/gu, ' ')
            .replace(/\s+([.।?!])/g, '$1')
            .replace(/([.।?!:])(\s*[.।?!:])+/g, '$1')
            .replace(/\s+/g, ' ')
            .trim();
    }

    // Split into sentences of at most ~180 characters: long single utterances get cut off in Chrome.
    function chunks(text) {
        const parts = text.split(/(?<=[.।?!])\s+/);
        const out = [];
        parts.forEach(function (p) {
            while (p.length > 180) {
                let cut = p.lastIndexOf(' ', 180);
                if (cut < 60) cut = 180;
                out.push(p.slice(0, cut)); p = p.slice(cut).trim();
            }
            if (p) out.push(p);
        });
        return out;
    }

    function voicesReady() {
        return new Promise(function (resolve) {
            const synth = window.speechSynthesis;
            if (synth.getVoices().length) return resolve(synth.getVoices());
            let done = false;
            const finish = function () { if (!done) { done = true; resolve(synth.getVoices()); } };
            synth.addEventListener('voiceschanged', finish, { once: true });
            setTimeout(finish, 1500);
        });
    }

    function pickVoice(voices, lang) {
        const want = lang.toLowerCase();
        const base = want.slice(0, 2);
        const norm = function (v) { return (v.lang || '').replace('_', '-').toLowerCase(); };
        const matches = voices.filter(function (v) { return norm(v) === want || norm(v).indexOf(base) === 0; });
        // Prefer natural/online voices (better Indian-language quality), then local ones.
        return matches.find(function (v) { return /natural|neural|google/i.test(v.name); }) || matches[0] || null;
    }

    function resetBtn() {
        queue = [];
        if (speakingBtn) {
            speakingBtn.classList.remove('is-speaking');
            speakingBtn.innerHTML = '🔊 ' + (I18N.readAloud || 'Read aloud');
            speakingBtn = null;
        }
    }

    function showVoiceHelp(btn) {
        let note = btn.nextElementSibling;
        if (!note || !note.classList.contains('ez-voice-help')) {
            note = document.createElement('p');
            note.className = 'ez-voice-help';
            note.setAttribute('role', 'status');
            btn.insertAdjacentElement('afterend', note);
        }
        note.textContent = I18N.noVoice || 'No voice is available for this language on this device.';
    }

    function speakNext(voice, lang) {
        if (!queue.length) { resetBtn(); return; }
        const u = new SpeechSynthesisUtterance(queue.shift());
        u.lang = voice ? voice.lang : lang;
        if (voice) u.voice = voice;
        u.rate = 0.85; // a little slower for older listeners
        u.onend = function () { if (speakingBtn) speakNext(voice, lang); };
        u.onerror = function (e) { if (e.error !== 'interrupted' && e.error !== 'canceled') resetBtn(); };
        window.speechSynthesis.speak(u);
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!canSpeak) {
            document.querySelectorAll('.ez-speak').forEach(function (b) { b.hidden = true; });
            return;
        }
        window.speechSynthesis.getVoices(); // start loading the voice list (Chrome loads it lazily)
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.ez-speak');
        if (!btn || !canSpeak) return;
        const synth = window.speechSynthesis;
        if (speakingBtn === btn) { synth.cancel(); resetBtn(); return; }
        synth.cancel();
        resetBtn();

        const lang = document.documentElement.getAttribute('data-speech-lang') || I18N.speechLang || 'en-IN';
        voicesReady().then(function (voices) {
            const voice = pickVoice(voices, lang);
            const isEnglish = lang.indexOf('en') === 0;
            if (!voice && !isEnglish) {
                // No Telugu/Hindi voice here: explain how to get one instead of reading gibberish.
                showVoiceHelp(btn);
                return;
            }
            queue = chunks(textFor(btn));
            speakingBtn = btn;
            btn.classList.add('is-speaking');
            btn.innerHTML = '⏹ ' + (I18N.stop || 'Stop');
            speakNext(voice, lang);
        });
    });

    // Stop reading when leaving the page.
    window.addEventListener('pagehide', function () { if (canSpeak) window.speechSynthesis.cancel(); });
})();
