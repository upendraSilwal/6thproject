<?php
/**
 * Security Utilities for Urban Oasis
 * 
 * This file contains comprehensive security functions for:
 * - Input validation and sanitization
 * - CSRF token management
 * - Security headers
 * - Rate limiting
 * - File upload security
 * - XSS prevention
 * - SQL injection prevention
 */

// Prevent direct access
if (!defined('URBAN_OASIS_APP')) {
    die('Direct access not permitted');
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token HTML input field
 */
function getCSRFTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Comprehensive input sanitization
 */
function sanitizeInput($input, $type = 'string') {
    if (is_array($input)) {
        return array_map(function($item) use ($type) {
            return sanitizeInput($item, $type);
        }, $input);
    }
    
    // Remove null bytes
    $input = str_replace(chr(0), '', $input);
    
    switch ($type) {
        case 'email':
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        
        case 'url':
            return filter_var(trim($input), FILTER_SANITIZE_URL);
        
        case 'filename':
            // Remove dangerous characters from filenames
            $input = preg_replace('/[^a-zA-Z0-9._-]/', '', $input);
            return trim($input, '.');
        
        case 'html':
            // Allow only safe HTML tags
            return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        case 'string':
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

/**
 * Validate input data
 */
function validateInput($input, $type, $options = []) {
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
        
        case 'phone':
            // Nepali phone number validation (10 digits starting with 98)
            return preg_match('/^98[0-9]{8}$/', preg_replace('/[^0-9]/', '', $input));
        
        case 'int':
            $min = $options['min'] ?? null;
            $max = $options['max'] ?? null;
            $value = filter_var($input, FILTER_VALIDATE_INT);
            if ($value === false) return false;
            if ($min !== null && $value < $min) return false;
            if ($max !== null && $value > $max) return false;
            return true;
        
        case 'float':
            $min = $options['min'] ?? null;
            $max = $options['max'] ?? null;
            $value = filter_var($input, FILTER_VALIDATE_FLOAT);
            if ($value === false) return false;
            if ($min !== null && $value < $min) return false;
            if ($max !== null && $value > $max) return false;
            return true;
        
        case 'string':
            $minLength = $options['min_length'] ?? 0;
            $maxLength = $options['max_length'] ?? 1000;
            $length = strlen($input);
            return $length >= $minLength && $length <= $maxLength;
        
        case 'password':
            // Strong password: min 8 chars, at least 1 uppercase, 1 lowercase, 1 number
            return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $input);
        
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL) !== false;
        
        default:
            return true;
    }
}

/**
 * Rate limiting functionality
 */
class RateLimiter {
    private static $limits = [];
    
    public static function checkLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
        $now = time();
        $key = md5($identifier);
        
        // Clean old entries
        if (isset(self::$limits[$key])) {
            self::$limits[$key] = array_filter(self::$limits[$key], function($timestamp) use ($now, $timeWindow) {
                return ($now - $timestamp) < $timeWindow;
            });
        } else {
            self::$limits[$key] = [];
        }
        
        // Check if limit exceeded
        if (count(self::$limits[$key]) >= $maxAttempts) {
            return false;
        }
        
        // Add current attempt
        self::$limits[$key][] = $now;
        return true;
    }
    
    public static function getRemainingAttempts($identifier, $maxAttempts = 5) {
        $key = md5($identifier);
        $currentAttempts = isset(self::$limits[$key]) ? count(self::$limits[$key]) : 0;
        return max(0, $maxAttempts - $currentAttempts);
    }
}

/**
 * Set security headers
 */
function setSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Content type sniffing protection
    header('X-Content-Type-Options: nosniff');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https: http:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self'");
    
    // HSTS (uncomment for production with HTTPS)
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    // Feature policy
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

/**
 * Secure file upload validation
 */
function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 5242880) {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'Invalid file upload';
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        $errors[] = 'File size too large (max: ' . ($maxSize / 1024 / 1024) . 'MB)';
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes)) {
        $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes);
    }
    
    // Check MIME type
    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    
    if (isset($allowedMimes[$extension])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($mimeType !== $allowedMimes[$extension]) {
            $errors[] = 'File content does not match extension';
        }
    }
    
    // Check for embedded PHP code (basic check)
    $content = file_get_contents($file['tmp_name']);
    if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
        $errors[] = 'File contains potentially dangerous content';
    }
    
    return $errors;
}

/**
 * Generate secure filename
 */
function generateSecureFilename($originalName, $prefix = '') {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $filename = $prefix . uniqid() . '_' . time();
    return $filename . '.' . $extension;
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = [], $severity = 'INFO') {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'severity' => $severity,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? null,
        'details' => $details
    ];
    
    $logFile = __DIR__ . '/../logs/security.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Validate and sanitize general form data
 * (Property-specific validation is handled in property_utils.php)
 */
function validateFormData($data, $rules = []) {
    $errors = [];
    $sanitized = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        
        // Required field check
        if ($rule['required'] && empty($value)) {
            $errors[$field] = ucfirst($field) . ' is required';
            continue;
        }
        
        // Skip validation if field is not required and empty
        if (!$rule['required'] && empty($value)) {
            continue;
        }
        
        // Type-specific validation
        switch ($rule['type']) {
            case 'email':
                if (!validateInput($value, 'email')) {
                    $errors[$field] = 'Please enter a valid email address';
                } else {
                    $sanitized[$field] = sanitizeInput($value, 'email');
                }
                break;
                
            case 'phone':
                if (!validateInput($value, 'phone')) {
                    $errors[$field] = 'Please enter a valid phone number';
                } else {
                    $sanitized[$field] = sanitizeInput($value);
                }
                break;
                
            case 'password':
                if (!validateInput($value, 'password')) {
                    $errors[$field] = 'Password must be at least 8 characters with uppercase, lowercase, and numbers';
                } else {
                    $sanitized[$field] = $value; // Don't sanitize passwords
                }
                break;
                
            case 'string':
                $options = $rule['options'] ?? [];
                if (!validateInput($value, 'string', $options)) {
                    $errors[$field] = 'Invalid ' . $field . ' format';
                } else {
                    $sanitized[$field] = sanitizeInput($value);
                }
                break;
                
            case 'int':
                $options = $rule['options'] ?? [];
                if (!validateInput($value, 'int', $options)) {
                    $errors[$field] = 'Invalid ' . $field . ' value';
                } else {
                    $sanitized[$field] = (int)$value;
                }
                break;
                
            case 'float':
                $options = $rule['options'] ?? [];
                if (!validateInput($value, 'float', $options)) {
                    $errors[$field] = 'Invalid ' . $field . ' value';
                } else {
                    $sanitized[$field] = (float)$value;
                }
                break;
                
            default:
                $sanitized[$field] = sanitizeInput($value);
        }
    }
    
    return ['errors' => $errors, 'data' => $sanitized];
}

/**
 * Password strength checker
 */
function checkPasswordStrength($password) {
    $strength = 0;
    $feedback = [];
    
    if (strlen($password) >= 8) {
        $strength += 1;
    } else {
        $feedback[] = 'Password should be at least 8 characters long';
    }
    
    if (preg_match('/[a-z]/', $password)) {
        $strength += 1;
    } else {
        $feedback[] = 'Password should contain lowercase letters';
    }
    
    if (preg_match('/[A-Z]/', $password)) {
        $strength += 1;
    } else {
        $feedback[] = 'Password should contain uppercase letters';
    }
    
    if (preg_match('/[0-9]/', $password)) {
        $strength += 1;
    } else {
        $feedback[] = 'Password should contain numbers';
    }
    
    if (preg_match('/[^a-zA-Z0-9]/', $password)) {
        $strength += 1;
    } else {
        $feedback[] = 'Password should contain special characters';
    }
    
    $levels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    return [
        'strength' => $strength,
        'level' => $levels[$strength] ?? 'Very Weak',
        'feedback' => $feedback
    ];
}

/**
 * Secure redirect function
 */
function secureRedirect($url, $allowedDomains = []) {
    // Parse URL
    $parsedUrl = parse_url($url);
    
    // If it's a relative URL, allow it
    if (!isset($parsedUrl['host'])) {
        header('Location: ' . $url);
        exit;
    }
    
    // Check if domain is allowed
    $currentDomain = $_SERVER['HTTP_HOST'];
    $allowedDomains[] = $currentDomain;
    
    if (in_array($parsedUrl['host'], $allowedDomains)) {
        header('Location: ' . $url);
        exit;
    }
    
    // If not allowed, redirect to home
    header('Location: index.php');
    exit;
}

/**
 * Database connection with security enhancements
 */
function getSecureDBConnection() {
    try {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            $options
        );
        
        return $pdo;
    } catch (PDOException $e) {
        logSecurityEvent('database_connection_failed', ['error' => $e->getMessage()], 'ERROR');
        die('Database connection failed');
    }
}
?>
