<?php
// Mock JWT sub and fetch profile
require_once __DIR__ . '/api/Database.php';

$db = Database::getInstance();
$stmt = $db->prepare('SELECT id, name, email, role, email_verified_at, created_at FROM users WHERE id = :id');
$stmt->execute([':id' => 2]); // Kaito's ID
$user = $stmt->fetch(PDO::FETCH_ASSOC);

var_dump($user);
echo json_encode(['user' => $user]);
