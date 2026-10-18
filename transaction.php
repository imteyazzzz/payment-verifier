<?php
require 'dbinfo.php';
if (!isset($_SESSION['merchant_id'])) { header("Location: index.php"); exit; }

if (!isset($_GET['order'])) { die("No order specified."); }
$order_id = $conn->real_escape_string($_GET['order']);
$merchant_id = $_SESSION['merchant_id'];

// Fetch Order info
$query = "SELECT o.*, u.shop_name, u.gateway_type, u.upi_id FROM orders o JOIN users u ON o.merchant_id = u.merchant_id WHERE o.order_id='$order_id' AND o.merchant_id='$merchant_id'";
$result = $conn->query($query);
if ($result->num_rows == 0) { die("Transaction not found."); }
$order = $result->fetch_assoc();

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$verify_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/transaction.php?order=" . $order['order_id'];

// [CRITICAL FIX] Fetch image via PHP to bypass JavaScript CORS issues for PDF generation
$logo_url = "https://famgateway.in/assets/img/logo-navbar-bright.png?v=9.0";
$logo_data = @file_get_contents($logo_url);
$logo_base64 = $logo_data ? 'data:image/png;base64,' . base64_encode($logo_data) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction | <?php echo $order['order_id']; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #F6F9FC; /* Stripe classic background */
            color: #1A1F36; 
        }
        
        /* Stripe-like Cards */
        .stripe-card { 
            background: #FFFFFF; 
            border-radius: 8px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); 
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .stripe-header {
            background: #FFFFFF;
            border-bottom: 1px solid #E3E8EE;
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }

        /* Hidden PDF Container CSS */
        #receipt-container { width: 800px; background: #ffffff; padding: 48px; position: relative; font-family: 'Inter', sans-serif; color: #1A1F36; box-sizing: border-box; }
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 130px; font-weight: 800; color: rgba(26, 31, 54, 0.02); white-space: nowrap; pointer-events: none; z-index: 1; user-select: none; letter-spacing: -3px; }
        .receipt-content { position: relative; z-index: 10; }
        .pdf-dashed { border-bottom: 1px dashed #E3E8EE; margin: 24px 0; }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    <!-- Top Navigation Bar -->
    <header class="stripe-header sticky top-0 z-20 h-16 flex items-center justify-between px-6 lg:px-12">
        <div class="flex items-center gap-4">
            <img src="https://famgateway.in/assets/img/logo-navbar-bright.png?v=9.0" alt="Logo" class="h-5 object-contain">
            <div class="h-5 w-px bg-slate-200 hidden sm:block"></div>
            <nav class="hidden sm:flex text-sm font-medium text-slate-500 space-x-2">
                <a href="dashboard.php" class="hover:text-slate-900 transition-colors">Payments</a>
                <span>/</span>
                <span class="text-slate-900 font-mono"><?php echo $order['order_id']; ?></span>
            </nav>
        </div>
        
        <div>
            <a href="dashboard.php" class="text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-md transition-colors flex items-center gap-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 py-10 px-4 sm:px-6">
        <div class="max-w-4xl mx-auto space-y-6">
            
            <!-- Main Status Card -->
            <div class="stripe-card p-6 md:p-8 relative overflow-hidden">
                <?php if($order['status'] == 'success'): ?>
                    <div class="absolute top-0 left-0 w-full h-1 bg-emerald-500"></div>
                <?php elseif($order['status'] == 'failed'): ?>
                    <div class="absolute top-0 left-0 w-full h-1 bg-rose-500"></div>
                <?php else: ?>
                    <div class="absolute top-0 left-0 w-full h-1 bg-amber-500"></div>
                <?php endif; ?>

                <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider flex items-center gap-1.5
                                <?php echo $order['status'] == 'success' ? 'bg-emerald-50 text-emerald-700' : 
                                         ($order['status'] == 'failed' ? 'bg-rose-50 text-rose-700' : 
                                         'bg-amber-50 text-amber-700'); ?>">
                                <?php if($order['status'] == 'success'): ?><i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> Succeeded
                                <?php elseif($order['status'] == 'failed'): ?><i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Failed / Expired
                                <?php else: ?><i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Processing<?php endif; ?>
                            </span>
                        </div>
                        <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight flex items-center gap-1">
                            <span class="text-2xl text-slate-400 font-normal">₹</span><?php echo number_format($order['final_amount'], 2); ?>
                        </h1>
                    </div>
                    
                    <div class="mt-6 md:mt-0 text-left md:text-right">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Date</p>
                        <p class="text-sm font-medium text-slate-900">
                            <?php echo $order['success_time'] ? date('M j, Y, g:i A', strtotime($order['success_time'])) : date('M j, Y, g:i A', strtotime($order['created_at'])); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Two Column Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Payment Details -->
                <div class="stripe-card p-6">
                    <h2 class="text-sm font-bold text-slate-900 mb-4 border-b border-slate-100 pb-3">Payment details</h2>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-500 font-medium">Order ID</span>
                            <span class="text-sm font-mono font-medium text-slate-900"><?php echo $order['order_id']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-slate-500 font-medium">Security Note</span>
                            <span class="text-xs font-mono font-bold bg-slate-100 text-slate-700 px-2 py-1 rounded"><?php echo $order['txn_note']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-500 font-medium">Payment Method</span>
                            <span class="text-sm font-medium text-slate-900">UPI (Verified)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-500 font-medium">API Source</span>
                            <span class="text-sm font-medium text-slate-900 capitalize"><?php echo $order['source']; ?> Endpoint</span>
                        </div>
                    </div>
                </div>

                <!-- Merchant Details -->
                <div class="stripe-card p-6">
                    <h2 class="text-sm font-bold text-slate-900 mb-4 border-b border-slate-100 pb-3">Merchant details</h2>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-500 font-medium">Business Name</span>
                            <span class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($order['shop_name']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-500 font-medium">Gateway Platform</span>
                            <span class="text-sm font-medium text-slate-900 capitalize"><?php echo htmlspecialchars($order['gateway_type']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-500 font-medium">Receiving UPI ID</span>
                            <span class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($order['upi_id']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Execution Logs -->
            <div class="stripe-card p-6">
                <h2 class="text-sm font-bold text-slate-900 mb-6 flex items-center gap-2">
                    <i data-lucide="activity" class="w-4 h-4 text-slate-400"></i> Event Logs
                </h2>
                
                <div class="relative border-l border-slate-200 ml-2 space-y-6 pb-2">
                    <!-- Event 1 -->
                    <div class="relative pl-6">
                        <div class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full bg-slate-300 ring-4 ring-white"></div>
                        <p class="text-sm font-semibold text-slate-900">Payment intent created</p>
                        <p class="text-xs text-slate-500 mt-1"><?php echo date('M j, Y, g:i:s A', strtotime($order['created_at'])); ?></p>
                    </div>
                    
                    <!-- Event 2 -->
                    <?php if($order['status'] == 'success'): ?>
                    <div class="relative pl-6">
                        <div class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-4 ring-white"></div>
                        <p class="text-sm font-semibold text-emerald-700">Payment succeeded</p>
                        <p class="text-xs text-slate-500 mt-1">Verified securely via note parameter.</p>
                        <p class="text-xs font-semibold text-slate-700 mt-1"><?php echo $order['success_time'] ? date('M j, Y, g:i:s A', strtotime($order['success_time'])) : ''; ?></p>
                    </div>
                    <?php elseif($order['status'] == 'failed'): ?>
                    <div class="relative pl-6">
                        <div class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full bg-rose-500 ring-4 ring-white"></div>
                        <p class="text-sm font-semibold text-rose-700">Payment intent expired</p>
                        <p class="text-xs text-slate-500 mt-1">10-minute active window closed.</p>
                    </div>
                    <?php else: ?>
                    <div class="relative pl-6">
                        <div class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full bg-blue-500 ring-4 ring-white animate-pulse"></div>
                        <p class="text-sm font-semibold text-blue-700">Awaiting payment</p>
                        <p class="text-xs text-slate-500 mt-1">Polling inbox for transaction signature.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Download Button -->
            <?php if($order['status'] == 'success'): ?>
            <div class="flex justify-end pt-2">
                <button onclick="generatePDF()" id="pdfBtn" class="bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm py-2.5 px-5 rounded-md transition-all flex items-center shadow-sm">
                    <i data-lucide="download" class="w-4 h-4 mr-2"></i> Download Receipt
                </button>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-200 bg-white py-6 mt-auto">
        <div class="max-w-4xl mx-auto px-6 flex justify-between items-center text-xs font-medium text-slate-500">
            <p>Powered by FamGateway</p>
            <p>MSME Reg: UDYAM-BR-28-0050000</p>
        </div>
    </footer>

    <!-- ---------------------------------------------------- -->
    <!-- HIDDEN RECEIPT TEMPLATE (FOR PDF GENERATION ONLY) -->
    <!-- ---------------------------------------------------- -->
    <div style="display:none;">
        <div id="receipt-container">
            <div class="watermark">HeyUPI</div>
            
            <div class="receipt-content">
                <!-- Header with Pre-Loaded Base64 Logo -->
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px dashed #E3E8EE; padding-bottom: 24px; margin-bottom: 32px;">
                    <div style="display: flex; align-items: center;">
                        <img src="<?php echo $logo_base64; ?>" alt="Logo" style="height: 28px; object-fit: contain;">
                    </div>
                    <div style="text-align: right;">
                        <p style="font-size: 13px; font-weight: 800; color: #1A1F36; letter-spacing: 1.5px; margin: 0 0 4px 0;">OFFICIAL RECEIPT</p>
                        <p style="font-size: 11px; font-weight: 600; color: #64748B; margin: 0; font-family: monospace;">#<?php echo $order['order_id']; ?></p>
                    </div>
                </div>

                <!-- Amount Section -->
                <div style="text-align: center; margin-bottom: 40px; background: #F6F9FC; padding: 24px; border-radius: 8px; border: 1px solid #E3E8EE;">
                    <p style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 1px; margin: 0;">Amount Received</p>
                    <h2 style="font-size: 48px; font-weight: 800; color: #10B981; margin: 8px 0; letter-spacing: -1px;">₹<?php echo number_format($order['final_amount'], 2); ?> <span style="font-size: 18px; font-weight: 600; color: #1A1F36; letter-spacing: 0;">Captured</span></h2>
                    <p style="font-size: 12px; font-weight: 600; color: #475569; margin: 0;">Processed on: <?php echo $order['success_time'] ? date('d-m-Y \a\t h:i:s A', strtotime($order['success_time'])) : date('d-m-Y \a\t h:i:s A'); ?></p>
                </div>

                <!-- Transaction Details Table -->
                <div style="margin-bottom: 40px;">
                    <h3 style="font-size: 13px; font-weight: 800; color: #1A1F36; border-bottom: 2px solid #1A1F36; padding-bottom: 12px; margin-bottom: 16px; margin-top: 0; display: inline-block;">TRANSACTION DETAILS</h3>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 12px 0; color: #64748B; font-size: 12px; font-weight: 600; width: 35%;">Payment ID</td>
                            <td style="padding: 12px 0; color: #1A1F36; font-size: 13px; font-weight: 600; font-family: monospace;">| <?php echo $order['order_id']; ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 12px 0; color: #64748B; font-size: 12px; font-weight: 600;">Security Note ID</td>
                            <td style="padding: 12px 0; color: #1A1F36; font-size: 13px; font-weight: 600; font-family: monospace;">| <?php echo $order['txn_note']; ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 12px 0; color: #64748B; font-size: 12px; font-weight: 600;">Payment Method</td>
                            <td style="padding: 12px 0; color: #1A1F36; font-size: 13px; font-weight: 600;">| UPI (Verified)</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 12px 0; color: #64748B; font-size: 12px; font-weight: 600;">Merchant Target</td>
                            <td style="padding: 12px 0; color: #1A1F36; font-size: 13px; font-weight: 600;">| <?php echo htmlspecialchars($order['shop_name']); ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 12px 0; color: #64748B; font-size: 12px; font-weight: 600;">Source Generation</td>
                            <td style="padding: 12px 0; color: #1A1F36; font-size: 13px; font-weight: 600; text-transform: capitalize;">| <?php echo $order['source']; ?> API Request</td>
                        </tr>
                        <tr>
                            <td style="padding: 12px 0; color: #64748B; font-size: 12px; font-weight: 600;">Settlement Policy</td>
                            <td style="padding: 12px 0; color: #1A1F36; font-size: 13px; font-weight: 600;">| 100% Instant P2P - Zero Platform Fee</td>
                        </tr>
                    </table>
                </div>

                <!-- Settlement Flow Diagram -->
                <div style="display: flex; justify-content: space-between; align-items: center; background: #FFFFFF; border: 1px solid #E3E8EE; border-radius: 8px; padding: 20px; margin-bottom: 40px;">
                    <div style="text-align: center; width: 30%;">
                        <p style="font-size: 11px; color: #64748B; font-weight: 700; text-transform: uppercase; margin: 0 0 4px 0;">Customer</p>
                        <p style="font-size: 14px; color: #1A1F36; font-weight: 700; margin: 0;">Paid ₹<?php echo $order['final_amount']; ?></p>
                    </div>
                    <div style="text-align: center; width: 40%; color: #3B82F6;">
                        <p style="font-size: 10px; font-weight: 700; margin: 0 0 4px 0; background: #EFF6FF; display: inline-block; padding: 2px 8px; border-radius: 4px;">₹0.00 Fee</p>
                        <p style="font-size: 14px; font-weight: 700; letter-spacing: 2px; margin: 4px 0;">➔ ➔ ➔</p>
                        <p style="font-size: 9px; font-weight: 700; margin: 0; letter-spacing: 1px;">INSTANT SETTLEMENT</p>
                    </div>
                    <div style="text-align: center; width: 30%;">
                        <p style="font-size: 11px; color: #64748B; font-weight: 700; text-transform: uppercase; margin: 0 0 4px 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($order['shop_name']); ?></p>
                        <p style="font-size: 14px; color: #10B981; font-weight: 700; margin: 0;">Received ₹<?php echo $order['final_amount']; ?></p>
                    </div>
                </div>

                <!-- Footer Legal Info -->
                <div class="pdf-dashed"></div>
                <div style="text-align: center; padding-top: 10px;">
                    <p style="font-size: 11px; color: #64748B; font-weight: 500; margin: 0 0 8px 0;">This is an official system-generated transaction receipt.</p>
                    <p style="font-size: 10px; color: #3B82F6; font-weight: 600; margin: 0 0 20px 0;">Verification Link: <span style="text-decoration: underline;"><?php echo $verify_link; ?></span></p>
                    
                    <div style="background: #F6F9FC; border: 1px solid #E3E8EE; padding: 12px; border-radius: 6px; font-size: 9px; color: #475569; font-weight: 600; display: inline-block; letter-spacing: 0.5px;">
                        ARYANISPE | Ministry of MSME, Govt. of India | Reg: UDYAM-BR-28-0050000<br>
                        <span style="color: #94A3B8; font-weight: 500; margin-top: 4px; display: inline-block;">Platform Powered by HeyUPI Architecture</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function generatePDF() {
            const btn = document.getElementById('pdfBtn');
            const originalText = btn.innerHTML;
            
            // Set loading state
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i> Processing...';
            lucide.createIcons();

            const orderId = "<?php echo $order['order_id']; ?>";
            const element = document.getElementById('receipt-container');
            const wrapper = element.parentElement;
            
            // Temporarily unhide to allow html2pdf to capture the layout
            wrapper.style.display = 'block';
            wrapper.style.position = 'absolute';
            wrapper.style.left = '-9999px';

            const opt = {
                margin:       [0, 0, 0, 0],
                filename:     `heyupi_${orderId}.pdf`,
                image:        { type: 'jpeg', quality: 1 },
                html2canvas:  { scale: 2, useCORS: true, logging: false, backgroundColor: '#ffffff' },
                jsPDF:        { unit: 'px', format: [800, element.offsetHeight], orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                wrapper.style.display = 'none';
                btn.innerHTML = originalText;
                lucide.createIcons();
            }).catch(err => {
                console.error(err);
                alert("An error occurred generating the PDF.");
                wrapper.style.display = 'none';
                btn.innerHTML = originalText;
                lucide.createIcons();
            });
        }
    </script>
</body>
</html>
