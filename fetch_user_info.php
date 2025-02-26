<?php
require_once 'includes/auth_check.php';
require_once 'config/database.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['user_id'])) {
        throw new Exception('User ID not provided');
    }
    
    $stmt = $pdo->prepare("SELECT balance, poin FROM users WHERE id = ?");
    $stmt->execute([$_POST['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    echo json_encode([
        'success' => true,
        'balance' => $user['balance'],
        'poin' => $user['poin']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}