<?php
require 'dbinfo.php';
if (!isset($_SESSION['merchant_id'])) { header("Location: index.php"); exit; }

$merchant_id = $_SESSION['merchant_id'];
$msg = '';
$err = '';

// Smart Auto-patch Database: Check if columns exist before adding
$cols = [
    'api_key' => 'VARCHAR(100) DEFAULT ""', 
    'webhook_url' => 'VARCHAR(255) DEFAULT ""', 
    'expiry_minutes' => 'INT DEFAULT 10'
];
foreach($cols as $col => $def) {
    $chk = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if($chk->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN $col $def");
    }
}

// Handle Settings Update
if (isset($_POST['update_settings'])) {
    $upi = $conn->real_escape_string($_POST['upi_id']);
    $imap_email = $conn->real_escape_string($_POST['imap_email']);
    $imap_pass = str_replace(' ', '', $_POST['imap_pass']);
    $expiry_minutes = isset($_POST['expiry_minutes']) ? max(1, intval($_POST['expiry_minutes'])) : 10;
    
    $conn->query("UPDATE users SET upi_id='$upi', imap_email='$imap_email', imap_pass='$imap_pass', expiry_minutes='$expiry_minutes' WHERE merchant_id='$merchant_id'");
    $msg = "Configuration saved successfully!";
}

// Handle API Keys Generation
if (isset($_POST['generate_api'])) {
    $new_api_key = 'sk_live_' . bin2hex(random_bytes(16));
    $webhook = $conn->real_escape_string($_POST['webhook_url']);
    $conn->query("UPDATE users SET api_key='$new_api_key', webhook_url='$webhook' WHERE merchant_id='$merchant_id'");
    $msg = "API Key Generated!";
}

// Fetch User Data
$user = $conn->query("SELECT * FROM users WHERE merchant_id='$merchant_id'")->fetch_assoc();
$is_configured = (!empty($user['upi_id']) && !empty($user['imap_email']) && !empty($user['imap_pass']));
$user_expiry = isset($user['expiry_minutes']) ? $user['expiry_minutes'] : 10;

// Handle Manual Link Generation (Only if configured)
$payment_link = '';
if (isset($_POST['create_link'])) {
    if (!$is_configured) {
        $err = "System Locked: Please configure email and UPI first.";
    } else {
        $base_amt = (float)$_POST['amount'];
        // Time limit checking active orders within expiry window
        $time_limit = date('Y-m-d H:i:s', strtotime("-{$user_expiry} minutes"));
        $check_active = $conn->query("SELECT COUNT(*) as c FROM orders WHERE merchant_id='$merchant_id' AND base_amount='$base_amt' AND status='pending' AND created_at > '$time_limit'");
        $active_count = $check_active->fetch_assoc()['c'];
        
        $final_amt = $base_amt + ($active_count * 0.01);
        $order_id = 'ORD' . time() . rand(10,99);
        
        $conn->query("INSERT INTO orders (order_id, merchant_id, base_amount, final_amount) VALUES ('$order_id', '$merchant_id', '$base_amt', '$final_amt')");
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $payment_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/pay.php?orderid=" . $order_id;
        $msg = "Payment link generated!";
    }
}

// Fetch Stats
$stats = $conn->query("SELECT 
    COUNT(*) as total, 
    SUM(CASE WHEN status='success' THEN final_amount ELSE 0 END) as total_revenue,
    SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as success,
    SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending
    FROM orders WHERE merchant_id='$merchant_id'")->fetch_assoc();

$orders = $conn->query("SELECT * FROM orders WHERE merchant_id='$merchant_id' ORDER BY created_at DESC LIMIT 30");

// Fetch Chart Data
$chart_query = $conn->query("SELECT DATE(created_at) as date, SUM(final_amount) as daily_total FROM orders WHERE merchant_id='$merchant_id' AND status='success' AND created_at >= DATE(NOW()) - INTERVAL 7 DAY GROUP BY DATE(created_at) ORDER BY date ASC");
$chart_labels = []; $chart_values = [];
for($i=6; $i>=0; $i--) {
    $date_str = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[$date_str] = date('d M', strtotime("-$i days"));
    $chart_values[$date_str] = 0;
}
while($row = $chart_query->fetch_assoc()) { $chart_values[$row['date']] = $row['daily_total']; }
$labels_json = json_encode(array_values($chart_labels));
$values_json = json_encode(array_values($chart_values));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | HeyUPI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = { 
            darkMode: 'class', 
            theme: { 
                extend: { 
                    colors: { 
                        stripe: { blurple: '#635BFF', hover: '#0A2540', dark: '#0A2540', bg: '#F6F9FC', border: '#E6E8EB', text: '#425466' },
                        dark: { bg: '#0A2540', card: '#112C47', border: '#1E3E5E' }
                    }
                } 
            } 
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #F6F9FC; }
        .dark body { background-color: #0A2540; }
        .card { background: #ffffff; border: 1px solid #E6E8EB; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); transition: box-shadow 0.3s; }
        .card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.06); }
        .dark .card { background: #112C47; border-color: #1E3E5E; box-shadow: none; }
        
        .stripe-input { border: 1px solid #E6E8EB; box-shadow: 0 1px 2px rgba(0,0,0,0.03); outline: none; transition: all 0.2s; }
        .stripe-input:focus { border-color: #635BFF; box-shadow: 0 0 0 3px rgba(99, 91, 255, 0.15); }
        .dark .stripe-input { border-color: #1E3E5E; background: rgba(0,0,0,0.2); }
        .dark .stripe-input:focus { border-color: #635BFF; }
        
        .tab-content { transition: opacity 0.3s ease; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark ::-webkit-scrollbar-thumb { background: #475569; }
    </style>
</head>
<body class="text-stripe-text dark:text-slate-300 transition-colors duration-300 h-screen flex overflow-hidden">

    <div id="sidebarOverlay" class="fixed inset-0 bg-stripe-dark/40 z-40 hidden lg:hidden backdrop-blur-sm transition-opacity" onclick="toggleSidebar()"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-dark-card border-r border-stripe-border dark:border-dark-border transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col">
        <div class="h-16 flex items-center px-6 mt-2">
            <span class="text-xl font-bold tracking-tight text-stripe-dark dark:text-white">HeyUPI<span class="text-stripe-blurple">.</span></span>
        </div>
        
        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            <button onclick="switchTab('overview')" id="btn-overview" class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg bg-stripe-bg dark:bg-slate-800 text-stripe-blurple dark:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg> Overview
            </button>
            <button onclick="switchTab('orders')" id="btn-orders" class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-stripe-text hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5 mr-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg> Transactions
            </button>
            <button onclick="switchTab('api')" id="btn-api" class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-stripe-text hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5 mr-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg> Developers (API)
            </button>
            <button onclick="switchTab('settings')" id="btn-settings" class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-stripe-text hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5 mr-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg> Settings
            </button>
        </nav>
        
        <div class="p-4 border-t border-stripe-border dark:border-dark-border">
            <a href="logout.php" class="flex items-center justify-center w-full px-4 py-2.5 text-sm font-medium text-stripe-dark dark:text-white hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors border border-transparent hover:border-stripe-border dark:hover:border-dark-border">
                <svg class="w-4 h-4 mr-2 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg> Sign out
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0 lg:ml-64">
        
        <header class="h-16 flex items-center justify-between px-6 lg:px-10 z-10 sticky top-0 bg-stripe-bg/80 dark:bg-dark-bg/80 backdrop-blur-md">
            <div class="flex items-center">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 mr-2 text-slate-500 hover:text-stripe-dark dark:hover:text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h1 class="text-2xl font-bold text-stripe-dark dark:text-white" id="pageTitle">Overview</h1>
            </div>
            
            <div class="flex items-center space-x-3">
                <button onclick="toggleDark()" class="p-2 rounded-full text-slate-400 hover:bg-white dark:hover:bg-slate-800 hover:shadow-sm transition-all border border-transparent hover:border-stripe-border dark:hover:border-dark-border">
                    <svg class="w-5 h-5 dark:hidden" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                    <svg class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path></svg>
                </button>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto px-6 lg:px-10 py-6">
            
            <?php if($msg): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 text-sm font-medium flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg> <?php echo $msg; ?>
                </div>
            <?php endif; ?>
            <?php if($err): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm font-medium flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg> <?php echo $err; ?>
                </div>
            <?php endif; ?>

            <!-- OVERVIEW TAB -->
            <div id="overview" class="tab-content block animate-[fadeIn_0.3s_ease-out]">
                <?php if(!$is_configured): ?>
                    <div class="card p-10 text-center max-w-2xl mx-auto mt-10 border-dashed border-2 border-slate-300 dark:border-slate-700 bg-white/50">
                        <div class="w-16 h-16 bg-stripe-bg dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4 text-stripe-blurple border border-stripe-border dark:border-dark-border">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <h2 class="text-xl font-bold mb-2 text-stripe-dark dark:text-white">Gateway Setup Required</h2>
                        <p class="text-slate-500 mb-6 text-sm">You must configure your receiving UPI ID and Gmail IMAP credentials to unlock the dashboard and accept payments.</p>
                        <button onclick="switchTab('settings')" class="bg-stripe-blurple text-white px-6 py-2.5 rounded-full text-sm font-semibold hover:opacity-90 transition-opacity shadow-sm">
                            Complete Setup &rarr;
                        </button>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-6 lg:mb-8">
                        <div class="card p-5 lg:p-6">
                            <p class="text-sm font-semibold text-stripe-text dark:text-slate-400">Total Volume</p>
                            <p class="text-3xl font-bold mt-2 text-stripe-dark dark:text-white">₹<?php echo number_format($stats['total_revenue'], 2); ?></p>
                        </div>
                        <div class="card p-5 lg:p-6">
                            <p class="text-sm font-semibold text-stripe-text dark:text-slate-400">Successful</p>
                            <p class="text-3xl font-bold mt-2 text-green-600 dark:text-green-400"><?php echo (int)$stats['success']; ?></p>
                        </div>
                        <div class="card p-5 lg:p-6">
                            <p class="text-sm font-semibold text-stripe-text dark:text-slate-400">Pending</p>
                            <p class="text-3xl font-bold mt-2 text-amber-500 dark:text-amber-400"><?php echo (int)$stats['pending']; ?></p>
                        </div>
                        <div class="card p-5 lg:p-6">
                            <p class="text-sm font-semibold text-stripe-text dark:text-slate-400">Failed</p>
                            <p class="text-3xl font-bold mt-2 text-red-500 dark:text-red-400"><?php echo (int)$stats['failed']; ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                        <div class="card p-6 lg:col-span-2">
                            <h3 class="text-base font-bold mb-6 text-stripe-dark dark:text-white">Revenue (7 Days)</h3>
                            <div class="relative h-64 w-full"><canvas id="revenueChart"></canvas></div>
                        </div>

                        <div class="card p-6 flex flex-col">
                            <h3 class="text-base font-bold mb-4 text-stripe-dark dark:text-white">Payment Links</h3>
                            <p class="text-sm text-slate-500 mb-6">Generate a quick payment link to share with your customers.</p>
                            
                            <form method="POST" class="flex-1 flex flex-col">
                                <input type="hidden" name="create_link" value="1">
                                <div class="mb-4">
                                    <label class="block text-xs font-semibold text-stripe-text dark:text-slate-300 mb-2 uppercase tracking-wide">Amount (INR)</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-medium">₹</span>
                                        <input type="number" step="1" name="amount" placeholder="0.00" required class="stripe-input w-full bg-white dark:bg-slate-800/50 pl-8 pr-3 py-2.5 rounded-lg text-sm text-stripe-dark dark:text-white">
                                    </div>
                                </div>
                                <button type="submit" class="mt-auto w-full bg-stripe-dark hover:bg-slate-800 dark:bg-stripe-blurple dark:hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors shadow-sm">
                                    Create Link
                                </button>
                            </form>
                            
                            <?php if($payment_link): ?>
                                <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800 rounded-lg">
                                    <p class="text-xs font-bold text-green-700 dark:text-green-500 mb-1">Generated URL:</p>
                                    <input type="text" readonly value="<?php echo $payment_link; ?>" class="w-full bg-transparent text-sm text-green-700 dark:text-green-400 outline-none cursor-pointer truncate font-mono" onclick="this.select(); document.execCommand('copy'); alert('Copied to clipboard!');">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TRANSACTIONS TAB -->
            <div id="orders" class="tab-content hidden animate-[fadeIn_0.3s_ease-out]">
                <div class="card overflow-hidden">
                    <div class="p-6 border-b border-stripe-border dark:border-dark-border flex justify-between items-center">
                        <h3 class="text-base font-bold text-stripe-dark dark:text-white">Recent Transactions</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[600px]">
                            <thead>
                                <tr class="bg-stripe-bg dark:bg-slate-800/50 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-stripe-border dark:border-dark-border">
                                    <th class="p-4 font-bold">Order ID</th>
                                    <th class="p-4 font-bold">Amount</th>
                                    <th class="p-4 font-bold">Date</th>
                                    <th class="p-4 font-bold text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stripe-border dark:divide-dark-border text-sm">
                                <?php if($orders->num_rows == 0): ?>
                                    <tr><td colspan="4" class="p-8 text-center text-slate-500">No transactions yet.</td></tr>
                                <?php endif; ?>
                                <?php while($row = $orders->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="p-4 font-mono text-xs text-stripe-dark dark:text-slate-300"><?php echo $row['order_id']; ?></td>
                                    <td class="p-4 font-semibold text-stripe-dark dark:text-white">₹<?php echo $row['final_amount']; ?></td>
                                    <td class="p-4 text-slate-500 text-xs"><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                    <td class="p-4 text-right">
                                        <?php if($row['status'] == 'success'): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-green-100 text-green-700">Paid</span>
                                        <?php elseif($row['status'] == 'failed'): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-red-100 text-red-700">Failed</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- API TAB -->
            <div id="api" class="tab-content hidden animate-[fadeIn_0.3s_ease-out]">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 lg:gap-8">
                    <div class="xl:col-span-1 space-y-6">
                        <div class="card p-6 <?php echo !$is_configured ? 'opacity-50 pointer-events-none' : ''; ?>">
                            <div class="flex justify-between items-center mb-1">
                                <h3 class="text-base font-bold text-stripe-dark dark:text-white">API Keys</h3>
                            </div>
                            <p class="text-sm text-slate-500 mb-6">Use these keys to authenticate API requests.</p>
                            
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="generate_api" value="1">
                                <div>
                                    <label class="block text-xs font-semibold text-stripe-text dark:text-slate-300 mb-2 uppercase tracking-wide">Secret Key</label>
                                    <input type="text" id="api_key_field" readonly value="<?php echo $user['api_key'] ? $user['api_key'] : 'Not Generated'; ?>" class="stripe-input w-full bg-slate-50 dark:bg-slate-800/50 px-3 py-2.5 rounded-lg text-sm font-mono text-stripe-dark outline-none cursor-pointer" onclick="this.select(); document.execCommand('copy'); alert('Copied!');">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-stripe-text dark:text-slate-300 mb-2 uppercase tracking-wide">Webhook URL</label>
                                    <input type="url" name="webhook_url" placeholder="https://yoursite.com/webhook" value="<?php echo htmlspecialchars($user['webhook_url']); ?>" class="stripe-input w-full bg-white dark:bg-slate-800/50 px-3 py-2.5 rounded-lg text-sm text-stripe-dark dark:text-white">
                                </div>
                                <button type="submit" class="w-full bg-white hover:bg-slate-50 text-stripe-dark font-semibold py-2.5 rounded-lg text-sm transition-colors border border-stripe-border shadow-sm mt-2">
                                    Roll Secret Key
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SETTINGS TAB (Includes Expiry Time) -->
            <div id="settings" class="tab-content hidden animate-[fadeIn_0.3s_ease-out]">
                <div class="max-w-2xl">
                    <div class="card p-6 lg:p-8">
                        <div class="mb-8">
                            <h3 class="text-lg font-bold text-stripe-dark dark:text-white">Business Settings</h3>
                            <p class="text-sm text-slate-500 mt-1">Configure your bank, email routing details, and payment link settings.</p>
                        </div>
                        
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="update_settings" value="1">
                            
                            <div>
                                <label class="block text-xs font-semibold text-stripe-text dark:text-slate-300 mb-2 uppercase tracking-wide">Receiving UPI ID</label>
                                <input type="text" name="upi_id" placeholder="e.g. name@fam" value="<?php echo htmlspecialchars($user['upi_id']); ?>" required class="stripe-input w-full bg-white dark:bg-slate-800/50 px-4 py-3 rounded-lg text-sm text-stripe-dark dark:text-white">
                                <p class="text-[11px] text-slate-500 mt-1.5">Funds will be settled directly to this VPA.</p>
                            </div>

                            <div class="pt-4 border-t border-stripe-border dark:border-dark-border">
                                <h4 class="text-sm font-semibold text-stripe-dark dark:text-white mb-4">Payment Link Settings</h4>
                                <div>
                                    <label class="block text-xs font-semibold text-stripe-text dark:text-slate-300 mb-2 uppercase tracking-wide">Link Expiry Time (Minutes)</label>
                                    <input type="number" name="expiry_minutes" min="1" max="1440" value="<?php echo $user_expiry; ?>" required class="stripe-input w-full bg-white dark:bg-slate-800/50 px-4 py-3 rounded-lg text-sm text-stripe-dark dark:text-white">
                                    <p class="text-[11px] text-slate-500 mt-1.5">Set how many minutes until the generated payment link expires (e.g., 5 or 10).</p>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-stripe-border dark:border-dark-border">
                                <h4 class="text-sm font-semibold text-stripe-dark dark:text-white mb-4">Verification Layer</h4>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-stripe-text dark:text-slate-300 mb-2 uppercase tracking-wide">Gmail Address (IMAP)</label>
                                        <input type="email" name="imap_email" placeholder="your_email@gmail.com" value="<?php echo htmlspecialchars($user['imap_email']); ?>" required class="stripe-input w-full bg-white dark:bg-slate-800/50 px-4 py-3 rounded-lg text-sm text-stripe-dark dark:text-white">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-stripe-text dark:text-slate-300 mb-2 uppercase tracking-wide">App Password (16 chars)</label>
                                        <input type="password" name="imap_pass" id="imap_pass" 
                                               onfocus="this.type='text'" 
                                               onblur="this.type='password'" 
                                               placeholder="••••••••••••••••" 
                                               value="<?php echo htmlspecialchars($user['imap_pass']); ?>" 
                                               required 
                                               class="stripe-input w-full bg-white dark:bg-slate-800/50 px-4 py-3 rounded-lg text-sm font-mono tracking-widest text-stripe-dark dark:text-white transition-all">
                                        <p class="text-[11px] text-slate-500 mt-1.5">Tap field to view. Encrypted before storage. Required for UTR reconciliation.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pt-4">
                                <button type="submit" class="bg-stripe-blurple hover:bg-indigo-600 text-white font-semibold px-6 py-2.5 rounded-full text-sm transition-colors shadow-sm">
                                    Save Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        const html = document.documentElement;
        function toggleDark() {
            html.classList.toggle('dark');
            localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
            if(window.myChart) updateChartTheme();
        }
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        }

        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        const titles = { 'overview': 'Overview', 'orders': 'Transactions', 'api': 'Developers', 'settings': 'Settings' };
        
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            const defaultClass = "w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-stripe-text hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors";
            ['overview', 'orders', 'api', 'settings'].forEach(id => { document.getElementById('btn-' + id).className = defaultClass; });
            
            document.getElementById(tabId).classList.remove('hidden');
            const activeBtn = document.getElementById('btn-' + tabId);
            activeBtn.className = "w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg bg-stripe-bg dark:bg-slate-800 text-stripe-blurple dark:text-white transition-colors";
            
            document.getElementById('pageTitle').innerText = titles[tabId];
            if(window.innerWidth < 1024 && !sidebar.classList.contains('-translate-x-full')) toggleSidebar();
        }

        <?php if($is_configured): ?>
        const ctx = document.getElementById('revenueChart').getContext('2d');
        window.myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo $labels_json; ?>,
                datasets: [{
                    data: <?php echo $values_json; ?>,
                    borderColor: '#635BFF', backgroundColor: 'rgba(99, 91, 255, 0.1)',
                    borderWidth: 2, pointBackgroundColor: '#FFFFFF', pointBorderColor: '#635BFF',
                    pointBorderWidth: 2, pointRadius: 4, fill: true, tension: 0.4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(230, 232, 235, 1)', drawBorder: false }, ticks: { font: { family: 'Inter', size: 12 }, color: '#637381' } },
                    x: { grid: { display: false, drawBorder: false }, ticks: { font: { family: 'Inter', size: 12 }, color: '#637381' } }
                }
            }
        });
        function updateChartTheme() {
            const isDark = html.classList.contains('dark');
            window.myChart.options.scales.y.grid.color = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(230, 232, 235, 1)';
            window.myChart.options.scales.y.ticks.color = isDark ? '#94a3b8' : '#637381';
            window.myChart.options.scales.x.ticks.color = isDark ? '#94a3b8' : '#637381';
            window.myChart.update();
        }
        updateChartTheme();
        <?php endif; ?>
    </script>
</body>
</html>
