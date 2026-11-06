# AGENTS.md — AushadhSeva Project & Design Engineering Guidelines

This document defines the development, architectural, and design-engineering standards for the **AushadhSeva** codebase. All AI agents, contributors, and tools operating in this workspace must adhere to these principles.

---

## 1. Design Engineering Standards (Emil Kowalski & UI Skills)

AushadhSeva is a high-trust, clinical healthcare application. The interface must balance medical clarity, utmost reliability, and modern tactile craft.

### Core Philosophy
- **Taste is intentional**: Software must not look or feel generic. Avoid standard sluggish linear transitions and floaty, disconnected animations.
- **Micro-interactions matter**: Interactive elements (buttons, medicine pills, cards) must provide immediate tactile feedback (`:active { transform: scale(0.98); }`).
- **Unseen details compound**: Consistent elevation, subtle border contrast (`rgba(0, 0, 0, 0.06)`), and responsive feedback create subconscious trust for patients and doctors.

### Animation & Motion Rules
1. **Never use `transition: all`**: It triggers layout reflows and recalculates every property on every frame. Always name exact properties:
   `transition: transform 0.2s var(--ease-spring), opacity 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;`
2. **Spring Physics Curves**:
   - Standard Interaction / Modal / Popover: `--ease-spring: cubic-bezier(0.16, 1, 0.3, 1)` (fast start, natural physical deceleration).
   - Snappy Micro-actions (switches, buttons, tags): `--ease-snappy: cubic-bezier(0.2, 0, 0, 1)`.
   - Standard Duration: **150ms to 240ms**. Never exceed 300ms for routine interactions.
3. **Composite Layer Acceleration**: Animate **only** `transform` and `opacity`. Never animate `height`, `top`, `margin`, or `padding`.

### Mobile-Native Feel (`mobile-native`)
- **No Sticky Hovers on Touch**: Never apply bare `:hover` styles to touch elements without gating:
  ```css
  @media (hover: hover) and (pointer: fine) {
    .btn:hover { ... }
  }
  ```
- **Responsive Tap Response**: Always include `touch-action: manipulation;` and `-webkit-tap-highlight-color: transparent;`.
- **Viewport Safe Areas**: Account for mobile browser bottom navigation bars (`env(safe-area-inset-bottom)` and `dvh` units).

### Accessibility & Reduced Motion (`fixing-accessibility`)
- **Strict Reduced Motion**: Always respect user system preferences:
  ```css
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
      animation-duration: 0.01ms !important;
      animation-iteration-count: 1 !important;
      transition-duration: 0.01ms !important;
      scroll-behavior: auto !important;
    }
  }
  ```
- **Crisp Focus Rings**: Ensure interactive elements have distinct, visible keyboard focus:
  `:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }`

### Stress-Testing Interfaces (`break-ui`)
- All components must cleanly handle:
  - Long generic drug strings (e.g., `amoxicillin; clavulanic acid`, `piperacillin + tazobactam`).
  - Empty prescription lists, missing patient eGFR labs, and offline network state.
  - Multi-language text expansion across 8 Indian languages (`en`, `te`, `hi`, `ta`, `ml`, `kn`, `or`, `ur`).

---

## 2. Integrated Agent Skills Repository (`.agent/skills/`)

The following specialized design-engineering skills are integrated in the workspace:

| Skill | Source | Purpose |
| :--- | :--- | :--- |
| `emil-design-eng` | Emil Kowalski | Core design engineering philosophy, polish, invisible details |
| `animate` | Emil Kowalski | Building custom animations with spring curves and physics |
| `apple-design` | Emil Kowalski | Apple-grade fluid spring motion, translucent elevation, and depth |
| `mobile-native` | Emil Kowalski | Fixing mobile touch delay, sticky hover, 100vh bugs |
| `review-animations` | Emil Kowalski | 60fps audit checklist, layout thrashing detection |
| `break-ui` | Emil Kowalski | Stress-testing components against extreme or missing data |
| `baseline-ui` | Julien Thibeaut (ui-skills) | Spacing tokens, typography scale, subtle surfaces, contrast |
| `fixing-accessibility`| Julien Thibeaut (ui-skills) | WCAG accessibility, contrast checks, keyboard navigation |
| `fixing-motion-performance` | Julien Thibeaut | Zero reflows, composite layers, will-change usage |

---

## 3. Clinical Architecture & Data Safety
- **Offline-First & DPDP Act 2023**: Medical decision evaluation runs in local SQLite on node:22 native drivers with zero PHI leakage to external cloud LLMs.
- **Localization**: Preserve translation keys and support all 8 Indian languages without emojis (use Lucide SVG icons).
