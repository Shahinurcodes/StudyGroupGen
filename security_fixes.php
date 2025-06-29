<?php
/**
 * Security Fixes for StudyGroupGen
 * This file contains all the security improvements needed for the application
 */

// 1. Production Environment Configuration
// Add this to the top of all PHP files in production
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'production'); // Change to 'development' for debugging
}

if (ENVIRONMENT === 'production') {
    // Disable error reporting in production
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    
    // Enable error logging instead
    ini_set('log_errors', 1);
    ini_set('error_log', '/path/to/error.log'); // Set appropriate path
} else {
    // Development environment
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// 2. Enhanced CSRF Protection
function generateSecureCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    // Regenerate token every 30 minutes for security
    if (time() - $_SESSION['csrf_token_time'] > 1800) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    return $_SESSION['csrf_token'];
}

function validateSecureCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Check if token is expired (30 minutes)
    if (time() - $_SESSION['csrf_token_time'] > 1800) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// 3. Input Validation and Sanitization
function sanitizeInput($input, $type = 'string', $maxLength = 255) {
    if (is_array($input)) {
        return array_map(function($item) use ($type, $maxLength) {
            return sanitizeInput($item, $type, $maxLength);
        }, $input);
    }
    
    $input = trim($input);
    
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) ? $input : '';
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int)$input : 0;
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (float)$input : 0.0;
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL) ? $input : '';
        case 'string':
        default:
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            return strlen($input) <= $maxLength ? $input : substr($input, 0, $maxLength);
    }
}

// 4. Session Security
function secureSessionStart() {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1); // Enable in HTTPS
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// 5. Password Security
function validatePassword($password) {
    // Minimum 8 characters, at least one uppercase, one lowercase, one number
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/';
    return preg_match($pattern, $password);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

// 6. Rate Limiting
class RateLimiter {
    private $redis;
    private $maxAttempts;
    private $window;
    
    public function __construct($maxAttempts = 10, $window = 60) {
        $this->maxAttempts = $maxAttempts;
        $this->window = $window;
        // In a real implementation, use Redis or database
    }
    
    public function checkLimit($identifier) {
        // Simple in-memory implementation (use Redis in production)
        $key = "rate_limit:$identifier";
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        // Remove old attempts
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now) {
            return $timestamp > ($now - $this->window);
        });
        
        // Check if limit exceeded
        if (count($_SESSION[$key]) >= $this->maxAttempts) {
            return false;
        }
        
        // Add current attempt
        $_SESSION[$key][] = $now;
        return true;
    }
}

// 7. SQL Injection Prevention Helper
function safeQuery($conn, $sql, $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Query preparation failed");
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params)); // Assume all strings for safety
        $stmt->bind_param($types, ...$params);
    }
    
    return $stmt;
}

// 8. XSS Prevention
function escapeOutput($data) {
    if (is_array($data)) {
        return array_map('escapeOutput', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// 9. File Upload Security
function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 5242880) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if ($file['size'] > $maxSize) {
        return false;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    
    return in_array($mimeType, $allowedMimes);
}

// 10. Security Headers
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' cdnjs.cloudflare.com; img-src \'self\' data:; font-src \'self\' cdnjs.cloudflare.com;');
}

// 11. Authentication Helper
function requireAuth($userType = null) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        header('Location: login.php');
        exit();
    }
    
    if ($userType && $_SESSION['user_type'] !== $userType) {
        header('Location: login.php');
        exit();
    }
}

// 12. Logging
function logSecurityEvent($event, $details = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'event' => $event,
        'details' => $details
    ];
    
    error_log(json_encode($logEntry) . PHP_EOL, 3, '/path/to/security.log');
}

// Usage Examples:
/*
// In your PHP files, add at the top:
require_once 'security_fixes.php';
secureSessionStart();
setSecurityHeaders();

// For forms:
<form method="post">
    <?php echo '<input type="hidden" name="csrf_token" value="' . generateSecureCSRFToken() . '">'; ?>
    <!-- form fields -->
</form>

// For processing forms:
if (!validateSecureCSRFToken($_POST['csrf_token'])) {
    logSecurityEvent('csrf_attempt', ['form' => 'login']);
    die('Invalid security token');
}

// For input validation:
$email = sanitizeInput($_POST['email'], 'email');
$name = sanitizeInput($_POST['name'], 'string', 100);

// For authentication:
requireAuth('student'); // or 'faculty'

// For rate limiting:
$rateLimiter = new RateLimiter(5, 60); // 5 attempts per minute
if (!$rateLimiter->checkLimit($_SESSION['user_id'])) {
    die('Too many attempts. Please try again later.');
}
*/
?> 