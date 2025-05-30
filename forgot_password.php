<?php
require_once 'database.php';
require_once 'vendor/autoload.php'; // Composer autoloader

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Twilio\Rest\Client;

session_start();

$errors = [];
$success = '';
$step = $_GET['step'] ?? 'request'; // 'request' or 'verify'

// Redirect if no session on verify step
if ($step === 'verify' && empty($_SESSION['password_reset_user_id'])) {
    header('Location: forgot_password.php');
    exit;
}

// Send email via PHPMailer SMTP
function sendEmailSafe($to, $subject, $message) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'replace@gmail.com';
        $mail->Password   = 'xxxx xxxx xxxx xxxx';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('noreply@ridesharing.com', 'Ride Sharing');
        $mail->addAddress($to);

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        error_log("Email sent via SMTP to $to");
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $e->getMessage());
        return false;
    }
}

// Normalize phone number for SMS sending
function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);

    if (strlen($phone) == 11 && strpos($phone, '0') === 0) {
        return '+880' . substr($phone, 1);
    }
    if (strlen($phone) == 13 && strpos($phone, '880') === 0) {
        return '+' . $phone;
    }
    if (strlen($phone) == 10) {
        return '+1' . $phone;
    }
    if (strpos($phone, '+') !== 0) {
        $phone = '+' . $phone;
    }
    return $phone;
}

// Twilio credentials and trial flag
$account_sid = 'replace';
$auth_token = 'replace';
$twilio_number = '+replace';

$isTrialAccount = true;

function sendSMS($phone, $token) {
    // Simulate sending SMS for demo/testing instead of real Twilio SMS
    error_log("Simulated SMS to $phone: Your OTP code is $token");
    
    // You can also save it in session or a file if you want to display on a page
    $_SESSION['simulated_sms'] = "OTP sent to $phone is: $token (Simulation only, no real SMS)";
    
    return true;  // pretend SMS was sent successfully
}


// Step 1: Request password reset
if ($step === 'request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_phone = trim($_POST['email_or_phone'] ?? '');

    if (!$email_or_phone) {
        $errors[] = "Please enter your email or phone number.";
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            $normalized_input = formatPhoneNumber($email_or_phone);

            $stmt = $conn->prepare("SELECT id, email, phone FROM users WHERE email = :email OR phone = :phone OR phone = :phone_normalized LIMIT 1");
            $stmt->bindValue(':email', $email_or_phone);
            $stmt->bindValue(':phone', $email_or_phone);
            $stmt->bindValue(':phone_normalized', $normalized_input);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $errors[] = "No account found with that email or phone number.";
            } else {
                $token = sprintf("%06d", random_int(100000, 999999));
                $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

                // Invalidate old tokens
                $clearOld = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = :user_id AND used = 0");
                $clearOld->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
                $clearOld->execute();

                // Insert new token
                $insert = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at) VALUES (:user_id, :token, :expires_at, NOW())");
                $insert->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
                $insert->bindValue(':token', $token);
                $insert->bindValue(':expires_at', $expires_at);
                $insert->execute();

                error_log("Generated token $token for user ID {$user['id']}");

                $messageSent = false;

                // Always send Email OTP
                if (!empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                    $subject = "Password Reset Code";
                    $message = "Your reset code is: $token\nExpires in 30 minutes.";
                    if (sendEmailSafe($user['email'], $subject, $message)) {
                        $messageSent = true;
                    }
                }

                // Try sending SMS OTP only if not trial
                if (!$isTrialAccount && !empty($user['phone'])) {
                    $formatted_phone = formatPhoneNumber($user['phone']);
                    if (sendSMS($formatted_phone, $token)) {
                        $messageSent = true;
                    }
                }

                if ($messageSent) {
                    $_SESSION['password_reset_user_id'] = $user['id'];
                    $_SESSION['reset_method'] = $email_or_phone;
                    header("Location: forgot_password.php?step=verify");
                    exit;
                } else {
                    $errors[] = "Failed to send reset code. Please try again.";
                }
            }
        } catch (PDOException $e) {
            error_log("DB error: " . $e->getMessage());
            $errors[] = "System error. Please try later.";
        }
    }
}

// Step 2: Verify token and reset password
if ($step === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_input = trim($_POST['token'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_id = $_SESSION['password_reset_user_id'] ?? null;

    if (!$token_input) {
        $errors[] = "Please enter the verification code.";
    }
    if (!$new_password) {
        $errors[] = "Please enter a new password.";
    }
    if ($new_password && strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (!$user_id) {
        $errors[] = "Session expired. Please start again.";
    }

    if (empty($errors)) {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            $stmt = $conn->prepare("SELECT * FROM password_reset_tokens WHERE user_id = :user_id AND token = :token AND used = 0 ORDER BY created_at DESC LIMIT 1");
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':token', $token_input);
            $stmt->execute();

            $token_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($token_record) {
                if (strtotime($token_record['expires_at']) < time()) {
                    $errors[] = "Verification code expired. Please request a new code.";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id");
                    $update->bindValue(':password', $hashed_password);
                    $update->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                    $update->execute();

                    $markUsed = $conn->prepare("UPDATE password_reset_tokens SET used = 1, updated_at = NOW() WHERE id = :id");
                    $markUsed->bindValue(':id', $token_record['id'], PDO::PARAM_INT);
                    $markUsed->execute();

                    unset($_SESSION['password_reset_user_id']);
                    unset($_SESSION['reset_method']);

                    $success = "Password reset successful! You can now <a href='login.php'>log in</a>.";
                    $step = 'done';
                }
            } else {
                $errors[] = "Invalid or already used verification code.";
            }
        } catch (PDOException $e) {
            error_log("DB error on verify: " . $e->getMessage());
            $errors[] = "System error. Please try later.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password | Ride Sharing</title>
<style>
body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif; background: #f8f9fa; color: #333; line-height: 1.6;}
.form-container { max-width: 400px; margin: 48px auto; padding: 32px 24px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
h1 { font-weight: 700; font-size: 1.75rem; margin-bottom: 8px; text-align: center; color: #2c3e50;}
.subtitle { text-align: center; color: #6c757d; margin-bottom: 32px; font-size: 14px;}
label { display: block; font-weight: 600; margin-bottom: 8px; color: #495057;}
input { width: 100%; padding: 12px 16px; margin-bottom: 20px; border: 1px solid #dee2e6; border-radius: 6px; font-size: 16px; box-sizing: border-box; transition: border-color 0.3s ease;}
input:focus { outline: none; border-color: #1ca3d8; box-shadow: 0 0 0 3px rgba(28, 163, 216, 0.1);}
button { background: #1ca3d8; color: #fff; border: none; padding: 14px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 16px; width: 100%; transition: background-color 0.3s ease;}
button:hover { background: #1587b5;}
button:disabled { background: #6c757d; cursor: not-allowed;}
.errors { margin-bottom: 24px; padding: 16px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 6px; color: #721c24;}
.errors ul { margin: 0; padding-left: 20px;}
.errors li { margin-bottom: 8px;}
a.back-link { display: inline-block; margin-top: 24px; color: #1ca3d8; text-decoration: none; font-weight: 500; text-align: center; width: 100%;}
a.back-link:hover { text-decoration: underline;}
.success-message { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 16px; border-radius: 6px; font-weight: 600; margin-bottom: 24px; text-align: center;}
.success-message a { color: #0c5460; font-weight: 700;}
.code-info { background: #e7f3ff; border: 1px solid #b8daff; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; color: #004085;}
.debug-info { background: #fff3cd; border: 1px solid #ffeaa7; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 12px; color: #856404; font-family: monospace;}
</style>
</head>
<body>

<div class="form-container">

<?php if ($step === 'request'): ?>

    <h1>Forgot Password</h1>
    <p class="subtitle">Enter your email or phone number to receive a reset code</p>

    <?php if ($errors): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="email_or_phone">Email or Phone Number</label>
        <input 
            type="text" 
            id="email_or_phone" 
            name="email_or_phone" 
            required 
            placeholder="Enter your email or phone number"
            value="<?= htmlspecialchars($_POST['email_or_phone'] ?? '') ?>"
        >
        <button type="submit">Send Reset Code</button>
    </form>

    <a href="login.php" class="back-link">← Back to Login</a>

<?php elseif ($step === 'verify'): ?>

    <h1>Enter Verification Code</h1>
    <p class="subtitle">We've sent a 6-digit code to <?= htmlspecialchars($_SESSION['reset_method'] ?? 'your registered contact') ?></p>

    <?php if ($errors): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="code-info">
        📱 Check your SMS messages and email inbox for the 6-digit verification code. The code expires in 30 minutes.
    </div>

    <form method="POST" action="?step=verify">
        <label for="token">Verification Code</label>
        <input 
            type="text" 
            id="token" 
            name="token" 
            required 
            maxlength="6"
            placeholder="Enter 6-digit code"
            pattern="[0-9]{6}"
            value="<?= htmlspecialchars($_POST['token'] ?? '') ?>"
            autocomplete="off"
        >

        <label for="new_password">New Password</label>
        <input 
            type="password" 
            id="new_password" 
            name="new_password" 
            required
            minlength="6"
            placeholder="Enter new password (min 6 characters)"
        >

        <label for="confirm_password">Confirm New Password</label>
        <input 
            type="password" 
            id="confirm_password" 
            name="confirm_password" 
            required
            minlength="6"
            placeholder="Confirm your new password"
        >

        <button type="submit">Reset Password</button>
    </form>

    <a href="forgot_password.php" class="back-link">← Request New Code</a>

<?php elseif ($step === 'done'): ?>

    <h1>Success!</h1>
    <div class="success-message"><?= $success ?></div>
    <a href="login.php" class="back-link">Go to Login</a>

<?php else: ?>

    <h1>Error</h1>
    <p>Invalid step in the password reset process.</p>
    <a href="forgot_password.php" class="back-link">← Start Over</a>

<?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const emailOrPhoneInput = document.getElementById('email_or_phone');
    if (emailOrPhoneInput) {
        emailOrPhoneInput.addEventListener('input', function(e) {
            let value = e.target.value;
            if (/^\d/.test(value) && !value.includes('@')) {
                value = value.replace(/[^\d+]/g, '');
                e.target.value = value;
            }
        });
    }

    const tokenInput = document.getElementById('token');
    if (tokenInput) {
        tokenInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 6) {
                value = value.substring(0, 6);
            }
            e.target.value = value;
        });
        tokenInput.focus();
    }

    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    if (confirmPasswordInput && newPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            if (newPasswordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        });
    }
});
</script>

</body>
</html>
