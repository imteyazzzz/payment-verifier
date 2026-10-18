<?php
require 'config.php';

$error = '';
$msg = isset($_GET['msg']) && $_GET['msg'] == 'verified' ? "Account verified! You can now log in." : '';

// Generate Math Captcha for Login
if (!isset($_SESSION['login_captcha_ans']) || isset($_GET['reload'])) {
    $num1 = rand(1, 9);
    $num2 = rand(1, 9);
    $_SESSION['login_captcha_ans'] = $num1 + $num2;
    $_SESSION['login_captcha_str'] = "$num1 + $num2";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = $_POST['password'];
    $captcha = intval($_POST['captcha']);

    if ($captcha !== $_SESSION['login_captcha_ans']) {
        $error = "Incorrect Math CAPTCHA.";
        $num1 = rand(1, 9); $num2 = rand(1, 9);
        $_SESSION['login_captcha_ans'] = $num1 + $num2;
        $_SESSION['login_captcha_str'] = "$num1 + $num2";
    } else {
        $query = $conn->query("SELECT merchant_id, password_hash, is_verified FROM users WHERE email='$email'");
        
        if ($query->num_rows > 0) {
            $user = $query->fetch_assoc();
            if ($user['is_verified'] == 0) {
                $error = "Please verify your email first.";
            } elseif (password_verify($password, $user['password_hash'])) {
                $_SESSION['merchant_id'] = $user['merchant_id'];
                header("Location: ../dashboard.php"); // Assuming dashboard is one folder back
                exit;
            } else {
                $error = "Invalid credentials.";
            }
        } else {
            $error = "Invalid credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | HeyUPI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background-color: #F7F9FC; }</style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-[0_20px_40px_rgba(0,0,0,0.08)] border border-slate-200 overflow-hidden">
        <div class="p-8">
            <div class="flex justify-center mb-6">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center border border-blue-100">
                    <i data-lucide="lock" class="w-6 h-6"></i>
                </div>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 text-center mb-2">Welcome back</h2>
            <p class="text-slate-500 text-sm text-center mb-6">Sign in to your merchant dashboard.</p>

            <?php if($msg): ?>
                <div class="bg-green-50 text-green-600 p-3 rounded-lg text-sm font-medium mb-4 border border-green-100 flex items-center">
                    <i data-lucide="check-circle-2" class="w-4 h-4 mr-2"></i> <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm font-medium mb-4 border border-red-100 flex items-center">
                    <i data-lucide="alert-circle" class="w-4 h-4 mr-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email Address</label>
                    <input type="email" name="email" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none transition-all text-sm">
                </div>
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-xs font-bold text-slate-500 uppercase">Password</label>
                    </div>
                    <input type="password" name="password" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none transition-all text-sm">
                </div>

                <!-- Math CAPTCHA -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Verify you are human</label>
                        <div class="text-lg font-bold text-slate-800 tracking-wider"><?php echo $_SESSION['login_captcha_str']; ?> = ?</div>
                    </div>
                    <input type="number" name="captcha" required placeholder="Answer" class="w-24 px-3 py-2 text-center rounded-lg border border-slate-200 focus:border-blue-500 outline-none font-bold text-slate-800">
                </div>

                <button type="submit" class="w-full bg-[#0052FF] hover:bg-[#0040CC] text-white font-bold py-3.5 rounded-xl transition-all shadow-[0_4px_14px_rgba(0,82,255,0.3)] mt-2">
                    Sign in to Dashboard
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-slate-500">
                Don't have an account? <a href="register.php" class="text-blue-600 font-bold hover:underline">Sign up</a>
            </div>
        </div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
