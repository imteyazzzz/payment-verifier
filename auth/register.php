<?php
require 'config.php';

// Require PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Smart Auto-patch Database: Add missing columns automatically to prevent SQL errors
$required_columns = [
    'name' => 'VARCHAR(100) DEFAULT ""',
    'otp' => 'VARCHAR(10) DEFAULT NULL',
    'shop_name' => 'VARCHAR(100) DEFAULT ""',
    'is_verified' => 'TINYINT(1) DEFAULT 0',
    'password_hash' => 'VARCHAR(255) DEFAULT ""'
];

foreach ($required_columns as $col => $definition) {
    $check_col = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN $col $definition");
    }
}

$error = '';
$success = '';

// Generate Math Captcha
if (!isset($_SESSION['captcha_ans']) || isset($_GET['reload_captcha'])) {
    $num1 = rand(1, 9);
    $num2 = rand(1, 9);
    $_SESSION['captcha_ans'] = $num1 + $num2;
    $_SESSION['captcha_str'] = "$num1 + $num2";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string(trim($_POST['name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = $_POST['password'];
    $captcha = intval($_POST['captcha']);
    $privacy = isset($_POST['privacy']) ? true : false;

    if (!$privacy) {
        $error = "You must agree to the Privacy Policy.";
    } elseif ($captcha !== $_SESSION['captcha_ans']) {
        $error = "Incorrect Math CAPTCHA. Please try again.";
        // Reset Captcha on fail
        $num1 = rand(1, 9); $num2 = rand(1, 9);
        $_SESSION['captcha_ans'] = $num1 + $num2;
        $_SESSION['captcha_str'] = "$num1 + $num2";
    } else {
        // Check if email exists
        $check = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($check && $check->num_rows > 0) {
            $error = "Email is already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $merchant_id = 'MID_' . strtoupper(substr(md5(time()), 0, 8));
            $otp = rand(100000, 999999);
            
            // SQL Injection Fix for Shop Name containing single quotes (')
            $raw_shop_name = explode('@', $email)[0] . "'s Shop";
            $shop_name = $conn->real_escape_string($raw_shop_name);

            $insert = $conn->query("INSERT INTO users (merchant_id, name, email, password_hash, otp, shop_name) VALUES ('$merchant_id', '$name', '$email', '$hash', '$otp', '$shop_name')");

            if ($insert) {
                // Send OTP via Authenticated SMTP (ShipMyCode)
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'mail.shipmycode.in';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'support@shipmycode.in';
                    $mail->Password   = 'YOUR_SMTP_PASSWORD';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Port 465 requires SMTPS
                    $mail->Port       = 465;

                    $mail->setFrom('support@shipmycode.in', 'HeyUPI Support');
                    $mail->addAddress($email, $name);

                    $mail->isHTML(true);
                    $mail->Subject = 'Verify your HeyUPI Account';
                    $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; background-color: #f4f7f6; padding: 40px 20px; text-align: center;'>
                        <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; padding: 40px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>
                            <h2 style='color: #0A2540; font-size: 24px; margin-bottom: 10px;'>Welcome, $name!</h2>
                            <p style='color: #425466; font-size: 16px; margin-bottom: 20px;'>Your account verification OTP is:</p>
                            <div style='background-color: #E5F0FF; border: 1px dashed #0052FF; padding: 15px; border-radius: 12px; margin-bottom: 25px;'>
                                <h1 style='color: #0052FF; letter-spacing: 6px; font-size: 32px; margin: 0;'>$otp</h1>
                            </div>
                            <p style='color: #637381; font-size: 14px;'>Please enter this code on the verification page to activate your account. Do not share this OTP with anyone.</p>
                        </div>
                    </div>";

                    $mail->send();
                    $_SESSION['temp_email'] = $email;
                    header("Location: verify.php");
                    exit;
                } catch (Exception $e) {
                    $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            } else {
                $error = "Registration failed. Try again. Error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account | HeyUPI</title>
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
                    <i data-lucide="user-plus" class="w-6 h-6"></i>
                </div>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 text-center mb-2">Create your account</h2>
            <p class="text-slate-500 text-sm text-center mb-6">Join thousands of developers using HeyUPI.</p>

            <?php if($error): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm font-medium mb-4 border border-red-100 flex items-center">
                    <i data-lucide="alert-circle" class="w-4 h-4 mr-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Full Name</label>
                    <input type="text" name="name" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none transition-all text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email Address</label>
                    <input type="email" name="email" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none transition-all text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none transition-all text-sm">
                </div>

                <!-- Math CAPTCHA -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Solve to verify</label>
                        <div class="text-lg font-bold text-slate-800 tracking-wider"><?php echo $_SESSION['captcha_str']; ?> = ?</div>
                    </div>
                    <input type="number" name="captcha" required placeholder="Answer" class="w-24 px-3 py-2 text-center rounded-lg border border-slate-200 focus:border-blue-500 outline-none font-bold text-slate-800">
                </div>

                <!-- Privacy Policy -->
                <div class="flex items-start mt-4">
                    <input type="checkbox" name="privacy" id="privacy" required class="mt-1 w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                    <label for="privacy" class="ml-2 text-sm text-slate-500">
                        I agree to the <a href="#" class="text-blue-600 font-semibold hover:underline">Privacy Policy</a> & Terms of Service.
                    </label>
                </div>

                <button type="submit" class="w-full bg-[#0052FF] hover:bg-[#0040CC] text-white font-bold py-3.5 rounded-xl transition-all shadow-[0_4px_14px_rgba(0,82,255,0.3)] mt-2 flex items-center justify-center">
                    Create Account <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-slate-500">
                Already have an account? <a href="login.php" class="text-blue-600 font-bold hover:underline">Sign in</a>
            </div>
        </div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
