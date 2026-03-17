<?php
/**
 * KHADEMNI — Middleware (Auth + Rate Limiting)
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/config.php';

class Middleware {
    /**
     * Require valid JWT. Returns user payload or sends 401.
     */
    public static function authenticate(): array {
        $user = JWT::getUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in.']);
            exit;
        }
        return $user;
    }

    /**
     * Check rate limit for login attempts. Returns true if allowed.
     */
    public static function checkRateLimit(string $email): bool {
        $db = Database::getInstance();
        $ip = self::getClientIp();

        // Count recent attempts
        $stmt = $db->prepare(
            'SELECT COUNT(*) as cnt FROM login_attempts
             WHERE email = :email AND ip_address = :ip
             AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->execute([
            ':email'   => $email,
            ':ip'      => $ip,
            ':minutes' => LOGIN_LOCKOUT_MINUTES
        ]);
        $count = (int)$stmt->fetch()['cnt'];

        return $count < MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Record a login attempt.
     */
    public static function recordLoginAttempt(string $email): void {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip)'
        );
        $stmt->execute([
            ':email' => $email,
            ':ip'    => self::getClientIp()
        ]);
    }

    /**
     * Clear login attempts after successful login.
     */
    public static function clearLoginAttempts(string $email): void {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'DELETE FROM login_attempts WHERE email = :email AND ip_address = :ip'
        );
        $stmt->execute([
            ':email' => $email,
            ':ip'    => self::getClientIp()
        ]);
    }

    /**
     * Get remaining lockout time in seconds, or 0 if not locked.
     */
    public static function getLockoutRemaining(string $email): int {
        $db = Database::getInstance();
        $ip = self::getClientIp();

        $stmt = $db->prepare(
            'SELECT MIN(attempted_at) as first_attempt FROM login_attempts
             WHERE email = :email AND ip_address = :ip
             AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->execute([
            ':email'   => $email,
            ':ip'      => $ip,
            ':minutes' => LOGIN_LOCKOUT_MINUTES
        ]);
        $row = $stmt->fetch();

        if (!$row || !$row['first_attempt']) return 0;

        $firstAttempt = strtotime($row['first_attempt']);
        $lockoutEnd = $firstAttempt + (LOGIN_LOCKOUT_MINUTES * 60);
        $remaining = $lockoutEnd - time();

        return max(0, $remaining);
    }

    private static function getClientIp(): string {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '127.0.0.1';
    }
}
