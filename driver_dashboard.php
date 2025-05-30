<?php
session_start();
require_once 'database.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    header('Location: login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$driverId = $_SESSION['user_id'];
$driverName = $_SESSION['user_name'] ?? 'Driver';

$errors = [];
$success = '';

// 1) Handle driver profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $full_name = $first_name . ' ' . $last_name;
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $vehicle_model = trim($_POST['vehicle_model'] ?? '');
    $vehicle_plate = trim($_POST['vehicle_plate'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');

    if (!$first_name || !$last_name || !$email || !$phone || !$vehicle_type || !$vehicle_model || !$license_number) {
        $errors[] = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        try {
            // Check for duplicate email or phone excluding current user
            $stmt = $conn->prepare("SELECT id FROM users WHERE (email = :email OR phone = :phone) AND id != :id LIMIT 1");
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':phone', $phone);
            $stmt->bindValue(':id', $driverId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $errors[] = "Email or phone already used by another account.";
            } else {
                // Update users table
                $updateFields = "full_name = :full_name, email = :email, phone = :phone";
                $params = [
                    ':full_name' => $full_name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':id' => $driverId
                ];

                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateFields .= ", password = :password";
                    $params[':password'] = $hashedPassword;
                }

                $query = "UPDATE users SET $updateFields WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->execute($params);

                // Update driver_profiles table
                $stmt = $conn->prepare("UPDATE driver_profiles SET vehicle_type = :vehicle_type, vehicle_model = :vehicle_model, vehicle_plate = :vehicle_plate, license_number = :license_number WHERE user_id = :user_id");
                $stmt->bindValue(':vehicle_type', $vehicle_type);
                $stmt->bindValue(':vehicle_model', $vehicle_model);
                $stmt->bindValue(':vehicle_plate', $vehicle_plate);
                $stmt->bindValue(':license_number', $license_number);
                $stmt->bindValue(':user_id', $driverId, PDO::PARAM_INT);
                $stmt->execute();

                $_SESSION['user_name'] = $full_name;
                $driverName = $full_name;
                $success = "Profile updated successfully!";
            }
        } catch (PDOException $e) {
            $errors[] = "DB error: " . $e->getMessage();
        }
    }
}

// 2) Handle online/offline status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability'])) {
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $current_latitude = floatval($_POST['current_latitude'] ?? 0);
    $current_longitude = floatval($_POST['current_longitude'] ?? 0);

    try {
        $stmt = $conn->prepare("UPDATE driver_profiles SET is_available = :is_available, current_latitude = :lat, current_longitude = :lng WHERE user_id = :user_id");
        $stmt->bindValue(':is_available', $is_available, PDO::PARAM_INT);
        $stmt->bindValue(':lat', $current_latitude);
        $stmt->bindValue(':lng', $current_longitude);
        $stmt->bindValue(':user_id', $driverId, PDO::PARAM_INT);
        $stmt->execute();

        $success = $is_available ? "You are now online and available for rides!" : "You are now offline.";
    } catch (PDOException $e) {
        $errors[] = "Error updating availability: " . $e->getMessage();
    }
}

// 3) Handle accept ride
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_ride'])) {
    $ride_id = intval($_POST['ride_id']);
    
    try {
        $stmt = $conn->prepare("UPDATE rides SET driver_id = :driver_id, status = 'confirmed' WHERE id = :ride_id AND status = 'requested'");
        $stmt->bindValue(':driver_id', $driverId, PDO::PARAM_INT);
        $stmt->bindValue(':ride_id', $ride_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount()) {
            $success = "Ride accepted successfully!";
        } else {
            $errors[] = "Failed to accept ride or ride no longer available.";
        }
    } catch (PDOException $e) {
        $errors[] = "Error accepting ride: " . $e->getMessage();
    }
}

// 4) Handle decline ride
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decline_ride'])) {
    $ride_id = intval($_POST['ride_id']);
    
    try {
        $stmt = $conn->prepare("UPDATE rides SET status = 'declined' WHERE id = :ride_id AND status = 'requested'");
        $stmt->bindValue(':ride_id', $ride_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount()) {
            $success = "Ride declined.";
        } else {
            $errors[] = "Failed to decline ride.";
        }
    } catch (PDOException $e) {
        $errors[] = "Error declining ride: " . $e->getMessage();
    }
}

// 5) Handle start ride
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_ride'])) {
    $ride_id = intval($_POST['ride_id']);
    
    try {
        $stmt = $conn->prepare("UPDATE rides SET status = 'in_progress' WHERE id = :ride_id AND driver_id = :driver_id AND status = 'confirmed'");
        $stmt->bindValue(':ride_id', $ride_id, PDO::PARAM_INT);
        $stmt->bindValue(':driver_id', $driverId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount()) {
            $success = "Ride started!";
        } else {
            $errors[] = "Failed to start ride.";
        }
    } catch (PDOException $e) {
        $errors[] = "Error starting ride: " . $e->getMessage();
    }
}

// 6) Handle complete ride
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_ride'])) {
    $ride_id = intval($_POST['ride_id']);
    $actual_price = floatval($_POST['actual_price'] ?? 0);
    
    try {
        $stmt = $conn->prepare("UPDATE rides SET status = 'completed', actual_price = :actual_price WHERE id = :ride_id AND driver_id = :driver_id AND status = 'in_progress'");
        $stmt->bindValue(':ride_id', $ride_id, PDO::PARAM_INT);
        $stmt->bindValue(':actual_price', $actual_price);
        $stmt->bindValue(':driver_id', $driverId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount()) {
            $success = "Ride completed successfully!";
        } else {
            $errors[] = "Failed to complete ride.";
        }
    } catch (PDOException $e) {
        $errors[] = "Error completing ride: " . $e->getMessage();
    }
}

// Fetch driver info for form
$driverStmt = $conn->prepare("
    SELECT u.full_name, u.email, u.phone, dp.vehicle_type, dp.vehicle_model, dp.vehicle_plate, dp.license_number, dp.is_available, dp.rating, dp.current_latitude, dp.current_longitude
    FROM users u
    LEFT JOIN driver_profiles dp ON u.id = dp.user_id
    WHERE u.id = :id LIMIT 1
");
$driverStmt->bindValue(':id', $driverId, PDO::PARAM_INT);
$driverStmt->execute();
$driverInfo = $driverStmt->fetch(PDO::FETCH_ASSOC);

// Fetch available rides (requested rides)
$availableRidesStmt = $conn->prepare("
    SELECT r.*, u.full_name as rider_name, u.phone as rider_phone
    FROM rides r
    INNER JOIN users u ON r.user_id = u.id
    WHERE r.status = 'requested'
    ORDER BY r.created_at ASC
");
$availableRidesStmt->execute();
$availableRides = $availableRidesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch driver's rides
$myRidesStmt = $conn->prepare("
    SELECT r.*, u.full_name as rider_name, u.phone as rider_phone
    FROM rides r
    INNER JOIN users u ON r.user_id = u.id
    WHERE r.driver_id = :driver_id
    ORDER BY r.created_at DESC
");
$myRidesStmt->bindValue(':driver_id', $driverId, PDO::PARAM_INT);
$myRidesStmt->execute();
$myRides = $myRidesStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total earnings per day for plotting
$earningsStmt = $conn->prepare("
    SELECT DATE(created_at) as date, SUM(actual_price) as total_earned
    FROM rides
    WHERE driver_id = :driver_id AND status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
");
$earningsStmt->bindValue(':driver_id', $driverId, PDO::PARAM_INT);
$earningsStmt->execute();
$earnings = $earningsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch reviews and ratings
$reviewsStmt = $conn->prepare("
    SELECT rr.*, r.pickup_location, r.dropoff_location, u.full_name as rider_name
    FROM ride_reviews rr
    INNER JOIN rides r ON rr.ride_id = r.id
    INNER JOIN users u ON r.user_id = u.id
    WHERE r.driver_id = :driver_id
    ORDER BY rr.created_at DESC
");
$reviewsStmt->bindValue(':driver_id', $driverId, PDO::PARAM_INT);
$reviewsStmt->execute();
$reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Driver Dashboard | Ride Sharing</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* Enhanced Driver Dashboard Styling */

:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ffa500 100%);
    --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    --card-hover-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    --glass-bg: rgba(255, 255, 255, 0.1);
    --glass-border: rgba(255, 255, 255, 0.2);
    --text-gradient: linear-gradient(135deg, #667eea, #764ba2);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    background-attachment: fixed;
    color: #333;
    line-height: 1.6;
    overflow-x: hidden;
}

/* Animated Background */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 40% 40%, rgba(120, 200, 255, 0.2) 0%, transparent 50%);
    animation: float 20s ease-in-out infinite;
    z-index: -1;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    33% { transform: translateY(-20px) rotate(1deg); }
    66% { transform: translateY(10px) rotate(-1deg); }
}

/* Navbar Styling */
.navbar {
    background: var(--glass-bg) !important;
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--glass-border);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    padding: 1rem 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    transition: all 0.3s ease;
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.5rem;
    color: white !important;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
}

.navbar-brand:hover {
    transform: scale(1.05);
    text-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

.navbar-brand i {
    margin-right: 0.5rem;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.logout-btn {
    background: var(--danger-gradient) !important;
    color: white !important;
    text-decoration: none !important;
    padding: 10px 20px !important;
    border-radius: 50px !important;
    font-weight: 600 !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3) !important;
    position: relative;
    overflow: hidden;
}

.logout-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s;
}

.logout-btn:hover::before {
    left: 100%;
}

.logout-btn:hover {
    transform: translateY(-2px) scale(1.05) !important;
    box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4) !important;
    color: white !important;
}

.online-status, .offline-status {
    padding: 8px 20px !important;
    border-radius: 50px !important;
    font-size: 0.9em !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
    animation: statusPulse 3s infinite;
    backdrop-filter: blur(10px);
}

.online-status {
    background: var(--success-gradient) !important;
    box-shadow: 0 4px 15px rgba(79, 172, 254, 0.4) !important;
}

.offline-status {
    background: var(--danger-gradient) !important;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4) !important;
}

@keyframes statusPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}

/* Card Styling */
.card {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(20px) !important;
    border: none !important;
    border-radius: 20px !important;
    box-shadow: var(--card-shadow) !important;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
    overflow: hidden !important;
    position: relative;
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}

.card:hover::before {
    transform: scaleX(1);
}

.card:hover {
    transform: translateY(-8px) !important;
    box-shadow: var(--card-hover-shadow) !important;
}

.card-header {
    background: var(--primary-gradient) !important;
    color: white !important;
    border: none !important;
    padding: 1.2rem 1.5rem !important;
    font-weight: 600 !important;
    position: relative;
    overflow: hidden;
}

.card-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transform: rotate(45deg);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}

.card-header h5 {
    margin: 0 !important;
    font-size: 1.1rem !important;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.card-body {
    padding: 1.5rem !important;
}

/* Ride Card Styling */
.ride-card {
    border-left: 6px solid transparent !important;
    border-image: var(--primary-gradient) 1 !important;
    margin-bottom: 20px !important;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.95)) !important;
    position: relative;
    overflow: hidden;
}

.ride-card::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1));
    transform: translateX(100px);
    transition: transform 0.3s ease;
}

.ride-card:hover::after {
    transform: translateX(0);
}

/* Button Styling */
.btn {
    border-radius: 50px !important;
    font-weight: 600 !important;
    padding: 10px 20px !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    position: relative !important;
    overflow: hidden !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}

.btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.3s ease, height 0.3s ease;
}

.btn:hover::before {
    width: 300px;
    height: 300px;
}

.btn-primary {
    background: var(--primary-gradient) !important;
    border: none !important;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3) !important;
}

.btn-primary:hover {
    transform: translateY(-2px) scale(1.02) !important;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4) !important;
}

.btn-success {
    background: var(--success-gradient) !important;
    border: none !important;
    box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3) !important;
}

.btn-success:hover {
    transform: translateY(-2px) scale(1.02) !important;
    box-shadow: 0 8px 25px rgba(79, 172, 254, 0.4) !important;
}

.btn-danger {
    background: var(--danger-gradient) !important;
    border: none !important;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3) !important;
}

.btn-danger:hover {
    transform: translateY(-2px) scale(1.02) !important;
    box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4) !important;
}

.btn-warning {
    background: var(--warning-gradient) !important;
    border: none !important;
    box-shadow: 0 4px 15px rgba(250, 112, 154, 0.3) !important;
    color: white !important;
}

.btn-warning:hover {
    transform: translateY(-2px) scale(1.02) !important;
    box-shadow: 0 8px 25px rgba(250, 112, 154, 0.4) !important;
    color: white !important;
}

/* Form Controls */
.form-control, .form-select {
    border: 2px solid rgba(102, 126, 234, 0.2) !important;
    border-radius: 15px !important;
    padding: 12px 16px !important;
    transition: all 0.3s ease !important;
    background: rgba(255, 255, 255, 0.9) !important;
    backdrop-filter: blur(10px) !important;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea !important;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
    transform: translateY(-2px) !important;
}

.form-label {
    font-weight: 600 !important;
    color: #555 !important;
    margin-bottom: 8px !important;
}

/* Switch Styling */
.form-check-input:checked {
    background-color: #667eea !important;
    border-color: #667eea !important;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3) !important;
}

.form-check-input {
    width: 3em !important;
    height: 1.5em !important;
    border-radius: 50px !important;
    transition: all 0.3s ease !important;
}

/* Stats Cards */
.card .text-center h4 {
    background: var(--text-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

/* Rating Stars */
.rating-stars {
    color: #ffc107 !important;
    filter: drop-shadow(0 2px 4px rgba(255, 193, 7, 0.3));
    animation: twinkle 2s infinite alternate;
}

@keyframes twinkle {
    0% { filter: drop-shadow(0 2px 4px rgba(255, 193, 7, 0.3)); }
    100% { filter: drop-shadow(0 4px 8px rgba(255, 193, 7, 0.6)); }
}

/* Badge Styling */
.badge {
    padding: 8px 16px !important;
    border-radius: 50px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1) !important;
}

.bg-success {
    background: var(--success-gradient) !important;
}

.bg-danger {
    background: var(--danger-gradient) !important;
}

/* Alert Styling */
.alert {
    border: none !important;
    border-radius: 15px !important;
    backdrop-filter: blur(10px) !important;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
    font-weight: 500 !important;
}

.alert-success {
    background: linear-gradient(135deg, rgba(79, 172, 254, 0.1), rgba(0, 242, 254, 0.1)) !important;
    color: #0066cc !important;
    border-left: 4px solid #4facfe !important;
}

.alert-danger {
    background: linear-gradient(135deg, rgba(255, 107, 107, 0.1), rgba(255, 165, 0, 0.1)) !important;
    color: #cc0000 !important;
    border-left: 4px solid #ff6b6b !important;
}

/* Container Styling */
.container {
    animation: fadeInUp 0.8s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Modal Styling */
.modal-content {
    border: none !important;
    border-radius: 20px !important;
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(20px) !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2) !important;
}

.modal-header {
    background: var(--primary-gradient) !important;
    color: white !important;
    border: none !important;
    border-radius: 20px 20px 0 0 !important;
}

.modal-body {
    padding: 2rem !important;
}

.modal-footer {
    border: none !important;
    padding: 1rem 2rem 2rem !important;
}

/* Chart Container */
canvas {
    border-radius: 15px !important;
    background: rgba(255, 255, 255, 0.5) !important;
    backdrop-filter: blur(10px) !important;
}

/* Live Location Map Styling */
.card-body [id^="map-"] {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
    border: 2px solid rgba(102, 126, 234, 0.2) !important;
    border-radius: 15px !important;
    box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.1) !important;
    transition: all 0.3s ease !important;
}

.card-body [id^="map-"]:hover {
    transform: scale(1.02) !important;
    box-shadow: inset 0 4px 20px rgba(0, 0, 0, 0.15) !important;
}

/* Phone Link Styling */
a[href^="tel:"] {
    color: #667eea !important;
    text-decoration: none !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
}

a[href^="tel:"]:hover {
    color: #764ba2 !important;
    transform: scale(1.05) !important;
}

/* Responsive Design */
@media (max-width: 768px) {
    .card {
        margin-bottom: 1rem !important;
    }
    
    .btn {
        padding: 8px 16px !important;
        font-size: 0.9rem !important;
    }
    
    .navbar-brand {
        font-size: 1.2rem !important;
    }
    
    .card-header h5 {
        font-size: 1rem !important;
    }
}

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

::-webkit-scrollbar-thumb {
    background: var(--primary-gradient);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--dark-gradient);
}

/* Text Animations */
h1, h5, h6 {
    animation: slideInLeft 0.6s ease-out;
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Loading Animation for Buttons */
.btn:active {
    transform: scale(0.98) !important;
}

/* Glassmorphism Effect for Important Elements */
.navbar-text {
    background: rgba(255, 255, 255, 0.1) !important;
    backdrop-filter: blur(10px) !important;
    padding: 8px 16px !important;
    border-radius: 50px !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: white !important;
    font-weight: 600 !important;
}

/* Enhanced Shadow Effects */
.card, .btn, .form-control, .alert {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

/* Micro Interactions */
.fa, .fas, .far {
    transition: transform 0.2s ease !important;
}

.btn:hover .fa,
.btn:hover .fas,
.btn:hover .far {
    transform: scale(1.1) !important;
}

/* Enhanced Focus States */
*:focus {
    outline: none !important;
}

.btn:focus {
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.25) !important;
}

/* Improved Typography */
.card-body p, .card-body small {
    line-height: 1.6 !important;
    color: #555 !important;
}

.card-body strong {
    color: #333 !important;
    font-weight: 600 !important;
}

/* Advanced Hover Effects */
.ride-card:hover {
    transform: translateY(-5px) scale(1.02) !important;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15) !important;
}

/* Performance Optimizations */
.card, .btn, .form-control {
    will-change: transform, box-shadow;
}

/* Color Coded Status Indicators */
.card[data-status="completed"] {
    border-left-color: #4facfe !important;
}

.card[data-status="in_progress"] {
    border-left-color: #fa709a !important;
}

.card[data-status="confirmed"] {
    border-left-color: #fee140 !important;
}

.card[data-status="cancelled"] {
    border-left-color: #ff6b6b !important;
}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand" href="#"><i class="fas fa-car"></i> RideShare Driver</a>
    <div class="d-flex align-items-center gap-3">
      <span class="<?= $driverInfo['is_available'] ? 'online-status' : 'offline-status' ?>">
        <i class="fas fa-circle"></i> <?= $driverInfo['is_available'] ? 'Online' : 'Offline' ?>
      </span>
      <span class="navbar-text text-white">Welcome, <?= htmlspecialchars($driverName) ?>!</span>
      <a href="logout.php" class="logout-btn">Logout <i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>
</nav>

<div class="container mt-4">

    <div class="row">
        <div class="col-md-8">
            <h1>Driver Dashboard</h1>
            
            <!-- Online/Offline Toggle -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-toggle-on"></i> Availability Status</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="availabilityForm">
                        <input type="hidden" name="toggle_availability" value="1" />
                        <input type="hidden" name="current_latitude" id="latitude" value="<?= $driverInfo['current_latitude'] ?? '' ?>" />
                        <input type="hidden" name="current_longitude" id="longitude" value="<?= $driverInfo['current_longitude'] ?? '' ?>" />
                        
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_available" id="availabilityToggle" 
                                <?= $driverInfo['is_available'] ? 'checked' : '' ?> onchange="updateAvailability()">
                            <label class="form-check-label" for="availabilityToggle">
                                <strong><?= $driverInfo['is_available'] ? 'You are Online' : 'You are Offline' ?></strong>
                            </label>
                        </div>
                        <small class="text-muted">Toggle to go online/offline for receiving ride requests</small>
                    </form>
                </div>
            </div>

            <!-- Available Rides -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Available Ride Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($availableRides)): ?>
                        <p class="text-muted">No ride requests available at the moment.</p>
                    <?php else: ?>
                        <?php foreach ($availableRides as $ride): ?>
                            <div class="card ride-card p-3 mb-3">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6><i class="fas fa-map-marker-alt"></i> Ride #<?= $ride['id'] ?></h6>
                                        <p><strong>Rider:</strong> <?= htmlspecialchars($ride['rider_name']) ?> | 
                                           <strong>Phone:</strong> <?= htmlspecialchars($ride['rider_phone']) ?></p>
                                        <p><strong>Pickup:</strong> <?= htmlspecialchars($ride['pickup_location']) ?></p>
                                        <p><strong>Dropoff:</strong> <?= htmlspecialchars($ride['dropoff_location']) ?></p>
                                        <p><strong>Vehicle Type:</strong> <?= htmlspecialchars($ride['ride_type']) ?> | 
                                           <strong>Estimated Price:</strong> ৳<?= number_format($ride['estimated_price'], 2) ?></p>
                                        <small class="text-muted">Requested: <?= date('M d, Y H:i', strtotime($ride['created_at'])) ?></small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="ride_id" value="<?= $ride['id'] ?>" />
                                            <button type="submit" name="accept_ride" class="btn btn-success btn-sm mb-2">
                                                <i class="fas fa-check"></i> Accept
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="ride_id" value="<?= $ride['id'] ?>" />
                                            <button type="submit" name="decline_ride" class="btn btn-danger btn-sm mb-2">
                                                <i class="fas fa-times"></i> Decline
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- My Rides -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-car"></i> My Rides</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($myRides)): ?>
                        <p class="text-muted">No rides assigned yet.</p>
                    <?php else: ?>
                        <?php foreach ($myRides as $ride): ?>
                            <div class="card ride-card p-3 mb-3">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6>Ride #<?= $ride['id'] ?> - <?= ucfirst($ride['status']) ?></h6>
                                        <p><strong>Rider:</strong> <?= htmlspecialchars($ride['rider_name']) ?> | 
                                           <strong>Phone:</strong> 
                                           <?php if (in_array($ride['status'], ['confirmed', 'in_progress'])): ?>
                                               <a href="tel:<?= htmlspecialchars($ride['rider_phone']) ?>" class="text-primary">
                                                   <?= htmlspecialchars($ride['rider_phone']) ?>
                                               </a>
                                           <?php else: ?>
                                               <?= htmlspecialchars($ride['rider_phone']) ?>
                                           <?php endif; ?>
                                        </p>
                                        <p><strong>Pickup:</strong> <?= htmlspecialchars($ride['pickup_location']) ?></p>
                                        <p><strong>Dropoff:</strong> <?= htmlspecialchars($ride['dropoff_location']) ?></p>
                                        <p><strong>Vehicle Type:</strong> <?= htmlspecialchars($ride['ride_type']) ?></p>
                                        <?php if ($ride['actual_price']): ?>
                                            <p><strong>Final Price:</strong> ৳<?= number_format($ride['actual_price'], 2) ?></p>
                                        <?php else: ?>
                                            <p><strong>Estimated Price:</strong> ৳<?= number_format($ride['estimated_price'], 2) ?></p>
                                        <?php endif; ?>
                                        <small class="text-muted">Created: <?= date('M d, Y H:i', strtotime($ride['created_at'])) ?></small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <?php if ($ride['status'] === 'confirmed'): ?>
                                            <form method="POST" class="mb-2">
                                                <input type="hidden" name="ride_id" value="<?= $ride['id'] ?>" />
                                                <button type="submit" name="start_ride" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-play"></i> Start Ride
                                                </button>
                                            </form>
                                        <?php elseif ($ride['status'] === 'in_progress'): ?>
                                            <form method="POST" class="mb-2">
                                                <input type="hidden" name="ride_id" value="<?= $ride['id'] ?>" />
                                                <div class="mb-2">
                                                    <input type="number" name="actual_price" step="0.01" 
                                                           placeholder="Final fare" class="form-control form-control-sm" 
                                                           value="<?= $ride['estimated_price'] ?>" required />
                                                </div>
                                                <button type="submit" name="complete_ride" class="btn btn-success btn-sm">
                                                    <i class="fas fa-flag-checkered"></i> Complete Ride
                                                </button>
                                            </form>
                                        <?php elseif ($ride['status'] === 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($ride['status'] === 'cancelled'): ?>
                                            <span class="badge bg-danger">Cancelled</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="col-md-4">
            <!-- Driver Stats -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Driver Stats</h5>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <h4 class="text-primary"><?= number_format($driverInfo['rating'], 1) ?>/5.0</h4>
                        <div class="rating-stars mb-2">
                            <?php 
                            $rating = $driverInfo['rating'];
                            for ($i = 1; $i <= 5; $i++): 
                                if ($i <= $rating): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($i - 0.5 <= $rating): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif;
                            endfor; ?>
                        </div>
                        <small class="text-muted">Average Rating</small>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <h5><?= count(array_filter($myRides, fn($r) => $r['status'] === 'completed')) ?></h5>
                            <small>Completed Rides</small>
                        </div>
                        <div class="col-6">
                            <h5>৳<?= number_format(array_sum(array_map(fn($r) => $r['actual_price'] ?? 0, $myRides)), 2) ?></h5>
                            <small>Total Earnings</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Update Profile -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-user-edit"></i> Update Profile</h5>
                </div>
                <div class="card-body">
                    <?php if ($errors): ?>
                        <div class="alert alert-danger alert-sm">
                            <ul class="mb-0"><?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?></ul>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-sm"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1" />
                        
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input name="first_name" type="text" class="form-control form-control-sm" required
                                value="<?= htmlspecialchars(explode(' ', $driverInfo['full_name'])[0] ?? '') ?>" />
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input name="last_name" type="text" class="form-control form-control-sm" required
                                value="<?= htmlspecialchars(explode(' ', $driverInfo['full_name'])[1] ?? '') ?>" />
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input name="email" type="email" class="form-control form-control-sm" required
                                value="<?= htmlspecialchars($driverInfo['email']) ?>" />
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input name="phone" type="text" class="form-control form-control-sm" required
                                value="<?= htmlspecialchars($driverInfo['phone']) ?>" />
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Vehicle Type</label>
                            <select name="vehicle_type" class="form-control form-control-sm" required>
                                <option value="">-- Select Vehicle --</option>
                                <option value="Car" <?= $driverInfo['vehicle_type'] === 'Car' ? 'selected' : '' ?>>Car</option>
                                <option value="Truck" <?= $driverInfo['vehicle_type'] === 'Truck' ? 'selected' : '' ?>>Truck</option>
                                <option value="Bike" <?= $driverInfo['vehicle_type'] === 'Bike' ? 'selected' : '' ?>>Bike</option>
                                <option value="CNG" <?= $driverInfo['vehicle_type'] === 'CNG' ? 'selected' : '' ?>>CNG</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Vehicle Model</label>
                            <input name="vehicle_model" type="text" class="form-control form-control-sm" required
                                value="<?= htmlspecialchars($driverInfo['vehicle_model'] ?? '') ?>" />
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Vehicle Plate</label>
                            <input name="vehicle_plate" type="text" class="form-control form-control-sm"
                                value="<?= htmlspecialchars($driverInfo['vehicle_plate'] ?? '') ?>" />
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">License Number</label>
                            <input name="license_number" type="text" class="form-control form-control-sm" required
                                value="<?= htmlspecialchars($driverInfo['license_number'] ?? '') ?>" />
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input name="password" type="password" class="form-control form-control-sm" />
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-sm w-100">Update Profile</button>
                    </form>
                </div>
            </div>

            <!-- Reviews -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-star"></i> Recent Reviews</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reviews)): ?>
                        <p class="text-muted">No reviews yet.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($reviews, 0, 5) as $review): ?>
                            <div class="mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($review['rider_name']) ?></strong>
                                    <div class="rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="<?= $i <= $review['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p class="mb-1 small"><?= htmlspecialchars($review['comments']) ?></p>
                               <small class="text-muted"><?= date('M d, Y', strtotime($review['created_at'])) ?></small>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($reviews) > 5): ?>
                            <div class="text-center">
                                <button class="btn btn-outline-primary btn-sm" onclick="toggleAllReviews()">
                                    <span id="reviewToggleText">Show All Reviews</span>
                                </button>
                            </div>
                            <div id="allReviews" style="display: none;">
                                <?php foreach (array_slice($reviews, 5) as $review): ?>
                                    <div class="mb-3 pb-3 border-bottom">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($review['rider_name']) ?></strong>
                                            <div class="rating-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="<?= $i <= $review['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <p class="mb-1 small"><?= htmlspecialchars($review['comments']) ?></p>
                                        <small class="text-muted"><?= date('M d, Y', strtotime($review['created_at'])) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Live Location Tracking for Active Rides -->
            <?php 
            $activeRides = array_filter($myRides, function($ride) {
                return in_array($ride['status'], ['confirmed', 'in_progress']);
            });
            ?>
            
            <?php if (!empty($activeRides)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-map-marked-alt"></i> Live Tracking</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($activeRides as $ride): ?>
                        <div class="mb-3 p-3 border rounded">
                            <h6>Ride #<?= $ride['id'] ?> - <?= ucfirst($ride['status']) ?></h6>
                            <p><strong>Rider:</strong> <?= htmlspecialchars($ride['rider_name']) ?></p>
                            <p><strong>Contact:</strong> 
                                <a href="tel:<?= htmlspecialchars($ride['rider_phone']) ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-phone"></i> Call Rider
                                </a>
                            </p>
                            <div id="map-<?= $ride['id'] ?>" style="height: 200px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                <div class="text-center">
                                    <i class="fas fa-map-marker-alt fa-2x text-primary mb-2"></i>
                                    <p class="mb-0"><strong>Live Location Tracking</strong></p>
                                    <small class="text-muted">GPS coordinates will be updated here</small>
                                    <br>
                                    <button class="btn btn-primary btn-sm mt-2" onclick="startLocationTracking(<?= $ride['id'] ?>)">
                                        <i class="fas fa-crosshairs"></i> Start Tracking
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Earnings Chart -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line"></i> Daily Earnings Report</h5>
                </div>
                <div class="card-body">
                    <canvas id="earningsChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Location Update Modal -->
<div class="modal fade" id="locationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Location Access Required</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>To go online and receive ride requests, we need access to your current location.</p>
                <p>Please allow location access when prompted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="requestLocation()">Allow Location</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Global variables
let locationWatchId = null;
let trackingIntervals = {};

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    initializeEarningsChart();
    
    // Auto-refresh available rides every 30 seconds
    setInterval(function() {
        if (document.getElementById('availabilityToggle').checked) {
            refreshAvailableRides();
        }
    }, 30000);
    
    // Update location every 10 seconds if online
    setInterval(function() {
        if (document.getElementById('availabilityToggle').checked) {
            updateCurrentLocation();
        }
    }, 10000);
});

// Availability Toggle
function updateAvailability() {
    const toggle = document.getElementById('availabilityToggle');
    
    if (toggle.checked) {
        // Going online - need location
        if (navigator.geolocation) {
            showLocationModal();
        } else {
            alert('Geolocation is not supported by this browser.');
            toggle.checked = false;
        }
    } else {
        // Going offline
        if (locationWatchId) {
            navigator.geolocation.clearWatch(locationWatchId);
            locationWatchId = null;
        }
        document.getElementById('availabilityForm').submit();
    }
}

function showLocationModal() {
    const modal = new bootstrap.Modal(document.getElementById('locationModal'));
    modal.show();
}

function requestLocation() {
    navigator.geolocation.getCurrentPosition(
        function(position) {
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            
            // Start watching position
            locationWatchId = navigator.geolocation.watchPosition(
                function(pos) {
                    document.getElementById('latitude').value = pos.coords.latitude;
                    document.getElementById('longitude').value = pos.coords.longitude;
                },
                function(error) {
                    console.error('Location error:', error);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
            );
            
            // Close modal and submit form
            bootstrap.Modal.getInstance(document.getElementById('locationModal')).hide();
            document.getElementById('availabilityForm').submit();
        },
        function(error) {
            alert('Unable to get your location. Please try again.');
            document.getElementById('availabilityToggle').checked = false;
            bootstrap.Modal.getInstance(document.getElementById('locationModal')).hide();
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

function updateCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                // Send location update to server via AJAX
                fetch('update_location.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `latitude=${position.coords.latitude}&longitude=${position.coords.longitude}`
                });
            },
            function(error) {
                console.error('Location update error:', error);
            }
        );
    }
}

// Live Location Tracking for Active Rides
function startLocationTracking(rideId) {
    const mapDiv = document.getElementById(`map-${rideId}`);
    
    if (navigator.geolocation) {
        // Clear existing interval if any
        if (trackingIntervals[rideId]) {
            clearInterval(trackingIntervals[rideId]);
        }
        
        // Start tracking
        trackingIntervals[rideId] = setInterval(function() {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude.toFixed(6);
                    const lng = position.coords.longitude.toFixed(6);
                    const timestamp = new Date().toLocaleTimeString();
                    
                    mapDiv.innerHTML = `
                        <div class="text-center p-3">
                            <i class="fas fa-map-marker-alt fa-2x text-success mb-2"></i>
                            <h6 class="text-success">Live Tracking Active</h6>
                            <p class="mb-1"><strong>Current Location:</strong></p>
                            <p class="mb-1">Lat: ${lat}, Lng: ${lng}</p>
                            <small class="text-muted">Last updated: ${timestamp}</small>
                            <br>
                            <button class="btn btn-danger btn-sm mt-2" onclick="stopLocationTracking(${rideId})">
                                <i class="fas fa-stop"></i> Stop Tracking
                            </button>
                        </div>
                    `;
                    
                    // Send location to server for rider to see
                    fetch('update_driver_location.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `ride_id=${rideId}&latitude=${lat}&longitude=${lng}`
                    });
                },
                function(error) {
                    console.error('Tracking error:', error);
                    mapDiv.innerHTML = `
                        <div class="text-center p-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                            <p class="text-warning">Location tracking error</p>
                            <button class="btn btn-primary btn-sm" onclick="startLocationTracking(${rideId})">
                                <i class="fas fa-redo"></i> Retry
                            </button>
                        </div>
                    `;
                }
            );
        }, 5000); // Update every 5 seconds
        
    } else {
        alert('Geolocation is not supported by this browser.');
    }
}

function stopLocationTracking(rideId) {
    if (trackingIntervals[rideId]) {
        clearInterval(trackingIntervals[rideId]);
        delete trackingIntervals[rideId];
    }
    
    const mapDiv = document.getElementById(`map-${rideId}`);
    mapDiv.innerHTML = `
        <div class="text-center">
            <i class="fas fa-map-marker-alt fa-2x text-secondary mb-2"></i>
            <p class="mb-0"><strong>Tracking Stopped</strong></p>
            <button class="btn btn-primary btn-sm mt-2" onclick="startLocationTracking(${rideId})">
                <i class="fas fa-play"></i> Start Tracking
            </button>
        </div>
    `;
}

// Refresh available rides
function refreshAvailableRides() {
    fetch('get_available_rides.php')
        .then(response => response.json())
        .then(data => {
            // Update available rides section
            // This would require additional PHP endpoint
        })
        .catch(error => console.error('Error refreshing rides:', error));
}

// Reviews toggle
function toggleAllReviews() {
    const allReviews = document.getElementById('allReviews');
    const toggleText = document.getElementById('reviewToggleText');
    
    if (allReviews.style.display === 'none') {
        allReviews.style.display = 'block';
        toggleText.textContent = 'Show Less Reviews';
    } else {
        allReviews.style.display = 'none';
        toggleText.textContent = 'Show All Reviews';
    }
}

// Initialize Earnings Chart
function initializeEarningsChart() {
    const ctx = document.getElementById('earningsChart').getContext('2d');
    
    // Prepare data from PHP
    const earningsData = <?= json_encode($earnings) ?>;
    
    const labels = earningsData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    
    const data = earningsData.map(item => parseFloat(item.total_earned));
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Daily Earnings (৳)',
                data: data,
                borderColor: '#1ca3d8',
                backgroundColor: 'rgba(28, 163, 216, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#1ca3d8',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#1ca3d8',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            return 'Earned: ৳' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '৳' + value;
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
}

// Auto-logout after 1 hour of inactivity
let logoutTimer;
function resetLogoutTimer() {
    clearTimeout(logoutTimer);
    logoutTimer = setTimeout(function() {
        if (confirm('You have been inactive for a while. Do you want to continue your session?')) {
            resetLogoutTimer();
        } else {
            window.location.href = 'logout.php';
        }
    }, 3600000); // 1 hour
}

// Reset timer on user activity
document.addEventListener('mousemove', resetLogoutTimer);
document.addEventListener('keypress', resetLogoutTimer);
document.addEventListener('click', resetLogoutTimer);
document.addEventListener('scroll', resetLogoutTimer);

// Initialize logout timer
resetLogoutTimer();

// Notification sound for new rides
function playNotificationSound() {
    // Create audio context for notification
    if ('AudioContext' in window || 'webkitAudioContext' in window) {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.3);
    }
}

// Confirm before accepting/declining rides
document.querySelectorAll('button[name="accept_ride"]').forEach(button => {
    button.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to accept this ride?')) {
            e.preventDefault();
        }
    });
});

document.querySelectorAll('button[name="decline_ride"]').forEach(button => {
    button.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to decline this ride?')) {
            e.preventDefault();
        }
    });
});

// Form validation
document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
    const email = document.querySelector('input[name="email"]').value;
    const phone = document.querySelector('input[name="phone"]').value;
    
    if (!email.includes('@')) {
        alert('Please enter a valid email address.');
        e.preventDefault();
        return;
    }
    
    if (phone.length < 10) {
        alert('Please enter a valid phone number.');
        e.preventDefault();
        return;
    }
});

</script>

</body>
</html>