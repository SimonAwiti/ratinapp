<?php
// cron_notifications.php
require_once "admin/includes/config.php";

function sendExpiryNotifications($con) {
    // Find users whose subscriptions expire in 4 days (30 days - 26 days from approval)
    $query = "SELECT id, email, full_name, approved_date,
              DATE_ADD(approved_date, INTERVAL 30 DAY) as expiry_date
              FROM subscribed_users 
              WHERE status = 'active' 
              AND approved_date IS NOT NULL
              AND DATE(approved_date) = DATE_SUB(CURDATE(), INTERVAL 26 DAY)
              AND id NOT IN (
                  SELECT user_id FROM email_notifications 
                  WHERE notification_type = 'subscription_expiry' 
                  AND DATE(sent_at) = CURDATE()
              )";
    
    $stmt = $con->prepare($query);
    if (!$stmt) return 0;
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sent_count = 0;
    while ($user = $result->fetch_assoc()) {
        $expiry_date = date('F j, Y', strtotime($user['expiry_date']));
        $subject = "Your RATIN Subscription Expires Soon";
        $message = "Dear " . $user['full_name'] . ",\n\n";
        $message .= "This is a reminder that your RATIN subscription will expire in 4 days on {$expiry_date}.\n\n";
        $message .= "To continue enjoying uninterrupted access, please renew your subscription before it expires.\n\n";
        $message .= "Login to renew: " . (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . "/login.php\n\n";
        $message .= "Thank you for using RATIN Trade Analytics!\n";
        
        // Email headers
        $headers = "From: RATIN Trade Analytics <noreply@ratin.com>\r\n";
        $headers .= "Reply-To: support@ratin.com\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Send email
        $email_sent = mail($user['email'], $subject, $message, $headers);
        
        // Log the notification
        $log_stmt = $con->prepare("INSERT INTO email_notifications (user_id, notification_type, subject, message, sent_at, status) VALUES (?, 'subscription_expiry', ?, ?, NOW(), ?)");
        $status = $email_sent ? 'sent' : 'failed';
        $log_stmt->bind_param("isss", $user['id'], $subject, $message, $status);
        $log_stmt->execute();
        
        if ($email_sent) $sent_count++;
    }
    
    return $sent_count;
}

// Run the function
$count = sendExpiryNotifications($con);
echo "[" . date('Y-m-d H:i:s') . "] Sent {$count} expiry notification(s).\n";

// Log to file
$log_entry = date('Y-m-d H:i:s') . " - Sent {$count} notifications\n";
file_put_contents('/var/log/ratin_cron.log', $log_entry, FILE_APPEND);
?>