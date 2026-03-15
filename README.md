# Student Information & Registration System

Secure PHP + MySQL starter project for managing student registration and authentication.

## Files

- `index.php` - login page
- `dashboard.php` - admin/student dashboard
- `add_student.php` - admin-only student registration form
- `manage_students.php` - searchable student records list with CSV export
- `edit_student.php` - admin-only student update form
- `config.php` - database and session configuration
- `functions.php` - shared helpers for auth, security, uploads, and queries
- `database.sql` - schema and starter data
- `uploads/` - uploaded student documents

## Setup

1. Create a MySQL database and import `database.sql`.
2. Update database credentials in `config.php` if needed.
3. Serve the project with PHP/Apache.
4. Log in with the seeded admin account:
   - Username: `admin`
   - Password: `Admin@2026`
