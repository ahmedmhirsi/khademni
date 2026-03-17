<?php
/**
 * KHADEMNI — API Router
 * Single entry point for all /api/* requests.
 */

// CORS Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Load controllers
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Profile.php';

// Parse route — supports mod_rewrite, PATH_INFO, and ?route= query param
$path = '';

// Method 1: ?route= query parameter (always works, no mod_rewrite needed)
if (!empty($_GET['route'])) {
    $path = '/' . trim($_GET['route'], '/');
}
// Method 2: PATH_INFO (e.g. /api/index.php/register)
elseif (!empty($_SERVER['PATH_INFO'])) {
    $path = '/' . trim($_SERVER['PATH_INFO'], '/');
}
// Method 3: mod_rewrite (e.g. /khadelni/api/register)
else {
    $requestUri = $_SERVER['REQUEST_URI'];
    $basePath = '/khadelni/api';
    $parsed = parse_url($requestUri, PHP_URL_PATH);
    // Remove index.php if present
    $parsed = str_replace('/index.php', '', $parsed);
    $path = str_replace($basePath, '', $parsed);
    $path = '/' . trim($path, '/');
}

$method = $_SERVER['REQUEST_METHOD'];

// Route table
$route = "$method $path";

try {
    switch ($route) {
        // ---- Auth ----
        case 'POST /register':
            Auth::register();
            break;

        case 'POST /login':
            Auth::login();
            break;

        case 'POST /logout':
            Auth::logout();
            break;

        case 'POST /password-reset':
            Auth::requestPasswordReset();
            break;

        case 'POST /password-reset/confirm':
            Auth::confirmPasswordReset();
            break;

        case 'POST /auth/google':
            Auth::googleLogin();
            break;

        case 'GET /verify-email':
            Auth::verifyEmail();
            break;

        // ---- Profile ----
        case 'GET /profile':
            Profile::getProfile();
            break;

        case 'PUT /profile':
            Profile::updateProfile();
            break;

        case 'POST /profile/password':
            Profile::changePassword();
            break;

        case 'POST /profile/upload':
            Profile::uploadFile();
            break;

        // ---- 404 ----
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Endpoint not found.',
                'route'   => $route
            ]);
            break;
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please try again later.'
        // In dev: 'debug' => $e->getMessage()
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
