-- ============================================
-- Seed Data — Vet Clinics (for cross-verification testing)
--
-- Mix of clinics that WILL match mock scanner data
-- and clinics that won't (to test both paths).
-- ============================================

USE pet_insurance;

INSERT INTO vet_clinics (clinic_code, name, address, phone, email) VALUES

-- These MATCH the mock scanner scenarios
('VET-HP-2024',  'Happy Paws Veterinary Clinic',  '123 Pet Street, Animalville, AN 12345',      '(555) 123-4567', 'info@happypaws.vet'),
('VET-CC-2025',  'City Cat Clinic',                '456 Feline Avenue, Meowtown, MT 67890',      '(555) 234-5678', 'hello@citycatclinic.com'),

-- These exist but WON'T match mock data (tests verification failure)
('VET-SR-2024',  'Sunrise Animal Hospital',        '789 Dawn Drive, Petville, PV 11111',          '(555) 345-6789', 'care@sunriseah.vet'),
('VET-BV-2025',  'Bayside Veterinary Center',      '321 Harbor Blvd, Coasttown, CT 22222',        '(555) 456-7890', 'info@baysidevet.com'),
('VET-GF-2024',  'Green Fields Vet Practice',      '654 Meadow Lane, Farmdale, FD 33333',         '(555) 567-8901', 'contact@greenfieldsvet.com'),
('VET-PH-2025',  'Pet Haven Clinic',               '987 Shelter Road, Caretown, CW 44444',        '(555) 678-9012', 'team@pethaven.vet'),
('VET-AE-2024',  'Animal Emergency 24/7',          '111 Urgent Street, Quickcity, QC 55555',      '(555) 789-0123', 'emergency@ae247.vet'),
('VET-WP-2025',  'Westpark Veterinary Hospital',   '222 Park Avenue West, Westdale, WD 66666',    '(555) 890-1234', 'reception@westparkvet.com'),
('VET-FC-2024',  'Furry Companions Vet',           '333 Companion Court, Friendlytown, FT 77777', '(555) 901-2345', 'hello@furrycompanions.vet'),
('VET-NL-2025',  'New Life Animal Clinic',          '444 Hope Street, Freshstart, FS 88888',       '(555) 012-3456', 'care@newlifeanimal.com'),

-- Clinics with different naming patterns (tests fuzzy matching)
('REG-VT-10042', 'Dr. Martinez Veterinary Office',  '555 Oak Street, Oldtown, OT 99999',          '(555) 111-2222', 'drm@martinezvet.com'),
('LIC-2024-887', 'County Animal Care Center',       '666 Government Blvd, Capitol, CP 00000',     '(555) 222-3333', 'admin@countyacc.gov');