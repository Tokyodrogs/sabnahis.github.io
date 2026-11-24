# SABNAHIS Local Web-Based Enrollment System

A local network enrollment system for Sablayan National Agricultural and Horticultural
Integrated School (SABNAHIS), built with PHP, MySQL (MariaDB) and XAMPP. Administrators
log in to manage students, strands, grade levels, school years, and enrollment requests.
Students do not need an account — they enroll through a public online form.

## Requirements

- XAMPP (Apache + MySQL/MariaDB + PHP 8.x)
- A modern browser (Chrome or Edge)

## Setup Instructions

1. **Copy the project folder.**
   Copy the entire `sabnahis` folder into your XAMPP `htdocs` directory, e.g.
   `C:\xampp\htdocs\sabnahis`.

2. **Start Apache and MySQL** from the XAMPP Control Panel.

3. **Create the database.**
   Open `http://localhost/phpmyadmin`, go to the **Import** tab, and import
   `database/schema.sql`. This creates the `sabnahis_enrollment` database, all tables,
   and seed data (default grade levels, sample strands, a default school year, and one
   admin account).

4. **Check database credentials.**
   `config/db.php` is pre-configured for the default XAMPP MySQL setup (`root` user,
   no password). If your MySQL setup uses different credentials, edit that file.

5. **Set folder permissions (if needed).**
   The `uploads/` folder must be writable by the web server so student-submitted
   documents can be saved there.

6. **Open the system.**
   - Public site: `http://localhost/sabnahis/`
   - Admin login: `http://localhost/sabnahis/admin/login.php`

## Default Admin Login

| Username | Password  |
|----------|-----------|
| admin    | admin123  |

**Important:** Log in and change this password immediately (Admin > Settings > Change
Password) before using the system for real enrollment data.

## Folder Structure

```
sabnahis/
├── admin/                 Admin-only pages (login required)
│   ├── includes/          Shared admin header/sidebar/footer/auth check
│   ├── login.php, logout.php
│   ├── dashboard.php
│   ├── students.php, student_add.php, student_edit.php, student_view.php, student_delete.php
│   ├── enrollment_requests.php, enrollment_view.php, enrollment_print.php
│   ├── grade_levels.php, strands.php, school_year.php, announcements.php
│   ├── reports.php, reports_print.php
│   └── settings.php
├── config/                db.php (connection), constants.php (site settings)
├── includes/               functions.php (shared helpers), site_header.php, site_footer.php
├── database/schema.sql     Full DB schema + seed data
├── uploads/                Student-submitted documents (auto-created, write-protected)
├── assets/css, assets/js   Stylesheets and client-side scripts
├── index.php, about.php, guidelines.php, enroll.php, contact.php   Public pages
```

## Key Features

- **Admin module**: login, change password, manage account, dashboard with live
  counts (students, strands, enrolled, pending), logout.
- **Student management**: add/view/edit/delete, search, and filter by grade level,
  strand, school year, and status.
- **Enrollment management**: view submissions, approve/reject, mark officially
  enrolled (auto-generates an enrollment number), printable enrollment form.
- **Grade level & strand management**: add/edit/delete.
- **School year management**: open/close enrollment, set the current school year.
- **Reports**: enrolled students list, enrollment statistics, students per strand,
  students per grade level — all printable (use the browser's "Print > Save as PDF").
- **Public site**: home page with announcements, about page, enrollment guidelines,
  contact page, and the online enrollment form (no login required).

## Security Notes

- Passwords are hashed with PHP's `password_hash()` / verified with `password_verify()`.
- All database queries use PDO prepared statements (no raw string concatenation of
  user input into SQL).
- Sessions are used for admin authentication, with session regeneration on login and
  a 30-minute idle timeout.
- Uploaded files are validated by extension and MIME type, capped at 5MB, and the
  `uploads/` folder has a `.htaccess` rule that blocks script execution.
- All user-supplied output is escaped with `htmlspecialchars()` before being rendered.
- A CSRF token protects the public enrollment form submission.

## Customization

- Update the school's real contact details in `contact.php`.
- Update `BASE_URL` in `config/constants.php` if you rename the project folder inside
  `htdocs` (default assumes `/sabnahis/`).
- Add/edit strands and grade levels from the admin panel instead of editing the
  database directly.

## Notes on "Printable Reports (PDF)"

Reports and the enrollment form open in a print-friendly layout with a "Print / Save
as PDF" button. Selecting your browser's "Save as PDF" destination produces a PDF
without requiring any additional PHP libraries — convenient for a local XAMPP setup
with no internet/composer access. If you'd like true server-generated PDFs (e.g. via
a library like Dompdf or TCPDF), that can be added later as an enhancement.
