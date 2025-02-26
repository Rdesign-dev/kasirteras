<?php
require_once 'includes/auth_check.php';
require_once 'config/database.php';

// Add session debugging
if (!isset($_SESSION['branch_id'])) {
    error_log('Session data: ' . print_r($_SESSION, true));
    header('Location: auth.php');
    exit;
}

// Verify branch_id exists in database
$branchCheck = $pdo->prepare("SELECT id FROM branch WHERE id = ?");
$branchCheck->execute([$_SESSION['branch_id']]);
if (!$branchCheck->fetch()) {
    error_log('Invalid branch_id: ' . $_SESSION['branch_id']);
    session_destroy();
    header('Location: auth.php');
    exit;
}

$searchResult = null;
$error = '';
$success = '';

// Handle Top-up
if (isset($_POST['topup']) && isset($_POST['amount']) && isset($_POST['payment_method'])) {
    try {
        if (!isset($_SESSION['branch_id'])) {
            throw new Exception('Branch ID tidak ditemukan');
        }
        
        // Validate payment method
        if (!in_array($_POST['payment_method'], ['cash', 'transferBank'])) { // Changed 'transfer' to 'transferBank'
            throw new Exception('Metode pembayaran tidak valid');
        }
        
        // Validate amount
        if (!is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
            throw new Exception('Nominal tidak valid');
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
        $success = 'Top-up berhasil dengan metode ' . 
                  ($_POST['payment_method'] === 'cash' ? 'Tunai' : 'Transfer Bank');
                  
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}

// Handle Transaction (Payment)
if (isset($_POST['transaction']) && isset($_POST['amount'])) {
    try {
        $pdo->beginTransaction();
        
        // Check balance
        $balanceStmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $balanceStmt->execute([$_POST['user_id']]);
        $currentBalance = $balanceStmt->fetchColumn();

        if ($currentBalance >= $_POST['amount']) {
            // Update user balance
            $updateStmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
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
                ) VALUES (?, ?, ?, COALESCE(?, NULL), ?, ?, NOW())
            ");
            $insertStmt->execute([
                $_POST['user_id'],
                'Teras Japan Payment',
                $_POST['amount'],
                $_SESSION['branch_id'] ?? null,
                $_SESSION['user_id'],
                'Balance'
            ]);

            $pdo->commit();
            $success = 'Transaksi berhasil!';
        } else {
            throw new Exception('Saldo tidak mencukupi');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}

if (isset($_POST['search']) && !empty($_POST['member_number'])) {
    $stmt = $pdo->prepare("SELECT id, name, balance, phone_number, poin, level_id FROM users WHERE phone_number = ?");
    $stmt->execute([$_POST['member_number']]);
    $searchResult = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<html>
<head>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto p-4">
        <!-- Header -->
        <div class="flex justify-between items-center bg-blue-500 p-2 rounded">
            <div class="flex flex-col text-white">
                <span class="font-semibold">
                    <?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'No Name Found'; ?>
                </span>
                <span class="text-sm opacity-90">
                    <?php 
                    if (isset($_SESSION['branch_name'], $_SESSION['branch_code'])) {
                        echo "Cabang: " . htmlspecialchars($_SESSION['branch_name']) . 
                             " (" . htmlspecialchars($_SESSION['branch_code']) . ")";
                    } else {
                        echo isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'super_admin' 
                            ? 'Super Admin' 
                            : 'No Branch Assigned';
                    }
                    ?>
                </span>
            </div>
            <div class="flex items-center">
                <?php if (isset($_SESSION['photo']) && !empty($_SESSION['photo'])): ?>
                    <img src="<?php echo htmlspecialchars($_SESSION['photo']); ?>" 
                         alt="Profile" class="w-8 h-8 rounded-full">
                <?php else: ?>
                    <div class="w-8 h-8 bg-white rounded-full"></div>
                <?php endif; ?>
                <a href="logout.php" class="ml-4 text-white hover:text-gray-200">Logout</a>
            </div>
        </div>

        <!-- Search Section -->
        <div class="mt-4 flex">
            <form method="POST" class="flex w-full">
                <input type="text" name="member_number" 
                       placeholder="Masukkan Nomor Telepon" 
                       class="flex-grow p-2 border rounded-l"
                       value="<?php echo isset($_POST['member_number']) ? htmlspecialchars($_POST['member_number']) : ''; ?>">
                <button type="submit" name="search" 
                        class="bg-yellow-500 p-2 rounded-r text-white hover:bg-yellow-600">
                    Cari
                </button>
            </form>
        </div>

        <?php if (isset($_POST['search'])): ?>
            <?php if ($searchResult): ?>
                <!-- User Found Section -->
                <div class="mt-4 bg-green-600 p-2 rounded text-white">
                    Data user Ditemukan
                </div>

                <!-- User Info Section -->
                <div class="mt-2 bg-white p-4 rounded shadow">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-600">Nama:</p>
                            <p class="font-semibold"><?php echo htmlspecialchars($searchResult['name']); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">No. Telepon:</p>
                            <p class="font-semibold"><?php echo htmlspecialchars($searchResult['phone_number']); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Saldo:</p>
                            <p class="font-semibold" data-balance>Rp.<?php echo number_format($searchResult['balance'], 0, ',', '.'); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Poin:</p>
                            <p class="font-semibold" data-poin><?php echo number_format($searchResult['poin'], 0); ?></p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- User Not Found Section -->
                <div class="mt-4 bg-red-600 p-2 rounded text-white">
                    Data user tidak ditemukan
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Actions Section -->
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Top Up Saldo -->
            <div class="bg-white p-4 rounded shadow">
                <h2 class="text-center mb-4 text-blue-600">TOP UP Saldo</h2>
                <?php if ($searchResult): ?>
                    <form id="topupForm" method="POST" class="flex flex-col gap-2" onsubmit="return false;">
                        <input type="hidden" name="user_id" value="<?php echo $searchResult['id']; ?>">
                        <input type="number" name="amount" 
                               placeholder="Masukkan nominal" 
                               class="p-2 border rounded" required>
                        <button type="button" onclick="showPaymentModal()"
                                class="bg-blue-500 p-2 rounded text-white hover:bg-blue-600">Submit</button>
                    </form>
                <?php else: ?>
                    <p class="text-center text-gray-500">Silakan cari member terlebih dahulu</p>
                <?php endif; ?>
            </div>

            <!-- Transaksi -->
            <div class="bg-white p-4 rounded shadow">
                <h2 class="text-center mb-4 text-red-600">Transaksi</h2>
                <?php if ($searchResult): ?>
                    <form id="transactionForm" method="POST" class="flex flex-col gap-2" onsubmit="return false;">
                        <input type="hidden" name="user_id" value="<?php echo $searchResult['id']; ?>">
                        <input type="number" name="amount" 
                               placeholder="Masukkan nominal" 
                               class="p-2 border rounded" required>
                        <button type="button" onclick="showTransactionModal()"
                                class="bg-red-500 p-2 rounded text-white">Submit</button>
                    </form>
                <?php else: ?>
                    <p class="text-center text-gray-500">Silakan cari member terlebih dahulu</p>
                <?php endif; ?>
            </div>

            <!-- Redeem Voucher -->
            <div class="bg-white p-4 rounded shadow col-span-1 md:col-span-2">
                <h2 class="text-center mb-4 text-green-600">Redeem Voucher</h2>
                <?php if ($searchResult): ?>
                    <?php
                    // Fetch available vouchers for the user
                    $voucherStmt = $pdo->prepare("
                        SELECT rv.*, r.title 
                        FROM redeem_voucher rv
                        JOIN rewards r ON rv.reward_id = r.id
                        WHERE rv.user_id = ? 
                        AND rv.status = 'Available'
                        AND rv.expires_at > NOW()
                        ORDER BY rv.expires_at ASC
                    ");
                    $voucherStmt->execute([$searchResult['id']]);
                    $vouchers = $voucherStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if ($vouchers): 
                        foreach ($vouchers as $voucher):
                    ?>
                        <div class="flex items-center justify-between border border-gray-300 p-2 rounded mb-2">
                            <div class="flex items-center space-x-4">
                                <img alt="Voucher Image" class="w-16 h-16 rounded" height="100" 
                                     src="https://storage.googleapis.com/a1aa/image/FH4HBatpmNcVIsG-Dw-ELgZQG-ENO7SkKCzL15YzrB4.jpg" 
                                     width="100"/>
                                <div>
                                    <p class="font-bold"><?php echo htmlspecialchars($voucher['title']); ?></p>
                                    <p class="text-sm text-gray-600">Kode: <?php echo htmlspecialchars($voucher['kode_voucher']); ?></p>
                                    <p class="text-sm text-gray-600">
                                        Masa Berlaku: <?php echo date('d/m/Y', strtotime($voucher['expires_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <button onclick="useVoucher(<?php echo $voucher['redeem_id']; ?>, this)" 
                                    class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                                Gunakan
                            </button>
                        </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <p class="text-center text-gray-500">Tidak ada voucher yang tersedia</p>
                    <?php 
                    endif;
                    ?>
                <?php else: ?>
                    <p class="text-center text-gray-500">Silakan cari member terlebih dahulu</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Payment Method Modal -->
    <div id="paymentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">Pilih Metode Pembayaran</h3>
            <form id="paymentForm" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="user_id" id="modal_user_id">
                <input type="hidden" name="amount" id="modal_amount">
                <input type="hidden" name="payment_method" id="modal_payment_method">
                <button type="button" onclick="submitPayment('cash')" 
                        class="bg-green-500 text-white p-2 rounded hover:bg-green-600">Cash</button>
                <button type="button" onclick="submitPayment('transferBank')" 
                        class="bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Transfer Bank</button>
                <button type="button" onclick="hidePaymentModal()" 
                        class="bg-gray-500 text-white p-2 rounded hover:bg-gray-600">Batal</button>
            </form>
        </div>
    </div>

    <!-- Transaction Payment Method Modal -->
    <div id="transactionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">Pilih Metode Pembayaran</h3>
            <form id="transactionPaymentForm" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="user_id" id="trans_user_id">
                <input type="hidden" name="amount" id="trans_amount">
                <input type="hidden" name="payment_method" id="trans_payment_method">
                <button type="button" onclick="submitTransaction('Balance')" 
                        class="bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Saldo</button>
                <button type="button" onclick="submitTransaction('cash')" 
                        class="bg-green-500 text-white p-2 rounded hover:bg-green-600">Cash</button>
                <button type="button" onclick="submitTransaction('transferBank')" 
                        class="bg-yellow-500 text-white p-2 rounded hover:bg-yellow-600">Transfer Bank</button>
                <button type="button" onclick="hideTransactionModal()" 
                        class="bg-gray-500 text-white p-2 rounded hover:bg-gray-600">Batal</button>
            </form>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4 text-green-600">Berhasil!</h3>
            <p id="successMessage" class="mb-4"></p>
            <button onclick="hideSuccessModal()" 
                    class="bg-green-500 text-white p-2 rounded w-full">OK</button>
        </div>
    </div>

    <script>
    let isProcessing = false;

    function showPaymentModal() {
        const form = document.getElementById('topupForm');
        const userId = form.querySelector('[name="user_id"]').value;
        const amount = form.querySelector('[name="amount"]').value;
        
        if (!amount || amount <= 0) {
            alert('Silakan masukkan nominal yang valid');
            return;
        }
        
        document.getElementById('modal_user_id').value = userId;
        document.getElementById('modal_amount').value = amount;
        document.getElementById('paymentModal').classList.remove('hidden');
    }

    function hidePaymentModal() {
        document.getElementById('paymentModal').classList.add('hidden');
    }

    async function updateUserInfo(userId) {
        try {
            const response = await fetch('fetch_user_info.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `user_id=${userId}`
            });
            
            const data = await response.json();
            if (data.success) {
                // Update balance and points display
                document.querySelector('[data-balance]').textContent = 
                    'Rp.' + new Intl.NumberFormat('id-ID').format(data.balance);
                document.querySelector('[data-poin]').textContent = 
                    new Intl.NumberFormat('id-ID').format(data.poin);
            }
        } catch (error) {
            console.error('Error updating user info:', error);
        }
    }

    async function submitPayment(method) {
        if (isProcessing) return;
        isProcessing = true;
        
        try {
            // Make sure this matches your database ENUM values exactly
            const paymentMethod = method === 'transfer' ? 'transferBank' : 'cash';
            const userId = document.getElementById('modal_user_id').value;
            const amount = document.getElementById('modal_amount').value;
            
            const formData = new FormData();
            formData.append('topup', 'true');
            formData.append('payment_method', paymentMethod);
            formData.append('user_id', userId);
            formData.append('amount', amount);
            
            const response = await fetch('process_payment.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                hidePaymentModal();
                showSuccessModal(result.message);
                await updateUserInfo(userId);
                document.getElementById('topupForm').reset();
            } else {
                throw new Error(result.message || 'Terjadi kesalahan');
            }
        } catch (error) {
            alert(error.message);
        } finally {
            isProcessing = false;
        }
    }

    function showTransactionModal() {
        const form = document.getElementById('transactionForm');
        const userId = form.querySelector('[name="user_id"]').value;
        const amount = form.querySelector('[name="amount"]').value;
        
        if (!amount || amount <= 0) {
            alert('Silakan masukkan nominal yang valid');
            return;
        }
        
        document.getElementById('trans_user_id').value = userId;
        document.getElementById('trans_amount').value = amount;
        document.getElementById('transactionModal').classList.remove('hidden');
    }

    function hideTransactionModal() {
        document.getElementById('transactionModal').classList.add('hidden');
    }

    async function submitTransaction(paymentMethod) {
        if (isProcessing) return;
        isProcessing = true;
        
        try {
            const userId = document.getElementById('trans_user_id').value;
            const amount = document.getElementById('trans_amount').value;
            
            const formData = new FormData();
            formData.append('transaction', 'true');
            formData.append('payment_method', paymentMethod);
            formData.append('user_id', userId);
            formData.append('amount', amount);

            const response = await fetch('process_transaction.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
        
            if (result.success) {
                hideTransactionModal();
                showSuccessModal(result.message);
                await updateUserInfo(userId);
                document.getElementById('transactionForm').reset();
            } else {
                throw new Error(result.message || 'Terjadi kesalahan');
            }
        } catch (error) {
            alert(error.message);
        } finally {
            isProcessing = false;
        }
    }

    async function useVoucher(redeemId, element) {
        if (isProcessing) return;
        isProcessing = true;
        
        try {
            const response = await fetch('use_voucher.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `redeem_id=${redeemId}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Find and remove the voucher element
                const voucherCard = element.closest('.flex.items-center.justify-between');
                if (voucherCard) {
                    voucherCard.remove();
                }
                
                // Check if there are any vouchers left
                const voucherContainer = document.querySelector('.bg-white.p-4.rounded.shadow.col-span-1.md\\:col-span-2');
                const remainingVouchers = voucherContainer.querySelectorAll('.flex.items-center.justify-between');
                
                // If no vouchers left, show "no vouchers" message
                if (remainingVouchers.length === 0) {
                    const noVouchersMsg = document.createElement('p');
                    noVouchersMsg.className = 'text-center text-gray-500';
                    noVouchersMsg.textContent = 'Tidak ada voucher yang tersedia';
                    voucherContainer.appendChild(noVouchersMsg);
                }
                
                showSuccessModal(result.message);
            } else {
                throw new Error(result.message || 'Terjadi kesalahan');
            }
        } catch (error) {
            alert(error.message);
        } finally {
            isProcessing = false;
        }
    }

    function showSuccessModal(message) {
        document.getElementById('successMessage').textContent = message;
        document.getElementById('successModal').classList.remove('hidden');
    }

    function hideSuccessModal() {
        document.getElementById('successModal').classList.add('hidden');
    }

    // Add data attributes to the balance and poin elements
    document.addEventListener('DOMContentLoaded', function() {
        const balanceElement = document.querySelector('.font-semibold:contains("Rp.")');
        const poinElement = document.querySelector('.font-semibold:contains("poin")');
        
        if (balanceElement) balanceElement.setAttribute('data-balance', '');
        if (poinElement) poinElement.setAttribute('data-poin', '');
    });

    // Update form event listeners
    document.getElementById('topupForm').onsubmit = () => false;
    document.querySelector('form[name="transaction"]')?.addEventListener('submit', submitTransaction);
    </script>
</body>
</html>