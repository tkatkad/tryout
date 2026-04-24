<?php
/**
 * AUTHENTICATION HANDLER
 * Login, Logout, Session Management
 * 
 * SECURITY FEATURES:
 * - Session Fixation Protection (session_regenerate_id)
 * - CSRF Token Validation
 * - Brute Force Protection (5 attempts in 900 seconds)
 */

class Auth {
    
    // Konfigurasi Brute Force Protection
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const BLOCK_DURATION = 900; // 15 menit dalam detik
    
    /**
     * Generate CSRF Token untuk form login
     * Panggil method ini saat menampilkan form login
     * Contoh di login.php: <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCsrfToken(); ?>">
     */
    public static function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Reset CSRF Token (gunakan setelah login sukses atau logout)
     */
    private static function resetCsrfToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    /**
     * Cek apakah username sedang diblokir karena brute force
     * @param string $username
     * @return bool true jika diblokir, false jika tidak
     */
    private static function isBlocked($username) {
        $blockKey = 'login_attempts_' . md5($username);
        
        if (!isset($_SESSION[$blockKey])) {
            return false;
        }
        
        $attempts = $_SESSION[$blockKey];
        
        // Cek apakah sudah mencapai max attempts
        if ($attempts['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            // Cek apakah masih dalam durasi blokir
            $timeSinceLastAttempt = time() - $attempts['last_attempt'];
            if ($timeSinceLastAttempt < self::BLOCK_DURATION) {
                return true; // Masih diblokir
            } else {
                // Reset counter jika sudah lewat durasi blokir
                unset($_SESSION[$blockKey]);
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Record failed login attempt untuk brute force protection
     * @param string $username
     */
    private static function recordFailedAttempt($username) {
        $blockKey = 'login_attempts_' . md5($username);
        
        if (!isset($_SESSION[$blockKey])) {
            $_SESSION[$blockKey] = [
                'count' => 0,
                'last_attempt' => time()
            ];
        }
        
        $_SESSION[$blockKey]['count']++;
        $_SESSION[$blockKey]['last_attempt'] = time();
        
        // Log untuk monitoring
        error_log("Failed login attempt for {$username}: Attempt #{$_SESSION[$blockKey]['count']}");
    }
    
    /**
     * Reset failed login attempts setelah login sukses
     * @param string $username
     */
    private static function resetFailedAttempts($username) {
        $blockKey = 'login_attempts_' . md5($username);
        unset($_SESSION[$blockKey]);
    }
    
    /**
     * Login user dengan username dan password
     * 
     * @param string $username Username user
     * @param string $password Password user
     * @param string|null $csrf_token CSRF token dari form (optional untuk backward compatibility)
     * @return array ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public static function login($username, $password, $csrf_token = null) {
        try {
            $pdo = getDB();
            
            // 1. BRUTE FORCE CHECK: Cek apakah username sedang diblokir
            if (self::isBlocked($username)) {
                $blockKey = 'login_attempts_' . md5($username);
                $attempts = $_SESSION[$blockKey];
                $remainingTime = self::BLOCK_DURATION - (time() - $attempts['last_attempt']);
                $minutes = floor($remainingTime / 60);
                $seconds = $remainingTime % 60;
                
                return [
                    'success' => false, 
                    'message' => "Terlalu banyak percobaan gagal. Silakan coba lagi dalam {$minutes} menit {$seconds} detik.",
                    'blocked' => true
                ];
            }
            
            // 2. CSRF VALIDATION: Validasi token jika disediakan
            if ($csrf_token !== null) {
                if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    // Log potential CSRF attack
                    error_log("CSRF validation failed for user: {$username}");
                    return [
                        'success' => false, 
                        'message' => 'Token keamanan tidak valid. Silakan refresh halaman dan coba lagi.',
                        'csrf_error' => true
                    ];
                }
            }
            
            // 3. Ambil user dari database
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'student'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // Record failed attempt untuk brute force tracking
                self::recordFailedAttempt($username);
                return ['success' => false, 'message' => 'Username tidak ditemukan'];
            }
            
            // 4. Verify password
            if (!password_verify($password, $user['password_hash'])) {
                // Record failed attempt untuk brute force tracking
                self::recordFailedAttempt($username);
                return ['success' => false, 'message' => 'Password salah'];
            }
            
            // 5. LOGIN SUKSES: Reset failed attempts counter
            self::resetFailedAttempts($username);
            
            // 6. SESSION FIXATION PROTECTION: Regenerate session ID
            session_regenerate_id(true);
            
            // 7. Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // 8. Reset CSRF token untuk keamanan berikutnya
            self::resetCsrfToken();
            
            // 9. Log activity
            self::logActivity($user['id'], 'login');
            
            return ['success' => true, 'user' => $user];
            
        } catch (PDOException $e) {
            error_log("Auth Login Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
    
    /**
     * Login untuk admin dengan keamanan lengkap
     * 
     * @param string $username Username admin
     * @param string $password Password admin
     * @param string|null $csrf_token CSRF token dari form (optional)
     * @return array ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public static function loginAdmin($username, $password, $csrf_token = null) {
        try {
            $pdo = getDB();
            
            // 1. BRUTE FORCE CHECK: Cek apakah username sedang diblokir
            if (self::isBlocked($username)) {
                $blockKey = 'login_attempts_' . md5($username);
                $attempts = $_SESSION[$blockKey];
                $remainingTime = self::BLOCK_DURATION - (time() - $attempts['last_attempt']);
                $minutes = floor($remainingTime / 60);
                $seconds = $remainingTime % 60;
                
                return [
                    'success' => false, 
                    'message' => "Terlalu banyak percobaan gagal. Silakan coba lagi dalam {$minutes} menit {$seconds} detik.",
                    'blocked' => true
                ];
            }
            
            // 2. CSRF VALIDATION: Validasi token jika disediakan
            if ($csrf_token !== null) {
                if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    error_log("CSRF validation failed for admin: {$username}");
                    return [
                        'success' => false, 
                        'message' => 'Token keamanan tidak valid. Silakan refresh halaman dan coba lagi.',
                        'csrf_error' => true
                    ];
                }
            }
            
            // 3. Ambil admin dari database
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                self::recordFailedAttempt($username);
                return ['success' => false, 'message' => 'Username admin tidak ditemukan'];
            }
            
            // 4. Verify password
            if (!password_verify($password, $user['password_hash'])) {
                self::recordFailedAttempt($username);
                return ['success' => false, 'message' => 'Password salah'];
            }
            
            // 5. LOGIN SUKSES: Reset failed attempts counter
            self::resetFailedAttempts($username);
            
            // 6. SESSION FIXATION PROTECTION: Regenerate session ID
            session_regenerate_id(true);
            
            // 7. Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_role'] = 'admin';
            $_SESSION['login_time'] = time();
            
            // 8. Reset CSRF token
            self::resetCsrfToken();
            
            // 9. Log activity
            self::logActivity($user['id'], 'admin_login');
            
            return ['success' => true, 'user' => $user];
            
        } catch (PDOException $e) {
            error_log("Auth Admin Login Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
    
    /**
     * Logout user dengan cleanup security data
     */
    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            self::logActivity($_SESSION['user_id'], 'logout');
        }
        
        // Cleanup brute force tracking data
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'login_attempts_') === 0) {
                unset($_SESSION[$key]);
            }
        }
        
        session_unset();
        session_destroy();
        
        // Hapus session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }
    
    /**
     * Cek apakah user sudah login
     */
    public static function check() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
    }
    
    /**
     * Get current user ID
     */
    public static function id() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user role
     */
    public static function role() {
        return $_SESSION['user_role'] ?? null;
    }
    
    /**
     * Get current user data
     */
    public static function user() {
        if (!self::check()) return null;
        
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Log activity user
     */
    private static function logActivity($userId, $action) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare(
                "INSERT INTO user_activity_logs (user_id, action, ip_address, user_agent) 
                 VALUES (?, ?, ?, ?)"
            );
            
            $stmt->execute([
                $userId,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (PDOException $e) {
            // Ignore logging errors
            error_log("Activity Log Error: " . $e->getMessage());
        }
    }
    
    /**
     * Middleware: Require authentication
     */
    public static function requireAuth($redirectUrl = null) {
        if (!self::check()) {
            $redirect = $redirectUrl ?? BASE_URL . '/views/student/login.php';
            redirect($redirect, 'Silakan login terlebih dahulu', 'warning');
        }
    }
    
    /**
     * Middleware: Require specific role
     */
    public static function requireRole($roles) {
        self::requireAuth();
        
        $roles = is_array($roles) ? $roles : [$roles];
        
        if (!in_array(self::role(), $roles)) {
            redirect(BASE_URL . '/index.php', 'Akses ditolak. Anda tidak memiliki izin.', 'danger');
        }
    }
}
