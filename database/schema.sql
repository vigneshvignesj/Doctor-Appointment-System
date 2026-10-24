-- Doctor Appointment Booking System — schema + seed data
-- Import: mysql -u root -h 127.0.0.1 -P 3306 < database/schema.sql
-- (or via phpMyAdmin). Safe to re-run: drops and recreates the database.
--
-- Table shapes here are dictated by core/SlotEngine.php (the slot-computation
-- + concurrency-safe booking engine) — see FEATURES.md's "Availability Rules
-- -> Slot Engine -> Concurrency-Safe Booking" section for the full rationale.
-- The single most important line in this file is the UNIQUE KEY on
-- slot_reservations: that's the actual mutex two racing bookings collide on.

DROP DATABASE IF EXISTS doctor_appointment_booking_system;
CREATE DATABASE doctor_appointment_booking_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE doctor_appointment_booking_system;

-- ===================== SCHEMA =====================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin','doctor','patient') NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    -- Doctor staff accounts only (admin-generated, e.g. DOC-0007). NULL for admin/patient.
    staff_code VARCHAR(20) NULL UNIQUE,
    -- Block-list model for patients (admin can suspend for no-shows/abuse); doctor/admin
    -- accounts are always active once created (no self-registration for either).
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    reset_token VARCHAR(64) NULL,
    reset_token_expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE specializations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

-- One row per doctor's clinical profile. Deliberately a separate table from
-- `users` (not just extra columns on it) so doctor-only fields don't leak
-- onto admin/patient rows, and because SlotEngine::defaultSlotDuration()
-- queries this table by its own `id`, which doctor_id everywhere else refers to.
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    specialization_id INT NOT NULL,
    qualification VARCHAR(150) NOT NULL,
    consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    default_slot_duration_minutes INT NOT NULL DEFAULT 20,
    bio TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id)
) ENGINE=InnoDB;

-- A doctor's recurring template. day_of_week uses PHP's date('w') convention
-- (0 = Sunday .. 6 = Saturday) since that's what SlotEngine::windowsForDate()
-- queries against directly.
CREATE TABLE doctor_weekly_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration_minutes INT NOT NULL DEFAULT 20,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    INDEX idx_doctor_dow (doctor_id, day_of_week)
) ENGINE=InnoDB;

-- One-off overrides. `leave` blocks (start/end NULL = full day); `extra_hours`
-- adds a one-off window on top of the template. See SlotEngine::windowsForDate().
CREATE TABLE doctor_availability_exceptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    exception_date DATE NOT NULL,
    type ENUM('leave','extra_hours') NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    INDEX idx_doctor_date (doctor_id, exception_date)
) ENGINE=InnoDB;

-- THE race-proof table. One row per currently-active booking. The UNIQUE KEY
-- is the actual mutex: two concurrent booking transactions both attempt this
-- INSERT first, and MySQL's unique index rejects the second one outright
-- (error 1062) — a database-engine-level guarantee, not a PHP check-then-act
-- race. See SlotEngine::book()/cancel()/reschedule().
CREATE TABLE slot_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_doctor_slot (doctor_id, appointment_date, start_time),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    -- Set NULL when the reservation row is freed on cancel (SlotEngine::cancel()
    -- deletes the reservation; ON DELETE SET NULL keeps that a single FK-safe step).
    reservation_id INT NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('booked','completed','cancelled','no_show') NOT NULL DEFAULT 'booked',
    notes TEXT NULL,
    cancelled_by VARCHAR(20) NULL,
    -- Flags appointments created by the admin Concurrency Stress Test screen so
    -- they can never be mistaken for real clinic traffic in lists/reports.
    is_stress_test TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (reservation_id) REFERENCES slot_reservations(id) ON DELETE SET NULL,
    INDEX idx_doctor_date (doctor_id, appointment_date),
    INDEX idx_patient (patient_id)
) ENGINE=InnoDB;

CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NULL,
    actor_role VARCHAR(20) NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ===================== SEED DATA (generated) =====================

INSERT INTO specializations (name, description) VALUES
('Cardiology', 'Cardiology consultations and follow-ups.'),
('Dermatology', 'Dermatology consultations and follow-ups.'),
('Pediatrics', 'Pediatrics consultations and follow-ups.'),
('Orthopedics', 'Orthopedics consultations and follow-ups.'),
('Neurology', 'Neurology consultations and follow-ups.'),
('ENT (Otolaryngology)', 'ENT (Otolaryngology) consultations and follow-ups.'),
('Gynecology', 'Gynecology consultations and follow-ups.'),
('General Medicine', 'General Medicine consultations and follow-ups.'),
('Dentistry', 'Dentistry consultations and follow-ups.'),
('Psychiatry', 'Psychiatry consultations and follow-ups.');

-- Passwords: admin -> Admin@123, all doctors -> Doctor@123, all patients -> Patient@123
INSERT INTO users (role, name, email, password_hash, phone, staff_code, status) VALUES
('admin', 'TealCare Admin', 'admin@tealcare.test', '$2y$10$EK..scnxwR7thI3/MFJ/Lu2uBHws14hWRWzwfYChabFQ3UIoJn.P2', '9000000001', NULL, 'active'),
('doctor', 'Dr. Aditya Rao', 'doctor1@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001001', 'DOC-0001', 'active'),
('doctor', 'Dr. Priya Nair', 'doctor2@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001002', 'DOC-0002', 'active'),
('doctor', 'Dr. Karan Mehta', 'doctor3@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001003', 'DOC-0003', 'active'),
('doctor', 'Dr. Sneha Iyer', 'doctor4@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001004', 'DOC-0004', 'active'),
('doctor', 'Dr. Vikram Singh', 'doctor5@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001005', 'DOC-0005', 'active'),
('doctor', 'Dr. Ananya Das', 'doctor6@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001006', 'DOC-0006', 'active'),
('doctor', 'Dr. Rohan Kulkarni', 'doctor7@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001007', 'DOC-0007', 'active'),
('doctor', 'Dr. Meera Pillai', 'doctor8@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001008', 'DOC-0008', 'active'),
('doctor', 'Dr. Arjun Reddy', 'doctor9@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001009', 'DOC-0009', 'active'),
('doctor', 'Dr. Nisha Kapoor', 'doctor10@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001010', 'DOC-0010', 'active'),
('doctor', 'Dr. Rahul Verma', 'doctor11@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001011', 'DOC-0011', 'active'),
('doctor', 'Dr. Divya Menon', 'doctor12@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001012', 'DOC-0012', 'active'),
('doctor', 'Dr. Suresh Pillai', 'doctor13@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001013', 'DOC-0013', 'active'),
('doctor', 'Dr. Kavita Joshi', 'doctor14@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001014', 'DOC-0014', 'active'),
('doctor', 'Dr. Manish Gupta', 'doctor15@tealcare.test', '$2y$10$A01qEewoe7EVnx8t3iGtseXrtEsINpRYfJJxkKQqH9F3pF2IDLMVG', '9000001015', 'DOC-0015', 'active'),
('patient', 'Aarav Sharma', 'patient1@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002001', NULL, 'active'),
('patient', 'Isha Verma', 'patient2@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002002', NULL, 'active'),
('patient', 'Rohit Gupta', 'patient3@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002003', NULL, 'active'),
('patient', 'Neha Iyer', 'patient4@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002004', NULL, 'active'),
('patient', 'Kabir Reddy', 'patient5@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002005', NULL, 'active'),
('patient', 'Tanya Nair', 'patient6@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002006', NULL, 'active'),
('patient', 'Aryan Chatterjee', 'patient7@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002007', NULL, 'active'),
('patient', 'Pooja Rao', 'patient8@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002008', NULL, 'active'),
('patient', 'Dev Kapoor', 'patient9@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002009', NULL, 'active'),
('patient', 'Ritu Bhatia', 'patient10@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002010', NULL, 'active'),
('patient', 'Sameer Joshi', 'patient11@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002011', NULL, 'active'),
('patient', 'Anjali Menon', 'patient12@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002012', NULL, 'active'),
('patient', 'Yash Pillai', 'patient13@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002013', NULL, 'active'),
('patient', 'Meghna Saxena', 'patient14@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002014', NULL, 'active'),
('patient', 'Vivek Malhotra', 'patient15@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002015', NULL, 'active'),
('patient', 'Simran Bose', 'patient16@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002016', NULL, 'active'),
('patient', 'Aditya Naidu', 'patient17@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002017', NULL, 'active'),
('patient', 'Kritika Trivedi', 'patient18@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002018', NULL, 'active'),
('patient', 'Nikhil Sinha', 'patient19@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002019', NULL, 'suspended'),
('patient', 'Shreya Kaur', 'patient20@tealcare.test', '$2y$10$dZSmJpx6P60JcsyzXryTVuXV/GISE6Ope14bwKWkDSPvBi4dgQWPy', '9000002020', NULL, 'suspended');

INSERT INTO doctors (user_id, specialization_id, qualification, consultation_fee, default_slot_duration_minutes, bio, status) VALUES
(2, 1, 'MD (Cardiology)', 800.00, 20, 'Specialises in preventive cardiology and hypertension management.', 'active'),
(3, 2, 'MD (Dermatology)', 600.00, 20, 'Focuses on adult and paediatric skin conditions.', 'active'),
(4, 3, 'MD (Pediatrics)', 500.00, 20, 'Manages routine child healthcare and vaccinations.', 'active'),
(5, 4, 'MS (Orthopedics)', 700.00, 20, 'Treats joint, spine and sports injuries.', 'active'),
(6, 5, 'DM (Neurology)', 1200.00, 20, 'Manages migraine, epilepsy and stroke follow-up care.', 'active'),
(7, 6, 'MS (ENT)', 550.00, 20, 'Handles ENT infections, hearing and sinus issues.', 'active'),
(8, 7, 'MD (Obstetrics & Gynecology)', 650.00, 20, 'Provides prenatal, postnatal and general gynaecological care.', 'active'),
(9, 8, 'MBBS, MD (General Medicine)', 400.00, 20, 'Primary care physician for general adult health concerns.', 'active'),
(10, 9, 'BDS, MDS (Dentistry)', 450.00, 20, 'General dentistry, root canal and orthodontic consultations.', 'active'),
(11, 10, 'MD (Psychiatry)', 900.00, 20, 'Provides counselling and management for common mental health conditions.', 'active'),
(12, 1, 'DM (Cardiology)', 850.00, 20, 'Interventional cardiology and cardiac rehabilitation.', 'active'),
(13, 2, 'MD (Dermatology)', 620.00, 20, 'Cosmetic and clinical dermatology consultations.', 'active'),
(14, 3, 'MD (Pediatrics)', 480.00, 20, 'Newborn care and adolescent health specialist.', 'active'),
(15, 4, 'MS (Orthopedics)', 720.00, 20, 'Focuses on fracture care and joint replacement follow-ups.', 'active'),
(16, 5, 'MBBS (General Medicine)', 380.00, 20, 'General outpatient consultations and referrals.', 'inactive');

INSERT INTO doctor_weekly_availability (doctor_id, day_of_week, start_time, end_time, slot_duration_minutes) VALUES
(1, 1, '09:00:00', '13:00:00', 20),
(1, 2, '09:00:00', '13:00:00', 20),
(1, 3, '09:00:00', '13:00:00', 20),
(1, 4, '09:00:00', '13:00:00', 20),
(1, 5, '09:00:00', '13:00:00', 20),
(2, 1, '10:00:00', '14:00:00', 15),
(2, 3, '10:00:00', '14:00:00', 15),
(2, 5, '10:00:00', '14:00:00', 15),
(2, 2, '17:00:00', '20:00:00', 30),
(2, 4, '17:00:00', '20:00:00', 30),
(3, 2, '09:30:00', '12:30:00', 20),
(3, 4, '09:30:00', '12:30:00', 20),
(3, 6, '09:30:00', '12:30:00', 20),
(3, 1, '16:00:00', '18:00:00', 15),
(3, 3, '16:00:00', '18:00:00', 15),
(3, 5, '16:00:00', '18:00:00', 15),
(4, 1, '09:00:00', '13:00:00', 20),
(4, 2, '09:00:00', '13:00:00', 20),
(4, 3, '09:00:00', '13:00:00', 20),
(4, 4, '09:00:00', '13:00:00', 20),
(4, 5, '09:00:00', '13:00:00', 20),
(5, 1, '10:00:00', '14:00:00', 15),
(5, 3, '10:00:00', '14:00:00', 15),
(5, 5, '10:00:00', '14:00:00', 15),
(5, 2, '17:00:00', '20:00:00', 30),
(5, 4, '17:00:00', '20:00:00', 30),
(6, 2, '09:30:00', '12:30:00', 20),
(6, 4, '09:30:00', '12:30:00', 20),
(6, 6, '09:30:00', '12:30:00', 20),
(6, 1, '16:00:00', '18:00:00', 15),
(6, 3, '16:00:00', '18:00:00', 15),
(6, 5, '16:00:00', '18:00:00', 15),
(7, 1, '09:00:00', '13:00:00', 20),
(7, 2, '09:00:00', '13:00:00', 20),
(7, 3, '09:00:00', '13:00:00', 20),
(7, 4, '09:00:00', '13:00:00', 20),
(7, 5, '09:00:00', '13:00:00', 20),
(8, 1, '10:00:00', '14:00:00', 15),
(8, 3, '10:00:00', '14:00:00', 15),
(8, 5, '10:00:00', '14:00:00', 15),
(8, 2, '17:00:00', '20:00:00', 30),
(8, 4, '17:00:00', '20:00:00', 30),
(9, 2, '09:30:00', '12:30:00', 20),
(9, 4, '09:30:00', '12:30:00', 20),
(9, 6, '09:30:00', '12:30:00', 20),
(9, 1, '16:00:00', '18:00:00', 15),
(9, 3, '16:00:00', '18:00:00', 15),
(9, 5, '16:00:00', '18:00:00', 15),
(10, 1, '09:00:00', '13:00:00', 20),
(10, 2, '09:00:00', '13:00:00', 20),
(10, 3, '09:00:00', '13:00:00', 20),
(10, 4, '09:00:00', '13:00:00', 20),
(10, 5, '09:00:00', '13:00:00', 20),
(11, 1, '10:00:00', '14:00:00', 15),
(11, 3, '10:00:00', '14:00:00', 15),
(11, 5, '10:00:00', '14:00:00', 15),
(11, 2, '17:00:00', '20:00:00', 30),
(11, 4, '17:00:00', '20:00:00', 30),
(12, 2, '09:30:00', '12:30:00', 20),
(12, 4, '09:30:00', '12:30:00', 20),
(12, 6, '09:30:00', '12:30:00', 20),
(12, 1, '16:00:00', '18:00:00', 15),
(12, 3, '16:00:00', '18:00:00', 15),
(12, 5, '16:00:00', '18:00:00', 15),
(13, 1, '09:00:00', '13:00:00', 20),
(13, 2, '09:00:00', '13:00:00', 20),
(13, 3, '09:00:00', '13:00:00', 20),
(13, 4, '09:00:00', '13:00:00', 20),
(13, 5, '09:00:00', '13:00:00', 20),
(14, 1, '10:00:00', '14:00:00', 15),
(14, 3, '10:00:00', '14:00:00', 15),
(14, 5, '10:00:00', '14:00:00', 15),
(14, 2, '17:00:00', '20:00:00', 30),
(14, 4, '17:00:00', '20:00:00', 30),
(15, 2, '09:30:00', '12:30:00', 20),
(15, 4, '09:30:00', '12:30:00', 20),
(15, 6, '09:30:00', '12:30:00', 20),
(15, 1, '16:00:00', '18:00:00', 15),
(15, 3, '16:00:00', '18:00:00', 15),
(15, 5, '16:00:00', '18:00:00', 15),
(8, 0, '08:00:00', '09:00:00', 30),
(8, 1, '08:00:00', '09:00:00', 30),
(8, 2, '08:00:00', '09:00:00', 30),
(8, 3, '08:00:00', '09:00:00', 30),
(8, 4, '08:00:00', '09:00:00', 30),
(8, 5, '08:00:00', '09:00:00', 30),
(8, 6, '08:00:00', '09:00:00', 30);

INSERT INTO doctor_availability_exceptions (doctor_id, exception_date, type, start_time, end_time, reason) VALUES
(1, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(2 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(2 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), 'leave', NULL, NULL, 'Medical conference'),
(4, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), 'leave', NULL, NULL, 'Personal leave'),
(7, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(4 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(4 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), 'leave', NULL, NULL, 'Family function'),
(10, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), 'leave', NULL, NULL, 'Sick leave'),
(13, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(3 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(3 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), 'leave', NULL, NULL, 'Conference'),
(2, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), 'leave', '09:00:00', '11:00:00', 'Hospital rounds'),
(5, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), 'leave', '09:30:00', '11:00:00', 'Training session'),
(11, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(4 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(4 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), 'leave', '09:00:00', '10:00:00', 'Staff meeting'),
(3, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(5 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(5 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), 'extra_hours', '15:00:00', '17:00:00', 'Weekend special clinic'),
(6, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(6 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(6 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), 'extra_hours', '10:00:00', '12:00:00', 'Sunday walk-in clinic'),
(9, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(5 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(5 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), 'extra_hours', '14:00:00', '16:00:00', 'Extra Saturday slots'),
(12, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(2 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(2 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), 'extra_hours', '18:00:00', '19:30:00', 'Evening add-on clinic'),
(14, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(5 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(5 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), 'extra_hours', '13:00:00', '15:00:00', 'Camp follow-up clinic'),
(1, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 1 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 1 + 7, 7) END + 0) DAY), 'leave', NULL, NULL, 'Public holiday'),
(8, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 4 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 4 + 7, 7) END + 0) DAY), 'extra_hours', '11:00:00', '12:00:00', 'Health camp');

INSERT INTO slot_reservations (doctor_id, appointment_date, start_time, end_time) VALUES
(1, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 14) DAY), '09:00:00', '09:20:00'),
(1, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 7) DAY), '09:00:00', '09:20:00'),
(1, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 0) DAY), '09:00:00', '09:20:00'),
(1, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), '09:00:00', '09:20:00'),
(2, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 14) DAY), '10:00:00', '10:15:00'),
(2, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 7) DAY), '10:00:00', '10:15:00'),
(2, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 0) DAY), '10:00:00', '10:15:00'),
(2, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), '10:00:00', '10:15:00'),
(7, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 14) DAY), '09:00:00', '09:20:00'),
(7, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 7) DAY), '09:00:00', '09:20:00'),
(7, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 0) DAY), '09:00:00', '09:20:00'),
(7, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), '09:00:00', '09:20:00'),
(8, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 14) DAY), '10:00:00', '10:15:00'),
(8, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 7) DAY), '10:00:00', '10:15:00'),
(8, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 0) DAY), '10:00:00', '10:15:00'),
(8, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), '10:00:00', '10:15:00'),
(1, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '09:40:00', '10:00:00'),
(2, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '17:00:00', '17:30:00'),
(3, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '16:00:00', '16:15:00'),
(4, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '09:40:00', '10:00:00'),
(5, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '17:00:00', '17:30:00'),
(6, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '16:00:00', '16:15:00'),
(7, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '09:40:00', '10:00:00'),
(8, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '17:00:00', '17:30:00'),
(9, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '16:00:00', '16:15:00'),
(10, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '09:40:00', '10:00:00'),
(11, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '17:00:00', '17:30:00'),
(12, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '16:00:00', '16:15:00'),
(13, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '09:40:00', '10:00:00'),
(14, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '17:00:00', '17:30:00'),
(1, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) END + 0) DAY), '10:20:00', '10:40:00'),
(2, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) END + 0) DAY), '10:30:00', '10:45:00'),
(3, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 3 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 3 + 7, 7) END + 0) DAY), '10:10:00', '10:30:00'),
(4, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) END + 0) DAY), '10:20:00', '10:40:00'),
(5, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) END + 0) DAY), '10:30:00', '10:45:00'),
(9, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 3 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 3 + 7, 7) END + 0) DAY), '10:10:00', '10:30:00'),
(11, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) END + 0) DAY), '10:30:00', '10:45:00'),
(12, DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 3 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 3 + 7, 7) END + 0) DAY), '10:10:00', '10:30:00'),
(8, CURDATE(), '08:00:00', '08:30:00'),
(8, CURDATE(), '08:30:00', '09:00:00');

SET @res_first_id = (SELECT MIN(id) FROM slot_reservations);

INSERT INTO appointments (patient_id, doctor_id, reservation_id, appointment_date, start_time, end_time, status, notes, cancelled_by, is_stress_test) VALUES
(17, 1, (@res_first_id + 0), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 14) DAY), '09:00:00', '09:20:00', 'completed', 'Follow-up visit, condition improving steadily.', NULL, 0),
(17, 1, (@res_first_id + 1), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 7) DAY), '09:00:00', '09:20:00', 'completed', 'Reviewed reports, no major concerns. Continue current treatment.', NULL, 0),
(17, 1, (@res_first_id + 2), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 0) DAY), '09:00:00', '09:20:00', 'completed', 'Prescribed medication for symptoms, patient responding well.', NULL, 0),
(17, 1, (@res_first_id + 3), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), '09:00:00', '09:20:00', 'booked', NULL, NULL, 0),
(18, 2, (@res_first_id + 4), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 14) DAY), '10:00:00', '10:15:00', 'completed', 'Follow-up visit, condition improving steadily.', NULL, 0),
(18, 2, (@res_first_id + 5), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 7) DAY), '10:00:00', '10:15:00', 'completed', 'Reviewed reports, no major concerns. Continue current treatment.', NULL, 0),
(18, 2, (@res_first_id + 6), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 0) DAY), '10:00:00', '10:15:00', 'completed', 'Prescribed medication for symptoms, patient responding well.', NULL, 0),
(18, 2, (@res_first_id + 7), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), '10:00:00', '10:15:00', 'booked', NULL, NULL, 0),
(19, 7, (@res_first_id + 8), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 14) DAY), '09:00:00', '09:20:00', 'completed', 'Follow-up visit, condition improving steadily.', NULL, 0),
(19, 7, (@res_first_id + 9), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 7) DAY), '09:00:00', '09:20:00', 'completed', 'Reviewed reports, no major concerns. Continue current treatment.', NULL, 0),
(19, 7, (@res_first_id + 10), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 0) DAY), '09:00:00', '09:20:00', 'completed', 'Prescribed medication for symptoms, patient responding well.', NULL, 0),
(19, 7, (@res_first_id + 11), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), '09:00:00', '09:20:00', 'booked', NULL, NULL, 0),
(20, 8, (@res_first_id + 12), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 14) DAY), '10:00:00', '10:15:00', 'completed', 'Follow-up visit, condition improving steadily.', NULL, 0),
(20, 8, (@res_first_id + 13), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 7) DAY), '10:00:00', '10:15:00', 'completed', 'Reviewed reports, no major concerns. Continue current treatment.', NULL, 0),
(20, 8, (@res_first_id + 14), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 0 + 7, 7) END + 0) DAY), '10:00:00', '10:15:00', 'completed', 'Prescribed medication for symptoms, patient responding well.', NULL, 0),
(20, 8, (@res_first_id + 15), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), '10:00:00', '10:15:00', 'booked', NULL, NULL, 0),
(17, 1, (@res_first_id + 16), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '09:40:00', '10:00:00', 'booked', NULL, NULL, 0),
(18, 2, (@res_first_id + 17), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '17:00:00', '17:30:00', 'booked', NULL, NULL, 0),
(19, 3, (@res_first_id + 18), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '16:00:00', '16:15:00', 'booked', NULL, NULL, 0),
(20, 4, (@res_first_id + 19), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '09:40:00', '10:00:00', 'booked', NULL, NULL, 0),
(21, 5, (@res_first_id + 20), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '17:00:00', '17:30:00', 'booked', NULL, NULL, 0),
(22, 6, (@res_first_id + 21), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '16:00:00', '16:15:00', 'booked', NULL, NULL, 0),
(23, 7, (@res_first_id + 22), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '09:40:00', '10:00:00', 'booked', NULL, NULL, 0),
(24, 8, (@res_first_id + 23), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '17:00:00', '17:30:00', 'booked', NULL, NULL, 0),
(25, 9, (@res_first_id + 24), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '16:00:00', '16:15:00', 'booked', NULL, NULL, 0),
(26, 10, (@res_first_id + 25), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '09:40:00', '10:00:00', 'booked', NULL, NULL, 0),
(27, 11, (@res_first_id + 26), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '17:00:00', '17:30:00', 'booked', NULL, NULL, 0),
(28, 12, (@res_first_id + 27), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(0 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(0 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '16:00:00', '16:15:00', 'booked', NULL, NULL, 0),
(29, 13, (@res_first_id + 28), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '09:40:00', '10:00:00', 'booked', NULL, NULL, 0),
(30, 14, (@res_first_id + 29), DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(1 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(1 - WEEKDAY(CURDATE()) + 7, 7) END + 0) DAY), '17:00:00', '17:30:00', 'booked', NULL, NULL, 0),
(31, 1, (@res_first_id + 30), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) END + 0) DAY), '10:20:00', '10:40:00', 'completed', 'Prescribed medication for symptoms, patient responding well.', NULL, 0),
(32, 2, (@res_first_id + 31), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) END + 0) DAY), '10:30:00', '10:45:00', 'completed', 'Reviewed reports, no major concerns. Continue current treatment.', NULL, 0),
(33, 3, (@res_first_id + 32), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 3 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 3 + 7, 7) END + 0) DAY), '10:10:00', '10:30:00', 'no_show', NULL, NULL, 0),
(34, 4, (@res_first_id + 33), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) END + 0) DAY), '10:20:00', '10:40:00', 'completed', 'Prescribed medication for symptoms, patient responding well.', NULL, 0),
(35, 5, (@res_first_id + 34), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) END + 0) DAY), '10:30:00', '10:45:00', 'no_show', NULL, NULL, 0),
(36, 9, (@res_first_id + 35), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 3 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 3 + 7, 7) END + 0) DAY), '10:10:00', '10:30:00', 'completed', 'Routine check-up, vitals normal. Advised follow-up in 3 months.', NULL, 0),
(17, 11, (@res_first_id + 36), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 2 + 7, 7) END + 0) DAY), '10:30:00', '10:45:00', 'completed', 'Prescribed medication for symptoms, patient responding well.', NULL, 0),
(18, 12, (@res_first_id + 37), DATE_SUB(CURDATE(), INTERVAL (CASE WHEN MOD(WEEKDAY(CURDATE()) - 3 + 7, 7) = 0 THEN 7 ELSE MOD(WEEKDAY(CURDATE()) - 3 + 7, 7) END + 0) DAY), '10:10:00', '10:30:00', 'no_show', NULL, NULL, 0),
(19, 1, NULL, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(3 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(3 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), '11:00:00', '11:20:00', 'cancelled', NULL, 'patient', 0),
(20, 4, NULL, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(3 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(3 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), '11:00:00', '11:20:00', 'cancelled', NULL, 'doctor', 0),
(21, 7, NULL, DATE_ADD(CURDATE(), INTERVAL (CASE WHEN MOD(3 - WEEKDAY(CURDATE()) + 7, 7) = 0 THEN 7 ELSE MOD(3 - WEEKDAY(CURDATE()) + 7, 7) END + 7) DAY), '11:00:00', '11:20:00', 'cancelled', NULL, 'admin', 0),
(22, 8, (@res_first_id + 38), CURDATE(), '08:00:00', '08:30:00', 'booked', NULL, NULL, 0),
(23, 8, (@res_first_id + 39), CURDATE(), '08:30:00', '09:00:00', 'booked', NULL, NULL, 0);
