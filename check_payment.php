<?php
require 'dbinfo.php';
header('Content-Type: application/json');

if (!isset($_GET['orderid'])) { echo json_encode(['status' => 'error']); exit; }
$order_id = $conn->real_escape_string($_GET['orderid']);

$query = "SELECT o.created_at, o.final_amount, o.status, o.txn_note, u.imap_email, u.imap_pass, u.gateway_type FROM orders o JOIN users u ON o.merchant_id = u.merchant_id WHERE o.order_id = '$order_id'";
$result = $conn->query($query);
if ($result->num_rows == 0) { echo json_encode(['status' => 'error']); exit; }
$order = $result->fetch_assoc();

if ($order['status'] == 'success' || $order['status'] == 'failed') {
    echo json_encode(['status' => $order['status']]); exit;
}

// 10 MINUTE TIMEOUT (600 Seconds)
if ((time() - strtotime($order['created_at'])) > 600) {
    $conn->query("UPDATE orders SET status='failed' WHERE order_id='$order_id'");
    echo json_encode(['status' => 'failed']); exit;
}

// Strict Matching Variables
$expected_amount = $order['final_amount'];
$expected_note = strtolower($order['txn_note']); 

$imap_server = '{imap.gmail.com:993/imap/ssl}INBOX';
$mbox = @imap_open($imap_server, $order['imap_email'], $order['imap_pass']);
if (!$mbox) { echo json_encode(['status' => 'pending']); exit; }

$emails = imap_search($mbox, 'UNSEEN');
$payment_received = false;

if ($emails) {
    rsort($emails); 
    foreach ($emails as $email_number) {
        $header = imap_headerinfo($mbox, $email_number);
        $from = strtolower($header->from[0]->mailbox . "@" . $header->from[0]->host);
        
        // Filter Based on User's Gateway Settings
        $is_fampay = (strpos($from, 'fam') !== false);
        $is_slice = (strpos($from, 'slice') !== false);
        
        $valid = false;
        if ($order['gateway_type'] == 'fampay' && $is_fampay) $valid = true;
        if ($order['gateway_type'] == 'slice' && $is_slice) $valid = true;
        if ($order['gateway_type'] == 'both' && ($is_fampay || $is_slice)) $valid = true;
        if (!$valid) continue; 

        // Decode and strip body for accurate search
        $body = imap_body($mbox, $email_number);
        $body = quoted_printable_decode($body);
        $clean_body = strtolower(strip_tags($body));
        $clean_body = preg_replace('/\s+/', '', $clean_body); 
        
        // Rule 1: Text contains typical success indicators
        $has_success = (strpos($clean_body, 'successfullyreceived') !== false || strpos($clean_body, 'received') !== false);
        
        // Rule 2: STRICT VERIFICATION - Contains the unique Note ID
        $has_note = (strpos($clean_body, $expected_note) !== false);

        if ($has_success && $has_note) {
            $payment_received = true;
            imap_setflag_full($mbox, $email_number, "\\Seen");
            
            // Mark Order Success & Log Timestamp
            $conn->query("UPDATE orders SET status='success', success_time=CURRENT_TIMESTAMP WHERE order_id='$order_id'");
            break;
        }
    }
}
imap_close($mbox);

if ($payment_received) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'pending']);
}
?>
