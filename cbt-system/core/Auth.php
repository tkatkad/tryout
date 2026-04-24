<?php
/**
 * AUTHENTICATION HANDLER
 * Login, Logout, Session Management
 */

class Auth {
    
    /**
     * Login user dengan username dan password
     */
    public static function login($username, $password) {
        try {
            $pdo = getDB();
            
            // Ambil user dari database
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'student'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Username tidak ditemukan'];
            }
            
            // Verify password (gunakan password_verify untuk production)
            // Untuk development: password_hash('password123', PASSWORD_DEFAULT)
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Password salah'];
            }
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Log activity
            self::logActivity($user['id'], 'login');
            
            return ['success' => true, 'user' => $user];
            
        } catch (PDOException $e) {
            error_log("Auth Login Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
    
    /**
     * Login untuk admin
     */
    public static function loginAdmin($username, $password) {
        try {
            $pdo = getDB();
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Username admin tidak ditemukan'];
            }
            
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Password salah'];
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_role'] = 'admin';
            $_SESSION['login_time'] = time();
            
            self::logActivity($user['id'], 'admin_login');
            
            return ['success' => true, 'user' => $user];
            
        } catch (PDOException $e) {
            error_log("Auth Admin Login Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            self::logActivity($_SESSION['user_id'], 'logout');
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
