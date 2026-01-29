CREATE DATABASE IF NOT EXISTS helpify_db;
USE helpify_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'helper', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    base_price DECIMAL(10, 2) NOT NULL,
    icon VARCHAR(255) -- For mapping to frontend icons
);

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    helper_id INT, -- Can be NULL initially if not assigned
    service_id INT NOT NULL,
    booking_date DATE NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (helper_id) REFERENCES users(id),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Insert some default services
INSERT INTO services (name, description, base_price, icon) VALUES 
('Cleaning', 'Home cleaning services', 500.00, 'cleaning_services'),
('Cooking', 'Personal chef and cooking services', 800.00, 'rice_bowl'),
('Babysitting', 'Care for children', 400.00, 'child_care'),
('Elderly Care', 'Assistance for elderly', 600.00, 'elderly'),
('Plumbing', 'Fixing leaks and pipes', 300.00, 'plumbing');
