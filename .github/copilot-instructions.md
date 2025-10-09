## Quick orientation — Umoja Sacco (USMS)

Purpose: Give an AI coding agent the minimum, practical knowledge to make safe, correct edits in this repo.

High-level architecture
- Procedural PHP app served by Apache/XAMPP. No framework or front controller: each page is a standalone PHP file (root pages, `admin/`, `member/`).
- MySQL (mysqli) accessed directly via `config/db_connect.php`.
- UI: Bootstrap v5 (CDN) + FontAwesome; UI assets in `images/` and `assets/`.
- PDF exports: `fpdf/` (bundled) and Composer `vendor/dompdf` are present for report generation.

How requests flow (big picture)
- Browser -> specific PHP page (e.g., `login.php`, `admin/dashboard.php`).
- Pages include `config/db_connect.php` and use $_SESSION for auth.
- Admin vs Member separation: admin pages sit in `admin/` and check `$_SESSION['admin_id']`; member pages in `member/` check `$_SESSION['member_id']`.

Key files and examples to read before editing
- `config/db_connect.php` — mysqli connection (servername, user, password, database). Example: database name variable set to `umoja_sacco`.
- `login.php` — dual auth: first checks `admin` table (sha256 comparison), then `members` table (password_verify). See: admin uses `hash('sha256', $password)` while members use `password_verify`.
- `register.php` and `admin/add_member.php` — member creation paths; note `register.php` uses `password_hash(..., PASSWORD_DEFAULT)` and `add_member.php` uses `PASSWORD_BCRYPT` explicitly.
- `admin/dashboard.php`, `admin/manage_members.php`, `member/dashboard.php` — examples of database queries, table rendering and session checks.
- `admin/export_contributions.php` / `export_contributions_pdf.php` — examples of report generation using bundled PDF libraries.

Project-specific conventions & gotchas
- Routing: no router; pages are linked directly. Keep relative include paths correct (`include("../config/db_connect.php")` from subfolders).
- Auth/session keys: `admin_id`, `admin_name`, `member_id`, `member_name`. Use these exact keys when adding auth checks or redirects.
- Password storage inconsistency: admins appear to store sha256 hashes (in `login.php` they compare hash('sha256', $password)), while members use PHP's password_hash/password_verify. When modifying auth, preserve both behaviors or migrate carefully with a DB migration and feature flag.
- DB API: mixture of prepared statements (preferred) and raw `$conn->query()` exists in many files. Prefer prepared statements for new code.
- Output escaping: `htmlspecialchars()` is used in some table outputs (good), but not universally. Escape user content in HTML contexts.
- Date fields: watch for inconsistent column naming (e.g., `date_joined` vs `join_date`) across files — verify against the actual DB schema before renaming.

Local developer workflows (quick)
- Run on Windows with XAMPP: start Apache + MySQL and place the repo in `htdocs` (already in `c:\xampp\htdocs\usms`).
- DB: import the project's SQL (not included). Confirm credentials in `config/db_connect.php` (defaults to `root`/empty on localhost). 
- Composer/vendor: composer autoload exists; run `composer install` in the project root if you add or update PHP packages.

Editing guidance and safe change checklist
- Always open `config/db_connect.php` to confirm DB credentials and DB name.
- When changing auth or passwords, preserve backward compatibility with existing admin/member hash schemes; add migration scripts if you convert hashes.
- Use prepared statements for SQL with user input. For display use `htmlspecialchars()`.
- Keep include paths relative to the file's location (`../` when in `admin/` or `member/`).

Where to add features
- Admin UI: add pages under `admin/` and link from `admin/dashboard.php` navigation.
- Member features: add under `member/` with similar session checks.
- Shared utilities: there is no `lib/` yet—consider adding `includes/helpers.php` and include it where needed, but maintain the lightweight include pattern already in use.

Integration & external deps
- Composer + vendor (dompdf, php-font-lib, etc.) — found under `vendor/`.
- Bundled `fpdf/` (legacy) — several export scripts use it; don't remove unless migrating exports and testing PDF outputs.

Debugging & tests
- No automated test suite is present. Smoke tests: start XAMPP, visit `http://localhost/usms/login.php` and exercise admin/member flows.
- For PHP errors enable display or check Apache/PHP logs. When adding features, test both admin and member sessions and exports (PDF).

If you (the agent) need to make changes
- Mention the exact files you will edit in the PR description.
- Run a quick manual smoke test: login as admin (if test credentials exist), navigate to modified pages, and verify DB updates.
- Note any schema assumptions in the PR (column names, types).

Contact points in code to inspect for non-obvious behavior
- `login.php` (dual auth), `register.php`, `config/db_connect.php`, `admin/add_member.php`, `admin/dashboard.php`, `member/dashboard.php`, `admin/export_contributions_pdf.php`.

End — please review these notes and tell me any missing bits (DB schema, seed data, local credentials) you want added.
