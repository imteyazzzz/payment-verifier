<?php
require 'dbinfo.php';

if (isset($_SESSION['merchant_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    if ($action == 'signup') {
        $check = $conn->query("SELECT * FROM users WHERE email='$email'");
        if ($check->num_rows > 0) {
            $error = 'Email already exists!';
        } else {
            $merchant_id = 'MCH_' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
            $conn->query("INSERT INTO users (merchant_id, email, password) VALUES ('$merchant_id', '$email', '$hashed_pass')");
            $_SESSION['merchant_id'] = $merchant_id;
            header("Location: dashboard.php");
            exit;
        }
    } elseif ($action == 'login') {
        $result = $conn->query("SELECT * FROM users WHERE email='$email'");
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['merchant_id'] = $user['merchant_id'];
                header("Location: dashboard.php");
                exit;
            } else {
                $error = 'Invalid Password!';
            }
        } else {
            $error = 'Email not found!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auth | Merchant System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .mesh-bg { background-color: #f9f9fb; background-image: radial-gradient(at 40% 20%, hsla(228,100%,74%,0.4) 0px, transparent 50%), radial-gradient(at 80% 0%, hsla(189,100%,56%,0.4) 0px, transparent 50%), radial-gradient(at 0% 50%, hsla(355,100%,93%,0.4) 0px, transparent 50%); background-attachment: fixed; }
        .glass-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.5); }
    </style>
</head>
<body class="mesh-bg flex items-center justify-center min-h-screen p-4">
    <div class="glass-panel p-8 rounded-3xl shadow-xl w-full max-w-sm">
        <h2 class="text-2xl font-extrabold text-gray-900 text-center mb-6" id="title">Login to Gateway</h2>
        
        <?php if($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg text-sm mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" id="authForm" class="space-y-4">
            <input type="hidden" name="action" id="formAction" value="login">
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Email</label>
                <input type="email" name="email" required class="w-full px-4 py-3 rounded-xl bg-white/50 border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Password</label>
                <input type="password" name="password" required class="w-full px-4 py-3 rounded-xl bg-white/50 border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <button type="submit" id="submitBtn" class="w-full bg-gray-900 text-white font-medium py-3 rounded-xl shadow-md">Login</button>
        </form>
        
        <p class="text-center text-sm text-gray-500 mt-4 cursor-pointer hover:text-blue-600" onclick="toggleAuth()">Don't have an account? Sign Up</p>
    </div>

    <script>
        let isLogin = true;
        function toggleAuth() {
            isLogin = !isLogin;
            document.getElementById('title').innerText = isLogin ? "Login to Gateway" : "Create Merchant Account";
            document.getElementById('formAction').value = isLogin ? "login" : "signup";
            document.getElementById('submitBtn').innerText = isLogin ? "Login" : "Sign Up";
            document.querySelector('.cursor-pointer').innerText = isLogin ? "Don't have an account? Sign Up" : "Already have an account? Login";
        }
    </script>
</body>
</html>
