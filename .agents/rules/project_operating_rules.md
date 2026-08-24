# Project Operating Rules

## Core Principle
Every request follows the three-part pattern by default:
1. **Assume a role** relevant to the task (Senior PM, Product Designer, AppSec Engineer, Senior Staff Engineer, etc.)
2. **Pause before acting** — ask clarifying questions, audit, or diagnose before writing/changing code
3. **Define the output** — exact sections, exact format, no guessing, no filler

---

## Standing Rules for this Repo
- **Attribution**: Every project built under this workflow is a **Bezalel Technologies** product. Include attribution in the footer (and README) crediting Bezalel Technologies with a link to `https://www.bezalel.website/` — e.g., `"Built by [Bezalel Technologies](https://www.bezalel.website/)"`. Match existing footer/credits patterns rather than duplicating.
- **No Shotgun Changes**: Do NOT refactor unrelated code. Do NOT fix things not asked about.
- **Identical Behaviour**: Maintain existing behaviour unless explicitly asked to change. No new dependencies without asking. No renaming public APIs without asking.
- **Flag Ambiguity**: If a requirement, cause, or fix is ambiguous, flag it rather than inventing an answer.
- **Reviewable Diffs**: Provide a clear summary of every non-trivial change for review before considering it done.
- **Explicit Cleanliness**: If something is genuinely clean / has no issues, state so explicitly rather than staying silent.

---

## Reference Library

### Color Palettes
- **Palette A — Warm Neutral / Earthy**: Pebble `#EEEEEE` · Yam `#EA9216` · Cadet Blue `#3A4750` · High Tide `#313841`
- **Palette B — Muted Natural / Sage**: Almond `#D6BD98` · Matcha Brew `#677D6A` · Forest Roast `#40534C` · Eclipse `#1A3636`
- **Palette C — Corporate Blue/Orange**: White `#F9F9F9` · Blue `#004E72` · Navy Blue `#092634` · Orange `#FF6E42`
- **Palette D — Fresh Green (Fintech/Agri)**: Soft Sage Mint `#E2F0CC` · Apple Green `#8BC53D` · Dark Forest Green `#012F13` · Near-Black Green `#011207`
- **Palette E — Luxury / High-Contrast**: Crimson Depth `#710014` · Warm Sand `#B38F6F` · Soft Pearl `#F2F1ED` · Obsidian Black `#161616`

*Suggested fits*: A/C for corporate & B2B, B/D for agribusiness/eco brands, D for fintech/wallet, E for luxury/premium client work.

### UI Pattern Reference — Fintech / Wallet Apps
- **Home**: Greeting header (logo + notification bell + avatar), masked account number, large balance display (bold whole number, muted decimals), pill-shaped primary/secondary action buttons (+ Add Money filled, Withdraw outlined/gray), Recent Activity list with icon + merchant + timestamp + amount (green for credit, black/gray for debit).
- **Invest Tab**: Portfolio value header, asset-category cards, watchlist rows with ticker + price + % change.
- **Bottom / Sidebar Nav**: Pill/capsule style, active tab highlighted in rounded container.
- **Activity Tab**: Search bar + Today/Week/Month filter chips, grouped-by-date transaction list.
- **Notifications**: Grouped Today/This Week sections, icon-tagged entries (money, security, cashback, promo).

---

## Phase Protocols

### Phase 0 — New Project Bootstrap
- **0a. Full PRD**: Assume Senior PM role. Ask up to 5 clarifying questions before drafting. Output: 1. Problem statement, 2. Target user + 2 personas, 3. Goals & non-goals, 4. User stories, 5. Feature list (MVP/v2/later), 6. Detailed functional requirements per MVP feature, 7. Data model sketch, 8. Edge cases & failure states, 9. Success metrics, 10. Open questions.
- **0b. Full UI/UX Design Brief**: Assume Senior Product Designer role. Deliver: 1. Design principles, 2. Visual direction, 3. Design tokens, 4. Screen inventory, 5. User flows, 6. Per-screen layout, 7. Component library, 8. States (empty/loading/error/success/offline), 9. Responsive behaviour, 10. Accessibility.

### Phase 1 — Development Workflows
- **1a. Debug an Error Fast**: Stop before writing fixes. Step 1: Restate problem. Step 2: 3-5 ranked root causes. Step 3: Single fastest verification check per cause. Step 4: Stop & wait for results. Step 5: Minimal confirmed fix.
- **1b. Pre-Launch Security Audit**: Assume AppSec Engineer. Audit authentication, authorization (IDOR/BOLA), secrets, injections, route protections, input validation, rate limits, CORS/headers, uploads, CSRF/XSS, payment tampering, logs. Report severity, file/line, exploit scenario, exact code fix. Explicitly state if clean.
- **1c. E2E Playwright Testing**: Resilient selectors, list journeys for approval first, auth fixtures, test data seeding/cleanup, npm scripts, CI workflow.
- **1d. Dead Code Cleanup & Refactoring**: Phase 1 audit table (zero changes, wait for approval) → Phase 2 execute approved deletions/extractions only.
- **1e. Clean Conventional Git Commits**: Atomic commits, conventional commit syntax, commands with explicit file targets.
- **1f. Reusable Skill Creation**: Turn completed task into standardized SKILL.md.
