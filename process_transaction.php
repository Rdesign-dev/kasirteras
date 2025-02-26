<?php
require_once 'includes/auth_check.php';
require_once 'config/database.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['transaction'], $_POST['amount'], $_POST['user_id'], $_POST['payment_method'])) {
        throw new Exception('Missing required fields');
    }

    $pdo->beginTransaction();
    
    // If paying with Balance, check user's balance
    if ($_POST['payment_method'] === 'Balance') {
        $balanceStmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $balanceStmt->execute([$_POST['user_id']]);
        $currentBalance = $balanceStmt->fetchColumn();

        if ($currentBalance < $_POST['amount']) {
            throw new Exception('Saldo tidak mencukupi');
        }

        // Update user balance
        $updateStmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $updateStmt->execute([$_POST['amount'], $_POST['user_id']]);
    }

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
        ) VALUES (?, 'Teras Japan Payment', ?, ?, ?, ?, NOW())
    ");
    
    $insertStmt->execute([
        $_POST['user_id'],
        $_POST['amount'],
        $_SESSION['branch_id'],
        $_SESSION['user_id'],
        $_POST['payment_method']
    ]);

    $pdo->commit();
    
    $methodText = [
        'Balance' => 'Saldo',
        'cash' => 'Tunai',
        'transferBank' => 'Transfer Bank'
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Transaksi berhasil menggunakan ' . $methodText[$_POST['payment_method']]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}