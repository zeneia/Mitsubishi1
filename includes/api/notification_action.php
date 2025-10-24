<?php
session_start();
require_once 'notification_api.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$action = $_POST['action'] ?? '';

$response = ['success' => false, 'message' => ''];

try {
    switch ($action) {
        case 'mark_read':
            $notification_id = intval($_POST['notification_id'] ?? 0);
            if ($notification_id) {
                $result = markNotificationRead($notification_id, $user_id);
                $response = [
                    'success' => $result,
                    'message' => $result ? 'Notification marked as read' : 'Failed to mark notification as read'
                ];
            } else {
                $response['message'] = 'Invalid notification ID';
            }
            break;
            
        case 'delete':
            $notification_id = intval($_POST['notification_id'] ?? 0);
            if ($notification_id) {
                $result = deleteNotification($notification_id, $user_id);
                $response = [
                    'success' => $result,
                    'message' => $result ? 'Notification deleted' : 'Failed to delete notification'
                ];
            } else {
                $response['message'] = 'Invalid notification ID';
            }
            break;
            
        case 'mark_all':
            $result = markAllNotificationsRead($user_id, $user_role);
            $response = [
                'success' => $result,
                'message' => $result ? 'All notifications marked as read' : 'Failed to mark all notifications as read'
            ];
            break;
            
        case 'clear_all':
            $result = clearAllNotifications($user_id, $user_role);
            $response = [
                'success' => $result,
                'message' => $result ? 'All notifications cleared' : 'Failed to clear all notifications'
            ];
            break;

        case 'get_notifications':
            $filter = $_POST['filter'] ?? 'all';
            $notifications = getNotifications($user_id, $user_role, $filter);
            $response = [
                'success' => true,
                'notifications' => $notifications
            ];
            break;

        default:
            http_response_code(400);
            $response['message'] = 'Invalid action';
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'An error occurred',
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);