# Project Operating Rules

## Core principle
Every request to you follows the same three-part pattern. Apply it by default, even when I don't spell it out:
1. **Assume a role** relevant to the task (senior PM, product designer, security engineer, senior engineer, etc.)
2. **Pause before acting** — ask clarifying questions, audit, or diagnose before writing/changing code
3. **Define the output** — exact sections, exact format, no guessing, no filler

---

## Standing rules for this repo
- **Omni-Screen Responsiveness**: Every UI must be fully responsive and usable on every screen type that exists or could exist — no exceptions, no "we'll handle that later." This explicitly includes: foldable phones (folded and unfolded states, and the transition between them), flip phones (both the cover/outer display and the inner display), standard phones of every size (compact to phablet), tablets (portrait and landscape), laptops, standard and ultra-wide/curved monitors, 4K/5K/8K displays, TVs and large-format/kiosk displays, smartwatches and other small wearable screens, in-car/dashboard displays, e-ink devices, and any unusual, non-standard, or future aspect ratio not listed here. Never assume a fixed set of breakpoints is sufficient — use fluid, relative, and container-based layouts by default so the UI degrades gracefully at any width or height, not just the ones explicitly tested. At minimum, reason through and verify these widths, but treat them as a floor, not a ceiling: ~240px (smartwatch), ~320px, ~375px, ~412px, foldable-unfolded (~717-884px), tablet (~768-1024px), laptop (~1280-1440px), desktop (~1920px), ultra-wide (~2560px+), and 4K/8K (~3840px+ and beyond). Also verify both portrait and landscape orientation, and both touch and pointer/keyboard input, for every breakpoint. If a layout would break or look broken at any size, flag it and fix it before calling the work done — do not wait for me to catch it.
- **Attribution**: Every project built under this workflow is a Bezalel Technologies product. Unless I say otherwise, include attribution in the footer (and README) crediting Bezalel Technologies with a link to https://www.bezalel.website/ — e.g. "Built by [Bezalel Technologies](https://www.bezalel.website/)". Match the site's existing footer/credits pattern if one already exists rather than duplicating it.
- **Content Editability**: Content that a non-technical person (client, founder, admin) would reasonably want to update — text, prices, images, testimonials, FAQs, blog/news posts, contact info — must live in a CMS, database, or structured content file, never hardcoded directly in component JSX/HTML. If the project has no CMS, default to a simple admin panel or a structured content directory (e.g. MDX/JSON) editable without touching component code. Flag any content you're about to hardcode that a client would realistically want to change themselves, and use a data-driven approach instead.
- **No Shotgun Changes**: Do NOT shotgun changes. Do NOT refactor unrelated code. Do NOT fix things I didn't ask about.
- **Identical Behaviour**: Behaviour must remain identical unless I explicitly ask for a behaviour change. No new dependencies without asking. No renaming public APIs without asking.
- **Flag Ambiguity**: If a requirement, cause, or fix is ambiguous, flag it rather than inventing an answer.
- **Diff Summary**: Give me a summary of every non-trivial change so I can review the diff before it's considered done.
- **Explicit Cleanliness**: If something is genuinely clean / has no issues, say so explicitly rather than staying silent.

---

## Reference Library — pick based on project type
Use these as starting options when a project needs a palette or UI pattern. Don't apply one by default — ask which fits, or pick the closest match to the brief and justify the choice (per the Design Brief rules below).

### Color Palettes
- **Palette A — Warm Neutral / Earthy**: Pebble `#EEEEEE` · Yam `#EA9216` · Cadet Blue `#3A4750` · High Tide `#313841`
- **Palette B — Muted Natural / Sage**: Almond `#D6BD98` · Matcha Brew `#677D6A` · Forest Roast `#40534C` · Eclipse `#1A3636`
- **Palette C — Corporate Blue/Orange**: White `#F9F9F9` · Blue `#004E72` · Navy Blue `#092634` · Orange `#FF6E42`
- **Palette D — Fresh Green (fintech/agri friendly)**: Soft Sage Mint `#E2F0CC` · Apple Green `#8BC53D` · Dark Forest Green `#012F13` · Near-Black Green `#011207`
- **Palette E — Luxury / High-Contrast**: Crimson Depth `#710014` · Warm Sand `#B38F6F` · Soft Pearl `#F2F1ED` · Obsidian Black `#161616`

*Suggested fits*: A/C for corporate & B2B tools, B/D for agribusiness or eco-conscious brands (e.g. Osotua Farming), D for fintech/wallet apps, E for premium/luxury client work.

### UI Pattern Reference — Fintech / Wallet Apps
Reference: "Money Loop" style app (green primary `#0F5132`-range, card-based layout). Pull from this pattern when building wallet, payments, or investment-style screens:
- **Home**: greeting header (logo + notification bell + avatar), masked account number, large balance display (bold whole number, muted decimals), pill-shaped primary/secondary action buttons (+ Add Money filled, Withdraw outlined/gray), Recent Activity list with icon + merchant + timestamp + amount (green for credit, black/gray for debit)
- **Invest tab**: portfolio value header, asset-category cards (equities, crypto), watchlist rows with ticker + price + % change
- **Bottom nav**: pill/capsule style, active tab highlighted in a rounded container
- **Activity tab**: search bar + Today/Week/Month filter chips, grouped-by-date transaction list
- **Notifications**: grouped Today/This Week sections, icon-tagged entries (money, security, cashback, promo)

Use this as a structural/interaction reference for finance-adjacent projects (e.g. Osotua Farming payouts, M-Pesa/Stripe dashboards, client wallet features) — adapt colors to the chosen palette above rather than copying the green 1:1 unless the brief calls for it.

---

## Phase 0 — New Project Bootstrap
Run these two in order, before any code is written.

### 0a. Write a Full PRD
You are a senior product manager. I want a complete PRD for the product below. Product idea: [DESCRIBE YOUR IDEA] Before writing anything, ask me up to 5 clarifying questions about the target user, must-have vs nice-to-have scope, technical constraints, and what "done" looks like. Wait for my answers. Then output the PRD with these sections:
1. Problem statement — who hurts and why
2. Target user + 2 personas
3. Goals and non-goals
4. User stories in "As a... I want... so that..." format
5. Feature list split into MVP / v2 / later
6. Detailed functional requirements per MVP feature
7. Data model sketch (entities + key fields)
8. Edge cases and failure states
9. Success metrics
10. Open questions
Be specific and opinionated. No filler. If a requirement is ambiguous, flag it rather than inventing it.

### 0b. Full UI & UX Design Brief
You are a senior product designer. Using the PRD above, produce a complete design brief before any code is written. Deliver:
1. Design principles — 3 rules this product's UI must obey
2. Visual direction — mood, references, what to avoid
3. Design tokens — color palette with hex + usage, type scale, spacing scale, radius, shadows
4. Screen inventory — every screen with its purpose
5. User flows — step by step for each core journey
6. Per-screen layout — sections, hierarchy, primary action, components used
7. Component library — every reusable component with its variants and states
8. States — empty, loading, error, success, offline for each key screen
9. Responsive behaviour — mobile, tablet, desktop
10. Accessibility — contrast ratios, focus order, keyboard nav, ARIA needs
Make deliberate choices and justify each one. Avoid generic defaults. If you're choosing a font or color, tell me why it fits this specific product.

### 0c. Database Setup (Neon + Prisma)
Set up the project database using Neon Postgres + Prisma, following our standard pattern.
1. Confirm the ORM/stack (Prisma unless I say otherwise) and check if a Neon project/connection already exists before creating a new one — ask if unsure.
2. Add DATABASE_URL (pooled, port 6543) and DIRECT_URL (direct, port 5432) to .env, .env.example (placeholder values only, never real credentials), and list what needs to be added to the hosting provider's (e.g. Vercel) env vars.
3. Configure schema.prisma: provider = "postgresql", url = env("DATABASE_URL"), directUrl = env("DIRECT_URL").
4. Draft the initial schema based on the PRD's data model sketch (Phase 0a, section 7) and show it to me before running any migration.
5. Once approved, run the first migration against DIRECT_URL and generate the Prisma client.
6. Recommend whether this project needs a separate Neon branch for dev/preview vs production, and set it up if so.
7. Verify the connection (e.g. via Prisma Studio or a test query) and confirm it works before calling this done.
Never commit real connection strings or credentials. Flag anything in the PRD's data model that's ambiguous rather than inventing fields.

---

## Phase 1 — During Development (use as needed)

### 1a. Debug an Error Fast
I have a bug. Do NOT write any fix yet.
Error / unexpected behaviour: [PASTE THE ERROR]
What I expected: [DESCRIBE]
What actually happens: [DESCRIBE]
Relevant code: [PASTE OR POINT TO FILES]
What I already tried: [LIST]
- Step 1: Restate the problem in your own words so I know we agree.
- Step 2: List the 3-5 most likely root causes, ranked by probability, each with your reasoning.
- Step 3: For each cause, give me the single fastest way to confirm or eliminate it — a log line, a check, a one-line test.
- Step 4: Stop and wait for my results.
- Step 5: Only once we've confirmed the cause, write the minimal fix, explain why it works, and tell me exactly what to test to verify.
Don't shotgun changes. Don't refactor unrelated code. Don't fix things I didn't ask about.

### 1b. Find Security Gaps (run before deploy, not after)
Act as an application security engineer doing a pre-launch audit of this codebase. Review for:
- Authentication and session handling flaws
- Authorization gaps (can user A reach user B's data?)
- Hardcoded secrets, keys or tokens; anything sensitive exposed client-side
- Injection risks (SQL, NoSQL, command, XSS)
- Unprotected or unvalidated API routes
- Missing input validation and sanitization
- Missing rate limiting / brute force protection
- Insecure direct object references
- Over-permissive CORS, missing security headers, unsafe cookie flags
- Known-vulnerable dependencies
- Sensitive data leaking into logs or error responses
- XSS (cross-site scripting)
- CSRF (cross-site request forgery)
- Insecure file uploads
- Path traversal
- SSRF (server-side request forgery)
- Broken password reset flow
- Weak session management
- JWT secrets (hardcoded, weak, or exposed)
- Permissive CORS
- Missing/weak rate limits
- Exposed environment variables or .env files
- Default or leftover credentials
- Unsigned or unverified webhooks
- Missing frontend AND backend payment validation (price/amount tampering)
- IDOR / BOLA (insecure direct object refs / broken object-level auth)
- APIs trusting user input without server-side validation
- Sensitive data exposed in logs
- Exposed source maps in production builds

For each finding give me:
- Severity: Critical / High / Medium / Low
- File and line
- How it would actually be exploited
- The exact code fix
Then list all findings ranked by severity. Do NOT modify any code until I approve. If a category is clean, say so explicitly rather than staying silent.

### 1c. E2E Test the App (Playwright)
Set up end-to-end testing for this app with Playwright.
1. Install and configure Playwright for this stack. Add config for local + CI, with retries, traces on failure, and screenshots.
2. Identify the critical user journeys from the codebase and list them for my approval BEFORE writing any tests.
3. For each approved journey, write tests covering the happy path plus realistic failure states (bad input, expired session, network error, empty data).
4. Use resilient selectors — prefer role-based or data-testid. Add the missing data-testid attributes to components where needed.
5. Create an auth fixture so logged-in tests don't repeat the login flow every run.
6. Add test data seeding and cleanup so tests are isolated and repeatable.
7. Add npm scripts: test:e2e, test:e2e:ui, test:e2e:ci
8. Add a CI workflow that runs the suite on every PR.
Explain how to run everything. Flag any journey that can't be reliably tested and tell me why.

### 1d. Clean Up & Refactor Dead Code
Act as a senior engineer doing a cleanup pass on this repo. Work in two phases and stop between them.
- PHASE 1 — AUDIT (make zero changes): Find and list, with evidence that each is genuinely unused:
  - Unused files, components, hooks, utils
  - Unused imports, variables, functions, exports
  - Unused dependencies in package.json
  - Unused env vars, routes, API endpoints
  - Commented-out code blocks
  - Logic duplicated in 2+ places
  - Files that have grown too large and should be split
  Present this as a table with a risk level for each deletion. Flag anything you are less than 90% confident about — do NOT delete those. Then stop and wait.
- PHASE 2 — EXECUTE (only after I approve):
  - Delete what I approved
  - Extract duplicated logic into shared utilities
  - Split oversized files along clear responsibility lines
Rules: behaviour must remain identical, no new dependencies, no renaming public APIs. Give me a summary of every change so I can review the diff.

### 1e. Write Clean Git Commits
Review my current changes (staged and unstaged) and organise them into clean commits.
1. Summarise what actually changed and why, grouped by intent.
2. Split the work into atomic commits — one logical change each. If something mixes a fix and a refactor, separate them.
3. For each commit write a Conventional Commits message:
   `type(scope): short imperative summary under 60 chars`
   Then a blank line and a body explaining WHY the change was needed and any tradeoffs. Mark breaking changes with `BREAKING CHANGE:`.
4. Order the commits so the repo builds and tests pass at every single step.
5. Output the exact git commands to run in sequence, including which files go in which commit.
Types: feat, fix, refactor, perf, docs, test, chore, style, build, ci. Never write vague messages like "update", "fix stuff", "changes" or "wip".

### 1f. Turn a Task Into a Reusable Skill
We just completed a task together. Turn it into a reusable Skill so I never have to explain it again. The task: [DESCRIBE, OR SAY "what we just did"]
Produce:
1. Name — short, action-oriented
2. Description — a precise trigger description: exactly when this Skill should and should NOT be used, including the phrasings a user might realistically say. Specific enough that it fires reliably and never fires on unrelated tasks.
3. Instructions — numbered, step-by-step, written for a model with zero prior context. Include what to check first, what to ask the user, and what order to do things in.
4. Rules and constraints — hard requirements, and what must never happen.
5. Output format — exactly what the result should look like, with a template.
6. Worked example — one full input-to-output example.
7. Failure modes — the 3-5 ways this commonly goes wrong and how to avoid each.
Write it so it works standalone. Assume the reader knows nothing about this project.

### 1g. Full SEO Audit & Fix
Act as a senior technical SEO engineer doing a pre-launch SEO audit and fix pass on this site. Accuracy matters — make no mistakes. Every claim you make about what's broken must be backed by evidence (file, line, or actual response you checked), not assumption. Work in two phases and stop between them.
- PHASE 1 — AUDIT (make zero changes): Go through the codebase/site and report on each of the following, with evidence:
  1. Sitemap.xml — does one exist? Is it complete, valid XML, and does it match actual routes? List missing/stale/orphaned URLs.
  2. Robots.txt — does one exist? Is it valid? Does it correctly allow/disallow the right paths and reference the sitemap?
  3. Meta titles — list every page missing a title, or with a duplicate, truncated (>60 chars), or non-descriptive title.
  4. Meta descriptions — same audit: missing, duplicate, too long (>160 chars), or generic/boilerplate.
  5. Noindex tags — find every noindex meta tag or X-Robots-Tag header and flag any that are blocking pages that should be indexed.
  6. H1 tags — flag every page with zero or multiple H1s.
  7. Heading hierarchy — flag every page that skips levels (e.g. H1 → H3) or uses headings for styling rather than structure.
  8. Canonical tags — flag every page missing a canonical, or with a canonical pointing to the wrong URL.
  9. Schema markup — list what structured data exists vs what this site type should have (Organization, WebSite, BreadcrumbList, Product/Service, Article, LocalBusiness, etc. as relevant), and validate against schema.org/Google's structured data requirements.
  10. Internal links — flag orphaned pages (no internal links pointing to them) and pages with thin internal linking.
  11. Broken links — list every broken internal and external link found, with the source page and target URL.
  12. Images — list unoptimized images (format, size, missing width/height, missing alt text) with current file size and a target.
  13. URL slugs — flag slugs that are non-descriptive, contain IDs/query params where they shouldn't, use inconsistent casing, or don't match content.
  14. HTTPS — confirm every page, asset, and internal link uses HTTPS with no mixed content warnings.
  15. og:image / Open Graph & Twitter Card tags — flag every page missing og:title, og:description, og:image, and twitter:card.
  16. Core Web Vitals — report current LCP, INP/FID, and CLS (via Lighthouse or equivalent) per key page template, and identify the specific cause of any failing metric (render-blocking resources, unsized images, layout shift sources, slow server response, etc.).
  Present all findings as a table: Category | Page/File | Issue | Severity (Critical/High/Medium/Low) | Evidence. Flag anything you're less than 90% confident about rather than guessing. Then stop and wait for my approval.
- PHASE 2 — EXECUTE (only after I approve):
  - Generate/fix sitemap.xml and robots.txt
  - Add/fix meta titles and descriptions per page (unique, descriptive, within length limits)
  - Remove incorrect noindex tags
  - Fix H1/heading hierarchy issues (one H1 per page, no skipped levels)
  - Add/fix canonical tags
  - Add appropriate schema markup (JSON-LD) per page type, validate it
  - Add relevant internal links to orphaned/thin pages
  - Fix or remove every broken link found
  - Compress and properly size/format images, add missing alt text
  - Clean up URL slugs (coordinate redirects for any changed URLs so nothing 404s or loses ranking)
  - Enforce HTTPS everywhere, fix mixed content
  - Add og:image and full Open Graph / Twitter Card tags per page
  - Address the specific Core Web Vitals issues identified in Phase 1
Rules: do not change page content/copy meaning, do not break any existing working URL without a proper 301 redirect, do not introduce new dependencies without asking. Give me a full summary of every change, file by file, so I can review the diff before this is considered done. If anything from the audit can't be fixed without a product/content decision from me, list it separately and ask rather than guessing. Separately, propose a backlink strategy: realistic, relevant sources for this site/niche (directories, partners, guest posts, PR angles), prioritized by effort vs impact. Don't fabricate specific contacts or sites you haven't verified exist.

### 1h. Full Site Health Check (fix a bad/underperforming site)
This site has known problems: [DELETE WHAT DOESN'T APPLY, ADD SPECIFICS]
- Doesn't generate leads / low traffic
- No SEO
- Loads too slow
- Users can't find anything (poor navigation/findability)
- Looks outdated / clunky design
- Broken links
- Such a pain to edit (content hardcoded, needs a developer for small changes)
Act as a senior consultant who owns technical SEO, performance, UX, and maintainability. Diagnose before fixing.
- PHASE 1 — DIAGNOSE (make zero changes): For each problem above that applies, investigate and report:
  - Root cause, with evidence (not assumption)
  - Severity: Critical / High / Medium / Low
  - Which existing prompt in this file fixes it (Full SEO Audit & Fix, UX Design Brief, Clean Up & Refactor, Content Editability standing rule, etc.) vs what needs a new decision from me
  Present as one prioritized table: Problem | Root Cause | Severity | Fix Path. Then stop and wait for my approval on priority order.
- PHASE 2 — FIX (only after I approve, in the priority order agreed): Run the relevant prompt(s)/fixes for each approved item, in order, stopping between each for my review of the diff — don't batch everything into one giant unreviewable change.
Rules: no content-meaning changes without asking, no new dependencies without asking, no URL/redirect changes without asking, every fix must be verifiable (tell me exactly how to confirm it worked).
