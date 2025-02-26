<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("
            SELECT a.*, b.branch_name, b.branch_code 
            FROM accounts a 
            LEFT JOIN branch b ON a.branch_id = b.id 
            WHERE a.username = ? AND a.password = ?
        ");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Debug line - remove after testing
            error_log('User data: ' . print_r($user, true));
            
            // Store all necessary session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['Name'];
            $_SESSION['account_type'] = $user['account_type'];
            $_SESSION['photo'] = $user['photo'];
            $_SESSION['branch_id'] = $user['branch_id']; // Add branch_id to session
            $_SESSION['branch_name'] = $user['branch_name'];
            $_SESSION['branch_code'] = $user['branch_code'];
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>
<html>
<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gradient-to-r from-blue-400 to-purple-500 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg w-80">
        <h2 class="text-center text-2xl font-semibold mb-6 text-gray-700">Login</h2>
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-4">
                <input type="text" name="username" placeholder="Username" 
                       class="w-full p-3 bg-gray-200 text-center rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <input type="password" name="password" placeholder="Password" 
                       class="w-full p-3 bg-gray-200 text-center rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="text-center mb-4">
                <button type="submit" 
                        class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600 transition duration-300">
                    Sign In
                </button>
            </div>
            <div class="text-center">
                <a href="#" class="text-blue-500 hover:underline">Forgot Password?</a>
            </div>
        </form>
    </div>
</body>
</html>