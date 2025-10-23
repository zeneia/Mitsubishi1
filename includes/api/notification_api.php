<?php
require_once dirname(__DIR__) . '/database/db_conn.php';

/**
 * Create a notification for a user or role.
 *
 * Usage:
 *   // Notify a specific user (e.g., after order placement)
 *   createNotification($userId, null, 'Order Placed', 'Your order #123 has been placed.', 'order', $orderId);
 *
 *   // Notify all agents (e.g., new test drive scheduled)
 *   createNotification(null, 'SalesAgent', 'Test Drive Scheduled', 'A customer scheduled a test drive.', 'test_drive', $testDriveId);
 *
 *   // Notify all users (system-wide)
 *   createNotification(null, null, 'System Update', 'The system will be down for maintenance.', 'system');
 *
 * @param int|null $user_id      Target user ID (null for role/global)
 * @param string|null $target_role Target role (e.g., 'Admin', 'Customer', 'SalesAgent')
 * @param string $title          Notification title
 * @param string $message        Notification message
 * @param string|null $type      Notification type (e.g., 'order', 'test_drive', 'system')
 * @param int|null $related_id   Optional related entity ID (order, test drive, etc.)
 * @return bool                  True on success, false on failure
 */
function createNotification($user_id, $target_role, $title, $message, $type = null, $related_id = null) {
    global $connect;
    $stmt = $connect->prepare("INSERT INTO notifications (user_id, target_role, title, message, type, related_id) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $target_role, $title, $message, $type, $related_id]);
}

function getNotifications($user_id, $user_role, $filter = 'all',$limit = null) {
    global $connect;
    $where = "(user_id = ? OR target_role = ? OR (user_id IS NULL AND target_role IS NULL))";
    $params = [$user_id, $user_role];
    if ($filter === 'unread') {
        $where .= " AND is_read = 0";
    } elseif ($filter !== 'all') {
        $where .= " AND type = ?";
        $params[] = $filter;
    }
    $sql = "SELECT * FROM notifications WHERE $where ORDER BY created_at DESC";
    if ($limit !== null) {
    $sql .= " LIMIT " . (int)$limit;
        
    }

    $stmt = $connect->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markNotificationRead($notification_id, $user_id) {
    global $connect;
    $stmt = $connect->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
    return $stmt->execute([$notification_id, $user_id]);
}

function markAllNotificationsRead($user_id, $user_role) {
    global $connect;
    $stmt = $connect->prepare("UPDATE notifications SET is_read = 1 WHERE (user_id = ? OR target_role = ?)");
    return $stmt->execute([$user_id, $user_role]);
}

function deleteNotification($notification_id, $user_id) {
    global $connect;
    $stmt = $connect->prepare("DELETE FROM notifications WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
    return $stmt->execute([$notification_id, $user_id]);
}

function clearAllNotifications($user_id, $user_role) {
    global $connect;
    $stmt = $connect->prepare("DELETE FROM notifications WHERE (user_id = ? OR target_role = ?)");
    return $stmt->execute([$user_id, $user_role]);
}
// End of notification API