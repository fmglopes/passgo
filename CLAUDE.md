# CLAUDE.md — Project rules for PASS GO

Read `SPEC.md` first. It is the source of truth for what to build. Read `docs/ARCHITECTURE.md` for how the code is organised. Do not add features that are not in SPEC.md.

## Stack (do not change without asking)
- PHP, written to run on PHP 7.4 and later (no enums, no `match`, no readonly properties, no named arguments, no `str_contains`). Code stays compatible with the FEUP Apache server, but the live app runs from the `Dockerfile` (PHP 8.3 + Apache) on Render with a Neon PostgreSQL database.
- PostgreSQL accessed through PDO (`pdo_pgsql`). No ORM, no framework, no Composer dependencies at runtime.
- Plain HTML, one CSS file (`public/assets/css/app.css`), one small JS file (`public/assets/js/app.js`). The app must work with JavaScript disabled.

## Folder structure
```
public/       web root: one PHP entry script per screen + assets
src/          application code (classes). Logic lives here, not in pages
templates/    HTML partials (layout, icons, components)
database/     schema.sql, seed.sql, topup_slots.sql (PostgreSQL)
bin/          setup_db.php (create tables + demo data, idempotent)
config/       config.example.php (copy to config.php, never commit secrets)
tests/        run.php (test runner), unit/ and flow/ tests
docs/         ARCHITECTURE.md, DEPLOY.md, DEMO_SCRIPT.md, DATA_TABLES.md
tests/ux/     browser check: screenshots, overflow, touch-target size
```

## Architecture rules
1. Pages in `public/` are thin: bootstrap, check auth/CSRF, call a repository or service, render a template. No SQL and no business rules inside pages.
2. All SQL lives in `src/Repository/*`. Business rules (booking, cancelling, validation) live in `src/Service/*`.
3. Every query uses PDO prepared statements with bound parameters. Never concatenate user input into SQL.
4. Every `POST` handler calls `Csrf::check()` first and ends with a redirect (Post/Redirect/Get).
5. Every value printed into HTML goes through `e()`. No exceptions.
6. Pages that need a user call `Auth::require()`; it redirects to login and remembers the target page.
7. Booking and cancelling run inside a database transaction and lock the slot row with `SELECT ... FOR UPDATE`.
8. Show errors to the user with friendly messages (flash messages or inline form errors). Log technical details with `error_log`, never print stack traces.

## Code style
- 4 spaces, `declare(strict_types=1)` is NOT used (PHP 7.4 compatibility is kept simple), but add type hints where they help.
- One class per file, class name equals file name, namespace `PassGo\...` mapped by the autoloader in `src/bootstrap.php`.
- Small functions, descriptive names, comments only for the "why".

## UI rules
- Mobile first. Test at 360px width, then desktop.
- Design tokens (colours, spacing, radius) are CSS variables at the top of `app.css`. Use them; do not hard-code colours elsewhere.
- Contrast: body text AA or better. Buttons at least 44px high. Visible `:focus-visible` outline.
- Reuse components from `templates/components.php`. Keep the PASS GO look from the Assignment 1 mockups: cream background, terracotta accent, rounded cards, bottom tab bar on phones.

## Workflow for every change
1. State which user story (US-xx) the change implements.
2. Make the smallest change that satisfies its acceptance criteria.
3. Add or update a test in `tests/`.
4. Run `php tests/run.php` and read the result. A change is not done while tests fail.
5. Open the page by hand at phone width and desktop width.
6. Only then move to the next story.

## Commands
```
php tests/run.php                       # run all automated tests (needs the test database)
php -S localhost:8080 -t public         # run the app locally
psql "$DSN" -f database/schema.sql      # create tables
psql "$DSN" -f database/seed.sql        # load demo data
```

## Things to never do
- Never commit `config/config.php` or real passwords.
- Never store plain-text passwords or return different errors for "unknown email" vs "wrong password".
- Never trust IDs from the URL: check that the resource belongs to the logged-in user.
- Never invent official data (fees, laws, opening hours) as fact: seed data is illustrative and labelled as such.
