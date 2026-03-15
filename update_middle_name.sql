USE student_registration;

DELETE FROM users WHERE role = 'faculty';

ALTER TABLE students
DROP FOREIGN KEY fk_student_faculty;

ALTER TABLE students
ADD COLUMN middle_name VARCHAR(100) NOT NULL DEFAULT '' AFTER first_name,
ADD COLUMN contact_number VARCHAR(30) NOT NULL DEFAULT '' AFTER email,
DROP COLUMN faculty_user_id;

ALTER TABLE users
MODIFY COLUMN role ENUM('admin', 'student') NOT NULL;
