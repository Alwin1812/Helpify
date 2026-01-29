ALTER TABLE users ADD COLUMN gender ENUM('Male', 'Female', 'Other') DEFAULT NULL AFTER email;

UPDATE users SET job_role = 'Cleaning' WHERE name LIKE 'alwin%' AND role = 'helper';
