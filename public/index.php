<?php
declare(strict_types=1);

// Naikkan batas upload agar multipart tidak terputus di device
@ini_set('post_max_size','50M');
@ini_set('upload_max_filesize','50M');
@ini_set('max_execution_time','120');
@ini_set('memory_limit','256M');

// Front Controller / Router dasar

require_once __DIR__ . '/../src/bootstrap.php';

use App\Core\Router;

$router = new Router();

// Web Admin pages
$router->get('/', 'AuthController@loginPage');
$router->get('/login', 'AuthController@loginPage');
$router->post('/login', 'AuthController@login');
$router->get('/dashboard', 'AuthController@dashboard');
$router->get('/logout', 'AuthController@logout');
$router->post('/logout', 'AuthController@logout');
$router->get('/users', 'UsersController@index');
$router->post('/users/reset', 'UsersController@reset');
$router->post('/users/delete', 'UsersController@delete');
$router->get('/monitoring', 'MonitoringController@index');
$router->post('/monitoring/approve', 'MonitoringController@approve');
$router->post('/monitoring/reject', 'MonitoringController@reject');
$router->post('/monitoring/undo-approve', 'MonitoringController@undoApprove');
$router->post('/monitoring/undo-reject', 'MonitoringController@undoReject');

// API endpoints (v1)
$router->post('/api/v1/auth/login', 'Api\\AuthController@login');
$router->post('/api/v1/attendance/check-in', 'Api\\AttendanceController@checkIn');
$router->post('/api/v1/attendance/check-out', 'Api\\AttendanceController@checkOut');
$router->get('/api/v1/attendance/mine', 'Api\\AttendanceController@mine');
$router->get('/api/v1/attendance/can', 'Api\\AttendanceController@can');
$router->post('/api/v1/attendance/approve', 'Api\\AttendanceController@approve');
$router->post('/api/v1/attendance/reject', 'Api\\AttendanceController@reject');
$router->get('/api/v1/reports/missing', 'Api\\ReportsController@missing');
$router->get('/api/v1/reports/export', 'Api\\ReportsController@export');
$router->post('/api/v1/auth/signup-meta', 'Api\\AuthController@signupMeta');
// Storage
$router->post('/api/v1/storage/sign-upload', 'Api\\StorageController@signUpload');
$router->post('/api/v1/storage/upload', 'Api\\StorageController@upload');
$router->get('/api/v1/storage/view-photo', 'Api\\StorageController@viewPhoto');
$router->get('/api/v1/storage/proxy-photo', 'Api\\StorageController@proxyPhoto');
$router->get('/api/v1/storage/test', 'Api\\StorageController@testPhoto');
$router->get('/api/v1/storage/test-proxy', 'Api\\StorageController@testProxy');
$router->get('/api/v1/storage/check-bucket', 'Api\\StorageController@checkBucket');
$router->get('/api/v1/storage/list-files', 'Api\\StorageController@listFiles');
$router->get('/api/v1/storage/debug-photo', 'Api\\StorageController@debugPhoto');
$router->get('/api/v1/storage/test-proxy-photo', 'Api\\StorageController@testProxyPhoto');
$router->get('/api/v1/storage/scan-bucket', 'Api\\StorageController@scanBucket');

// Admin user management (Supabase)
$router->get('/api/v1/admin/users', 'Api\\AdminUsersController@list');
$router->post('/api/v1/admin/users/reset-password', 'Api\\AdminUsersController@resetPassword');
$router->delete('/api/v1/admin/users/delete', 'Api\\AdminUsersController@delete');

$router->dispatch($_SERVER['REQUEST_METHOD'], strtok($_SERVER['REQUEST_URI'], '?'));


