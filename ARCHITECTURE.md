# PASS GO — Architecture

Decided **before** writing any code, together with SPEC.md and CLAUDE.md.

## Technology stack

| Layer | Choice | Why |
|---|---|---|
| Web server | Apache in a Docker image (Render free tier); FEUP Apache also supported | Free, public HTTPS URL; code stays PHP 7.4+ compatible for FEUP |
| Language | PHP (7.4+ compatible) | Only server-side option on the FEUP server |
| Database | PostgreSQL via PDO (Neon free tier in production) | Real relational integrity (PK, FK, unique, check); configured with `DATABASE_URL` |
| Front end | Server-rendered HTML + one CSS file + tiny JS | Fast, accessible, works without JS, no build step |
| Tests | Custom PHP test runner (`tests/run.php`) | No Composer or network needed on the server; unit + HTTP flow tests |

## Layered structure

```
Browser
   │  HTTP
   ▼
public/*.php  ──────────  thin controllers (one per screen)
   │  call
   ▼
src/Service/*   ────────  business rules (booking, cancelling, progress, validation)
   │  call
   ▼
src/Repository/* ───────  all SQL (PDO prepared statements)
   │
   ▼
PostgreSQL
   ▲
templates/*  ◄─────────── HTML rendering (escaped output only)
```

## Folder structure

```
passgo/
├── CLAUDE.md                project rules for the LLM agent
├── SPEC.md                  user stories + acceptance criteria
├── index.php                redirects to public/
├── public/                  web root
│   ├── index.php            home
│   ├── services.php         directory + search
│   ├── service.php          service detail + document checklist
│   ├── guide.php            step-by-step guide
│   ├── book.php             slot selection + booking
│   ├── appointments.php     my appointments + cancel
│   ├── appointment.php      ticket
│   ├── login.php / register.php / logout.php / profile.php
│   └── assets/css/app.css, assets/js/app.js
├── src/
│   ├── bootstrap.php        autoloader, config, session, helpers
│   ├── Database.php         PDO connection
│   ├── Auth.php  Csrf.php  Flash.php  Validator.php
│   ├── Repository/          UserRepository, ServiceRepository, GuideRepository,
│   │                        DocumentRepository, SlotRepository, AppointmentRepository
│   └── Service/             AccountService, BookingService, ProgressService
├── templates/               layout.php, icons.php, components.php
├── database/                schema.sql, seed.sql
├── config/                  config.example.php
├── tests/                   run.php, unit/, flow/
└── docs/                    this file, DEPLOY.md, DEMO_SCRIPT.md, DATA_TABLES.md
```

## Key design decisions

1. **One PHP script per screen** instead of URL rewriting: it works on any Apache setup, including the FEUP server where `.htaccess` rewrites may be disabled.
2. **Relative URLs through a `url()` helper** so the app works from a sub-folder such as `~user/passgo/public/`.
3. **Slots as rows** (`time_slots` with `capacity`) instead of free-form times: availability is a simple count of booked appointments and can be locked with `SELECT ... FOR UPDATE` to prevent overbooking.
4. **Progress and checklist are per-user link tables** (`user_step_progress`, `user_document_checks`), so the same guide serves every user.
5. **Appointments keep their own status** (`booked`, `cancelled`, `completed`) instead of being deleted, so the history is preserved.
6. **Security by default**: prepared statements, CSRF tokens, bcrypt, escaped output, HttpOnly + SameSite cookies.

## Request flow example: booking

1. `GET book.php?service=3` → `SlotRepository::availableFor(service)` → list of slots grouped by day.
2. `POST book.php` → `Csrf::check()` → `BookingService::book(user, service, slot)`:
   begin transaction → lock slot row → validate (exists, belongs to the service's institution, in the future, not full, user has no active booking for this service/slot) → insert appointment with unique ticket code → commit.
3. Redirect to `appointment.php?id=…` (ticket) with a flash message.
