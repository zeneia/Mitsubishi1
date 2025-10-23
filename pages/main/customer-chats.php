
<?php
// Start session first
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

include_once(dirname(__DIR__, 2) . '/includes/database/db_conn.php');

// Check if user is logged in and is a sales agent
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'SalesAgent') {
  header("Location: ../login.php");
  exit;
}

$agent_id = $_SESSION['user_id'];

/**
 * Call DeepSeek AI for Sales Agent assistance
 */
function callDeepSeekForAgent($query) {
    try {
        // Call DeepSeek chatbot API with sales agent context
        $apiUrl = '../../includes/api/deepseek_chatbot.php';
        $postData = json_encode(['message' => $query]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $postData
            ]
        ]);
        
        $response = file_get_contents($apiUrl, false, $context);
        
        if ($response === false) {
            throw new Exception('Failed to connect to AI service');
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['success']) && $result['success']) {
            return $result['response'];
        } else {
            throw new Exception($result['error'] ?? 'Unknown AI error');
        }
        
    } catch (Exception $e) {
        error_log("DeepSeek Agent API Error: " . $e->getMessage());
        throw $e;
    }
}

try {
  // Get conversations that need agent attention (bot conversations + assigned conversations)
  $conversationsQuery = $connect->prepare("
        SELECT c.*, 
               a.FirstName, a.LastName, a.Username,
               ci.firstname as customer_firstname, ci.lastname as customer_lastname,
               (SELECT message_text FROM messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT created_at FROM messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time,
               (SELECT COUNT(*) FROM messages WHERE conversation_id = c.conversation_id AND sender_type = 'Customer' AND is_read = 0) as unread_count,
               CASE 
                   WHEN c.agent_id IS NULL THEN 'Bot Conversation'
                   WHEN c.agent_id = ? THEN 'Your Customer'
                   ELSE 'Assigned to Other Agent'
               END as conversation_status
        FROM conversations c
        JOIN accounts a ON c.customer_id = a.Id
        LEFT JOIN customer_information ci ON a.Id = ci.account_id
        WHERE (c.agent_id IS NULL OR c.agent_id = ?)
        AND c.status IN ('Pending', 'Active')
        ORDER BY 
            CASE WHEN c.agent_id IS NULL THEN 0 ELSE 1 END,
            COALESCE(c.last_message_at, c.created_at) DESC
    ");
  $conversationsQuery->execute([$agent_id, $agent_id]);
  $conversations = $conversationsQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log("Error fetching conversations: " . $e->getMessage());
  $conversations = [];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');

  try {
    if ($_POST['action'] === 'take_conversation') {
      $conversation_id = $_POST['conversation_id'];

      // Assign this conversation to the current agent
      $assignConv = $connect->prepare("
                UPDATE conversations 
                SET agent_id = ?, status = 'Active', updated_at = NOW() 
                WHERE conversation_id = ? AND agent_id IS NULL
            ");
      $result = $assignConv->execute([$agent_id, $conversation_id]);

      if ($result && $assignConv->rowCount() > 0) {
        // Also update customer_information if not already assigned
        $updateCustomer = $connect->prepare("
                    UPDATE customer_information 
                    SET agent_id = ? 
                    WHERE account_id = (SELECT customer_id FROM conversations WHERE conversation_id = ?)
                    AND (agent_id IS NULL OR agent_id = 0)
                ");
        $updateCustomer->execute([$agent_id, $conversation_id]);

        echo json_encode(['success' => true]);
      } else {
        echo json_encode(['success' => false, 'error' => 'Conversation already taken']);
      }
      exit;
    }

    if ($_POST['action'] === 'transfer_to_ai') {
      $conversation_id = $_POST['conversation_id'];

      // Check if this agent owns this conversation
      $checkOwnership = $connect->prepare("
                SELECT agent_id FROM conversations WHERE conversation_id = ?
            ");
      $checkOwnership->execute([$conversation_id]);
      $conv = $checkOwnership->fetch();

      if ($conv && $conv['agent_id'] == $agent_id) {
        // Transfer conversation back to AI by setting agent_id to NULL
        $transferConv = $connect->prepare("
                    UPDATE conversations 
                    SET agent_id = NULL, status = 'Pending', updated_at = NOW() 
                    WHERE conversation_id = ? AND agent_id = ?
                ");
        $result = $transferConv->execute([$conversation_id, $agent_id]);

        if ($result && $transferConv->rowCount() > 0) {
          // Add a system message to indicate the transfer
          $insertMessage = $connect->prepare("
                        INSERT INTO messages (conversation_id, sender_id, sender_type, message_text) 
                        VALUES (?, 0, 'SalesAgent', ?)
                    ");
          $transferMessage = "🤖 This conversation has been transferred back to the AI chatbot. The AI will continue assisting the customer.";
          $insertMessage->execute([$conversation_id, $transferMessage]);

          // Update conversation last message time
          $updateConv = $connect->prepare("UPDATE conversations SET last_message_at = NOW() WHERE conversation_id = ?");
          $updateConv->execute([$conversation_id]);

          echo json_encode(['success' => true]);
        } else {
          echo json_encode(['success' => false, 'error' => 'Failed to transfer conversation']);
        }
      } else {
        echo json_encode(['success' => false, 'error' => 'Not authorized for this conversation']);
      }
      exit;
    }

    if ($_POST['action'] === 'send_message') {
      $conversation_id = $_POST['conversation_id'];
      $message_text = trim($_POST['message_text']);

      if (!empty($message_text)) {
        // Check if this agent owns this conversation
        $checkOwnership = $connect->prepare("
                    SELECT agent_id FROM conversations WHERE conversation_id = ?
                ");
        $checkOwnership->execute([$conversation_id]);
        $conv = $checkOwnership->fetch();

        if ($conv && $conv['agent_id'] == $agent_id) {
          // Check if this is an AI-assistance request (starts with /ai or @ai)
          $isAiRequest = (strpos($message_text, '/ai ') === 0 || strpos($message_text, '@ai ') === 0);
          
          if ($isAiRequest) {
            // Extract the actual query (remove /ai or @ai prefix)
            $aiQuery = preg_replace('/^(\/ai |@ai )/', '', $message_text);
            
            try {
              // Call DeepSeek AI for sales agent assistance
              $aiResponse = callDeepSeekForAgent($aiQuery);
              $message_text = "[AI Assistance] " . $aiResponse;
            } catch (Exception $e) {
              $message_text = "[AI Assistance] I couldn't process that request right now. Please try again later.";
              error_log("DeepSeek API Error for agent: " . $e->getMessage());
            }
          }
          
          // Insert message
          $insertMessage = $connect->prepare("
                        INSERT INTO messages (conversation_id, sender_id, sender_type, message_text) 
                        VALUES (?, ?, 'SalesAgent', ?)
                    ");
          $insertMessage->execute([$conversation_id, $agent_id, $message_text]);

          // Update conversation last message time
          $updateConv = $connect->prepare("UPDATE conversations SET last_message_at = NOW() WHERE conversation_id = ?");
          $updateConv->execute([$conversation_id]);

          echo json_encode(['success' => true, 'ai_assisted' => $isAiRequest]);
        } else {
          echo json_encode(['success' => false, 'error' => 'Not authorized for this conversation']);
        }
      } else {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
      }
      exit;
    }

    if ($_POST['action'] === 'get_messages') {
      $conversation_id = $_POST['conversation_id'];

      // Mark messages as read
      $markRead = $connect->prepare("
                UPDATE messages SET is_read = 1 
                WHERE conversation_id = ? AND sender_type = 'Customer'
            ");
      $markRead->execute([$conversation_id]);

      // Get messages with proper sender information
      $messagesQuery = $connect->prepare("
                SELECT m.*, 
                       CASE 
                           WHEN m.sender_type = 'SalesAgent' AND m.sender_id = 0 THEN 'Support Bot'
                           WHEN m.sender_type = 'SalesAgent' THEN CONCAT(COALESCE(a.FirstName, ''), ' ', COALESCE(a.LastName, ''))
                           ELSE CONCAT(COALESCE(ca.FirstName, ''), ' ', COALESCE(ca.LastName, ''))
                       END as sender_name,
                       CASE 
                           WHEN m.sender_type = 'SalesAgent' AND m.sender_id = 0 THEN 'Bot'
                           WHEN m.sender_type = 'SalesAgent' THEN COALESCE(a.Username, 'Agent')
                           ELSE COALESCE(ca.Username, 'Customer')
                       END as sender_username,
                       CASE 
                           WHEN m.sender_type = 'SalesAgent' AND m.sender_id = 0 THEN a.FirstName
                           WHEN m.sender_type = 'SalesAgent' THEN a.FirstName
                           ELSE ca.FirstName
                       END as FirstName,
                       CASE 
                           WHEN m.sender_type = 'SalesAgent' AND m.sender_id = 0 THEN a.Username
                           WHEN m.sender_type = 'SalesAgent' THEN a.Username
                           ELSE ca.Username
                       END as Username
                FROM messages m
                LEFT JOIN accounts a ON m.sender_id = a.Id AND m.sender_type = 'SalesAgent' AND m.sender_id != 0
                LEFT JOIN accounts ca ON m.sender_id = ca.Id AND m.sender_type = 'Customer'
                WHERE m.conversation_id = ?
                ORDER BY m.created_at ASC
            ");
      $messagesQuery->execute([$conversation_id]);
      $messages = $messagesQuery->fetchAll(PDO::FETCH_ASSOC);

      echo json_encode(['success' => true, 'messages' => $messages]);
      exit;
    }

    if ($_POST['action'] === 'get_customer_info') {
      $conversation_id = $_POST['conversation_id'];

      // Get detailed customer information
      $customerInfoQuery = $connect->prepare("
        SELECT c.conversation_id, c.created_at as conversation_started,
               a.Id as customer_id, a.Username, a.Email, a.FirstName, a.LastName, a.CreatedAt as account_created,
               ci.firstname, ci.lastname, ci.middlename, ci.suffix, ci.nationality, ci.birthday, ci.age, 
               ci.gender, ci.civil_status, ci.mobile_number, ci.employment_status, ci.company_name, 
               ci.position, ci.monthly_income, ci.valid_id_type, ci.valid_id_number, ci.Status as verification_status,
               (SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND sender_type = 'Customer') as total_messages,
               (SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND sender_type = 'SalesAgent' AND sender_id != 0) as agent_responses,
               (SELECT created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC LIMIT 1) as first_message_time,
               (SELECT created_at FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1) as last_activity
        FROM conversations c
        JOIN accounts a ON c.customer_id = a.Id
        LEFT JOIN customer_information ci ON a.Id = ci.account_id
        WHERE c.conversation_id = ?
      ");
      $customerInfoQuery->execute([$conversation_id, $conversation_id, $conversation_id, $conversation_id, $conversation_id]);
      $customerInfo = $customerInfoQuery->fetch(PDO::FETCH_ASSOC);

      if ($customerInfo) {
        echo json_encode(['success' => true, 'customer_info' => $customerInfo]);
      } else {
        echo json_encode(['success' => false, 'error' => 'Customer information not found']);
      }
      exit;
    }
  } catch (Exception $e) {
    error_log("Error in agent AJAX handler: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
    exit;
  }
}

$current_conversation = null;
if (isset($_GET['conversation_id'])) {
  foreach ($conversations as $conv) {
    if ($conv['conversation_id'] == $_GET['conversation_id']) {
      $current_conversation = $conv;
      break;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customer Chats - Mitsubishi</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <style>
    /* ==========================================================================
       CSS Custom Properties (Variables)
       ========================================================================== */

    :root {
      --primary-red: #dc2626;
      --primary-dark: #b91c1c;
      --primary-light: #fef2f2;
      --accent-blue: #2563eb;
      --text-dark: #1f2937;
      --text-light: #6b7280;
      --border-light: #e5e7eb;
      --shadow-light: 0 1px 3px rgba(0, 0, 0, 0.1);
      --shadow-medium: 0 4px 6px rgba(0, 0, 0, 0.1);
      --shadow-large: 0 10px 15px rgba(0, 0, 0, 0.1);
      --transition: all 0.3s ease;
      --border-radius: 8px;
      --border-radius-large: 15px;
    }

    /* ==========================================================================
       Sidebar Override - Remove Gradient Background
       ========================================================================== */
    
    /* Override the gradient background from common-styles.css */
    .sidebar {
      background: #1a1a1a !important; /* Solid dark background instead of gradient */
    }

    /* ==========================================================================
       Base Styles & Layout
       ========================================================================== */

    html,
    body {
      background: #ffffff;
      height: 100%;
      width: 100%;
      margin: 0;
      padding: 0;
      overflow: hidden;
    }
    
    body{
        zoom: 85%;
    }

    .main {
      height: 100vh;
      width: 100%;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .chat-container {
      display: grid;
      grid-template-columns: 350px 1fr;
      height: calc(100vh - 80px);
      gap: 0;
      overflow: hidden;
    }

    /* ==========================================================================
       Chat Sidebar Components
       ========================================================================== */

    .chat-sidebar {
      background: white;
      border-right: 1px solid var(--border-light);
      display: flex;
      flex-direction: column;
    }

    .chat-header {
      padding: 20px;
      border-bottom: 1px solid var(--border-light);
      background: #fef2f3;
    }

    .chat-header h2 {
      font-size: 1.2rem;
      color: var(--text-dark);
      margin-bottom: 15px;
    }

    .chat-search {
      position: relative;
    }

    .chat-search input {
      width: 100%;
      padding: 10px 15px 10px 40px;
      border: 1px solid var(--border-light);
      border-radius: 20px;
      font-size: 14px;
      transition: var(--transition);
    }

    .chat-search input:focus {
      outline: none;
      border-color: var(--primary-red);
      box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
    }

    .chat-search i {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
    }

    .chat-list {
      flex: 1;
      overflow-y: auto;
    }

    /* ==========================================================================
       Chat Item Components
       ========================================================================== */

    .chat-item {
      padding: 15px 20px;
      border-bottom: 1px solid var(--border-light);
      cursor: pointer;
      transition: var(--transition);
      position: relative;
    }

    .chat-item:hover {
      background: var(--primary-light);
    }

    .chat-item.active {
      background: var(--primary-red);
      color: white;
    }

    .chat-item.unread::before {
      content: '';
      position: absolute;
      left: 8px;
      top: 50%;
      transform: translateY(-50%);
      width: 8px;
      height: 8px;
      background: var(--primary-red);
      border-radius: 50%;
    }

    .chat-item.active.unread::before {
      background: white;
    }

    .chat-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .chat-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--primary-red);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 14px;
    }

    .chat-item.active .chat-avatar {
      background: white;
      color: var(--primary-red);
    }

    .chat-details {
      flex: 1;
      min-width: 0;
    }

    .chat-name {
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 4px;
    }

    .chat-preview {
      font-size: 12px;
      opacity: 0.8;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .chat-meta {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 5px;
    }

    .chat-time {
      font-size: 11px;
      opacity: 0.7;
    }

    .chat-badge {
      background: var(--primary-red);
      color: white;
      border-radius: 10px;
      padding: 2px 8px;
      font-size: 10px;
      font-weight: bold;
      min-width: 18px;
      text-align: center;
    }

    .chat-item.active .chat-badge {
      background: white;
      color: var(--primary-red);
    }

    .bot-conversation {
      border-left: 3px solid #fbbf24;
    }

    /* ==========================================================================
       Chat Main Area
       ========================================================================== */

    .chat-main {
      display: flex;
      flex-direction: column;
      background: #f8f9fa;
      height: 100%;
      min-height: 0;
      overflow: hidden;
    }

    .chat-topbar {
      background: white;
      padding: 20px 25px;
      border-bottom: 1px solid var(--border-light);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-shrink: 0;
    }

    .active-chat-info {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .active-chat-avatar {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      background: var(--primary-red);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
    }

    .active-chat-details h3 {
      font-size: 1.1rem;
      color: var(--text-dark);
      margin-bottom: 3px;
    }

    .active-chat-details p {
      font-size: 13px;
      color: var(--text-light);
    }

    .chat-actions {
      display: flex;
      gap: 10px;
    }

    .chat-action-btn {
      padding: 8px 12px;
      border: 1px solid var(--border-light);
      background: white;
      border-radius: 6px;
      cursor: pointer;
      transition: var(--transition);
    }

    .chat-action-btn:hover {
      background: var(--primary-light);
      border-color: var(--primary-red);
    }

    .chat-action-btn:focus {
      outline: 2px solid var(--primary-red);
      outline-offset: 2px;
    }

    /* ==========================================================================
       Message Components
       ========================================================================== */

    .chat-messages {
      flex: 1;
      padding: 20px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 15px;
      min-height: 0;
    }

    .message {
      display: flex;
      gap: 12px;
      max-width: 70%;
    }

    .message.sent {
      align-self: flex-end;
      flex-direction: row-reverse;
    }

    .message-avatar {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background: var(--primary-red);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 12px;
      flex-shrink: 0;
    }

    .message.sent .message-avatar {
      background: var(--accent-blue);
    }

    .message-content {
      flex: 1;
    }

    .message-bubble {
      background: white;
      padding: 12px 16px;
      border-radius: 18px;
      box-shadow: var(--shadow-light);
      font-size: 14px;
      line-height: 1.4;
    }

    .message.sent .message-bubble {
      background: var(--primary-red);
      color: white;
    }

    .message-time {
      font-size: 11px;
      color: var(--text-light);
      margin-top: 5px;
      padding: 0 5px;
    }

    .message.sent .message-time {
      text-align: right;
    }

    /* ==========================================================================
       Chat Input Components
       ========================================================================== */

    .chat-input-container {
      background: white;
      padding: 20px;
      border-top: 1px solid var(--border-light);
      flex-shrink: 0;
    }

    .chat-input-wrapper {
      display: flex;
      gap: 10px;
      align-items: flex-end;
    }

    .chat-input {
      flex: 1;
      padding: 12px 16px;
      border: 1px solid var(--border-light);
      border-radius: 20px;
      resize: none;
      max-height: 100px;
      font-family: inherit;
      font-size: 14px;
      transition: var(--transition);
    }

    .chat-input:focus {
      outline: none;
      border-color: var(--primary-red);
      box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
    }

    .chat-send-btn {
      width: 45px;
      height: 45px;
      background: var(--primary-red);
      color: white;
      border: none;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: var(--transition);
    }

    .chat-send-btn:hover {
      background: var(--primary-dark);
      transform: scale(1.05);
    }

    .chat-send-btn:focus {
      outline: 2px solid var(--primary-red);
      outline-offset: 2px;
    }

    /* State styles */
    .chat-input:disabled,
    .chat-send-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* ==========================================================================
       Empty State
       ========================================================================== */

    .empty-chat {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: var(--text-light);
      text-align: center;
      padding: 40px;
    }

    .empty-chat i {
      font-size: 4rem;
      margin-bottom: 20px;
      opacity: 0.3;
    }

    .empty-chat h3 {
      font-size: 1.5rem;
      margin-bottom: 10px;
    }

    /* ==========================================================================
       Mobile Toggle
       ========================================================================== */

    .mobile-chat-toggle {
      display: none;
    }

    /* ==========================================================================
       Customer Info Modal Styles
       ========================================================================== */

    /* Modal Scrollbar */
    #customerInfoContent::-webkit-scrollbar {
      width: 12px;
    }

    #customerInfoContent::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: var(--border-radius);
    }

    #customerInfoContent::-webkit-scrollbar-thumb {
      background: linear-gradient(135deg, var(--primary-red), var(--primary-dark));
      border-radius: var(--border-radius);
    }

    #customerInfoContent::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(135deg, var(--primary-dark), #991b1b);
    }

    /* Chat List Scrollbar */
    .chat-list::-webkit-scrollbar {
      width: 8px;
    }

    .chat-list::-webkit-scrollbar-track {
      background: #f1f1f1;
    }

    .chat-list::-webkit-scrollbar-thumb {
      background: var(--primary-red);
      border-radius: 4px;
    }

    .chat-list::-webkit-scrollbar-thumb:hover {
      background: var(--primary-dark);
    }

    /* Chat Messages Scrollbar */
    .chat-messages::-webkit-scrollbar {
      width: 8px;
    }

    .chat-messages::-webkit-scrollbar-track {
      background: #f1f1f1;
    }

    .chat-messages::-webkit-scrollbar-thumb {
      background: var(--primary-red);
      border-radius: 4px;
    }

    .chat-messages::-webkit-scrollbar-thumb:hover {
      background: var(--primary-dark);
    }

    /* Info Grid Layout */
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
      gap: 30px;
      margin-bottom: 30px;
    }

    .info-card {
      background: linear-gradient(135deg, #f8fafc, #f1f5f9);
      padding: 30px;
      border-radius: var(--border-radius-large);
      border: 1px solid #e2e8f0;
      box-shadow: var(--shadow-medium);
      min-height: 350px;
    }

    .info-card h3 {
      color: var(--primary-red);
      margin-bottom: 25px;
      font-size: 1.4rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      border-bottom: 3px solid var(--primary-red);
      padding-bottom: 15px;
    }

    .info-card h3 i {
      margin-right: 12px;
      background: linear-gradient(135deg, var(--primary-red), var(--primary-dark));
      color: white;
      padding: 10px;
      border-radius: 10px;
      font-size: 1.1rem;
    }

    .info-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 0;
      border-bottom: 1px solid var(--border-light);
    }

    .info-item:last-child {
      border-bottom: none;
    }

    .info-label {
      font-weight: 600;
      color: #374151;
      min-width: 160px;
      font-size: 1rem;
    }

    .info-value {
      color: #6b7280;
      text-align: right;
      flex: 1;
      font-size: 1rem;
    }

    /* Statistics Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 25px;
      margin: 30px 0;
    }

    .stat-card {
      text-align: center;
      padding: 25px;
      background: white;
      border-radius: var(--border-radius);
      border-left: 6px solid;
      box-shadow: var(--shadow-medium);
      transition: var(--transition);
      min-height: 120px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-large);
    }

    .stat-number {
      font-size: 2.2rem;
      font-weight: bold;
      margin-bottom: 10px;
      line-height: 1;
    }

    .stat-label {
      font-size: 1.1rem;
      color: #6b7280;
      font-weight: 500;
    }

    /* Status Badge */
    .status-badge {
      display: inline-block;
      padding: 8px 16px;
      border-radius: 25px;
      font-size: 0.9rem;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Close Button */
    .close-button {
      background: linear-gradient(135deg, var(--primary-red), var(--primary-dark));
      color: white;
      border: none;
      padding: 15px 40px;
      border-radius: var(--border-radius);
      cursor: pointer;
      font-weight: 600;
      font-size: 1.1rem;
      transition: var(--transition);
      box-shadow: 0 6px 12px rgba(220, 38, 38, 0.3);
    }

    .close-button:focus {
      outline: 2px solid var(--primary-red);
      outline-offset: 2px;
    }

    .close-button:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-large);
    }

    /* Full Width Card */
    .full-width-card {
      grid-column: 1 / -1;
      min-height: 400px;
    }

    /* ==========================================================================
       Responsive Design
       ========================================================================== */

    /* Mobile Devices */
    @media (max-width: 575px) {
      .main {
        height: 100dvh;
      }
      
      .chat-container {
        grid-template-columns: 1fr;
        position: relative;
        height: calc(100dvh - 60px);
      }

      .chat-sidebar {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        z-index: 1000;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
      }

      .chat-sidebar.active {
        transform: translateX(0);
      }

      .mobile-chat-toggle {
        display: block;
        position: absolute;
        top: 20px;
        left: 20px;
        z-index: 1001;
        background: var(--primary-red);
        color: white;
        border: none;
        padding: 10px;
        border-radius: var(--border-radius);
        cursor: pointer;
        transition: var(--transition);
      }
      
      .mobile-chat-toggle:hover {
        background: var(--primary-dark);
      }
      
      .mobile-chat-toggle:focus {
        outline: 2px solid white;
        outline-offset: 2px;
      }

      .chat-main {
        width: 100%;
      }

      .message {
        max-width: 85%;
      }
      
      .chat-topbar {
        padding: 15px;
      }
      
      .active-chat-details h3 {
        font-size: 1rem;
      }
      
      .active-chat-details p {
        font-size: 12px;
      }
      
      .chat-action-btn {
        padding: 6px 10px;
        font-size: 12px;
      }
      
      .chat-messages {
        padding: 15px;
      }
      
      .chat-input-container {
        padding: 15px;
      }
    }

    /* Tablet Devices */
    @media (min-width: 576px) and (max-width: 767px) {
      .main {
        height: 100vh;
      }
      
      .chat-container {
        grid-template-columns: 300px 1fr;
        height: calc(100vh - 65px);
      }

      .message {
        max-width: 80%;
      }
      
      .chat-header {
        padding: 15px;
      }
      
      .chat-topbar {
        padding: 18px 20px;
      }
    }

    /* Small Desktop */
    @media (min-width: 768px) and (max-width: 991px) {
      .main {
        height: 100vh;
      }
      
      .chat-container {
        grid-template-columns: 320px 1fr;
        height: calc(100vh - 70px);
      }
    }

    /* Modal Responsive */
    @media (max-width: 1024px) {
      #modalContainer {
        width: 95vw;
        height: 95vh;
        max-height: 95vh;
      }
      
      .info-grid {
        grid-template-columns: 1fr;
        gap: 25px;
      }
      
      .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
      }
      
      .info-card {
        padding: 25px;
        min-height: 300px;
      }
      
      .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        padding: 12px 0;
      }
      
      .info-value {
        text-align: left;
      }
    }

    @media (max-width: 768px) {
      #modalContainer {
        width: 98vw;
        height: 98vh;
        max-height: 98vh;
      }
      
      #customerInfoContent {
        padding: 20px;
      }
      
      .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
      }
      
      .stat-card {
        padding: 20px;
        min-height: 100px;
      }
      
      .stat-number {
        font-size: 1.8rem;
      }
      
      .stat-label {
        font-size: 1rem;
      }
      
      .info-card {
        padding: 20px;
        min-height: 250px;
      }
      
      .info-card h3 {
        font-size: 1.2rem;
        margin-bottom: 20px;
      }
    }
    
    @media (max-width: 480px) {
      #modalContainer {
        width: 100vw;
        height: 100vh;
        max-height: 100vh;
        border-radius: 0;
      }
      
      .info-card h3 {
        font-size: 1.1rem;
      }
      
      .info-label, .info-value {
        font-size: 0.9rem;
      }
      
      .stat-number {
        font-size: 1.5rem;
      }
      
      .stat-label {
        font-size: 0.9rem;
      }
    }
  </style>
</head>

<body>
  <?php include '../../includes/components/sidebar.php'; ?>

  <div class="main">
    <?php include '../../includes/components/topbar.php'; ?>

    <div class="chat-container">
      <button class="mobile-chat-toggle" onclick="toggleChatSidebar()">
        <i class="fas fa-bars"></i>
      </button>

      <div class="chat-sidebar" id="chatSidebar">
        <div class="chat-header">
          <h2>Customer Chats</h2>
          <div class="chat-search">
            <input type="text" placeholder="Search conversations..." id="searchInput">
            <i class="fas fa-search"></i>
          </div>
        </div>

        <div class="chat-list">
          <?php foreach ($conversations as $conv): ?>
            <div class="chat-item <?php echo $conv['unread_count'] > 0 ? 'unread' : ''; ?> <?php echo $conv['conversation_status'] == 'Bot Conversation' ? 'bot-conversation' : ''; ?>"
              data-conversation-id="<?php echo $conv['conversation_id']; ?>">
              <div class="chat-info">
                <div class="chat-avatar">
                  <?php
                  $name = !empty($conv['customer_firstname']) ? $conv['customer_firstname'] : $conv['Username'];
                  echo strtoupper(substr($name, 0, 1));
                  ?>
                </div>
                <div class="chat-details">
                  <div class="chat-name">
                    <?php echo htmlspecialchars(!empty($conv['customer_firstname']) ?
                      $conv['customer_firstname'] . ' ' . $conv['customer_lastname'] : $conv['Username']); ?>
                    <?php if ($conv['conversation_status'] == 'Bot Conversation'): ?>
                      <span style="font-size: 10px; color: #fbbf24;">🤖 Needs Agent</span>
                    <?php endif; ?>
                  </div>
                  <div class="chat-preview">
                    <?php echo htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 50)); ?>
                  </div>
                </div>
                <div class="chat-meta">
                  <div class="chat-time">
                    <?php echo $conv['last_message_time'] ? date('g:i A', strtotime($conv['last_message_time'])) : ''; ?>
                  </div>
                  <?php if ($conv['unread_count'] > 0): ?>
                    <div class="chat-badge"><?php echo $conv['unread_count']; ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="chat-main">
        <?php if ($current_conversation): ?>
          <div class="chat-topbar">
            <div class="active-chat-info">
              <div class="active-chat-avatar">
                <?php
                $name = !empty($current_conversation['customer_firstname']) ?
                  $current_conversation['customer_firstname'] : $current_conversation['Username'];
                echo strtoupper(substr($name, 0, 1));
                ?>
              </div>
              <div class="active-chat-details">
                <h3><?php echo htmlspecialchars(!empty($current_conversation['customer_firstname']) ?
                      $current_conversation['customer_firstname'] . ' ' . $current_conversation['customer_lastname'] :
                      $current_conversation['Username']); ?></h3>
                <p><?php echo $current_conversation['conversation_status']; ?>
                  <?php if ($current_conversation['conversation_status'] == 'Your Customer'): ?>
                    <span style="color: #10b981; font-size: 11px; margin-left: 8px;">
                      <i class="fas fa-user-tie"></i> Agent Handling
                    </span>
                  <?php elseif ($current_conversation['conversation_status'] == 'Bot Conversation'): ?>
                    <span style="color: #fbbf24; font-size: 11px; margin-left: 8px;">
                      <i class="fas fa-robot"></i> AI Chatbot
                    </span>
                  <?php endif; ?>
                </p>
              </div>
            </div>
            <div class="chat-actions">
              <?php if ($current_conversation['conversation_status'] == 'Bot Conversation'): ?>
                <button class="chat-action-btn" id="takeConversationBtn" style="background: #fbbf24; color: #000;">
                  <i class="fas fa-hand-paper"></i> Take Over
                </button>
              <?php elseif ($current_conversation['conversation_status'] == 'Your Customer'): ?>
                <button class="chat-action-btn" id="transferToAiBtn" style="background: #10b981; color: white;">
                  <i class="fas fa-robot"></i> Transfer to AI
                </button>
              <?php endif; ?>
              <button class="chat-action-btn" id="customerInfoBtn">
                <i class="fas fa-info-circle"></i> Customer Info
              </button>
            </div>
          </div>

          <div class="chat-messages" id="chatMessages">
            <!-- Messages will be loaded here via JavaScript -->
          </div>

          <div class="chat-input-container">
            <div class="chat-input-wrapper">
              <textarea class="chat-input" id="messageInput" placeholder="Type your message... (Use /ai or @ai for AI assistance)" rows="1"
                <?php echo $current_conversation['conversation_status'] == 'Bot Conversation' ? 'disabled' : ''; ?>></textarea>
              <button class="chat-send-btn" id="sendBtn"
                <?php echo $current_conversation['conversation_status'] == 'Bot Conversation' ? 'disabled' : ''; ?>>
                <i class="fas fa-paper-plane"></i>
              </button>
            </div>
            <div style="font-size: 11px; color: #666; margin-top: 5px; text-align: center;">
              💡 Tip: Start your message with "/ai" or "@ai" for intelligent assistance (e.g., "/ai What's the best financing option for this customer?")
            </div>
            <?php if ($current_conversation['conversation_status'] == 'Bot Conversation'): ?>
              <p style="text-align: center; color: #666; font-size: 12px; margin-top: 10px;">
                Click "Take Over" to start responding to this customer
              </p>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="empty-chat">
            <i class="fas fa-comments"></i>
            <h3>Select a conversation</h3>
            <p>Choose a customer from the list to start messaging</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Customer Information Modal -->
  <div id="customerInfoModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; backdrop-filter: blur(5px);">
    <div id="modalContainer" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: var(--border-radius-large); padding: 0; max-width: 95vw; width: 1200px; height: 95vh; max-height: 95vh; overflow: hidden; box-shadow: var(--shadow-large);">

      <!-- Modal Header -->
      <div style="background: linear-gradient(135deg, var(--primary-red), var(--primary-dark)); color: white; padding: 25px 30px; border-radius: var(--border-radius-large) var(--border-radius-large) 0 0; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
        <h2 style="margin: 0; font-size: 1.8rem; font-weight: 600;">
          <i class="fas fa-user-circle" style="margin-right: 12px;"></i>Customer Information
        </h2>
        <button id="closeModal" style="background: rgba(255,255,255,0.2); border: none; font-size: 1.5rem; cursor: pointer; color: white; padding: 8px 12px; border-radius: var(--border-radius); transition: var(--transition);">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div id="customerInfoContent" style="padding: 30px; height: calc(95vh - 120px); overflow-y: auto; flex: 1;">
        <!-- Content will be loaded here -->
      </div>
    </div>
  </div>

  <script src="../../includes/js/common-scripts.js"></script>
  <script>
    let currentConversationId = <?php echo $current_conversation ? $current_conversation['conversation_id'] : 'null'; ?>;

    // Take over conversation functionality
    document.getElementById('takeConversationBtn')?.addEventListener('click', function() {
      if (!currentConversationId) return;

      fetch('customer-chats.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `action=take_conversation&conversation_id=${currentConversationId}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            location.reload(); // Refresh to update UI
          } else {
            alert(data.error || 'Failed to take conversation');
          }
        });
    });

    // Transfer to AI functionality
    document.getElementById('transferToAiBtn')?.addEventListener('click', function() {
      if (!currentConversationId) return;

      if (confirm('Are you sure you want to transfer this conversation back to the AI chatbot? The AI will continue assisting the customer.')) {
        fetch('customer-chats.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=transfer_to_ai&conversation_id=${currentConversationId}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              location.reload(); // Refresh to update UI
            } else {
              alert(data.error || 'Failed to transfer conversation to AI');
            }
          });
      }
    });

    function loadMessages(conversationId) {
      if (!conversationId) return;

      fetch('customer-chats.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `action=get_messages&conversation_id=${conversationId}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayMessages(data.messages);
          }
        });
    }

    function displayMessages(messages) {
      const chatMessages = document.getElementById('chatMessages');
      chatMessages.innerHTML = '';

      messages.forEach(message => {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${message.sender_type === 'SalesAgent' ? 'sent' : ''}`;

        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'message-avatar';

        if (message.sender_type === 'SalesAgent') {
          avatarDiv.textContent = message.sender_id == 0 ? '🤖' : 'A';
        } else {
          avatarDiv.textContent = message.FirstName ? message.FirstName.charAt(0).toUpperCase() : message.Username.charAt(0).toUpperCase();
        }

        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';

        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'message-bubble';
        bubbleDiv.textContent = message.message_text;

        const timeDiv = document.createElement('div');
        timeDiv.className = 'message-time';
        timeDiv.textContent = new Date(message.created_at).toLocaleTimeString('en-US', {
          hour: '2-digit',
          minute: '2-digit'
        });

        contentDiv.appendChild(bubbleDiv);
        contentDiv.appendChild(timeDiv);
        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(contentDiv);
        chatMessages.appendChild(messageDiv);
      });

      chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function sendMessage() {
      const messageInput = document.getElementById('messageInput');
      const messageText = messageInput.value.trim();

      if (!messageText || !currentConversationId || messageInput.disabled) return;

      fetch('customer-chats.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `action=send_message&conversation_id=${currentConversationId}&message_text=${encodeURIComponent(messageText)}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            messageInput.value = '';
            messageInput.style.height = 'auto';
            loadMessages(currentConversationId);
          }
        });
    }

    // Event listeners
    document.getElementById('sendBtn')?.addEventListener('click', sendMessage);
    document.getElementById('messageInput')?.addEventListener('keypress', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    // Auto-resize textarea
    document.getElementById('messageInput')?.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = this.scrollHeight + 'px';
    });

    // Chat item click handlers
    document.querySelectorAll('.chat-item').forEach(item => {
      item.addEventListener('click', function() {
        const conversationId = this.dataset.conversationId;
        window.location.href = `customer-chats.php?conversation_id=${conversationId}`;
      });
    });

    // Load messages on page load
    if (currentConversationId) {
      loadMessages(currentConversationId);

      // Auto-refresh messages every 3 seconds
      setInterval(() => {
        loadMessages(currentConversationId);
      }, 3000);
    }

    function toggleChatSidebar() {
      const sidebar = document.getElementById('chatSidebar');
      sidebar.classList.toggle('active');
    }

    // Customer Info Modal functionality
    document.getElementById('customerInfoBtn')?.addEventListener('click', function() {
      if (!currentConversationId) return;

      fetch('customer-chats.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `action=get_customer_info&conversation_id=${currentConversationId}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayCustomerInfo(data.customer_info);
            document.getElementById('customerInfoModal').style.display = 'block';
          } else {
            alert('Failed to load customer information');
          }
        });
    });

    // Close modal functionality
    document.getElementById('closeModal').addEventListener('click', function() {
      document.getElementById('customerInfoModal').style.display = 'none';
    });

    // Close modal when clicking outside
    document.getElementById('customerInfoModal').addEventListener('click', function(e) {
      if (e.target === this) {
        this.style.display = 'none';
      }
    });

    // Add hover effect to close button
    document.getElementById('closeModal').addEventListener('mouseenter', function() {
      this.style.background = 'rgba(255,255,255,0.3)';
    });

    document.getElementById('closeModal').addEventListener('mouseleave', function() {
      this.style.background = 'rgba(255,255,255,0.2)';
    });

    function displayCustomerInfo(info) {
      const content = document.getElementById('customerInfoContent');
      const fullName = info.firstname ?
        `${info.firstname} ${info.middlename || ''} ${info.lastname || ''} ${info.suffix || ''}`.trim() :
        `${info.FirstName || ''} ${info.LastName || ''}`.trim() || info.Username;

      const formatDate = (dateString) => {
        if (!dateString) return 'Not provided';
        return new Date(dateString).toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        });
      };

      const formatCurrency = (amount) => {
        if (!amount) return 'Not provided';
        return new Intl.NumberFormat('en-PH', {
          style: 'currency',
          currency: 'PHP'
        }).format(amount);
      };

      content.innerHTML = `
        <div class="info-grid">
          <!-- Personal Information -->
          <div class="info-card">
            <h3><i class="fas fa-user"></i>Personal Information</h3>
            <div class="info-item">
              <span class="info-label">Full Name:</span>
              <span class="info-value">${fullName}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Email:</span>
              <span class="info-value">${info.Email || 'Not provided'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Mobile:</span>
              <span class="info-value">${info.mobile_number || 'Not provided'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Birthday:</span>
              <span class="info-value">${formatDate(info.birthday)}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Age:</span>
              <span class="info-value">${info.age || 'Not provided'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Gender:</span>
              <span class="info-value">${info.gender || 'Not provided'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Civil Status:</span>
              <span class="info-value">${info.civil_status || 'Not provided'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Nationality:</span>
              <span class="info-value">${info.nationality || 'Not provided'}</span>
            </div>
          </div>

          <!-- Employment Information -->
          <div class="info-card">
            <h3><i class="fas fa-briefcase"></i>Employment Details</h3>
            <div class="info-item">
              <span class="info-label">Employment Status:</span>
              <span class="info-value">${info.employment_status || 'Not provided'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Company:</span>
              <span class="info-value">${info.company_name || 'Not provided'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Position:</span>
              <span class="info-value">${info.position || 'Not provided'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Monthly Income:</span>
              <span class="info-value">${formatCurrency(info.monthly_income)}</span>
            </div>
            <div class="info-item">
              <span class="info-label">ID Type:</span>
              <span class="info-value">${info.valid_id_type || 'Not provided'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">ID Number:</span>
              <span class="info-value">${info.valid_id_number || 'Not provided'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Verification Status:</span>
              <span class="info-value">
                <span class="status-badge" style="${info.verification_status === 'Approved' ? 'background: #dcfce7; color: #16a34a;' :
                  info.verification_status === 'Rejected' ? 'background: var(--primary-light); color: var(--primary-red);' :
                  'background: #fef3c7; color: #d97706;'}">
                  ${info.verification_status || 'Pending'}
                </span>
              </span>
            </div>
          </div>
        </div>

        <div class="info-grid">
          <!-- Account Information -->
          <div class="info-card">
            <h3><i class="fas fa-user-cog"></i>Account Details</h3>
            <div class="info-item">
              <span class="info-label">Customer ID:</span>
              <span class="info-value">${info.customer_id}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Username:</span>
              <span class="info-value">${info.Username}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Account Created:</span>
              <span class="info-value">${formatDate(info.account_created)}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Conversation Started:</span>
              <span class="info-value">${formatDate(info.conversation_started)}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Customer Type:</span>
              <span class="info-value">Online Registration</span>
            </div>
            <div class="info-item">
              <span class="info-label">Total Conversations:</span>
              <span class="info-value">1</span>
            </div>
          </div>
        </div>

        <!-- Conversation Statistics -->
        <div class="info-card full-width-card">
          <h3><i class="fas fa-chart-line"></i>Conversation & Communication Statistics</h3>
          <div class="stats-grid">
            <div class="stat-card" style="border-left-color: var(--primary-red);">
              <div class="stat-number" style="color: var(--primary-red);">${info.total_messages || 0}</div>
              <div class="stat-label">Customer Messages</div>
            </div>
            <div class="stat-card" style="border-left-color: #16a34a;">
              <div class="stat-number" style="color: #16a34a;">${info.agent_responses || 0}</div>
              <div class="stat-label">Agent Responses</div>
            </div>
            <div class="stat-card" style="border-left-color: var(--accent-blue);">
              <div class="stat-number" style="color: var(--accent-blue); font-size: 1.4rem;">${formatDate(info.first_message_time)}</div>
              <div class="stat-label">First Contact</div>
            </div>
            <div class="stat-card" style="border-left-color: #7c3aed;">
              <div class="stat-number" style="color: #7c3aed; font-size: 1.4rem;">${formatDate(info.last_activity)}</div>
              <div class="stat-label">Last Activity</div>
            </div>
            <div class="stat-card" style="border-left-color: #f59e0b;">
              <div class="stat-number" style="color: #f59e0b;">${((info.agent_responses || 0) / Math.max(info.total_messages || 1, 1) * 100).toFixed(1)}%</div>
              <div class="stat-label">Response Rate</div>
            </div>
            <div class="stat-card" style="border-left-color: #10b981;">
              <div class="stat-number" style="color: #10b981;">
                ${info.last_activity ? Math.ceil((new Date() - new Date(info.last_activity)) / (1000 * 60 * 60 * 24)) : 0}
              </div>
              <div class="stat-label">Days Since Last Activity</div>
            </div>
          </div>
        </div>

      `;
    }
  </script>
</body>

</html>