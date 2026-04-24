<?php
/**
 * REDIS CONFIGURATION
 * Session & Caching Handler untuk performa tinggi
 * 
 * Digunakan untuk:
 * - Auto-save jawaban (mencegah loss saat koneksi putus)
 * - Timer session management
 * - Rate limiting API calls
 */

class RedisCache {
    private static $instance = null;
    private $redis;
    
    private $host = '127.0.0.1';
    private $port = 6379;
    private $timeout = 2.5;
    
    private function __construct() {
        try {
            $this->redis = new Redis();
            $this->redis->connect($this->host, $this->port, $this->timeout);
            
            // Optional: Set password jika ada
            // $this->redis->auth('your_password');
            
        } catch (RedisException $e) {
            // Fallback: Log error, sistem tetap jalan tanpa Redis
            error_log("Redis Connection Error: " . $e->getMessage());
            $this->redis = null;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getRedis() {
        return $this->redis;
    }
    
    public function isAvailable() {
        return $this->redis !== null;
    }
    
    // Helper methods
    public function set($key, $value, $ttl = 3600) {
        if (!$this->redis) return false;
        return $this->redis->setex($key, $ttl, json_encode($value));
    }
    
    public function get($key) {
        if (!$this->redis) return null;
        $data = $this->redis->get($key);
        return $data ? json_decode($data, true) : null;
    }
    
    public function delete($key) {
        if (!$this->redis) return false;
        return $this->redis->del($key);
    }
    
    public function exists($key) {
        if (!$this->redis) return false;
        return $this->redis->exists($key);
    }
    
    // Prevent cloning
    private function __clone() {}
}

// Helper function
function getRedis() {
    return RedisCache::getInstance();
}
