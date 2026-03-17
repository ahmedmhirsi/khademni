<?php
/**
 * KHADEMNI — Configuration
 */

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'khademni');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// JWT
define('JWT_SECRET', 'khademni_jwt_s3cr3t_k3y_2025_ch4ng3_m3_1n_pr0duct10n');
define('JWT_EXPIRY', 86400); // 24 hours in seconds
define('JWT_ISSUER', 'khademni');

// Rate Limiting
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// File Uploads
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_CV_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// App
define('APP_URL', 'http://localhost:8081/khadelni');
define('APP_NAME', 'Khademni');
