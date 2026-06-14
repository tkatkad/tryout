<?php
/**
 * LOGIN PROCESS HANDLER
 * Memproses login student dengan keamanan lengkap
 */

// Load konfigurasi dan core
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/Auth.php';

// Start session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya terima method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/views/student/login.php', 'Akses tidak valid', 'danger');
}

// Ambil data dari form
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$csrf_token = $_POST['csrf_token'] ?? null;

// Validasi input tidak boleh kosong
if (empty($username) || empty($password)) {
    redirect(BASE_URL . '/views/student/login.php', 'Username dan password harus diisi', 'warning');
}

// Sanitasi username (hanya alphanumeric dan underscore)
$username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);

if (empty($username)) {
    redirect(BASE_URL . '/views/student/login.php', 'Username tidak valid', 'danger');
}

// Proses login dengan Auth class (sudah include CSRF validation & brute force protection)
$result = Auth::login($username, $password, $csrf_token);

if ($result['success']) {
    // Login berhasil
    // Redirect ke dashboard siswa atau exam list
    redirect(BASE_URL . '/modules/student/dashboard.php', 'Login berhasil! Selamat datang, ' . htmlspecialchars($result['user']['full_name']), 'success');
} else {
    // Login gagal
    if (isset($result['blocked']) && $result['blocked']) {
        // Akun diblokir karena brute force
        redirect(BASE_URL . '/views/student/login.php?blocked=' . urlencode($result['message']), $result['message'], 'warning');
    } elseif (isset($result['csrf_error']) && $result['csrf_error']) {
        // CSRF error - refresh halaman untuk token baru
        redirect(BASE_URL . '/views/student/login.php?error=' . urlencode($result['message']), $result['message'], 'danger');
    } else {
        // Username/password salah
        redirect(BASE_URL . '/views/student/login.php?error=' . urlencode($result['message']), $result['message'], 'warning');
    }
}
