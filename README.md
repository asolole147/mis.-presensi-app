## PHP Native (API + Web Admin)

Struktur disarankan
- public/index.php  (front controller, router)
- app/controllers/* (AuthController, AttendanceController, ReportsController, UsersController)
- app/models/* (User.php, Attendance.php, Shift.php, Geofence.php)
- app/views/* (layout.php, login.php, dashboard.php, users.php, ...)
- app/core/ (Router.php, DB.php, Auth.php, Response.php)
- storage/ (uploads jika pakai multipart)
- config.php (.env berisi DB creds)

Langkah awal
1. Buat `public/index.php` untuk routing dasar
2. Koneksi DB via PDO (MySQL/PostgreSQL)
3. Implement login (session/JWT) dan endpoint absensi dasar
4. Siapkan halaman login & dashboard kosong


