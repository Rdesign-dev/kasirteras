<?php
require_once 'includes/auth_check.php';
require_once 'config/database.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['redeem_id'])) {
        throw new Exception('ID voucher tidak ditemukan');
    }
    
    $pdo->beginTransaction();
    
    // Check if voucher exists and is available
    $voucherStmt = $pdo->prepare("
        SELECT * FROM redeem_voucher 
        WHERE redeem_id = ? 
        AND status = 'Available' 
        AND expires_at > NOW()
    ");
    $voucherStmt->execute([$_POST['redeem_id']]);
    $voucher = $voucherStmt->fetch();
    
    if (!$voucher) {
        throw new Exception('Voucher tidak valid atau sudah kadaluarsa');
    }
    
    // Update voucher status
    $updateStmt = $pdo->prepare("
        UPDATE redeem_voucher 
        SET status = 'Used' 
        WHERE redeem_id = ?
    ");
    $updateStmt->execute([$_POST['redeem_id']]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Voucher berhasil digunakan'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}