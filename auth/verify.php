<?php
require 'config.php';

if (!isset($_SESSION['temp_email'])) {
    header("Location: register.php");
    exit;
}

$email = $_SESSION['temp_email'];
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entered_otp = trim($_POST['otp']);

    $query = $conn->query("SELECT id, otp FROM users WHERE email='$email' AND is_verified=0");
    if ($query->num_rows > 0) {
        $user = $query->fetch_assoc();
        if ($user['otp'] === $entered_otp) {
            $conn->query("UPDATE users SET is_verified=1, otp=NULL WHERE email='$email'");
            unset($_SESSION['temp_email']);
            header("Location: login.php?msg=verified");
            exit;
        } else {
            $error = "Invalid OTP code. Please try again.";
        }
    } else {
        $error = "Account already verified or not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Email | HeyUPI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background-color: #F7F9FC; }</style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-[0_20px_40px_rgba(0,0,0,0.08)] border border-slate-200 overflow-hidden text-center p-8">
        <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 border border-blue-100">
            <i data-lucide="mail-check" class="w-8 h-8"></i>
        </div>
        <h2 class="text-2xl font-bold text-slate-900 mb-2">Check your email</h2>
        <p class="text-slate-500 text-sm mb-6">We sent a 6-digit verification code to <br><b class="text-slate-800"><?php echo htmlspecialchars($email); ?></b></p>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm font-medium mb-4 border border-red-100 text-left">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-6">
                <input type="text" name="otp" required maxlength="6" placeholder="Enter 6-digit code" class="w-full px-4 py-4 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none transition-all text-center text-2xl font-bold tracking-[0.5em] text-slate-800">
            </div>
            <button type="submit" class="w-full bg-[#0052FF] hover:bg-[#0040CC] text-white font-bold py-3.5 rounded-xl transition-all shadow-[0_4px_14px_rgba(0,82,255,0.3)]">
                Verify Account
            </button>
        </form>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
