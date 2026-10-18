<?php
require 'dbinfo.php';
if (!isset($_GET['orderid'])) { die("Invalid Link"); }
$order_id = $conn->real_escape_string($_GET['orderid']);

// Auto-patch Database: Check if column exists to avoid errors
$chk = $conn->query("SHOW COLUMNS FROM users LIKE 'expiry_minutes'");
if($chk->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN expiry_minutes INT DEFAULT 10");
}

// Fetch order and user details.
$query = "SELECT o.*, u.upi_id, u.shop_name, IFNULL(u.expiry_minutes, 10) as expiry_minutes FROM orders o JOIN users u ON o.merchant_id = u.merchant_id WHERE o.order_id = '$order_id'";
$result = $conn->query($query);
if ($result->num_rows == 0) { die("Order not found"); }
$order = $result->fetch_assoc();

$created_at_timestamp = strtotime($order['created_at']) * 1000;
$expiry_val = (isset($order['expiry_minutes']) && intval($order['expiry_minutes']) > 0) ? intval($order['expiry_minutes']) : 10;
$expiry_duration = $expiry_val * 60 * 1000; 

// Generate UPI String (Dynamic UPI ID from database)
$upiString = "upi://pay?pa=" . $order['upi_id'] . "&pn=" . urlencode($order['shop_name']) . "&am=" . $order['final_amount'] . "&tn=" . urlencode($order['txn_note']) . "&cu=INR";
// External API for reliable QR Generation
$qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=" . urlencode($upiString);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout - <?php echo htmlspecialchars($order['shop_name']); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            --primary-blue: #0052FF;
            --primary-blue-hover: #0040CC;
            --primary-blue-light: #E5F0FF;
            
            --bg-page: #F3F4F6;
            --bg-white: #FFFFFF;
            
            --text-dark: #111827;     
            --text-gray: #4B5563;     
            --text-light: #9CA3AF;
            
            --border-color: #E5E7EB;
            
            --font-sans: 'Inter', -apple-system, sans-serif;
            --font-mono: 'Fira Code', monospace;

            --shadow-mockup: 0 25px 50px -12px rgba(0,0,0,0.15);
            --shadow-inner: inset 0 2px 4px 0 rgba(0,0,0,0.04);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-font-smoothing: antialiased; }

        body {
            background-color: var(--bg-page);
            font-family: var(--font-sans);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .checkout-wrapper { position: relative; width: 100%; max-width: 460px; }
        .checkout-wrapper::before {
            content: ''; position: absolute; top: 15px; left: 15px; width: 100%; height: 100%;
            background-color: var(--primary-blue); border-radius: 24px; z-index: 0;
        }

        .checkout-window {
            background-color: var(--bg-white); border-radius: 24px; box-shadow: var(--shadow-mockup);
            position: relative; z-index: 1; border: 1px solid var(--border-color); overflow: hidden;
            display: flex; flex-direction: column;
        }

        .c-header {
            background-color: #F9FAFB; border-bottom: 1px solid var(--border-color); padding: 16px 24px;
            display: flex; align-items: center; justify-content: space-between;
        }

        .c-dots { display: flex; gap: 8px; }
        .c-dots span { width: 12px; height: 12px; border-radius: 50%; }
        .c-dots span:nth-child(1) { background-color: #EF4444; }
        .c-dots span:nth-child(2) { background-color: #F59E0B; }
        .c-dots span:nth-child(3) { background-color: #10B981; }

        .c-secure { font-size: 13px; font-weight: 600; color: var(--text-gray); display: flex; align-items: center; gap: 6px; }
        
        .c-body { padding: 32px 24px; }

        .merchant-name { font-size: 18px; font-weight: 800; color: var(--text-dark); margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .merchant-icon { width: 36px; height: 36px; background: var(--primary-blue-light); color: var(--primary-blue); border-radius: 10px; display: flex; align-items: center; justify-content: center; }

        .top-grid { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
        .c-lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-light); letter-spacing: 1px; margin-bottom: 6px; }
        .c-amount { font-size: 42px; font-weight: 800; color: var(--text-dark); line-height: 1; letter-spacing: -1px; }
        .c-amount span { font-size: 18px; font-weight: 600; color: var(--text-light); }
        .c-timer { font-family: var(--font-mono); font-size: 20px; font-weight: 700; color: #EF4444; background: #FEF2F2; padding: 6px 12px; border-radius: 8px; border: 1px solid #FCA5A5; }

        /* Razorpay style email input */
        .email-section { margin-bottom: 24px; }
        .email-input-wrapper {
            display: flex; align-items: center; background: #F9FAFB; border: 1px solid var(--border-color);
            border-radius: 12px; padding: 12px 16px; transition: 0.2s;
        }
        .email-input-wrapper:focus-within { border-color: var(--primary-blue); background: #FFF; box-shadow: 0 0 0 3px rgba(0,82,255,0.1); }
        .email-input-wrapper i { color: var(--text-light); margin-right: 12px; width: 18px; height: 18px; }
        .email-input { border: none; background: transparent; outline: none; width: 100%; font-size: 14px; font-family: var(--font-sans); color: var(--text-dark); }
        .email-input::placeholder { color: var(--text-light); }

        .qr-wrapper {
            background: #F9FAFB; border: 1px solid var(--border-color); border-radius: 16px;
            padding: 24px; display: flex; flex-direction: column; align-items: center; margin-bottom: 24px;
        }
        .qr-box { background: #FFF; padding: 12px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); margin-bottom: 16px; }
        .qr-box img { width: 180px; height: 180px; border-radius: 8px; }
        
        .btn-action {
            display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600;
            color: var(--primary-blue); background: var(--primary-blue-light); padding: 10px 20px;
            border-radius: 999px; cursor: pointer; border: none; transition: 0.2s;
        }
        .btn-action:hover { background: #D1E3FF; }

        .scanning-status {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            background: #F0FDF4; border: 1px solid #BBF7D0; color: #16A34A;
            padding: 14px; border-radius: 12px; font-size: 13px; font-weight: 600; margin-bottom: 24px;
        }
        .spinner { animation: spin 1s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        .apps-title { font-size: 11px; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; text-align: center; }
        .apps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        
        .app-btn {
            background: var(--bg-white); border: 1px solid var(--border-color); border-radius: 12px;
            padding: 12px 8px; display: flex; flex-direction: column; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 600; color: var(--text-dark); cursor: pointer; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .app-btn:hover { border-color: var(--primary-blue); box-shadow: 0 4px 12px rgba(0, 82, 255, 0.15); transform: translateY(-2px); }
        .app-btn img { width: 28px; height: 28px; border-radius: 8px; object-fit: cover; }

        .order-details { margin-top: 24px; background: #F9FAFB; padding: 16px; border-radius: 12px; font-size: 12px; color: var(--text-gray); border: 1px solid var(--border-color); }
        .o-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .o-row:last-child { margin-bottom: 0; }
        .o-val { font-weight: 600; color: var(--text-dark); }
        .o-mono { font-family: var(--font-mono); }

        /* --- Post Payment UI --- */
        .final-state { text-align: center; padding: 40px 20px; }
        .fs-icon { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
        .fs-title { font-size: 24px; font-weight: 800; color: var(--text-dark); margin-bottom: 12px; }
        .fs-desc { font-size: 14px; color: var(--text-gray); font-weight: 500; line-height: 1.6; margin-bottom: 24px; }
        .receipt-pill { background: #F3F4F6; padding: 8px 16px; border-radius: 100px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--text-dark); border: 1px solid var(--border-color); }
        
        .fs-success .fs-icon { background: #DCFCE7; color: #16A34A; box-shadow: 0 0 0 10px rgba(22, 163, 74, 0.1); }
        .fs-failed .fs-icon { background: #FEE2E2; color: #DC2626; box-shadow: 0 0 0 10px rgba(220, 38, 38, 0.1); }

        .hidden { display: none !important; }
        
        @media (max-width: 480px) {
            .checkout-wrapper::before { top: 10px; left: 10px; }
            .c-body { padding: 24px 20px; }
            .c-amount { font-size: 36px; }
        }
    </style>
</head>
<body>

    <div class="checkout-wrapper">
        <div class="checkout-window">
            
            <div class="c-header">
                <div class="c-dots"><span></span><span></span><span></span></div>
                <div class="c-secure"><i data-lucide="lock" style="width:14px; height:14px;"></i> Secure Checkout</div>
                <div style="width: 42px;"></div> <!-- Spacer for flex alignment -->
            </div>

            <div class="c-body">
                
                <?php if($order['status'] == 'success'): ?>
                    <div class="final-state fs-success">
                        <div class="fs-icon"><i data-lucide="check" style="width:40px; height:40px;"></i></div>
                        <div class="fs-title">Payment Successful</div>
                        <div class="fs-desc">Your payment has been securely processed.</div>
                        <div class="receipt-pill">
                            <i data-lucide="receipt" style="width:16px; height:16px;"></i> Ref: <span class="o-mono"><?php echo $order['txn_note']; ?></span>
                        </div>
                    </div>
                
                <?php elseif($order['status'] == 'failed'): ?>
                    <div class="final-state fs-failed">
                        <div class="fs-icon"><i data-lucide="x" style="width:40px; height:40px;"></i></div>
                        <div class="fs-title">Session Expired</div>
                        <div class="fs-desc">The secure payment window has closed. Please try again.</div>
                    </div>
                
                <?php else: ?>
                    <div id="payment-section">
                        
                        <div class="merchant-name">
                            <div class="merchant-icon"><i data-lucide="store" style="width:20px; height:20px;"></i></div>
                            <?php echo htmlspecialchars($order['shop_name']); ?>
                        </div>

                        <div class="top-grid">
                            <div>
                                <div class="c-lbl">Amount Payable</div>
                                <div class="c-amount">₹<?php echo $order['final_amount']; ?> <span>INR</span></div>
                            </div>
                            <div style="text-align: right;">
                                <div class="c-lbl">Expires In</div>
                                <div id="timer" class="c-timer">00:00</div>
                            </div>
                        </div>

                        <!-- Email Input for Receipt -->
                        <div class="email-section">
                            <div class="c-lbl">Contact Details</div>
                            <div class="email-input-wrapper">
                                <i data-lucide="mail"></i>
                                <input type="email" id="receiptEmail" class="email-input" placeholder="Email for receipt (optional)">
                            </div>
                        </div>

                        <!-- QR Code & Scanner -->
                        <div class="qr-wrapper">
                            <div class="qr-box">
                                <!-- Using direct API for perfect QR Code -->
                                <img src="<?php echo $qrImageUrl; ?>" alt="Scan to Pay">
                            </div>
                            <button class="btn-action" onclick="downloadQR()">
                                <i data-lucide="download" style="width:16px; height:16px;"></i> Download QR Code
                            </button>
                        </div>

                        <div class="scanning-status">
                            <i data-lucide="loader-2" class="spinner" style="width:18px; height:18px;"></i>
                            Awaiting payment confirmation...
                        </div>

                        <!-- Apps Grid -->
                        <div class="apps-title">Or Pay Directly Via App</div>
                        <div class="apps-grid">
                            <button onclick="openUPI('gpay')" class="app-btn">
                                <img src="https://play-lh.googleusercontent.com/HArtbyi53u0jnqhnnxkQnMx9dHOErrtqPhFHKSRToUhOvfopbNBzlNUhM43P62j7xA=w240-h480-rw" alt="GPay"> GPay
                            </button>
                            <button onclick="openUPI('phonepe')" class="app-btn">
                                <img src="https://play-lh.googleusercontent.com/6iyA2zVz5PyyMjK5SIxdUhrb7oh9nMHdzsqafpXWvJ3lD_fI7_bI_iQy1-kK0rJ3d5A=w240-h480-rw" alt="PhonePe"> PhonePe
                            </button>
                            <button onclick="openUPI('paytm')" class="app-btn">
                                <img src="https://play-lh.googleusercontent.com/6UgNmhlxlCPJNNpG_T7Fm92dJckB87_8R1g-zOebTldo3yU36YQz7m8sJ0_YF-kH2Q=w240-h480-rw" alt="Paytm"> Paytm
                            </button>
                            <button onclick="openUPI('fampay')" class="app-btn">
                                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQelW4gCRQy441D0mnXqMbOJV6pLk3i-kacAfgxXsP51Q&s=10" alt="FamPay"> FamPay
                            </button>
                            <button onclick="openUPI('slice')" class="app-btn">
                                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRyWs32uBOlAmGamgsluXeBhwAj1dIabTke0Dv2buo-Tg&s=10" alt="Slice"> Slice
                            </button>
                            <button onclick="openUPI('default')" class="app-btn">
                                <div style="width: 28px; height: 28px; background: var(--text-dark); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #FFF;">
                                    <i data-lucide="smartphone" style="width:16px; height:16px;"></i>
                                </div>
                                Other UPI
                            </button>
                        </div>

                        <!-- Order Summary -->
                        <div class="order-details">
                            <div class="o-row">
                                <span>Order ID</span>
                                <span class="o-val o-mono"><?php echo $order['order_id']; ?></span>
                            </div>
                            <div class="o-row">
                                <span>Note</span>
                                <span class="o-val o-mono"><?php echo $order['txn_note']; ?></span>
                            </div>
                        </div>

                    </div>

                    <!-- Post Payment UI dynamically toggled by JS -->
                    <div id="success-section" class="final-state fs-success hidden">
                        <div class="fs-icon"><i data-lucide="check" style="width:40px; height:40px;"></i></div>
                        <div class="fs-title">Payment Successful</div>
                        <div class="fs-desc" id="receipt-msg">Your payment has been securely processed.</div>
                        <div class="receipt-pill">
                            <i data-lucide="receipt" style="width:16px; height:16px;"></i> Ref: <span class="o-mono"><?php echo $order['txn_note']; ?></span>
                        </div>
                    </div>
                    
                    <div id="failed-section" class="final-state fs-failed hidden">
                        <div class="fs-icon"><i data-lucide="x" style="width:40px; height:40px;"></i></div>
                        <div class="fs-title">Session Expired</div>
                        <div class="fs-desc">The secure payment window has closed. Please try again.</div>
                    </div>

                    <script>
                        // Global Payment Variables
                        const upiString = `<?php echo $upiString; ?>`;
                        const orderId = "<?php echo $order['order_id']; ?>";
                        const expiryTime = <?php echo $created_at_timestamp; ?> + <?php echo $expiry_duration; ?>; 
                        
                        // Deep Linking Logic
                        function openUPI(app) {
                            let finalURI = upiString;
                            const isAndroid = /Android/i.test(navigator.userAgent);
                            
                            if (isAndroid) {
                                switch(app) {
                                    case 'gpay': finalURI = finalURI.replace('upi://pay', 'tez://upi/pay'); break;
                                    case 'phonepe': finalURI = finalURI.replace('upi://pay', 'phonepe://pay'); break;
                                    case 'paytm': finalURI = finalURI.replace('upi://pay', 'paytmmp://pay'); break;
                                    // FamPay, Slice and others will process standard upi://pay correctly
                                }
                            }
                            window.location.href = finalURI;
                        }

                        // Download QR Code (Direct File Download from API)
                        async function downloadQR() {
                            try {
                                const response = await fetch("<?php echo $qrImageUrl; ?>");
                                const blob = await response.blob();
                                const url = window.URL.createObjectURL(blob);
                                const a = document.createElement('a');
                                a.style.display = 'none';
                                a.href = url;
                                a.download = `QR_${orderId}.png`;
                                document.body.appendChild(a);
                                a.click();
                                window.URL.revokeObjectURL(url);
                                document.body.removeChild(a);
                            } catch(e) {
                                alert("Failed to download QR code. Please take a screenshot.");
                            }
                        }

                        // Real-time Polling & Timer using Vanilla JS fetch()
                        let checkInterval, timerInterval;

                        function updateTimer() {
                            const distance = expiryTime - new Date().getTime();
                            if (distance <= 0) {
                                stopTimers();
                                showScreen('failed');
                                return;
                            }
                            const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                            const s = Math.floor((distance % (1000 * 60)) / 1000);
                            document.getElementById('timer').innerText = (m<10?"0":"")+m+":"+(s<10?"0":"")+s;
                        }

                        async function verifyPayment() {
                            try {
                                const res = await fetch(`check_payment.php?orderid=${orderId}&_t=${Date.now()}`);
                                const data = await res.json();
                                
                                if (data.status === 'success') {
                                    stopTimers();
                                    handleSuccess();
                                } else if (data.status === 'failed') {
                                    stopTimers();
                                    showScreen('failed');
                                }
                            } catch (e) {
                                // Silent fail for polling errors
                            }
                        }

                        function handleSuccess() {
                            showScreen('success');
                            // Email Receipt Logic
                            const email = document.getElementById('receiptEmail').value.trim();
                            if (email) {
                                document.getElementById('receipt-msg').innerText = `Receipt will be sent to ${email}`;
                                // Fire and forget request to your email script
                                // fetch(`send_receipt.php?orderid=${orderId}&email=${encodeURIComponent(email)}`);
                            }
                        }

                        function stopTimers() {
                            clearInterval(timerInterval);
                            clearInterval(checkInterval);
                        }

                        function showScreen(screen) {
                            document.getElementById('payment-section').classList.add('hidden');
                            document.getElementById(`${screen}-section`).classList.remove('hidden');
                        }

                        // Start Timers
                        if(new Date().getTime() < expiryTime) {
                            timerInterval = setInterval(updateTimer, 1000);
                            checkInterval = setInterval(verifyPayment, 3000); 
                            updateTimer();
                        } else { 
                            showScreen('failed');
                        }
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Initialize Lucide SVG Icons -->
    <script>lucide.createIcons();</script>
</body>
</html>
