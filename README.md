# PawShield — INF1005 Group Project (PetCare Insurance)

**Team P7-12 | Submission: 21 February 2026**

**PawShield** is the product name in the app. This repo is the **INF1005** group submission: a full-stack **pet insurance** web app with **AI-powered vet receipt scanning** (Google Cloud Document AI), **claims / quotes**, **admin tools**, and **Stripe checkout** for subscriptions (**Review & Pay**).

**Application root:** all runnable code lives under **`pet-insurance-app/`** (clone the repo, then work inside that folder for Composer, PHP, and Apache).

---

## Features

- **Customer portal** – Register/login, pets, **Review & Pay** (plan + duration → Stripe Checkout), claims, quotes, profile
- **AI document scanner** – Upload receipts; **`AIScannerMock`** (local) or **Document AI** (live) extracts fields for the claim flow
- **Quote engine** – Premium / reimbursement logic tied to plans and scanned data
- **Tiered plans** – **Basic / Premium / Ultimate** (see DB seeds and landing page)
- **Admin dashboard** – Users, claims, audit-style workflows
- **Clinic verification** – Vet clinic data cross-check (seeded network)
- **Security** – CSRF, prepared statements, sanitization, password hashing
- **UX & accessibility** – Mobile hero strip, client-side form validation (`form-validation.js`), larger tap targets on small screens, shared focus/error styles (`accessibility.css`)

---

## Tech stack

| Layer | Technologies |
|-------|----------------|
| Frontend | HTML5, Bootstrap 5, CSS3, JavaScript (ES6+) |
| Backend | PHP 8.x |
| Database | MySQL 8.x |
| Payments | [Stripe](https://stripe.com/) (PHP SDK via Composer) |
| AI | [Google Cloud Document AI](https://cloud.google.com/document-ai) (optional for local dev) |
| Server | Apache (e.g. XAMPP) |
| Dependencies | Composer (`stripe/stripe-php`, `google/cloud-document-ai`) |

---

## Local setup

### Prerequisites

- **Apache + PHP + MySQL** (e.g. XAMPP)
- **[Composer](https://getcomposer.org/download/)**

### 1. Clone & install PHP dependencies

```bash
cd pet-insurance-app
composer install
```

`vendor/` is **gitignored**. Without `composer install`, **Stripe checkout**, **Document AI**, and related code will error (missing `vendor/autoload.php`).

**Windows: `composer` not found** → install [Composer-Setup.exe](https://getcomposer.org/Composer-Setup.exe), point it at `php.exe`, restart the terminal; or use `php composer.phar install` from `pet-insurance-app`.

### 2. Database

SQL files are in **`pet-insurance-app/database/`**:

| File | Purpose |
|------|---------|
| `schema.sql` | Tables |
| `seed-users.sql` | Test users (see below) |
| `seed-plans.sql` | Insurance plans |
| `seed-vet-clinics.sql` | Clinic seed data |

Import in order (e.g. phpMyAdmin or `mysql` CLI). Configure access in **`pet-insurance-app/config/database.php`** and/or **`.env`** (see below).

### 3. Environment (`.env`)

Create **`pet-insurance-app/.env`** (optional keys depend on what you’re testing). Loaded by **`config/constants.php`**.

| Variable | Purpose |
|----------|---------|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL |
| `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY` | **Review & Pay** / Checkout (`config/stripe.php`) |
| `AI_SCANNER_MODE` | `mock` (default, no GCP) or `live` (real Document AI) |
| `GCP_PROJECT_ID`, `GCP_LOCATION`, processor IDs | Document AI when `live` |
| `APP_ENV` | e.g. `local` vs `production` |

**Stripe:** without valid **`STRIPE_SECRET_KEY`** (`sk_test_…`), **Confirm and Pay** will fail with a generic error — configure test keys from the [Stripe Dashboard](https://dashboard.stripe.com/test/apikeys).

**AI:** default **`AI_SCANNER_MODE=mock`** uses **`AIScannerMock`** (no Google credentials). Set **`live`** only when GCP + `config/gcp-credentials.json` (or ADC) and processors are configured.

### 4. Open the site

`http://localhost/pet-insurance-app/`  
(Adjust host/path if your Apache vhost or folder name differs.)

---

## Test credentials

| Role | Email | Password |
|------|-------|----------|
| Customer | customer_one@email.com | TestCustomer123! |
| Admin | admin@pawshield.com | Adminpass123 |

The seeded customer includes pet/subscription data suitable for demos (exact state depends on seeds).

---

## Testing claim uploads (sample vet receipts)

Sample PNGs: **`pet-insurance-app/tests/fixtures/receipts/`** — use on a claim’s document upload (**`api/scan-receipt.php`**).

| File | Mock scenario (`AI_SCANNER_MODE=mock`) |
|------|----------------------------------------|
| `01-vet-receipt-clean.png` | Clean — Happy Paws, `VET-HP-2024`, full line items |
| `02-vet-receipt-partial.png` | Partial — no clinic code (verification may fail) |
| `03-vet-receipt-blurry.png` | Poor scan — low confidence style output |

`AIScannerMock` maps scenarios using **filename keywords** (`clean`, `partial`, `blur` / `poor`).

**Regenerate PNGs:**

```powershell
cd pet-insurance-app
powershell -ExecutionPolicy Bypass -File tests/fixtures/receipts/generate.ps1
```

```bash
cd pet-insurance-app
php tests/fixtures/receipts/generate.php   # requires PHP GD
```

**Multi-page PDF:** allowed; save under the same folder and include `clean`, `partial`, or `blur` in the filename for predictable mock behaviour. Allowed MIME types: **`config/constants.php`** → `ALLOWED_MIME_TYPES`.

---

## Automated checks (Phase 0)

From **`pet-insurance-app`**:

```bash
php tests/test-phase0.php
```

Verifies config load, DB, CSRF, sanitization, and mock scanner wiring (see file header for details).

---

## Project structure (overview)

```
INF1005_Project/
└── pet-insurance-app/
    ├── index.php              # Landing (PawShield marketing + plans)
    ├── .htaccess
    ├── composer.json
    ├── config/                # constants, database, session, stripe
    ├── database/              # schema.sql + seeds
    ├── assets/                # css, js, images
    ├── auth/                  # login, register, verification
    ├── dashboard/             # customer area (pets, claims, subscriptions/review-pay)
    ├── admin/
    ├── pages/                 # about, pricing, how-it-works, legal, etc.
    ├── api/                   # scan-receipt, create-checkout-session, quotes, etc.
    ├── services/              # AIScanner, AIScannerMock, FileUploadHandler, …
    ├── tests/
    │   ├── test-phase0.php
    │   └── fixtures/receipts/ # sample receipts + generate.ps1 / generate.php
    └── uploads/               # user uploads (gitignored except .htaccess)
```

---

## Deployment notes (short)

- Run **`composer install`** on the server; set **`.env`** or server env vars (**DB**, **Stripe**, optional **GCP**).
- If **`https://` fails** with connection refused but **`http://` works**, configure TLS / port **443** or use **HTTP** until SSL is set up.
- **Checkout errors** → almost always **Stripe keys** or **vendor/** missing on that host.

---

## Team

| Member | Student No. | Role |
|--------|-------------|------|
| Kuah Chin Yang Eddison | 2501606 | Architecture, AI Pipeline & Integration |
| Chourasia Anushka | 2503769 | Frontend |
| Lazer Lideon Raja | 2501098 | Frontend |
| Kok Yi Heng | 2501222 | Content, Seed Data, Testing & Documentation |
| Jun Keat Lim | 2501004 | Backend: Auth, CRUD & Admin |
