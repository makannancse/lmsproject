-- User management: phone, student profile fields for admin edit UI.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone VARCHAR(32) NULL AFTER email;

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS subject VARCHAR(255) NULL AFTER parent_email,
    ADD COLUMN IF NOT EXISTS default_payment_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subject;
