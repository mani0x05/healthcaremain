# Healthcare Hospitals Portal

A PHP and MySQL hospital management portal for tracking hospital beds, doctors, emergency availability, and incoming patients.

## Requirements

- XAMPP with Apache, PHP, and MySQL
- PHP with the `mysqli` extension

## Setup

1. Start Apache and MySQL in XAMPP.
2. Confirm the database settings in `db.php`:
   - Host: `localhost`
   - Username: `root`
   - Password: empty by default in XAMPP
   - Database: `healthcare`
4. Open this URL once:

   `http://localhost/HEALTHCARE/hospitals/create_demo_hospitals.php`

5. Sign in at:

   `http://localhost/HEALTHCARE/hospitals/`

## Demo login

- Username: `citycare`
- Password: `password`

Delete or protect `create_demo_hospitals.php` after creating the demo hospital.

## Main source files

- `index.php`: redirects users to the correct page
- `hospitals_login.php`: hospital authentication
- `hospital_dashboard.php`: resource summary
- `update_availability.php`: bed and emergency status management
- `doctors.php`: doctor management
- `incoming_patients.php`: patient status management
- `db.php`: MySQL connection
- `style.css`: shared styling
