<?php
/**
 * KHADEMNI — JWT (JSON Web Token) Implementation
 * Pure PHP, no external dependencies. Uses HMAC-SHA256.
 */

require_once __DIR__ . '/config.php';

class JWT {
    /**
     * Encode a payload into a JWT string.
     */
    public static function encode(array $payload): string {
        $header = self::base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]));

        // Add standard claims
        $payload['iss'] = JWT_ISSUER;
        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_EXPIRY;

        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payloadEncoded", JWT_SECRET, true)
        );

        return "$header.$payloadEncoded.$signature";
    }

    /**
     * Decode and validate a JWT string. Returns payload or null on failure.
     */
    public static function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expectedSignature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
        );

        if (!hash_equals($expectedSignature, $signature)) return null;

        $data = json_decode(self::base64UrlDecode($payload), true);
        if (!$data) return null;

        // Check expiration
        if (isset($data['exp']) && $data['exp'] < time()) return null;

        return $data;
    }

    /**
     * Extract user data from Authorization header.
     * Returns ['id' => ..., 'email' => ..., 'role' => ...] or null.
     */
    public static function getUser(): ?array {
        $header = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $header = $headers['Authorization'];
            } elseif (isset($headers['authorization'])) {
                $header = $headers['authorization'];
            }
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) return null;
        return self::decode($m[1]);
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
