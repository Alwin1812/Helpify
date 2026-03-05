CREATE DATABASE IF NOT EXISTS helpify_db;
USE helpify_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    gender ENUM('Male', 'Female', 'Other') DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'helper', 'admin') DEFAULT 'user',
    job_role VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    base_price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    helper_id INT,
    service_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('Cash', 'Online') DEFAULT 'Cash',
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (helper_id) REFERENCES users(id),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Insert Dummy Services
INSERT INTO services (name, description, base_price) VALUES 
('Cleaning', 'Professional home cleaning service', 500.00),
('Cooking', 'Expert home cooking service', 800.00),
('Babysitting', 'Reliable child care service', 600.00),
('Elderly Care', 'Compassionate care for seniors', 700.00);
