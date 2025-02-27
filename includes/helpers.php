<?php

function generateTransactionCode($branchId, $branchCode, $cashierId, $transactionType, $paymentMethod) {
    // Get current date in YYMMDD format
    $date = date('ymd');
    
    try {
        global $pdo;
        
        // Get current transaction count for the branch
        $stmt = $pdo->prepare("SELECT transaction_count FROM branch WHERE id = ?");
        $stmt->execute([$branchId]);
        $currentCount = $stmt->fetchColumn();
        
        // Increment count
        $newCount = $currentCount + 1;
        
        // Update branch transaction count
        $updateStmt = $pdo->prepare("UPDATE branch SET transaction_count = ? WHERE id = ?");
        $updateStmt->execute([$newCount, $branchId]);
        
        // Format transaction type
        $typeCode = $transactionType === 'Balance Top-up' ? 'TU' : 'TJP';
        
        // Format payment method
        $paymentCode = [
            'cash' => 'CSH',
            'transferBank' => 'TFB',
            'Balance' => 'BM'
        ][$paymentMethod];
        
        // Generate sequence number (4 digits)
        $sequence = str_pad($newCount, 4, '0', STR_PAD_LEFT);
        
        // Combine all parts without hyphens
        return sprintf('TX%s%s%s%s%s%s%s',
            $branchId,
            $branchCode,
            $cashierId,
            $typeCode,
            $paymentCode,
            $date,
            $sequence
        );
        
    } catch (Exception $e) {
        error_log('Error generating transaction code: ' . $e->getMessage());
        throw new Exception('Failed to generate transaction code');
    }
}