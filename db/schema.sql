-- Βάση δεδομένων για το site του διαιτολογικού γραφείου
-- Εισαγωγή: mysql -u USERNAME -p DATABASE_NAME < schema.sql

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(8,2) NOT NULL,
    type ENUM('session_package', 'ebook') NOT NULL,
    sessions_count INT DEFAULT NULL,      -- για πακέτα συνεδριών
    file_path VARCHAR(255) DEFAULT NULL,  -- για ebooks (path προς το αρχείο)
    image_path VARCHAR(255) DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT NOT NULL,   -- 0=Κυριακή ... 6=Σάββατο
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_minutes INT DEFAULT 45,
    active TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS blocked_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocked_date DATE NOT NULL,
    reason VARCHAR(255) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(150) NOT NULL,
    client_email VARCHAR(150) NOT NULL,
    client_phone VARCHAR(30) NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    notes TEXT,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slot (appointment_date, appointment_time)
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    client_name VARCHAR(150) NOT NULL,
    client_email VARCHAR(150) NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    viva_order_code VARCHAR(50) DEFAULT NULL,
    viva_transaction_id VARCHAR(100) DEFAULT NULL,
    status ENUM('pending', 'paid', 'failed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Ενδεικτικά δεδομένα διαθεσιμότητας: Δευτέρα-Παρασκευή 09:00-17:00
INSERT INTO availability (day_of_week, start_time, end_time, slot_minutes) VALUES
(1, '09:00:00', '17:00:00', 45),
(2, '09:00:00', '17:00:00', 45),
(3, '09:00:00', '17:00:00', 45),
(4, '09:00:00', '17:00:00', 45),
(5, '09:00:00', '15:00:00', 45);

-- Ενδεικτικές υπηρεσίες
INSERT INTO services (name, description, price, type, sessions_count, sort_order) VALUES
('Μεμονωμένη Συνεδρία', 'Μία συνεδρία διατροφικής αξιολόγησης και καθοδήγησης.', 40.00, 'session_package', 1, 1),
('Πακέτο 4 Συνεδριών', 'Μηνιαίο πρόγραμμα παρακολούθησης με 4 συνεδρίες.', 140.00, 'session_package', 4, 2),
('Πακέτο 8 Συνεδριών', 'Δίμηνο πρόγραμμα με στενή παρακολούθηση προόδου.', 260.00, 'session_package', 8, 3),
('E-book: Βασικές Αρχές Διατροφής', 'Ψηφιακός οδηγός με πρακτικές συμβουλές διατροφής.', 9.90, 'ebook', NULL, 4);
