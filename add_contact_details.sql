USE helpify_db;

ALTER TABLE users 
ADD COLUMN phone_number VARCHAR(20) NULL,
ADD COLUMN address TEXT NULL;
