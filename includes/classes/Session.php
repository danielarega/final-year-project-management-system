<!-- includes/classes/Session.php -->
<?php
class Session {
    private static $sessionStarted = false;
    
    public static function init() {
        if (!self::$sessionStarted) {
            session_start([
                'cookie_lifetime' => 86400, // 24 hours
                'cookie_secure' => isset($_SERVER['HTTPS']),
                'cookie_httponly' => true,
                'use_strict_mode' => true
            ]);
            self::$sessionStarted = true;
        }
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key) {
        return $_SESSION[$key] ?? null;
    }
    
    public static function remove($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    public static function destroy() {
        if (self::$sessionStarted) {
            session_destroy();
            $_SESSION = [];
        }
    }
    
    public static function setFlash($type, $message) {
        self::set('flash', ['type' => $type, 'message' => $message]);
    }
    
    public static function getFlash() {
        $flash = self::get('flash');
        self::remove('flash');
        return $flash;
    }
}
?>