<?php
require_once 'database.php';
session_start();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_phone = trim($_POST['email_or_phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';

    if (!$email_or_phone || !$password || !$user_type) {
        $errors[] = "All fields are required.";
    }

    $allowed_user_types = ['rider', 'driver', 'admin'];
    if (!in_array($user_type, $allowed_user_types)) {
        $errors[] = "Invalid user type selected.";
    }

    if (empty($errors)) {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            // Fixed query with separate placeholders
            $stmt = $conn->prepare("SELECT * FROM users WHERE (email = :email OR phone = :phone) AND user_type = :user_type LIMIT 1");
            $stmt->bindValue(':email', $email_or_phone);
            $stmt->bindValue(':phone', $email_or_phone);
            $stmt->bindValue(':user_type', $user_type);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $errors[] = "Your account is not active. Please contact support.";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_type'] = $user['user_type'];

                    // Redirect to the correct dashboard
                    if ($user['user_type'] === 'admin') {
                        header('Location: admin_dashboard.php');
                        exit;
                    } elseif ($user['user_type'] === 'rider') {
                        header('Location: rider_dashboard.php');
                        exit;
                    } elseif ($user['user_type'] === 'driver') {
                        header('Location: driver_dashboard.php'); // create this page later
                        exit;
                    } else {
                        // Unknown user type fallback
                        $errors[] = "Invalid user type.";
                    }
                }
            } else {
                $errors[] = "Invalid login credentials or user type.";
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
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Login | Ride Sharing</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* Enhanced Login Page Styling */
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
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    background-attachment: fixed;
    color: #333;
    line-height: 1.6;
    overflow-x: hidden;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
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

/* Animated Particles */
.particles {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: -1;
}

.particle {
    position: absolute;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    animation: particleFloat 15s infinite linear;
}

@keyframes particleFloat {
    0% {
        transform: translateY(100vh) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(-100px) rotate(360deg);
        opacity: 0;
    }
}

/* Header Bar */
.header-bar {
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 1000;
    transition: all 0.3s ease;
}

.logo {
    display: flex;
    align-items: center;
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
}

.logo i {
    margin-right: 0.5rem;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.header-bar a {
    color: white;
    text-decoration: none;
    font-weight: 600;
    padding: 10px 20px;
    border-radius: 50px;
    background: var(--primary-gradient);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.header-bar a::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s;
}

.header-bar a:hover::before {
    left: 100%;
}

.header-bar a:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

/* Main Content */
.main-content {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 2rem;
    min-height: calc(100vh - 200px);
}

/* Form Container */
.form-container {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    padding: 3rem 2.5rem;
    width: 100%;
    max-width: 450px;
    animation: fadeInUp 0.8s ease-out;
    position: relative;
    overflow: hidden;
}

.form-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
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

/* Form Title */
.form-container h1 {
    text-align: center;
    margin-bottom: 0.5rem;
    font-size: 2.5rem;
    font-weight: 700;
    background: var(--text-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.subtitle {
    text-align: center;
    color: #666;
    margin-bottom: 2rem;
    font-size: 1rem;
}

/* Error Messages */
.errors {
    background: linear-gradient(135deg, rgba(255, 107, 107, 0.1), rgba(255, 165, 0, 0.1));
    border: 1px solid rgba(255, 107, 107, 0.3);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    color: #dc3545;
}

.errors ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

.errors li {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
}

.errors li:before {
    content: '⚠';
    margin-right: 0.5rem;
    font-size: 1.2rem;
}

.errors li:last-child {
    margin-bottom: 0;
}

/* Form Elements */
.input-group {
    margin-bottom: 1.5rem;
}

.input-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #555;
    font-size: 0.95rem;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-wrapper input,
.input-wrapper select {
    width: 100%;
    padding: 12px 50px 12px 16px;
    border: 2px solid rgba(102, 126, 234, 0.2);
    border-radius: 12px;
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    outline: none;
}

.input-wrapper input:focus,
.input-wrapper select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    transform: translateY(-2px);
}

.input-wrapper.focused {
    transform: scale(1.02);
}

.input-icon {
    position: absolute;
    right: 16px;
    color: #667eea;
    font-size: 1.1rem;
    pointer-events: none;
    z-index: 2;
}

/* Select Styling */
select {
    appearance: none;
    -moz-appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 12px center;
    background-repeat: no-repeat;
    background-size: 16px;
}

/* Forgot Password Link */
.forgot-link {
    display: block;
    text-align: right;
    color: #667eea;
    text-decoration: none;
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.forgot-link:hover {
    color: #764ba2;
    transform: scale(1.05);
}

/* Login Button */
.login-btn {
    width: 100%;
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 15px;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    overflow: hidden;
}

.login-btn:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.login-btn:active {
    transform: scale(0.98);
}

/* Footer */
.footer {
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(20px);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    color: white;
    padding: 3rem 2rem 1rem;
    position: relative;
    z-index: 10;
    margin-top: auto;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
}

.footer-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 2.5rem;
    margin-bottom: 2.5rem;
}

.footer-section h3 {
    margin-bottom: 1.2rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #00d4ff;
    text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
}

.footer-section ul {
    list-style: none;
    padding-left: 0;
    margin: 0;
}

.footer-section li {
    margin-bottom: 0.8rem;
}

.footer-section a {
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 400;
}

.footer-section a:hover,
.footer-section a:focus {
    color: #00d4ff;
    text-shadow: 0 0 8px rgba(0, 212, 255, 0.4);
    transform: translateX(5px);
    outline: none;
}

.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 2rem;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.6);
}

.social-links {
    display: flex;
    gap: 1.5rem;
}

.social-links a {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.5rem;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    padding: 8px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
}

.social-links a:hover,
.social-links a:focus {
    color: #00d4ff;
    background: rgba(0, 212, 255, 0.15);
    transform: translateY(-3px) rotate(5deg);
    box-shadow: 0 8px 20px rgba(0, 212, 255, 0.3);
    outline: none;
}

.app-links {
    display: flex;
    gap: 1rem;
}

.app-links div {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    color: #fff;
    font-size: 0.8rem;
    padding: 12px;
    text-align: center;
    width: 120px;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.app-links div:hover,
.app-links div:focus {
    background: rgba(0, 212, 255, 0.15);
    border-color: rgba(0, 212, 255, 0.3);
    transform: translateY(-3px);
    box-shadow: 0 12px 25px rgba(0, 212, 255, 0.2);
    outline: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-bar {
        padding: 1rem;
        font-size: 1.2rem;
        flex-direction: column;
        gap: 1rem;
    }

    .main-content {
        padding: 1rem;
        min-height: calc(100vh - 150px);
    }

    .form-container {
        padding: 2rem 1.5rem;
        margin: 1rem 0;
    }

    .form-container h1 {
        font-size: 2.2rem;
    }

    .footer-content {
        grid-template-columns: 1fr;
        gap: 2rem;
    }

    .footer-bottom {
        flex-direction: column;
        text-align: center;
    }

    .app-links {
        justify-content: center;
    }

    .input-wrapper input,
    .input-wrapper select {
        padding: 10px 45px 10px 14px;
        font-size: 0.95rem;
    }

    .login-btn {
        padding: 12px;
        font-size: 1rem;
    }
}

@media (max-width: 480px) {
    .form-container {
        padding: 1.5rem 1rem;
    }

    .form-container h1 {
        font-size: 2rem;
    }

    .header-bar {
        padding: 0.8rem;
    }

    .logo {
        font-size: 1.3rem;
    }
}

/* Utility Classes */
.visually-hidden {
    border: 0 !important;
    clip: rect(0 0 0 0) !important;
    height: 1px !important; 
    margin: -1px !important; 
    overflow: hidden !important; 
    padding: 0 !important; 
    position: absolute !important; 
    width: 1px !important;
    white-space: nowrap !important;
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

/* Ripple Effect Animation */
@keyframes ripple {
    to {
        transform: scale(2);
        opacity: 0;
    }
}

/* Focus and Accessibility */
*:focus {
    outline: 2px solid #667eea;
    outline-offset: 2px;
}

button:focus,
input:focus,
select:focus,
a:focus {
    outline: 2px solid #667eea;
    outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .form-container {
        background: white;
        border: 2px solid #000;
    }
    
    .input-wrapper input,
    .input-wrapper select {
        border-color: #000;
        background: white;
    }
}

  /* Footer */
  .footer {
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(20px);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    color: white;
    padding: 3rem 2rem 1rem;
    position: relative;
    z-index: 10;
  }

  .container {
    max-width: 1200px;
    margin: 0 auto;
  }

  .footer-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 2.5rem;
    margin-bottom: 2.5rem;
  }

  .footer-section h3 {
    margin-bottom: 1.2rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #00d4ff;
    text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
  }

  .footer-section ul {
    list-style: none;
    padding-left: 0;
    margin: 0;
  }

  .footer-section li {
    margin-bottom: 0.8rem;
  }

  .footer-section a {
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 400;
  }

  .footer-section a:hover,
  .footer-section a:focus {
    color: #00d4ff;
    text-shadow: 0 0 8px rgba(0, 212, 255, 0.4);
    transform: translateX(5px);
    outline: none;
  }

  .footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 2rem;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.6);
  }

  .social-links {
    display: flex;
    gap: 1.5rem;
  }

  .social-links a {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.5rem;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    padding: 8px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
  }

  .social-links a:hover,
  .social-links a:focus {
    color: #00d4ff;
    background: rgba(0, 212, 255, 0.15);
    transform: translateY(-3px) rotate(5deg);
    box-shadow: 0 8px 20px rgba(0, 212, 255, 0.3);
    outline: none;
  }

  .app-links {
    display: flex;
    gap: 1rem;
  }

  .app-links div {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    color: #fff;
    font-size: 0.8rem;
    padding: 12px;
    text-align: center;
    width: 120px;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  }

  .app-links div:hover,
  .app-links div:focus {
    background: rgba(0, 212, 255, 0.15);
    border-color: rgba(0, 212, 255, 0.3);
    transform: translateY(-3px);
    box-shadow: 0 12px 25px rgba(0, 212, 255, 0.2);
    outline: none;
  }

  /* Responsive Design */
  @media (max-width: 768px) {
    .header-bar {
      padding: 1rem;
      font-size: 1.5rem;
      flex-direction: column;
      gap: 1rem;
    }

    .form-container {
      margin: 1rem;
      padding: 2rem 1.5rem;
    }

    h1 {
      font-size: 2.2rem;
    }

    .footer-content {
      grid-template-columns: 1fr;
      gap: 2rem;
    }

    .footer-bottom {
      flex-direction: column;
      text-align: center;
    }

    .app-links {
      justify-content: center;
    }
  }

  /* Utility Classes */
  .visually-hidden {
    border: 0 !important;
    clip: rect(0 0 0 0) !important;
    height: 1px !important; 
    margin: -1px !important; 
    overflow: hidden !important; 
    padding: 0 !important; 
    position: absolute !important; 
    width: 1px !important;
    white-space: nowrap !important;
  }
</style>
</head>
<body>

<!-- Animated Background Particles -->
<div class="particles">
  <div class="particle" style="left: 10%; width: 4px; height: 4px; animation-delay: -0.5s;"></div>
  <div class="particle" style="left: 20%; width: 6px; height: 6px; animation-delay: -1s;"></div>
  <div class="particle" style="left: 30%; width: 3px; height: 3px; animation-delay: -1.5s;"></div>
  <div class="particle" style="left: 40%; width: 5px; height: 5px; animation-delay: -2s;"></div>
  <div class="particle" style="left: 50%; width: 4px; height: 4px; animation-delay: -2.5s;"></div>
  <div class="particle" style="left: 60%; width: 6px; height: 6px; animation-delay: -3s;"></div>
  <div class="particle" style="left: 70%; width: 3px; height: 3px; animation-delay: -3.5s;"></div>
  <div class="particle" style="left: 80%; width: 5px; height: 5px; animation-delay: -4s;"></div>
  <div class="particle" style="left: 90%; width: 4px; height: 4px; animation-delay: -4.5s;"></div>
</div>

<div class="header-bar" style="display:flex; justify-content:space-between; padding:10px; background:#1ca3d8; color:#fff;">
  <div class="logo" style="font-weight:bold; font-size:1.5rem; cursor:pointer;">
    <i class="fas fa-car"></i> Ride Sharing
  </div>
  <div class="nav-links" style="display:flex; gap:20px; align-items:center;">
    <a href="index.php" aria-label="Go to home page" style="color:white; text-decoration:none;">
      <i class="fas fa-home"></i> Home
    </a>
    <a href="register.php" aria-label="Sign up page link" style="color:white; text-decoration:none;">
      <i class="fas fa-user-plus"></i> Sign up
    </a>
  </div>
</div>


<div class="main-content">
    <div class="form-container" role="main" aria-labelledby="loginTitle">
        <h1 id="loginTitle">Welcome Back</h1>
        <p class="subtitle">Sign in to continue your journey</p>

        <!-- Error messages would go here -->
        <!--
        <div class="errors" role="alert" aria-live="assertive">
            <ul>
                <li>Sample error message</li>
            </ul>
        </div>
        -->

        <form method="POST" action="" novalidate>
            <div class="input-group">
                <label for="email_or_phone">Email or Phone</label>
                <div class="input-wrapper">
                    <input type="text" id="email_or_phone" name="email_or_phone" required placeholder="Enter your email or phone" autocomplete="username" />
                    <i class="fas fa-user input-icon"></i>
                </div>
            </div>

            <div class="input-group">
                <label for="user_type">User Type</label>
                <div class="input-wrapper">
                    <select id="user_type" name="user_type" required aria-required="true" aria-describedby="userTypeDesc">
                        <option value="">-- Select User Type --</option>
                        <option value="rider">Rider</option>
                        <option value="driver">Driver</option>
                        <option value="admin">Admin</option>
                    </select>
                    <i class="fas fa-users input-icon"></i>
                </div>
                <span id="userTypeDesc" class="visually-hidden">Choose your user type</span>
            </div>

            <div class="input-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" required placeholder="Enter your password" autocomplete="current-password" />
                    <i class="fas fa-lock input-icon"></i>
                </div>
            </div>

            <a href="forgot_password.php" class="forgot-link" aria-label="Forgot password">Forgot password?</a>

            <button type="submit" class="login-btn" aria-label="Log in">
                <span>Sign In</span>
            </button>
        </form>
    </div>
</div>

<footer class="footer" role="contentinfo">
    <div class="container">
        <div class="footer-content">
            <section class="footer-section" aria-labelledby="companyInfoTitle">
                <h3 id="companyInfoTitle">Company</h3>
                <ul>
                    <li><a href="#" tabindex="0">About us</a></li>
                    <li><a href="#" tabindex="0">Our offerings</a></li>
                    <li><a href="#" tabindex="0">Newsroom</a></li>
                    <li><a href="#" tabindex="0">Investors</a></li>
                    <li><a href="#" tabindex="0">Blog</a></li>
                    <li><a href="#" tabindex="0">Careers</a></li>
                    <li><a href="#" tabindex="0">Ride_Sharing AI</a></li>
                </ul>
            </section>
            <section class="footer-section" aria-labelledby="productsTitle">
                <h3 id="productsTitle">Products</h3>
                <ul>
                    <li><a href="#" tabindex="0">Ride</a></li>
                    <li><a href="#" tabindex="0">Drive</a></li>
                    <li><a href="#" tabindex="0">Deliver</a></li>
                    <li><a href="#" tabindex="0">Eat</a></li>
                    <li><a href="#" tabindex="0">Ride_Sharing for Business</a></li>
                    <li><a href="#" tabindex="0">Ride_Sharing Freight</a></li>
                    <li><a href="#" tabindex="0">Gift cards</a></li>
                </ul>
            </section>
            <section class="footer-section" aria-labelledby="citizenshipTitle">
                <h3 id="citizenshipTitle">Global citizenship</h3>
                <ul>
                    <li><a href="#" tabindex="0">Safety</a></li>
                    <li><a href="#" tabindex="0">Sustainability</a></li>
                </ul>
            </section>
            <section class="footer-section" aria-labelledby="travelTitle">
                <h3 id="travelTitle">Travel</h3>
                <ul>
                    <li><a href="#" tabindex="0">Reserve</a></li>
                    <li><a href="#" tabindex="0">Airports</a></li>
                    <li><a href="#" tabindex="0">Cities</a></li>
                </ul>
            </section>
        </div>

        <div class="footer-bottom">
            <div class="social-links" aria-label="Social media links">
                <a href="#" aria-label="Facebook" tabindex="0"><i class="fab fa-facebook"></i></a>
                <a href="#" aria-label="Twitter" tabindex="0"><i class="fab fa-twitter"></i></a>
                <a href="#" aria-label="YouTube" tabindex="0"><i class="fab fa-youtube"></i></a>
                <a href="#" aria-label="LinkedIn" tabindex="0"><i class="fab fa-linkedin"></i></a>
                <a href="#" aria-label="Instagram" tabindex="0"><i class="fab fa-instagram"></i></a>
            </div>
            <div>
                <span>English</span> | <span>Dhaka</span>
            </div>
            <div class="app-links" role="list">
                <div role="listitem" tabindex="0" aria-label="Google Play Store">
                    <i class="fab fa-google-play"></i><br />
                    GET IT ON<br /><strong>Google Play</strong>
                </div>
                <div role="listitem" tabindex="0" aria-label="Apple App Store">
                    <i class="fab fa-apple"></i><br />
                    Download on the<br /><strong>App Store</strong>
                </div>
            </div>
        </div>

        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1); text-align: center; color: rgba(255,255,255,0.5); font-size: 0.9rem;">
            Bangladesh ridesharing related information
        </div>
    </div>
</footer>

<script>
// Add some interactive effects
document.addEventListener('DOMContentLoaded', function() {
    // Add floating animation to particles with random delays
    const particles = document.querySelectorAll('.particle');
    particles.forEach((particle, index) => {
        particle.style.animationDelay = `-${Math.random() * 5}s`;
        particle.style.top = `${Math.random() * 100}%`;
    });

    // Add input focus effects
    const inputs = document.querySelectorAll('input, select');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });

    // Add button ripple effect
    const button = document.querySelector('.login-btn');
    button.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
        `;
        
        this.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    });
    
    // Add CSS for ripple animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
        .login-btn {
            position: relative;
            overflow: hidden;
        }
    `;
    document.head.appendChild(style);
});
</script>

</body>
</html>
