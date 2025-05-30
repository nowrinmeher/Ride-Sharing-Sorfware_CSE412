<?php
// models/User.php - User model class

require_once 'database.php';

class User {
    private $conn;
    private $table = 'users';
    
    public $id;
    public $full_name;
    public $email;
    public $password;
    public $phone;
    public $profile_image;
    public $user_type;
    public $status;
    public $email_verified;
    public $created_at;
    public $updated_at;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // Create new user
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  SET full_name = :full_name,
                      email = :email,
                      password = :password,
                      phone = :phone,
                      user_type = :user_type";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->full_name = sanitizeInput($this->full_name);
        $this->email = sanitizeInput($this->email);
        $this->phone = sanitizeInput($this->phone);
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        $this->user_type = sanitizeInput($this->user_type);
        
        // Bind parameters
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $this->password);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':user_type', $this->user_type);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    // Find user by email
    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            $this->id = $row['id'];
            $this->full_name = $row['full_name'];
            $this->email = $row['email'];
            $this->password = $row['password'];
            $this->phone = $row['phone'];
            $this->profile_image = $row['profile_image'];
            $this->user_type = $row['user_type'];
            $this->status = $row['status'];
            $this->email_verified = $row['email_verified'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        
        return false;
    }
    
    // Find user by ID
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            $this->id = $row['id'];
            $this->full_name = $row['full_name'];
            $this->email = $row['email'];
            $this->password = $row['password'];
            $this->phone = $row['phone'];
            $this->profile_image = $row['profile_image'];
            $this->user_type = $row['user_type'];
            $this->status = $row['status'];
            $this->email_verified = $row['email_verified'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        
        return false;
    }
    
    // Verify password
    public function verifyPassword($password) {
        return password_verify($password, $this->password);
    }
    
    // Update user
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET full_name = :full_name,
                      phone = :phone,
                      profile_image = :profile_image
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->full_name = sanitizeInput($this->full_name);
        $this->phone = sanitizeInput($this->phone);
        $this->profile_image = sanitizeInput($this->profile_image);
        
        // Bind parameters
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':profile_image', $this->profile_image);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }
    
    // Update password
    public function updatePassword($newPassword) {
        $query = "UPDATE " . $this->table . " SET password = :password WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }
    
    // Verify email
    public function verifyEmail() {
        $query = "UPDATE " . $this->table . " SET email_verified = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }
    
    // Check if email exists
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
    
    // Get user statistics
    public function getUserStats() {
        $query = "SELECT 
                    (SELECT COUNT(*) FROM rides WHERE user_id = :user_id) as total_rides,
                    (SELECT COUNT(*) FROM rides WHERE user_id = :user_id AND status = 'completed') as completed_rides,
                    (SELECT SUM(actual_price) FROM rides WHERE user_id = :user_id AND status = 'completed') as total_spent
                  FROM dual";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    // Create email verification token
    public function createEmailVerificationToken() {
        $token = generateSecureToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $query = "INSERT INTO email_verification_tokens (user_id, token, expires_at) 
                  VALUES (:user_id, :token, :expires_at)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->id);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires_at', $expiresAt);
        
        if ($stmt->execute()) {
            return $token;
        }
        
        return false;
    }
    
    // Verify email verification token
    public function verifyEmailToken($token) {
        $query = "SELECT user_id FROM email_verification_tokens 
                  WHERE token = :token AND expires_at > NOW() 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            $userId = $row['user_id'];
            
            // Verify email
            $updateQuery = "UPDATE " . $this->table . " SET email_verified = 1 WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':id', $userId);
            $updateStmt->execute();
            
            // Delete token
            $deleteQuery = "DELETE FROM email_verification_tokens WHERE token = :token";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteStmt->bindParam(':token', $token);
            $deleteStmt->execute();
            
            return true;
        }
        
        return false;
    }
    
    // Create password reset token
    public function createPasswordResetToken() {
        $token = generateSecureToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $query = "INSERT INTO password_reset_tokens (user_id, token, expires_at) 
                  VALUES (:user_id, :token, :expires_at)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->id);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires_at', $expiresAt);
        
        if ($stmt->execute()) {
            return $token;
        }
        
        return false;
    }
    
    // Reset password with token
    public function resetPasswordWithToken($token, $newPassword) {
        $query = "SELECT user_id FROM password_reset_tokens 
                  WHERE token = :token AND expires_at > NOW() AND used = 0 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            $userId = $row['user_id'];
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateQuery = "UPDATE " . $this->table . " SET password = :password WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateQuery);
            
            $updateStmt->bindParam(':password', $hashedPassword);
            $updateStmt->bindParam(':id', $userId);
            $updateStmt->execute();
            
            // Mark token as used
            $markUsedQuery = "UPDATE password_reset_tokens SET used = 1 WHERE token = :token";
            $markUsedStmt = $this->conn->prepare($markUsedQuery);
            $markUsedStmt->bindParam(':token', $token);
            $markUsedStmt->execute();
            
            return true;
        }
        
        return false;
    }
}

?>