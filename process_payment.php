<?php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
require_once 'includes/helpers.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['topup'], $_POST['amount'], $_POST['payment_method'], $_POST['user_id'])) {
        throw new Exception('Missing required fields');
    }
    
    $pdo->beginTransaction();
    
    // Generate transaction code
    $transactionCode = generateTransactionCode(
        $_SESSION['branch_id'],
        $_SESSION['branch_code'],
        $_SESSION['user_id'],
        'Balance Top-up',
        $_POST['payment_method']
    );
    
    // Update user balance
    $updateStmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $updateStmt->execute([$_POST['amount'], $_POST['user_id']]);

    // Record transaction with code
    $insertStmt = $pdo->prepare("
        INSERT INTO transactions (
            transaction_codes,
            user_id, 
            transaction_type, 
            amount, 
            branch_id, 
            account_cashier_id,
            payment_method,
            created_at
        ) VALUES (?, ?, 'Balance Top-up', ?, ?, ?, ?, NOW())
    ");
    
    $insertStmt->execute([
        $transactionCode,
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
                    ($_POST['payment_method'] === 'cash' ? 'Tunai' : 'Transfer Bank'),
        'transaction_code' => $transactionCode
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}