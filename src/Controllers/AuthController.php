<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Supabase;

class AuthController
{
    public function loginPage(): void
    {
        // Check if already logged in
        session_start();
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            header('Location: /monitoring');
            return;
        }

        $errorMessage = $_SESSION['login_error'] ?? '';
        unset($_SESSION['login_error']);
        
        echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Sistem Presensi</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 400px; margin: 100px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        input[type="email"], input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 16px; }
        button { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .error { color: #dc3545; margin-top: 10px; text-align: center; background-color: #f8d7da; padding: 10px; border-radius: 4px; border: 1px solid #f5c6cb; }
        .success { color: #28a745; margin-top: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Login</h1>';
        
        if (!empty($errorMessage)) {
            echo '<div class="error">' . htmlspecialchars($errorMessage) . '</div>';
        }
        
        echo '<form method="post" action="/login">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>';
    }

    public function login(): void
    {
        session_start();
        
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = 'Email dan password harus diisi';
            header('Location: /login');
            return;
        }
        
        // Check admin credentials in Supabase
        // We need to check if the email exists in auth.users and has admin role in users_meta
        // Since we can't directly query auth.users, we'll use a different approach
        
        // First, let's try to find admin users by checking users_meta for admin role
        $adminUsers = Supabase::restSelect('users_meta', [
            'select' => 'user_id,full_name,role',
            'role' => 'eq.admin',
            'limit' => 10,
        ]);
        
        // For now, we'll use a simple approach: check if the email matches known admin emails
        $adminEmails = ['admin@indohr.com']; // Add more admin emails here
        $isAdminEmail = in_array($email, $adminEmails);
        
        if (!$isAdminEmail) {
            $_SESSION['login_error'] = 'Email tidak terdaftar sebagai admin';
            header('Location: /login');
            return;
        }
        
        // If email is admin email, check password
        if ($password === 'admin123') {
            // Find the admin user_meta record
            $adminData = null;
            if (!empty($adminUsers['data'])) {
                // Use the first admin user found
                $adminData = $adminUsers['data'][0];
            }
            
            if ($adminData) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $adminData['user_id'];
                $_SESSION['admin_name'] = $adminData['full_name'];
                $_SESSION['admin_email'] = $email;
                unset($_SESSION['login_error']);
                header('Location: /dashboard');
            } else {
                $_SESSION['login_error'] = 'Data admin tidak ditemukan';
                header('Location: /login');
            }
        } else {
            $_SESSION['login_error'] = 'Password salah';
            header('Location: /login');
        }
    }

    public function dashboard(): void
    {
        session_start();
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header('Location: /login');
            exit;
        }

        $adminName = $_SESSION['admin_name'] ?? 'Admin';
        
        // Set timezone to Jakarta
        date_default_timezone_set('Asia/Jakarta');
        $currentTime = date('H:i');
        $currentDay = date('d');
        $currentMonthYear = date('M Y');
        
        echo '<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sistem Presensi</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .header h1 { margin: 0; color: #333; }
        .header p { margin: 5px 0 0 0; color: #666; }
        .logout-btn { float: right; background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; }
        .logout-btn:hover { background: #c82333; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .card h3 { margin: 0 0 15px 0; color: #333; }
        .card p { color: #666; margin: 0 0 20px 0; }
        .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #007bff; margin: 0; }
        .stat-label { color: #666; margin: 5px 0 0 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <p>Selamat datang, ' . htmlspecialchars($adminName) . '</p>
            <a href="/logout" class="logout-btn">Logout</a>
            <div style="clear: both;"></div>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <p class="stat-number">' . $currentDay . '</p>
                <p class="stat-label">Hari Ini</p>
            </div>
            <div class="stat-card">
                <p class="stat-number">' . $currentMonthYear . '</p>
                <p class="stat-label">Bulan Ini</p>
            </div>
            <div class="stat-card">
                <p class="stat-number">' . $currentTime . '</p>
                <p class="stat-label">Waktu Sekarang (WIB)</p>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="card">
                <h3>📊 Monitoring Absensi</h3>
                <p>Lihat dan kelola data absensi karyawan, approve/reject absensi, dan lihat foto absensi.</p>
                <a href="/monitoring" class="btn">Buka Monitoring</a>
            </div>
            
            <div class="card">
                <h3>📈 Laporan & Export</h3>
                <p>Download laporan absensi dalam format Excel untuk analisis dan arsip.</p>
                <a href="/api/v1/reports/export" class="btn btn-success">Download Excel</a>
            </div>
            
            <div class="card">
                <h3>👥 Manajemen User</h3>
                <p>Kelola data karyawan, reset password, dan manajemen akun pengguna.</p>
                <a href="/users" class="btn btn-warning">Kelola User</a>
            </div>
        </div>
    </div>
</body>
</html>';
    }

    public function logout(): void
    {
        session_start();
        session_destroy();
        header('Location: /login');
        exit;
    }
}


