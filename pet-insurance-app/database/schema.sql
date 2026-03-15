-- ============================================
-- Pet Insurance Prototype — Database Schema
-- Updated with Stripe Integration Fields
-- ============================================

CREATE DATABASE IF NOT EXISTS pet_insurance
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE pet_insurance;

-- ============================================
-- USERS
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    phone           VARCHAR(20) DEFAULT NULL,
    -- STRIPE: Links the user to their Stripe Customer Profile
    stripe_customer_id VARCHAR(255) DEFAULT NULL, 
    role            ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    email_verified  TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_stripe_customer (stripe_customer_id)
) ENGINE=InnoDB;

-- ============================================
-- PETS
-- ============================================
CREATE TABLE IF NOT EXISTS pets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    name            VARCHAR(100) NOT NULL,
    species         ENUM('dog', 'cat', 'bird', 'rabbit', 'reptile', 'other') NOT NULL,
    breed           VARCHAR(100) DEFAULT NULL,
    date_of_birth   DATE DEFAULT NULL,
    microchip_id    VARCHAR(50) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pets_user (user_id)
) ENGINE=InnoDB;

-- ============================================
-- INSURANCE PLANS
-- ============================================
CREATE TABLE IF NOT EXISTS insurance_plans (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    description     TEXT DEFAULT NULL,
    monthly_premium DECIMAL(8,2) NOT NULL,
    annual_limit    DECIMAL(10,2) NOT NULL,
    deductible      DECIMAL(8,2) NOT NULL,
    coverage_pct    DECIMAL(5,2) NOT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- USER SUBSCRIPTIONS
-- ============================================
CREATE TABLE IF NOT EXISTS subscriptions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    pet_id          INT NOT NULL,
    plan_id         INT NOT NULL,
    -- STRIPE: The specific recurring subscription ID
    stripe_subscription_id VARCHAR(255) DEFAULT NULL,
    status          ENUM('active', 'cancelled', 'expired', 'past_due') NOT NULL DEFAULT 'active',
    start_date      DATE NOT NULL,
    end_date        DATE DEFAULT NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id)  REFERENCES pets(id)  ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES insurance_plans(id),
    INDEX idx_subs_user (user_id),
    INDEX idx_subs_status (status),
    INDEX idx_stripe_sub (stripe_subscription_id)
) ENGINE=InnoDB;

-- ============================================
-- VET CLINICS
-- ============================================
CREATE TABLE IF NOT EXISTS vet_clinics (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    clinic_code     VARCHAR(20) NOT NULL UNIQUE,
    name            VARCHAR(200) NOT NULL,
    address         TEXT DEFAULT NULL,
    phone           VARCHAR(20) DEFAULT NULL,
    email           VARCHAR(255) DEFAULT NULL,
    is_verified     TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_clinic_code (clinic_code)
) ENGINE=InnoDB;

-- ============================================
-- CLAIMS
-- ============================================
CREATE TABLE IF NOT EXISTS claims (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reference_id    VARCHAR(30) NOT NULL UNIQUE,
    user_id         INT NOT NULL,
    pet_id          INT NOT NULL,
    subscription_id INT NOT NULL,
    clinic_code     VARCHAR(20) DEFAULT NULL,
    visit_date      DATE DEFAULT NULL,
    description     TEXT DEFAULT NULL,
    status          ENUM('draft', 'scanning', 'scanned', 'quoted', 'verified', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE,
    FOREIGN KEY (pet_id)          REFERENCES pets(id)          ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
    INDEX idx_claims_reference (reference_id),
    INDEX idx_claims_user (user_id),
    INDEX idx_claims_status (status)
) ENGINE=InnoDB;

-- ============================================
-- CLAIM DOCUMENTS
-- ============================================
CREATE TABLE IF NOT EXISTS claim_documents (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    claim_id        INT NOT NULL,
    file_name       VARCHAR(255) NOT NULL,
    file_path       VARCHAR(500) NOT NULL,
    file_type       ENUM('receipt', 'vet_report', 'invoice', 'other') NOT NULL,
    mime_type       VARCHAR(100) DEFAULT NULL,
    file_size       INT DEFAULT NULL,
    processor_used  VARCHAR(50) DEFAULT NULL,
    ai_raw_response JSON DEFAULT NULL,
    ai_parsed_data  JSON DEFAULT NULL,
    ai_confidence   DECIMAL(5,4) DEFAULT NULL,
    scan_status     ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    uploaded_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
    INDEX idx_docs_claim (claim_id)
) ENGINE=InnoDB;

-- ============================================
-- QUOTES (The Financial Resolution of a Claim)
-- ============================================
CREATE TABLE IF NOT EXISTS quotes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reference_id    VARCHAR(30) NOT NULL UNIQUE,
    claim_id        INT NOT NULL,
    total_vet_cost  DECIMAL(10,2) NOT NULL,
    deductible      DECIMAL(8,2) NOT NULL,
    coverage_pct    DECIMAL(5,2) NOT NULL,
    covered_amount  DECIMAL(10,2) NOT NULL,
    customer_pays   DECIMAL(10,2) NOT NULL,
    line_items      JSON DEFAULT NULL,
    clinic_verified TINYINT(1) NOT NULL DEFAULT 0,
    -- STRIPE: If you demo paying out a claim via Stripe Connect
    stripe_payout_id VARCHAR(255) DEFAULT NULL,
    status          ENUM('draft', 'pending_review', 'approved', 'rejected', 'paid') NOT NULL DEFAULT 'draft',
    generated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at     DATETIME DEFAULT NULL,

    FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
    INDEX idx_quotes_claim (claim_id),
    INDEX idx_quotes_status (status)
) ENGINE=InnoDB;

-- ============================================
-- AUDIT LOG
-- ============================================
CREATE TABLE IF NOT EXISTS audit_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT DEFAULT NULL,
    action          VARCHAR(100) NOT NULL,
    entity_type     VARCHAR(50) DEFAULT NULL,
    entity_id       INT DEFAULT NULL,
    details         JSON DEFAULT NULL,
    ip_address      VARCHAR(45) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id)
) ENGINE=InnoDB;

-- ============================================
-- CONTACTS
-- ============================================
CREATE TABLE IF NOT EXISTS contact (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;