<?php
// config/database.php - Database configuration

class Database {
    private $host = 'localhost';
    private $db_name = 'ride_sharing_db';
    private $username = 'root'; // Change this to your MySQL username
    private $password = '';     // Change this to your MySQL password
    private $charset = 'utf8mb4';
    public $pdo;

    public function getConnection() {
        $this->pdo = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
        
        return $this->pdo;
    }
}

// Utility functions for the application
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    // At least 8 characters, one uppercase, one lowercase, one number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
}

function getCurrentIP() {
    // Get user IP address
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Session management
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        
        // Regenerate session ID for security
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }
}

function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function logout() {
    startSecureSession();
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Response helper functions
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function successResponse($message, $data = null) {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    jsonResponse($response);
}

function errorResponse($message, $statusCode = 400) {
    jsonResponse(['success' => false, 'message' => $message], $statusCode);
}

// Email configuration (you'll need to configure this based on your email service)
function sendEmail($to, $subject, $body, $isHTML = true) {
    // Using PHPMailer is recommended for production
    // For now, using PHP's mail() function
    $headers = "MIME-Version: 1.0" . "\r\n";
    if ($isHTML) {
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    }
    $headers .= 'From: noreply@ridesharing.com' . "\r\n";
    
    return mail($to, $subject, $body, $headers);
}

// Distance calculation helper (Haversine formula)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
    $lat1Rad = deg2rad($lat1);
    $lon1Rad = deg2rad($lon1);
    $lat2Rad = deg2rad($lat2);
    $lon2Rad = deg2rad($lon2);
    
    $deltaLat = $lat2Rad - $lat1Rad;
    $deltaLon = $lon2Rad - $lon1Rad;
    
    $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
         cos($lat1Rad) * cos($lat2Rad) *
         sin($deltaLon / 2) * sin($deltaLon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c; // Distance in kilometers
}

// Price calculation helper
function calculateRidePrice($distance, $rideType = 'UberX') {
    $basePrices = [
        'UberX' => 80,
        'UberPremium' => 120,
        'UberXL' => 144
    ];
    
    $pricePerKm = [
        'UberX' => 15,
        'UberPremium' => 22.5,
        'UberXL' => 27
    ];
    
    $basePrice = $basePrices[$rideType] ?? $basePrices['UberX'];
    $kmPrice = $pricePerKm[$rideType] ?? $pricePerKm['UberX'];
    
    $totalPrice = $basePrice + ($distance * $kmPrice);
    
    // Add surge pricing logic here if needed
    // For now, adding a small random variation (±20%)
    $variation = mt_rand(-20, 20) / 100;
    $totalPrice *= (1 + $variation);
    
    return max(round($totalPrice), $basePrice); // Ensure minimum fare
}

// Logging helper
function logActivity($message, $level = 'INFO') {
    $logFile = 'logs/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $userId = $_SESSION['user_id'] ?? 'Guest';
    $ip = getCurrentIP();
    
    $logEntry = "[{$timestamp}] [{$level}] [User: {$userId}] [IP: {$ip}] {$message}" . PHP_EOL;
    
    // Create logs directory if it doesn't exist
    if (!file_exists('logs')) {
        mkdir('logs', 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Rate limiting helper
function checkRateLimit($identifier, $maxRequests = 60, $timeWindow = 3600) {
    $cacheFile = "cache/rate_limit_{$identifier}.txt";
    
    // Create cache directory if it doesn't exist
    if (!file_exists('cache')) {
        mkdir('cache', 0755, true);
    }
    
    $currentTime = time();
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        $requests = array_filter($data['requests'], function($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        });
        
        if (count($requests) >= $maxRequests) {
            return false; // Rate limit exceeded
        }
        
        $requests[] = $currentTime;
    } else {
        $requests = [$currentTime];
    }
    
    file_put_contents($cacheFile, json_encode(['requests' => $requests]));
    return true;
}

// CSRF protection
function generateCSRFToken() {
    startSecureSession();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateSecureToken(16);
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    startSecureSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

?>