<?php
require_once 'database.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $full_name = $first_name . ' ' . $last_name;
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    
    // Driver-specific fields
    $license_number = trim($_POST['license_number'] ?? '');
    $nid_number = trim($_POST['nid_number'] ?? '');
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $vehicle_model = trim($_POST['vehicle_model'] ?? '');
    $seating_capacity = intval($_POST['seating_capacity'] ?? 0);
    $vehicle_plate = trim($_POST['vehicle_plate'] ?? '');
    
    // Admin verification code
    $admin_code = trim($_POST['admin_code'] ?? '');

    // Basic validation
    if (!$first_name || !$last_name || !$email || !$phone || !$password || !$user_type) {
        $errors[] = "All basic fields are required.";
    }

    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    $allowed_user_types = ['rider', 'driver', 'admin'];
    if (!in_array($user_type, $allowed_user_types)) {
        $errors[] = "Invalid user type selected.";
    }

    // Driver-specific validation
    if ($user_type === 'driver') {
        if (!$license_number || !$nid_number || !$vehicle_type || !$vehicle_model || !$seating_capacity || !$vehicle_plate) {
            $errors[] = "All driver fields are required.";
        }
        if ($license_number && strlen($license_number) < 5) {
            $errors[] = "License number must be at least 5 characters long.";
        }
        if ($nid_number && strlen($nid_number) < 10) {
            $errors[] = "NID number must be at least 10 characters long.";
        }
        if ($seating_capacity <= 0) {
            $errors[] = "Seating capacity must be a positive number.";
        }
    }

    // Admin code validation
    if ($user_type === 'admin') {
        if ($admin_code !== '1212') {
            $errors[] = "Invalid admin verification code.";
        }
    }

    if (empty($errors)) {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->bindValue(':email', $email);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email is already registered.";
            }

            // Check if phone already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE phone = :phone LIMIT 1");
            $stmt->bindValue(':phone', $phone);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $errors[] = "Phone number is already registered.";
            }

            // For drivers, check if license number already exists
            if ($user_type === 'driver' && $license_number) {
                $stmt = $conn->prepare("SELECT id FROM driver_profiles WHERE license_number = :license_number LIMIT 1");
                $stmt->bindValue(':license_number', $license_number);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    $errors[] = "License number is already registered.";
                }
            }

            // For drivers, check if NID number already exists
            if ($user_type === 'driver' && $nid_number) {
                $stmt = $conn->prepare("SELECT id FROM driver_profiles WHERE nid_number = :nid_number LIMIT 1");
                $stmt->bindValue(':nid_number', $nid_number);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    $errors[] = "NID number is already registered.";
                }
            }

            // Only insert if no duplicates
            if (empty($errors)) {
                // Start transaction
                $conn->beginTransaction();

                try {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $status = 'active';
                    $email_verified = 0;
                    $created_at = date('Y-m-d H:i:s');
                    $updated_at = $created_at;

                    // Insert user
                    $query = "INSERT INTO users (
                                full_name, email, password, phone, profile_image,
                                user_type, status, email_verified, created_at, updated_at
                              ) VALUES (
                                :full_name, :email, :password, :phone, NULL,
                                :user_type, :status, :email_verified, :created_at, :updated_at
                              )";

                    $stmt = $conn->prepare($query);
                    $stmt->bindValue(':full_name', $full_name);
                    $stmt->bindValue(':email', $email);
                    $stmt->bindValue(':password', $hashedPassword);
                    $stmt->bindValue(':phone', $phone);
                    $stmt->bindValue(':user_type', $user_type);
                    $stmt->bindValue(':status', $status);
                    $stmt->bindValue(':email_verified', $email_verified, PDO::PARAM_INT);
                    $stmt->bindValue(':created_at', $created_at);
                    $stmt->bindValue(':updated_at', $updated_at);

                    if ($stmt->execute()) {
                        $user_id = $conn->lastInsertId();

                        // If user is a driver, create driver profile with vehicle info
                        if ($user_type === 'driver') {
                            $driver_query = "INSERT INTO driver_profiles (
                                                user_id, license_number, nid_number, vehicle_type, vehicle_model, seating_capacity, vehicle_plate, rating, 
                                                total_rides, is_available, created_at
                                            ) VALUES (
                                                :user_id, :license_number, :nid_number, :vehicle_type, :vehicle_model, :seating_capacity, :vehicle_plate, 5.00,
                                                0, 1, :created_at
                                            )";

                            $driver_stmt = $conn->prepare($driver_query);
                            $driver_stmt->bindValue(':user_id', $user_id);
                            $driver_stmt->bindValue(':license_number', $license_number);
                            $driver_stmt->bindValue(':nid_number', $nid_number);
                            $driver_stmt->bindValue(':vehicle_type', $vehicle_type);
                            $driver_stmt->bindValue(':vehicle_model', $vehicle_model);
                            $driver_stmt->bindValue(':seating_capacity', $seating_capacity, PDO::PARAM_INT);
                            $driver_stmt->bindValue(':vehicle_plate', $vehicle_plate);
                            $driver_stmt->bindValue(':created_at', $created_at);

                            if (!$driver_stmt->execute()) {
                                throw new Exception("Failed to create driver profile.");
                            }
                        }

                        // Commit transaction
                        $conn->commit();
                        $success = "Registration successful! <a href='login.php' style='color:#00a1ff;'>Click here to log in</a>";
                    } else {
                        throw new Exception("Failed to register user.");
                    }
                } catch (Exception $e) {
                    // Rollback on error
                    $conn->rollback();
                    $errors[] = $e->getMessage();
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | Ride Sharing</title>

<!-- Font Awesome for icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* Reset and Base Styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(135deg, rgba(0,0,0,0.7), rgba(28,163,216,0.8)), 
              url('https://t3.ftcdn.net/jpg/02/97/24/44/360_F_297244496_ydzNxqv71AN7fCAp9x1AEH3FWFPrSnPH.jpg');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
  min-height: 100vh;
  color: #333;
  overflow-x: hidden;
}

/* Animated Background Elements */
.bg-animation {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 1;
}

.floating-shape {
  position: absolute;
  background: rgba(255,255,255,0.1);
  border-radius: 50%;
  animation: float 6s ease-in-out infinite;
}

.shape-1 { width: 80px; height: 80px; top: 20%; left: 10%; animation-delay: 0s; }
.shape-2 { width: 120px; height: 120px; top: 60%; right: 15%; animation-delay: 2s; }
.shape-3 { width: 60px; height: 60px; bottom: 30%; left: 20%; animation-delay: 4s; }
.shape-4 { width: 100px; height: 100px; top: 40%; right: 30%; animation-delay: 1s; }

@keyframes float {
  0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.3; }
  50% { transform: translateY(-20px) rotate(180deg); opacity: 0.8; }
}

/* Header */
.header-bar {
  background: rgba(255,255,255,0.95);
  backdrop-filter: blur(10px);
  padding: 16px 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  position: relative;
  z-index: 100;
}

.logo {
  color: #1ca3d8;
  font-weight: 800;
  font-size: 24px;
  text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
}

.header-links {
  display: flex;
  gap: 20px;
  align-items: center;
}

.header-links a {
  color: #333;
  text-decoration: none;
  font-weight: 500;
  transition: all 0.3s ease;
  padding: 8px 16px;
  border-radius: 25px;
}

.header-links a:hover {
  color: #1ca3d8;
  transform: translateY(-2px);
}

.header-links a.signup {
  background: linear-gradient(135deg, #1ca3d8, #0f679f);
  color: white;
  box-shadow: 0 4px 15px rgba(28,163,216,0.3);
}

.header-links a.signup:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(28,163,216,0.4);
}

/* Main Container */
.main-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 100px);
  padding: 40px 20px;
  position: relative;
  z-index: 50;
}

.registration-card {
  background: rgba(255,255,255,0.95);
  backdrop-filter: blur(20px);
  border-radius: 25px;
  padding: 40px;
  box-shadow: 0 25px 50px rgba(0,0,0,0.2);
  border: 1px solid rgba(255,255,255,0.2);
  max-width: 600px;
  width: 100%;
  position: relative;
  overflow: hidden;
  animation: slideUp 0.8s ease-out;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(50px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.registration-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 5px;
  background: linear-gradient(90deg, #1ca3d8, #0f679f, #1ca3d8);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

@keyframes shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

/* Form Title */
.form-title {
  text-align: center;
  margin-bottom: 30px;
}

.form-title h1 {
  font-size: 2.5rem;
  font-weight: 800;
  background: linear-gradient(135deg, #1ca3d8, #0f679f);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 10px;
}

.form-title p {
  color: #666;
  font-size: 16px;
  font-weight: 400;
}

/* Messages */
.message {
  margin-bottom: 25px;
  padding: 15px 20px;
  border-radius: 12px;
  font-weight: 500;
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

.message.success {
  background: linear-gradient(135deg, #d4edda, #c3e6cb);
  color: #155724;
  border-left: 4px solid #28a745;
}

.message.error {
  background: linear-gradient(135deg, #f8d7da, #f1b0b7);
  color: #721c24;
  border-left: 4px solid #dc3545;
}

.message ul {
  margin: 0;
  padding-left: 20px;
}

/* Form Styles */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
}

.form-group {
  position: relative;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  display: block;
  font-weight: 600;
  margin-bottom: 8px;
  color: #333;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 15px 20px;
  border: 2px solid rgba(28,163,216,0.2);
  border-radius: 12px;
  font-size: 16px;
  font-family: 'Poppins', sans-serif;
  background: rgba(255,255,255,0.9);
  transition: all 0.3s ease;
  position: relative;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #1ca3d8;
  box-shadow: 0 0 0 4px rgba(28,163,216,0.1);
  background: white;
  transform: translateY(-2px);
}

.form-group input::placeholder {
  color: #999;
  font-weight: 400;
}

/* Icon inputs */
.input-with-icon {
  position: relative;
}

.input-with-icon input {
  padding-left: 50px;
}

.input-with-icon i {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  color: #1ca3d8;
  font-size: 16px;
}

/* Conditional Fields */
.conditional-section {
  margin-top: 25px;
  padding: 25px;
  background: linear-gradient(135deg, rgba(28,163,216,0.05), rgba(15,103,159,0.05));
  border-radius: 15px;
  border: 2px dashed rgba(28,163,216,0.3);
  display: none;
  animation: expandIn 0.5s ease-out;
}

@keyframes expandIn {
  from {
    opacity: 0;
    max-height: 0;
    padding: 0 25px;
  }
  to {
    opacity: 1;
    max-height: 1000px;
    padding: 25px;
  }
}

.conditional-section.show {
  display: block;
}

.section-title {
  display: flex;
  align-items: center;
  margin-bottom: 20px;
  font-weight: 700;
  color: #1ca3d8;
  font-size: 18px;
}

.section-title i {
  margin-right: 12px;
  font-size: 20px;
  background: linear-gradient(135deg, #1ca3d8, #0f679f);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Submit Button */
.submit-btn {
  width: 100%;
  padding: 18px;
  background: linear-gradient(135deg, #1ca3d8, #0f679f);
  color: white;
  border: none;
  border-radius: 15px;
  font-size: 18px;
  font-weight: 700;
  font-family: 'Poppins', sans-serif;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-top: 25px;
}

.submit-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(28,163,216,0.4);
}

.submit-btn:active {
  transform: translateY(-1px);
}

.submit-btn::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  transition: left 0.5s;
}

.submit-btn:hover::before {
  left: 100%;
}

/* Footer */
.footer {
  background: rgba(0,0,0,0.9);
  backdrop-filter: blur(10px);
  color: white;
  padding: 60px 0 30px;
  margin-top: 50px;
  position: relative;
  z-index: 100;
}

.footer-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

.footer-content {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 40px;
  margin-bottom: 40px;
}

.footer-section h3 {
  margin-bottom: 20px;
  font-size: 20px;
  font-weight: 700;
  color: #1ca3d8;
}

.footer-section ul {
  list-style: none;
}

.footer-section li {
  margin-bottom: 12px;
}

.footer-section a {
  color: #ccc;
  text-decoration: none;
  transition: all 0.3s ease;
  font-weight: 400;
}

.footer-section a:hover {
  color: #1ca3d8;
  transform: translateX(5px);
}

.footer-bottom {
  border-top: 1px solid #333;
  padding-top: 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
}

.social-links {
  display: flex;
  gap: 15px;
}

.social-links a {
  width: 45px;
  height: 45px;
  background: linear-gradient(135deg, #1ca3d8, #0f679f);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.3s ease;
  font-size: 18px;
}

.social-links a:hover {
  transform: translateY(-3px) scale(1.1);
  box-shadow: 0 5px 15px rgba(28,163,216,0.4);
}

.app-links {
  display: flex;
  gap: 15px;
}

.app-link {
  background: white;
  color: #333;
  padding: 12px 20px;
  border-radius: 10px;
  text-align: center;
  transition: all 0.3s ease;
  text-decoration: none;
  min-width: 140px;
}

.app-link:hover {
  transform: translateY(-3px);
  box-shadow: 0 5px 15px rgba(255,255,255,0.2);
}

.app-link i {
  font-size: 24px;
  margin-bottom: 5px;
  display: block;
}

.app-link .store-text {
  font-size: 12px;
  line-height: 1.2;
}

/* Responsive Design */
@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
    gap: 15px;
  }
  
  .registration-card {
    margin: 20px;
    padding: 30px 25px;
  }
  
  .form-title h1 {
    font-size: 2rem;
  }
  
  .footer-bottom {
    flex-direction: column;
    text-align: center;
    gap: 20px;
  }
  
  .app-links {
    justify-content: center;
    flex-wrap: wrap;
  }
  
  .bg-animation {
    display: none;
  }
}

@media (max-width: 480px) {
  .header-bar {
    padding: 12px 20px;
  }
  
  .logo {
    font-size: 20px;
  }
  
  .header-links {
    gap: 10px;
  }
  
  .header-links a {
    padding: 6px 12px;
    font-size: 14px;
  }
}

/* Loading Animation */
.loading {
  display: inline-block;
  width: 20px;
  height: 20px;
  border: 3px solid rgba(255,255,255,.3);
  border-radius: 50%;
  border-top-color: #fff;
  animation: spin 1s ease-in-out infinite;
  margin-right: 10px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>

<script>
// Form functionality
function toggleUserTypeFields() {
    const userType = document.querySelector('select[name="user_type"]').value;
    const driverFields = document.querySelector('.driver-fields');
    const adminFields = document.querySelector('.admin-fields');
    
    // Hide all conditional fields first
    if (driverFields) driverFields.classList.remove('show');
    if (adminFields) adminFields.classList.remove('show');
    
    // Show relevant fields based on user type
    if (userType === 'driver' && driverFields) {
        driverFields.classList.add('show');
    } else if (userType === 'admin' && adminFields) {
        adminFields.classList.add('show');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleUserTypeFields();
    
    // Add floating animation to background shapes
    createFloatingShapes();
    
    // Add form validation animations
    addFormAnimations();
});

function createFloatingShapes() {
    const bgAnimation = document.querySelector('.bg-animation');
    if (!bgAnimation) return;
    
    for (let i = 1; i <= 4; i++) {
        const shape = document.createElement('div');
        shape.className = `floating-shape shape-${i}`;
        bgAnimation.appendChild(shape);
    }
}

function addFormAnimations() {
    const inputs = document.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });
}

// Form submission with loading state
function handleSubmit(event) {
    const submitBtn = event.target.querySelector('.submit-btn');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<span class="loading"></span>Creating Account...';
    submitBtn.disabled = true;
    
    // Reset after 3 seconds (you can remove this in actual implementation)
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 3000);
}
</script>
</head>
<body>

<!-- Animated Background -->
<div class="bg-animation"></div>

<!-- Header -->
<div class="header-bar">
    <div class="logo">🚗 Ride Sharing</div>
    <div class="header-links">
        <a href="index.php">Home</a>
        <a href="login.php">Log in</a>
        <a href="register.php" class="signup">Sign up</a>
    </div>
</div>

<!-- Main Container -->
<div class="main-container">
    <div class="registration-card">
        <div class="form-title">
            <h1>Create Account</h1>
            <p>Join our community and start your journey today</p>
        </div>
        
        <!-- Messages would go here in PHP -->
        <div class="message success" style="display: none;">
            Registration successful! <a href="login.php" style="color:#155724; font-weight: 600;">Click here to log in</a>
        </div>
        
        <div class="message error" style="display: none;">
            <ul>
                <li>Please fix the errors below</li>
            </ul>
        </div>

        <!-- Registration Form -->
        <form method="POST" action="" onsubmit="handleSubmit(event)">
            <div class="form-grid">
                <div class="form-group">
                    <label>First Name</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="first_name" required placeholder="Enter first name">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Last Name</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="last_name" required placeholder="Enter last name">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" required placeholder="Enter email address">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <div class="input-with-icon">
                        <i class="fas fa-phone"></i>
                        <input type="tel" name="phone" required placeholder="Enter phone number">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" required placeholder="Create password">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>User Type</label>
                    <div class="input-with-icon">
                        <i class="fas fa-users"></i>
                        <select name="user_type" required onchange="toggleUserTypeFields()">
                            <option value="">Select User Type</option>
                            <option value="rider">🚗 Rider</option>
                            <option value="driver">🚕 Driver</option>
                            <option value="admin">⚙️ Admin</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Driver Fields -->
            <div class="conditional-section driver-fields">
                <div class="section-title">
                    <i class="fas fa-car"></i>
                    Driver Information
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>License Number</label>
                        <div class="input-with-icon">
                            <i class="fas fa-id-card"></i>
                            <input type="text" name="license_number" placeholder="Enter license number">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>NID Number</label>
                        <div class="input-with-icon">
                            <i class="fas fa-address-card"></i>
                            <input type="text" name="nid_number" placeholder="Enter NID number">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Vehicle Type</label>
                        <div class="input-with-icon">
                            <i class="fas fa-car-side"></i>
                            <input type="text" name="vehicle_type" placeholder="e.g., Car, Van, Bike">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Vehicle Model</label>
                        <div class="input-with-icon">
                            <i class="fas fa-cog"></i>
                            <input type="text" name="vehicle_model" placeholder="e.g., Toyota Prius">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Seating Capacity</label>
                        <div class="input-with-icon">
                            <i class="fas fa-users"></i>
                            <input type="number" min="1" name="seating_capacity" placeholder="Number of seats">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>License Plate</label>
                        <div class="input-with-icon">
                            <i class="fas fa-hashtag"></i>
                            <input type="text" name="vehicle_plate" placeholder="License plate number">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Fields -->
            <div class="conditional-section admin-fields">
                <div class="section-title">
                    <i class="fas fa-shield-alt"></i>
                    Admin Verification
                </div>
                
                <div class="form-group">
                    <label>Admin Code</label>
                    <div class="input-with-icon">
                        <i class="fas fa-key"></i>
                        <input type="password" name="admin_code" placeholder="Enter admin verification code">
                    </div>
                    <small style="color: #666; font-size: 13px; margin-top: 5px; display: block;">
                        * Contact system administrator for the admin code
                    </small>
                </div>
            </div>

            <button type="submit" class="submit-btn">
                Create Account
            </button>
        </form>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Company</h3>
                <ul>
                    <li><a href="#">About us</a></li>
                    <li><a href="#">Our offerings</a></li>
                    <li><a href="#">Newsroom</a></li>
                    <li><a href="#">Investors</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Careers</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Products</h3>
                <ul>
                    <li><a href="#">Ride</a></li>
                    <li><a href="#">Drive</a></li>
                    <li><a href="#">Deliver</a></li>
                    <li><a href="#">Eat</a></li>
                    <li><a href="#">Business</a></li>
                    <li><a href="#">Freight</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Safety</h3>
                <ul>
                    <li><a href="#">Safety Center</a></li>
                    <li><a href="#">Community Guidelines</a></li>
                    <li><a href="#">Help</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Travel</h3>
                <ul>
                    <li><a href="#">Reserve</a></li>
                    <li><a href="#">Airports</a></li>
                    <li><a href="#">Cities</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-linkedin"></i></a>
                <a href="#"><i class="fab fa-youtube"></i></a>
            </div>
            
            <div style="color: #ccc;">
                <span>English</span> | <span>Dhaka</span>
            </div>
            
            <div class="app-links">
                <a href="#" class="app-link">
                    <i class="fab fa-google-play"></i>
                    <div class="store-text">GET IT ON<br><strong>Google Play</strong></div>
                </a>
                <a href="#" class="app-link">
                    <i class="fab fa-apple"></i>
                    <div class="store-text">Download on the<br><strong>App Store</strong></div>
                </a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>