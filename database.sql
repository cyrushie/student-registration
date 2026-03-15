-- Create the application database.
CREATE DATABASE IF NOT EXISTS student_registration CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_registration;

-- Shared user accounts for admins and students.
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Academic departments.
CREATE TABLE departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    head_of_department VARCHAR(100) DEFAULT NULL
);

-- Programs belong to departments.
CREATE TABLE programs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    duration VARCHAR(30) NOT NULL,
    CONSTRAINT fk_program_department
        FOREIGN KEY (department_id) REFERENCES departments(id)
        ON DELETE CASCADE
);

-- Student profile records plus uploaded document path.
CREATE TABLE students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    year_level VARCHAR(20) NOT NULL,
    dob DATE NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    contact_number VARCHAR(30) NOT NULL,
    department_id INT UNSIGNED NOT NULL,
    program_id INT UNSIGNED NOT NULL,
    document_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_student_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_student_department
        FOREIGN KEY (department_id) REFERENCES departments(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_student_program
        FOREIGN KEY (program_id) REFERENCES programs(id)
        ON DELETE RESTRICT
);

INSERT INTO users (username, password_hash, role) VALUES
('admin', '$2y$10$pwnm70Laz1i.FomM0XO0yOjtbAokiq/EwhMPqxl.J4tLtwFboGBtm', 'admin');

INSERT INTO departments (name, head_of_department) VALUES
('College of Computer Studies', 'Dr. Alicia Mendoza'),
('College of Business Administration', 'Prof. Ramon Torres'),
('College of Education', 'Dr. Sandra Flores');

INSERT INTO programs (department_id, name, duration) VALUES
(1, 'BS Information Technology', '4 Years'),
(1, 'BS Computer Science', '4 Years'),
(2, 'BS Business Administration', '4 Years'),
(3, 'Bachelor of Secondary Education', '4 Years');

-- Student IDs are generated in PHP, beginning with 1002026 and incrementing per new student.
-- Newly created students automatically receive:
-- username = generated student_id
-- password = chcc2026 (stored as a bcrypt hash)
-- role = student
--
-- Admin login:
-- username: admin
-- password: Admin@2026
