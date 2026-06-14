<?php
/**
 * GLOBAL CONSTANTS & HELPER FUNCTIONS
 * Digunakan di seluruh aplikasi
 */

// ========================================
// KONSTANTA GLOBAL
// ========================================
define('APP_NAME', 'CBT BIMBEL - Sistem Ujian Online');
define('APP_VERSION', '1.1.0');
define('BASE_URL', '/cbt-system/public');
define('ROOT_PATH', dirname(__DIR__));

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 jika menggunakan HTTPS
ini_set('session.gc_maxlifetime', 7200); // 2 jam

// Security: Set session cookie parameters BEFORE session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 7200,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'secure' => false, // Set true untuk HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// Upload Configuration
define('UPLOAD_DIR', ROOT_PATH . '/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Pagination
define('ITEMS_PER_PAGE', 20);

// ========================================
// FUNGSI HELPER
// ========================================

/**
 * Sanitasi input user untuk mencegah XSS
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect dengan pesan flash
 */
function redirect($url, $message = null, $type = 'info') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: {$url}");
    exit;
}

/**
 * Mendapatkan flash message dan menghapusnya
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Format angka ke format Rupiah
 */
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Format tanggal Indonesia
 */
function formatTanggal($tanggal, $format = 'd F Y') {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $pecahkan = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}

/**
 * Generate random token
 */
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Cek role user
 */
function hasRole($role) {
    if (!isLoggedIn()) return false;
    
    if (is_array($role)) {
        return in_array($_SESSION['user_role'], $role);
    }
    
    return $_SESSION['user_role'] === $role;
}

/**
 * Require login, redirect jika belum
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/views/student/login.php', 'Silakan login terlebih dahulu', 'warning');
    }
}

/**
 * Require specific role
 */
function requireRole($role) {
    requireLogin();
    
    if (!hasRole($role)) {
        redirect(BASE_URL . '/index.php', 'Akses ditolak. Anda tidak memiliki izin.', 'danger');
    }
}

/**
 * JSON Response helper untuk API
 */
function jsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

/**
 * Get difficulty label
 */
function getDifficultyLabel($level) {
    $labels = [
        1 => ['label' => 'Mudah', 'class' => 'bg-green-100 text-green-800'],
        2 => ['label' => 'Sedang', 'class' => 'bg-yellow-100 text-yellow-800'],
        3 => ['label' => 'Sukar', 'class' => 'bg-red-100 text-red-800']
    ];
    return $labels[$level] ?? $labels[2];
}

/**
 * Get cognitive level label
 */
function getCognitiveLevelLabel($level) {
    $labels = [
        1 => 'LOTS (Pengetahuan & Pemahaman)',
        2 => 'MOTS (Aplikasi & Analisis)',
        3 => 'HOTS (Evaluasi & Kreasi)'
    ];
    return $labels[$level] ?? 'Tidak diketahui';
}

/**
 * Debug helper (hanya aktif di development)
 */
function dd($data) {
    echo '<pre style="background:#f0f0f0;padding:10px;border-radius:5px;overflow:auto;">';
    var_dump($data);
    echo '</pre>';
    die();
}
