<?php
/**
 * KHADEMNI — Profile Controller
 * Handles: get profile, update profile, change password, file uploads
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Middleware.php';
require_once __DIR__ . '/config.php';

class Profile {

    /**
     * GET /api/profile
     */
    public static function getProfile(): void {
        $auth = Middleware::authenticate();
        $db = Database::getInstance();

        // Get user base
        $stmt = $db->prepare('SELECT id, name, email, role, email_verified_at, created_at FROM users WHERE id = :id');
        $stmt->execute([':id' => $auth['sub']]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            return;
        }

        // Get role-specific profile
        $profile = [];
        if ($user['role'] === 'candidate') {
            $stmt = $db->prepare('SELECT * FROM candidate_profiles WHERE user_id = :uid');
            $stmt->execute([':uid' => $user['id']]);
            $profile = $stmt->fetch() ?: [];
            // Decode skills JSON
            if (!empty($profile['skills'])) {
                $profile['skills'] = json_decode($profile['skills'], true);
            }
        } elseif ($user['role'] === 'company') {
            $stmt = $db->prepare('SELECT * FROM company_profiles WHERE user_id = :uid');
            $stmt->execute([':uid' => $user['id']]);
            $profile = $stmt->fetch() ?: [];
        }

        echo json_encode([
            'success' => true,
            'user'    => $user,
            'profile' => $profile
        ]);
    }

    /**
     * PUT /api/profile
     */
    public static function updateProfile(): void {
        $auth = Middleware::authenticate();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $db = Database::getInstance();

        // Get current user role
        $stmt = $db->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute([':id' => $auth['sub']]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            return;
        }

        // Update user name if provided
        if (!empty($data['name'])) {
            $stmt = $db->prepare('UPDATE users SET name = :name WHERE id = :id');
            $stmt->execute([':name' => trim($data['name']), ':id' => $auth['sub']]);
        }

        if ($user['role'] === 'candidate') {
            self::updateCandidateProfile($auth['sub'], $data);
        } elseif ($user['role'] === 'company') {
            self::updateCompanyProfile($auth['sub'], $data);
        }

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
    }

    private static function updateCandidateProfile(int $userId, array $data): void {
        $db = Database::getInstance();

        $fields = [];
        $params = [':uid' => $userId];

        if (array_key_exists('location', $data)) {
            $fields[] = 'location = :location';
            $params[':location'] = trim($data['location']);
        }
        if (array_key_exists('bio', $data)) {
            $fields[] = 'bio = :bio';
            $params[':bio'] = trim($data['bio']);
        }
        if (array_key_exists('experience_years', $data)) {
            $fields[] = 'experience_years = :exp';
            $params[':exp'] = (int)$data['experience_years'];
        }
        if (array_key_exists('skills', $data)) {
            $skills = is_array($data['skills']) ? $data['skills'] : [];
            $fields[] = 'skills = :skills';
            $params[':skills'] = json_encode($skills);
        }

        if (!empty($fields)) {
            $sql = 'UPDATE candidate_profiles SET ' . implode(', ', $fields) . ' WHERE user_id = :uid';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
    }

    private static function updateCompanyProfile(int $userId, array $data): void {
        $db = Database::getInstance();

        $fields = [];
        $params = [':uid' => $userId];

        if (array_key_exists('company_name', $data)) {
            $fields[] = 'company_name = :cname';
            $params[':cname'] = trim($data['company_name']);
        }
        if (array_key_exists('description', $data)) {
            $fields[] = 'description = :desc';
            $params[':desc'] = trim($data['description']);
        }
        if (array_key_exists('website', $data)) {
            $v = new Validator();
            $v->url('website', $data['website']);
            if (!$v->fails()) {
                $fields[] = 'website = :web';
                $params[':web'] = trim($data['website']);
            }
        }
        if (array_key_exists('location', $data)) {
            $fields[] = 'location = :location';
            $params[':location'] = trim($data['location']);
        }

        if (!empty($fields)) {
            $sql = 'UPDATE company_profiles SET ' . implode(', ', $fields) . ' WHERE user_id = :uid';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
    }

    /**
     * POST /api/profile/password
     */
    public static function changePassword(): void {
        $auth = Middleware::authenticate();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $current = $data['current_password'] ?? '';
        $newPass = $data['new_password'] ?? '';
        $confirm = $data['password_confirm'] ?? '';

        $v = new Validator();
        $v->required('current_password', $current, 'Current password')
          ->required('new_password', $newPass, 'New password')
          ->minLength('new_password', $newPass, 8, 'New password')
          ->match('password_confirm', $newPass, $confirm);

        if ($v->fails()) {
            http_response_code(422);
            echo json_encode(['success' => false, 'errors' => $v->errors()]);
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT password FROM users WHERE id = :id');
        $stmt->execute([':id' => $auth['sub']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            return;
        }

        $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare('UPDATE users SET password = :password WHERE id = :id');
        $stmt->execute([':password' => $hashed, ':id' => $auth['sub']]);

        echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
    }

    /**
     * POST /api/profile/upload — Handle CV or logo upload
     */
    public static function uploadFile(): void {
        $auth = Middleware::authenticate();

        if (empty($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
            return;
        }

        $file = $_FILES['file'];
        $type = $_POST['type'] ?? 'cv'; // 'cv' or 'logo'

        // Validate file size
        if ($file['size'] > MAX_FILE_SIZE) {
            http_response_code(413);
            echo json_encode(['success' => false, 'message' => 'File is too large. Maximum size is 5MB.']);
            return;
        }

        // Validate file type
        $mime = mime_content_type($file['tmp_name']);
        $allowed = ($type === 'logo') ? ALLOWED_IMAGE_TYPES : ALLOWED_CV_TYPES;

        if (!in_array($mime, $allowed)) {
            http_response_code(415);
            $allowed_str = ($type === 'logo') ? 'JPEG, PNG, WebP, GIF' : 'PDF, DOC, DOCX';
            echo json_encode(['success' => false, 'message' => "Invalid file type. Allowed: $allowed_str."]);
            return;
        }

        // Create upload directory
        $subDir = ($type === 'logo') ? 'logos' : 'cvs';
        $uploadDir = UPLOAD_DIR . $subDir . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $auth['sub'] . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save file. Please try again.']);
            return;
        }

        // Update database
        $relativePath = "uploads/$subDir/$filename";
        $db = Database::getInstance();

        if ($type === 'logo') {
            $stmt = $db->prepare('UPDATE company_profiles SET logo_path = :path WHERE user_id = :uid');
        } else {
            $stmt = $db->prepare('UPDATE candidate_profiles SET cv_path = :path WHERE user_id = :uid');
        }
        $stmt->execute([':path' => $relativePath, ':uid' => $auth['sub']]);

        echo json_encode([
            'success'  => true,
            'message'  => ucfirst($type) . ' uploaded successfully.',
            'file_path' => $relativePath
        ]);
    }
}
