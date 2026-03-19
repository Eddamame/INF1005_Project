-- ============================================
-- Seed Data — Users + Pets + Subscriptions
--
-- Inserts:
--  - 4 customer users + 1 admin user
--  - 1 pet each for customer_one..customer_three
--  - 1 active subscription for each of those 3 pets
--
-- Passwords are bcrypt hashes generated with PHP:
--   password_hash(<password>, PASSWORD_BCRYPT)
-- ============================================

USE pet_insurance;


INSERT INTO users (
    id, email, password_hash, first_name, last_name, phone, role, email_verified
) VALUES
    (9001, 'customer_one@email.com',   '$2y$10$XLrMddAyKaAu314dQEZpau/CpPL8RzDARheGxqCzFc/0TsEjYOpNe', 'Customer', 'One',   NULL, 'customer', 1),
    (9002, 'customer_two@email.com',   '$2y$10$XLrMddAyKaAu314dQEZpau/CpPL8RzDARheGxqCzFc/0TsEjYOpNe', 'Customer', 'Two',   NULL, 'customer', 1),
    (9003, 'customer_three@email.com', '$2y$10$XLrMddAyKaAu314dQEZpau/CpPL8RzDARheGxqCzFc/0TsEjYOpNe', 'Customer', 'Three', NULL, 'customer', 1),
    (9004, 'customer_four@email.com',  '$2y$10$XLrMddAyKaAu314dQEZpau/CpPL8RzDARheGxqCzFc/0TsEjYOpNe', 'Customer', 'Four',  NULL, 'customer', 1),
    (9005, 'admin@pawshield.com',      '$2y$10$jq29JF4x.Ysmmz/ttp6VOeH9o5bEwOgQUYdeOcNTgeh4cjDfp7fpy', 'Admin',    'User',  NULL, 'admin',    1)
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    first_name    = VALUES(first_name),
    last_name     = VALUES(last_name),
    phone         = VALUES(phone),
    role          = VALUES(role),
    email_verified= VALUES(email_verified);

-- Pets for customer_one..customer_three
INSERT INTO pets (
    id, user_id, name, species, breed, date_of_birth, microchip_id
) VALUES
    (9101, 9001, 'Bruno', 'dog', 'Golden Retriever', '2021-06-12', 'MC-ONE-0001'),
    (9102, 9002, 'Milo',  'cat', 'Domestic Shorthair','2022-03-05', 'MC-TWO-0001'),
    (9103, 9003, 'Luna',  'dog', 'Shiba Inu',        '2020-11-20', 'MC-THR-0001')
ON DUPLICATE KEY UPDATE
    user_id        = VALUES(user_id),
    name           = VALUES(name),
    species        = VALUES(species),
    breed          = VALUES(breed),
    date_of_birth  = VALUES(date_of_birth),
    microchip_id   = VALUES(microchip_id);

-- Subscriptions (one per pet) — assumes seed-plans.sql inserted plans with IDs 1..3
INSERT INTO subscriptions (
    id, user_id, pet_id, plan_id, status, start_date, end_date, stripe_subscription_id
) VALUES
    (9201, 9001, 9101, 2, 'active', '2026-03-18', '2027-03-18', 'cs_test_b1tK7L0TMKB9nZgSmz1vki7bzLSx3DdwjDMSYLdtcVOCip7UQdZWQJ1F9'), -- Premium
    (9202, 9002, 9102, 1, 'active', '2026-03-01', '2024-09-01', 'cs_test_a2sJ8M1UNLC0oAhTnA2wlj8czMTy4EexkENTZMeudWPDjq8VReAXRK2G0'), -- Basic
    (9203, 9003, 9103, 3, 'active', '2023-03-02', '2024-09-02', 'cs_test_c3uL9N2VOMD1pBiUoB3xmk9daNUz5FfylFOUANfveXQEk9WSfBYSL3H1')  -- Ultimate
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    pet_id  = VALUES(pet_id),
    plan_id = VALUES(plan_id),
    status  = VALUES(status),
    start_date = VALUES(start_date),
    end_date   = VALUES(end_date),
    stripe_subscription_id = VALUES(stripe_subscription_id);