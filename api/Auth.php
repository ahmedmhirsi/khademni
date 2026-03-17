<?php
/**
 * KHADEMNI — Auth Controller
 * Handles: register, login, password reset, email verification
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Middleware.php';
require_once __DIR__ . '/config.php';

class Auth {

    /**
     * POST /api/register
     */
    public static function register(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $name            = trim($data['name'] ?? '');
        $email           = trim($data['email'] ?? '');
        $password        = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';
        $role            = $data['role'] ?? 'candidate';
        $companyName     = trim($data['company_name'] ?? '');

        // Validate
        $v = new Validator();
        $v->required('name', $name, 'Name')
          ->required('email', $email, 'Email')
          ->email('email', $email)
          ->required('password', $password, 'Password')
          ->minLength('password', $password, 8, 'Password')
          ->match('password_confirm', $password, $passwordConfirm)
          ->enum('role', $role, ['candidate', 'company']);

        if ($role === 'company') {
            $v->required('company_name', $companyName, 'Company name');
        }

        if ($v->fails()) {
            http_response_code(422);
            echo json_encode(['success' => false, 'errors' => $v->errors()]);
            return;
        }

        $db = Database::getInstance();

        // Check duplicate email
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'An account with this email already exists.']);
            return;
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Try to generate a token with expiry
        $verificationToken = bin2hex(random_bytes(32));
        $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        try {
            $db->beginTransaction();

            // Create user — require email verification
            $stmt = $db->prepare(
                'INSERT INTO users (name, email, password, role, email_verified_at, verification_token, verification_token_expires)
                 VALUES (:name, :email, :password, :role, NULL, :vtoken, :vexpires)'
            );
            $stmt->execute([
                ':name'     => $name,
                ':email'    => $email,
                ':password' => $hashedPassword,
                ':role'     => $role,
                ':vtoken'   => $verificationToken,
                ':vexpires' => $tokenExpires
            ]);
            $userId = (int)$db->lastInsertId();

            // Create role-specific profile
            if ($role === 'candidate') {
                $stmt = $db->prepare('INSERT INTO candidate_profiles (user_id) VALUES (:uid)');
                $stmt->execute([':uid' => $userId]);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO company_profiles (user_id, company_name) VALUES (:uid, :name)'
                );
                $stmt->execute([':uid' => $userId, ':name' => $companyName]);
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Registration failed. Please try again.',
                'debug' => $e->getMessage()
            ]);
            return;
        }

        // Send Verification Email
        $verifyLink = APP_URL . "/verify.html?token=" . $verificationToken;
        $subject = "Verify your email - Khademni";
        $message = "Hi $name,\n\nPlease verify your email by clicking the link below:\n\n[$verifyLink]\n\nThis link expires in 24 hours.\n\nThank you,\nKhademni Team";
        $headers = "From: noreply@khademni.com";
        // @mail($email, $subject, $message, $headers);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully! Please check your email and click the verification link to activate your account.',
            // Return token in dev for easy local testing since XAMPP mail might not be setup
            'dev_token' => $verificationToken
        ]);
    }

    /**
     * POST /api/auth/google
     */
    public static function googleLogin(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $token = $data['token'] ?? '';
        $role = $data['role'] ?? 'candidate';

        if (!$token) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Google token is required.']);
            return;
        }

        // Verify token with Google public endpoint natively (since we don't use composer)
        $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
        $response = @file_get_contents($url);
        
        if (!$response) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid Google token.']);
            return;
        }

        $payload = json_decode($response, true);
        if (!isset($payload['email']) || !isset($payload['sub'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token payload.']);
            return;
        }

        $email = $payload['email'];
        $name = $payload['name'] ?? 'Google User';

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // If user doesn't exist, create them
        if (!$user) {
            $randomPass = bin2hex(random_bytes(16)); // Random secure password
            $hashedPassword = password_hash($randomPass, PASSWORD_BCRYPT, ['cost' => 12]);
            
            try {
                $db->beginTransaction();

                $stmt = $db->prepare(
                    'INSERT INTO users (name, email, password, role, email_verified_at)
                     VALUES (:name, :email, :password, :role, NOW())'
                );
                $stmt->execute([
                    ':name'     => $name,
                    ':email'    => $email,
                    ':password' => $hashedPassword,
                    ':role'     => $role
                ]);
                $userId = (int)$db->lastInsertId();

                if ($role === 'candidate') {
                    $stmt = $db->prepare('INSERT INTO candidate_profiles (user_id) VALUES (:uid)');
                    $stmt->execute([':uid' => $userId]);
                } else {
                    $stmt = $db->prepare('INSERT INTO company_profiles (user_id, company_name) VALUES (:uid, :name)');
                    $stmt->execute([':uid' => $userId, ':name' => $name]);
                }

                $db->commit();

                // Build new user array
                $user = [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'role' => $role
                ];
            } catch (\Exception $e) {
                $db->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create account with Google.']);
                return;
            }
        }

        // Generate Khademni JWT
        $jwtToken = JWT::encode([
            'sub'   => (int)$user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
            'name'  => $user['name']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Google Login successful!',
            'token'   => $jwtToken,
            'user'    => [
                'id'             => (int)$user['id'],
                'name'           => $user['name'],
                'email'          => $user['email'],
                'role'           => $user['role'],
                'email_verified' => true
            ]
        ]);
    }

    /**
     * POST /api/login
     */
    public static function login(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        // Validate
        $v = new Validator();
        $v->required('email', $email, 'Email')
          ->email('email', $email)
          ->required('password', $password, 'Password');

        if ($v->fails()) {
            http_response_code(422);
            echo json_encode(['success' => false, 'errors' => $v->errors()]);
            return;
        }

        // Rate limit check
        if (!Middleware::checkRateLimit($email)) {
            $remaining = Middleware::getLockoutRemaining($email);
            $minutes = ceil($remaining / 60);
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => "Too many login attempts. Please try again in $minutes minute(s).",
                'retry_after' => $remaining
            ]);
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            Middleware::recordLoginAttempt($email);
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
            return;
        }

        // Check if email is verified
        if ($user['email_verified_at'] === null) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Please verify your email address to log in. Check your inbox for the verification link.']);
            return;
        }

        // Clear attempts on success
        Middleware::clearLoginAttempts($email);

        // Generate JWT
        $token = JWT::encode([
            'sub'   => (int)$user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
            'name'  => $user['name']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'token'   => $token,
            'user'    => [
                'id'             => (int)$user['id'],
                'name'           => $user['name'],
                'email'          => $user['email'],
                'role'           => $user['role'],
                'email_verified' => $user['email_verified_at'] !== null
            ]
        ]);
    }

    /**
     * POST /api/logout — Stateless, just confirms. Token removal is client-side.
     */
    public static function logout(): void {
        echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
    }

    /**
     * POST /api/password-reset — Request reset token
     */
    public static function requestPasswordReset(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = trim($data['email'] ?? '');

        $v = new Validator();
        $v->required('email', $email, 'Email')->email('email', $email);

        if ($v->fails()) {
            http_response_code(422);
            echo json_encode(['success' => false, 'errors' => $v->errors()]);
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Always return success to prevent email enumeration
        if ($user) {
            $resetToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $db->prepare(
                'UPDATE users SET reset_token = :token, reset_token_expires = :expires WHERE id = :id'
            );
            $stmt->execute([
                ':token'   => $resetToken,
                ':expires' => $expires,
                ':id'      => $user['id']
            ]);

            // In production: send email with reset link
            // For dev: include token in response
        }

        echo json_encode([
            'success' => true,
            'message' => 'If an account exists with that email, you will receive a password reset link.',
            // DEV ONLY — remove in production:
            'dev_token' => $resetToken ?? null
        ]);
    }

    /**
     * POST /api/password-reset/confirm — Reset with token
     */
    public static function confirmPasswordReset(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $token       = $data['token'] ?? '';
        $password    = $data['password'] ?? '';
        $passConfirm = $data['password_confirm'] ?? '';

        $v = new Validator();
        $v->required('token', $token, 'Reset token')
          ->required('password', $password, 'New password')
          ->minLength('password', $password, 8, 'New password')
          ->match('password_confirm', $password, $passConfirm);

        if ($v->fails()) {
            http_response_code(422);
            echo json_encode(['success' => false, 'errors' => $v->errors()]);
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT id FROM users WHERE reset_token = :token AND reset_token_expires > NOW()'
        );
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token.']);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare(
            'UPDATE users SET password = :password, reset_token = NULL, reset_token_expires = NULL WHERE id = :id'
        );
        $stmt->execute([':password' => $hashedPassword, ':id' => $user['id']]);

        echo json_encode(['success' => true, 'message' => 'Password reset successfully. You can now log in.']);
    }

    /**
     * GET /api/verify-email?token=xxx
     */
    public static function verifyEmail(): void {
        $token = $_GET['token'] ?? '';

        if (!$token) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Verification token is required.']);
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM users WHERE verification_token = :token AND verification_token_expires > NOW() AND email_verified_at IS NULL');
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired verification token.']);
            return;
        }

        $stmt = $db->prepare(
            'UPDATE users SET email_verified_at = NOW(), verification_token = NULL, verification_token_expires = NULL WHERE id = :id'
        );
        $stmt->execute([':id' => $user['id']]);

        echo json_encode(['success' => true, 'message' => 'Email verified successfully! You can now log in.']);
    }
}
