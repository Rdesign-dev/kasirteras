<?php
require_once 'includes/auth_check.php';
require_once 'config/database.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['topup'], $_POST['amount'], $_POST['payment_method'], $_POST['user_id'])) {
        throw new Exception('Missing required fields');
    }
    
    // Validate payment method
    if (!in_array($_POST['payment_method'], ['cash', 'transferBank'])) {
        throw new Exception('Metode pembayaran tidak valid: ' . $_POST['payment_method']);
    }
    
    $pdo->beginTransaction();
    
    // Update user balance
    $updateStmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $updateStmt->execute([$_POST['amount'], $_POST['user_id']]);

    // Record transaction
    $insertStmt = $pdo->prepare("
        INSERT INTO transactions (
            user_id, 
            transaction_type, 
            amount, 
            branch_id, 
            account_cashier_id,
            payment_method,
            created_at
        ) VALUES (?, 'Balance Top-up', ?, ?, ?, ?, NOW())
    ");
    
    $insertStmt->execute([
        $_POST['user_id'],
        $_POST['amount'],
        $_SESSION['branch_id'],
        $_SESSION['user_id'],
        $_POST['payment_method']
    ]);

    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Top-up berhasil dengan metode ' . 
                    ($_POST['payment_method'] === 'cash' ? 'Tunai' : 'Transfer Bank')
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}