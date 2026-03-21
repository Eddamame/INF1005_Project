# PetCare Insurance — INF1005 Group Project

**Team P7-12 | Submission: 21 February 2026**

A full-stack web-based pet insurance platform that streamlines claim processing through AI-powered document scanning using Google Cloud Document AI.

---

## Features

- **Customer Portal** – Pet profile management, claim submission, quote viewing, and claim tracking
- **AI Document Scanner** – Automated extraction of treatment details from veterinary receipts via Google Cloud Document AI
- **Quote Engine** – Intelligent premium calculation (Basic / Standard / Premium plans)
- **Admin Dashboard** – Claims management, user administration, and audit logging
- **Clinic Verification** – Cross-references 500+ Singapore vet clinics to validate claims
- **Security** – CSRF protection, SQL injection prevention (prepared statements), XSS sanitization, BCRYPT password hashing

---

## Tech Stack

| Layer | Technologies |
|---|---|
| Frontend | HTML5, Bootstrap 5, CSS3, JavaScript (ES6+) |
| Backend | PHP 8.4.8 |
| Database | MySQL 8.5 |
| AI Services | Google Cloud Document AI API |
| Web Server | Apache (via XAMPP) |
| Version Control | Git |

---

## Local Setup

### Prerequisites
- XAMPP (Apache + MySQL + PHP 8.4)
- Composer

### Steps
1. Clone this repo into your XAMPP `htdocs` directory
2. Run `composer install` to install Google Cloud dependencies
3. Import the database schema and seed files from `/database/`
4. Configure your database connection in `/config/database.php`
5. Configure Google Cloud Document AI credentials in `/config/constants.php`
6. Access the app at `http://localhost/pet-insurance-app`

---

## Test Credentials

| Role | Email | Password |
|---|---|---|
| Customer | customer_one@email.com | TestCustomer123! |
| Admin | admin@pawshield.com | Adminpass123 |

The test customer account includes 1 pet profile with an active subscription. 

---

## Project Structure
```
/pet-insurance-app
├── index.php               # Landing page / routing entry point
├── .htaccess               # URL rewriting and security headers
├── composer.json           # Google Cloud library dependencies
├── /config                 # DB connections, constants, session management
├── /assets                 # CSS, JavaScript, images
├── /auth                   # Register, login, logout
├── /dashboard              # Customer portal (profile, pets, claims, quotes)
├── /admin                  # Admin interface (users, claims, audit logs)
├── /api                    # AJAX endpoints (scan-receipt, generate-quote, verify-clinic)
└── /services               # Business logic (AIScanner, QuoteEngine, ClaimProcessor)
```

---

## Team

| Member | Student No. | Role |
|---|---|---|
| Kuah Chin Yang Eddison | 2501606 | Architecture, AI Pipeline & Integration |
| Chourasia Anushka | 2503769 | Frontend |
| Lazer Lideon Raja | 2501098 | Frontend |
| Kok Yi Heng | 2501222 | Content, Seed Data, Testing & Documentation |
| Jun Keat Lim | 2501004 | Backend: Auth, CRUD & Admin |