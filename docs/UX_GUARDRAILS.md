# UX Guardrails (Reference)

Industry-aligned practices we should apply across UX work (esp. MS13). These are based on established frameworks:

- **Nielsen’s 10 Heuristics**: match real-world language, visibility of status, user control, consistency, error prevention/recovery, recognition over recall, efficiency for power users, minimalist copy, clear errors/help.
- **WCAG 2.2 AA**: focus order, keyboard operability, status messages, contrast, target sizes, headings/labels; verify forms respect accessibility for validation states.
- **GovUK / USWDS Form Guidance**: inline errors near fields, preserve input on failure, focus the first errored field, concise hints, no modals for basic validation, readable plain language.
- **Material / Apple HIG Form Patterns**: label + helper text + error slot, calm validation states, adequate spacing and touch targets, avoid layout shift when showing errors.
- **Fintech Trust Cues (Stripe-style)**: clear status/feedback, avoid jargon, cautious irreversible actions, progressive disclosure for advanced details, friendly recovery paths.

Project-specific non-negotiables:
- Mainnet-first wallet UX: network derives from env; show a small testnet helper only when not mainnet; no noisy badges on mainnet.
- Inline guidance over pop-ups: keep help/“Where do I find this?” inline and above the fold; minimal scrolling for critical fields.
- Preserve state: never clear inputs on validation errors; keep CTAs enabled after error; focus on the first error.
- No layout jump: reserve space for helper/error text; avoid page reflow when showing validation.
- Mobile/accessibility: visible focus rings, keyboard navigability for accordions, adequate tap targets, readable on small screens.
- Copy: plain language, avoid jargon, safety reminders (e.g., “receive-only key; never share your seed”), friendly errors.

Videos for n8 to watch:
- Nielsen’s 10 heuristics (NN/g, 2023): https://www.youtube.com/watch?v=cTtc90jCULU
- WCAG 2.2 overview (Deque, 2024): https://www.youtube.com/watch?v=sVYZ7QM3UUM
- GOV.UK forms talk (2023): https://www.youtube.com/watch?v=MMfqMSPKGj4
- USWDS form patterns webinar (2023): https://www.youtube.com/watch?v=kP-9tJFm3Ag
- Material Design text fields/validation (2023): https://www.youtube.com/watch?v=FeptggMEL8g
- Apple HIG/iOS design (WWDC24 session): https://www.youtube.com/watch?v=HyQgpxX__-A
- Stripe payment flow design (2024): https://www.youtube.com/watch?v=XSMfwiZBOvs
- Heuristics + accessibility overlap (2023 webinar): https://www.youtube.com/watch?v=uJTAmaEx8Cw
