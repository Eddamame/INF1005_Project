-- ============================================
-- Seed Data — Insurance Plans
-- ============================================

USE pet_insurance;

INSERT INTO insurance_plans (name, description, monthly_premium, annual_limit, deductible, coverage_pct) VALUES
(
    'Basic',
    'Essential coverage for routine vet visits and minor treatments. Ideal for young, healthy pets with minimal medical history.',
    29.99,
    5000.00,
    100.00,
    70.00
),
(
    'Premium',
    'Comprehensive coverage including surgeries, diagnostics, and specialist referrals. Our most popular plan for peace of mind.',
    49.99,
    15000.00,
    50.00,
    80.00
),
(
    'Ultimate',
    'Full coverage with zero deductible. Covers everything from routine checkups to emergency surgeries and chronic conditions.',
    79.99,
    50000.00,
    0.00,
    90.00
);