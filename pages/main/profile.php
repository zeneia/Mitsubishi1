<?php
// Include the session initialization file at the very beginning
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../pages/login.php");
    exit();
}

// Initialize response variables
$response = ['success' => false, 'message' => '', 'type' => ''];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Handle profile update
        try {
            $email = trim($_POST['email'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $dateOfBirth = $_POST['date_of_birth'] ?? null;
            
            // Validate required fields
            if (empty($email) || empty($firstName) || empty($lastName)) {
                throw new Exception('Email, First Name, and Last Name are required.');
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format.');
            }
            
            // Check if email is already taken by another user
            $emailCheck = $connect->prepare("SELECT Id FROM accounts WHERE Email = ? AND Id != ?");
            $emailCheck->execute([$email, $_SESSION['user_id']]);
            if ($emailCheck->fetch()) {
                throw new Exception('Email is already taken by another user.');
            }

            // Fetch current user data to get existing profile image
            $currentUserStmt = $connect->prepare("SELECT ProfileImage FROM accounts WHERE Id = ?");
            $currentUserStmt->execute([$_SESSION['user_id']]);
            $currentUserData = $currentUserStmt->fetch(PDO::FETCH_ASSOC);
            $profileImageDataToUpdate = $currentUserData['ProfileImage'] ?? null;

            // Handle file upload
            if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['avatar_file']['tmp_name'];
                $fileName = $_FILES['avatar_file']['name'];
                $fileSize = $_FILES['avatar_file']['size'];
                // $fileType = $_FILES['avatar_file']['type']; // MIME type, not stored in current schema
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($fileExtension, $allowedfileExtensions)) {
                    if ($fileSize < 5000000) { // Max 5MB
                        // Read the file content into a variable
                        $profileImageDataToUpdate = file_get_contents($fileTmpPath);
                        if ($profileImageDataToUpdate === false) {
                            throw new Exception('Failed to read uploaded file content.');
                        }
                    } else {
                        throw new Exception('File is too large. Max size is 5MB.');
                    }
                } else {
                    throw new Exception('Invalid file type. Allowed types: jpg, jpeg, png, gif.');
                }
            } elseif (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                throw new Exception('Error uploading file. Code: ' . $_FILES['avatar_file']['error']);
            }
            
            // Update user profile with ProfileImage (BLOB)
            $updateQuery = "UPDATE accounts SET Email = ?, FirstName = ?, LastName = ?, DateOfBirth = ?, ProfileImage = ?, UpdatedAt = NOW() WHERE Id = ?";
            $stmt = $connect->prepare($updateQuery);
            // For BLOBs, PDO needs to know it's a LOB type
            $stmt->bindParam(1, $email);
            $stmt->bindParam(2, $firstName);
            $stmt->bindParam(3, $lastName);
            $stmt->bindParam(4, $dateOfBirth);
            $stmt->bindParam(5, $profileImageDataToUpdate, PDO::PARAM_LOB);
            $stmt->bindParam(6, $_SESSION['user_id']); // User ID is now the 6th parameter
            
            $stmt->execute();
            
            // If user is a Sales Agent, update their additional profile
            if ($_SESSION['user_role'] === 'SalesAgent') {
                // Add any specific Sales Agent profile updates here if needed
                // For example, if they have a separate display_name or other fields in sales_agent_profiles
                $agentBio = trim($_POST['agent_bio'] ?? ''); // Example field
                $agentDisplayName = trim($_POST['agent_display_name'] ?? ''); // Example field

                // Check if agent profile exists, then update or insert
                $checkAgentProfile = $connect->prepare("SELECT agent_profile_id FROM sales_agent_profiles WHERE account_id = ?");
                $checkAgentProfile->execute([$_SESSION['user_id']]);
                if ($checkAgentProfile->fetch()) {
                    $agentUpdateQuery = "UPDATE sales_agent_profiles SET bio = ?, display_name = ? WHERE account_id = ?";
                    $agentStmt = $connect->prepare($agentUpdateQuery);
                    $agentStmt->execute([$agentBio, $agentDisplayName, $_SESSION['user_id']]);
                } else {
                    // Optionally insert if it makes sense for your application flow
                    // $agentInsertQuery = "INSERT INTO sales_agent_profiles (account_id, bio, display_name) VALUES (?, ?, ?)";
                    // $agentStmt = $connect->prepare($agentInsertQuery);
                    // $agentStmt->execute([$_SESSION['user_id'], $agentBio, $agentDisplayName]);
                }
            }
            
            $response = ['success' => true, 'message' => 'Profile updated successfully!', 'type' => 'profile'];
            
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Profile update error: ' . $e->getMessage(), 'type' => 'profile'];
        }
    }
    
    if ($action === 'change_password') {
        // Handle password change
        try {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Validate required fields
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                throw new Exception('Please fill in all password fields.');
            }
            
            // Check if new passwords match
            if ($newPassword !== $confirmPassword) {
                throw new Exception('New password and confirmation do not match.');
            }
            
            // Validate password strength
            if (strlen($newPassword) < 8) {
                throw new Exception('New password must be at least 8 characters long.');
            }
            
            // Fetch current user data
            $userQuery = $connect->prepare("SELECT PasswordHash FROM accounts WHERE Id = ?");
            $userQuery->execute([$_SESSION['user_id']]);
            $userData = $userQuery->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                throw new Exception('User account not found.');
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $userData['PasswordHash'])) {
                throw new Exception('Current password is incorrect.');
            }
            
            // Hash new password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $passwordUpdate = $connect->prepare("UPDATE accounts SET PasswordHash = ?, UpdatedAt = NOW() WHERE Id = ?");
            $passwordUpdate->execute([$newPasswordHash, $_SESSION['user_id']]);
            
            $response = ['success' => true, 'message' => 'Password changed successfully!', 'type' => 'password'];
            
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage(), 'type' => 'password'];
        }
    }
    
    // Return JSON response for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
}

// Fetch logged-in user's complete data
$user_data = null;
$agent_data = null;
try {
    $stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If user is a Sales Agent, fetch their additional profile data
    if ($user_data && $user_data['Role'] === 'SalesAgent') {
        $agentStmt = $connect->prepare("SELECT * FROM sales_agent_profiles WHERE account_id = ?");
        $agentStmt->execute([$_SESSION['user_id']]);
        $agent_data = $agentStmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    header("Location: ../../pages/login.php");
    exit();
}

// If user not found, redirect to login
if (!$user_data) {
    header("Location: ../../pages/login.php");
    exit();
}

// Store role in session for easy access
$_SESSION['user_role'] = $user_data['Role'];

// Set display values with proper null checks
$display_name = 'User';
$first_name = $user_data['FirstName'] ?? '';
$last_name = $user_data['LastName'] ?? '';

// For Sales Agents, use display_name if available
if ($user_data['Role'] === 'SalesAgent' && !empty($agent_data['display_name'])) {
    $display_name = $agent_data['display_name'];
} elseif (!empty($first_name) && !empty($last_name)) {
    $display_name = $first_name . ' ' . $last_name;
} elseif (!empty($first_name)) {
    $display_name = $first_name;
} else {
    $display_name = $user_data['Username'] ?? 'User';
}

$display_role = $user_data['Role'] ?? 'Customer';

// Format dates safely
$last_login_display = 'Never';
if (!empty($user_data['LastLoginAt']) && $user_data['LastLoginAt'] !== null) {
    $last_login_display = date('M d, Y h:i A', strtotime($user_data['LastLoginAt']));
}

$created_display = 'Unknown';
if (!empty($user_data['CreatedAt']) && $user_data['CreatedAt'] !== null) {
    $created_display = date('M d, Y', strtotime($user_data['CreatedAt']));
}

// Prepare avatar HTML
$avatar_html = '';
if (!empty($user_data['ProfileImage'])) {
    $imageData = base64_encode($user_data['ProfileImage']);
    // Defaulting to image/jpeg as MIME type is not stored.
    // For robust multi-type support, store MIME type in DB.
    $imageMimeType = 'image/jpeg'; 
    $avatar_html = '<img src="data:' . $imageMimeType . ';base64,' . $imageData . '" alt="' . htmlspecialchars($display_name) . ' Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
} else {
    $initials = '';
    if (!empty($first_name)) {
        $initials .= strtoupper(substr($first_name, 0, 1));
    }
    if (!empty($last_name)) {
        $initials .= strtoupper(substr($last_name, 0, 1));
    }
    if (empty($initials) && !empty($user_data['Username'])) {
        $initials = strtoupper(substr($user_data['Username'], 0, 1));
    }
    if (empty($initials)) {
        $initials = 'U'; // Default User initial
    }
    $avatar_html = htmlspecialchars($initials);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profile Settings - Mitsubishi</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <style>
        
    html, body {
      height: 100%;
      width: 100%;
      margin: 0;
      padding: 0;
      overflow: hidden;
    }
    
    body {
      zoom: 85%;
    }
    .profile-container {
      display: grid;
      grid-template-columns: 350px 1fr;
      gap: 30px;
      /* Remove fixed height to allow content to determine height */
      min-height: calc(100vh - 80px);
    }

    .profile-sidebar {
      background: white;
      border-radius: 12px;
      box-shadow: var(--shadow-light);
      padding: 0;
      overflow: hidden;
      height: fit-content;
      /* Add sticky positioning to keep sidebar visible */
      position: sticky;
      top: 20px;
    }

    .profile-header {
      background: linear-gradient(135deg, var(--primary-red), #b91c3c);
      color: white;
      padding: 30px 25px;
      text-align: center;
    }

    .profile-avatar-section {
      position: relative;
      margin-bottom: 20px;
    }

    .profile-avatar-large {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.5rem;
      color: white;
      margin: 0 auto 15px;
      border: 4px solid rgba(255, 255, 255, 0.3);
    }

    .profile-avatar-large img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
    }

    .avatar-upload {
      position: absolute;
      bottom: 0;
      right: calc(50% - 60px);
      background: white;
      color: var(--primary-red);
      border: none;
      border-radius: 50%;
      width: 35px;
      height: 35px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: var(--shadow-light);
      transition: var(--transition);
    }

    .avatar-upload:hover {
      transform: scale(1.1);
    }

    .profile-name {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .profile-role {
      opacity: 0.9;
      font-size: 14px;
    }

    .profile-nav {
      padding: 0;
    }

    .nav-item {
      display: flex;
      align-items: center;
      padding: 15px 25px;
      cursor: pointer;
      transition: var(--transition);
      border-bottom: 1px solid var(--border-light);
      color: var(--text-dark);
      text-decoration: none;
    }

    .nav-item:hover {
      background: var(--primary-light);
    }

    .nav-item.active {
      background: var(--primary-red);
      color: white;
    }

    .nav-item i {
      width: 20px;
      margin-right: 12px;
      text-align: center;
    }

    .profile-content {
      background: white;
      border-radius: 12px;
      box-shadow: var(--shadow-light);
      overflow: hidden;
    }

    .content-header {
      padding: 25px 30px;
      border-bottom: 1px solid var(--border-light);
      background: var(--primary-light);
    }

    .content-header h2 {
      font-size: 1.5rem;
      color: var(--text-dark);
      margin: 0;
    }

    .content-body {
      padding: 30px;
      /* Remove max-height constraint */
      /* Remove overflow-y to allow natural expansion */
    }

    .form-section {
      margin-bottom: 40px;
    }

    .section-title {
      font-size: 1.2rem;
      color: var(--text-dark);
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid var(--border-light);
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .form-label {
      font-size: 14px;
      font-weight: 600;
      color: var(--text-dark);
    }

    .form-input, .form-select, .form-textarea {
      padding: 12px 15px;
      border: 1px solid var(--border-light);
      border-radius: 8px;
      font-size: 14px;
      transition: var(--transition);
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
      outline: none;
      border-color: var(--primary-red);
      box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.1);
    }

    .form-textarea {
      resize: vertical;
      min-height: 100px;
    }

    .form-actions {
      display: flex;
      gap: 15px;
      justify-content: flex-end;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid var(--border-light);
    }

    .btn {
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .btn-primary {
      background: var(--primary-red);
      color: white;
    }

    .btn-primary:hover {
      background: #b91c3c;
      transform: translateY(-1px);
    }

    .btn-secondary {
      background: var(--border-light);
      color: var(--text-dark);
    }

    .btn-secondary:hover {
      background: #dee2e6;
    }

    /* Removed activity and stats styles as these sections are deleted */

    /* Responsive Design */
    @media (max-width: 575px) {
      .profile-container {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .profile-sidebar {
        order: 2;
      }

      .profile-content {
        order: 1;
      }

      .content-body {
        padding: 20px;
        /* Remove max-height: none; since we've removed the constraint */
      }

      .form-grid {
        grid-template-columns: 1fr;
        gap: 15px;
      }

      .form-actions {
        flex-direction: column;
      }

      .profile-header {
        padding: 20px;
      }

      .profile-avatar-large {
        width: 80px;
        height: 80px;
        font-size: 2rem;
      }
    }

    @media (min-width: 576px) and (max-width: 767px) {
      .profile-container {
        grid-template-columns: 300px 1fr;
        gap: 25px;
      }

      .form-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (min-width: 768px) and (max-width: 991px) {
      .profile-container {
        grid-template-columns: 320px 1fr;
      }

      .form-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (min-width: 992px) and (max-width: 1199px) {
      .stats-grid {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    .content-section {
      display: none;
    }

    .content-section.active {
      display: block;
    }

    /* Add styles for agent-specific fields */
    .agent-section {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 30px;
    }
    
    .agent-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      background: var(--primary-red);
      color: white;
      margin-left: 10px;
    }
    
    .status-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .status-badge.active {
      background: #28a745;
      color: white;
    }
    
    .status-badge.inactive {
      background: #6c757d;
      color: white;
    }
    
    .status-badge.on_leave {
      background: #ffc107;
      color: #212529;
    }
  </style>
  <!-- Add SweetAlert CDN -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <?php include '../../includes/components/sidebar.php'; ?>

  <div class="main">
    <?php include '../../includes/components/topbar.php'; ?>

    <div class="main-content">
      <div class="profile-container">
        <div class="profile-sidebar">
          <div class="profile-header">
            <div class="profile-avatar-section">
              <div class="profile-avatar-large">
                <?php
                  echo $avatar_html; // Display the generated avatar HTML
                ?>
              </div>
              <input type="file" id="avatarFile" name="avatar_file" style="display: none;" accept="image/*">
              <button class="avatar-upload" type="button" title="Change profile picture"><i class="fas fa-camera"></i></button>
            </div>
            <h2 class="profile-name"><?php echo htmlspecialchars($display_name); ?></h2>
            <p class="profile-role"><?php echo htmlspecialchars($display_role); ?></p>
          </div>
          <nav class="profile-nav">
            <div class="nav-item active" data-section="personal">
              <i class="fas fa-user"></i>
              <span>Personal Information</span>
            </div>
            <?php if ($user_data['Role'] === 'SalesAgent'): ?>
            <div class="nav-item" data-section="agent">
              <i class="fas fa-briefcase"></i>
              <span>Agent Profile</span>
            </div>
            <?php endif; ?>
            <div class="nav-item" data-section="security">
              <i class="fas fa-shield-alt"></i>
              <span>Security Settings</span>
            </div>
          </nav>
        </div>

        <div class="profile-content">
          <div class="content-header">
            <h2 id="contentTitle">Personal Information</h2>
          </div>

          <div class="content-body">
            <!-- Personal Information Section -->
            <div class="content-section active" id="personal">
              <form id="profileForm">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-section">
                  <h3 class="section-title">Basic Information</h3>
                  <div class="form-grid">
                    <div class="form-group">
                      <label class="form-label">Username</label>
                      <input type="text" class="form-input" value="<?php echo htmlspecialchars($user_data['Username'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Email Address *</label>
                      <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user_data['Email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                      <label class="form-label">First Name *</label>
                      <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($first_name); ?>" required>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Last Name *</label>
                      <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($last_name); ?>" required>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Role</label>
                      <input type="text" class="form-input" value="<?php echo htmlspecialchars($display_role); ?>" readonly>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Date of Birth</label>
                      <input type="date" name="date_of_birth" class="form-input" value="<?php echo htmlspecialchars($user_data['DateOfBirth'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                      <label class="form-label">Last Login</label>
                      <input type="text" class="form-input" value="<?php echo htmlspecialchars($last_login_display); ?>" readonly>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Member Since</label>
                      <input type="text" class="form-input" value="<?php echo htmlspecialchars($created_display); ?>" readonly>
                    </div>
                  </div>
                </div>

                <?php if ($user_data['Role'] === 'SalesAgent'): ?>
                <!-- Sales Agent Additional Fields (Hidden in this section, shown in Agent Profile) -->
                <input type="hidden" name="agent_id_number" value="<?php echo htmlspecialchars($agent_data['agent_id_number'] ?? ''); ?>">
                <input type="hidden" name="display_name" value="<?php echo htmlspecialchars($agent_data['display_name'] ?? ''); ?>">
                <input type="hidden" name="bio" value="<?php echo htmlspecialchars($agent_data['bio'] ?? ''); ?>">
                <input type="hidden" name="contact_number" value="<?php echo htmlspecialchars($agent_data['contact_number'] ?? ''); ?>">
                <input type="hidden" name="agent_status" value="<?php echo htmlspecialchars($agent_data['status'] ?? 'Inactive'); ?>">
                <?php endif; ?>

                <div class="form-actions">
                  <button type="button" class="btn btn-secondary" onclick="resetProfileForm()">
                    <i class="fas fa-times"></i> Cancel
                  </button>
                  <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                  </button>
                </div>
              </form>
            </div>

            <?php if ($user_data['Role'] === 'SalesAgent'): ?>
            <!-- Agent Profile Section -->
            <div class="content-section" id="agent">
              <form id="agentForm">
                <input type="hidden" name="action" value="update_profile">
                
                <!-- Copy basic info as hidden fields to save together -->
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_data['Email'] ?? ''); ?>">
                <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
                <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
                <input type="hidden" name="date_of_birth" value="<?php echo htmlspecialchars($user_data['DateOfBirth'] ?? ''); ?>">
                <!-- Keep status as hidden field so it's still submitted with the form but not editable -->
                <input type="hidden" name="agent_status" value="<?php echo htmlspecialchars($agent_data['status'] ?? 'Inactive'); ?>">
                
                <div class="form-section">
                  <h3 class="section-title">Sales Agent Information</h3>
                  <div class="form-grid">
                    <div class="form-group">
                      <label class="form-label">Agent ID Number *</label>
                      <input type="text" name="agent_id_number" class="form-input" 
                             value="<?php echo htmlspecialchars($agent_data['agent_id_number'] ?? ''); ?>" 
                             placeholder="e.g., SA-001" required>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Display Name</label>
                      <input type="text" name="display_name" class="form-input" 
                             value="<?php echo htmlspecialchars($agent_data['display_name'] ?? ''); ?>"
                             placeholder="How you want to be called">
                    </div>
                    <div class="form-group">
                      <label class="form-label">Contact Number</label>
                      <input type="tel"
                      name="contact_number"
                      class="form-input"
                      value="<?php echo htmlspecialchars($agent_data['contact_number'] ?? ''); ?>"
                      placeholder="+63 XXX XXX XXXX"
                      oninput="this.value = this.value.replace(/[^0-9+]/g, '')"
                      onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />
                    </div>
                    <!-- Agent Status dropdown removed as requested -->
                  </div>
                  
                  <div class="form-group" style="margin-top: 20px;">
                    <label class="form-label">About Yourself / Bio</label>
                    <textarea name="bio" class="form-textarea" rows="4" 
                              placeholder="Tell us about your experience, specialties, or anything you'd like customers to know..."><?php echo htmlspecialchars($agent_data['bio'] ?? ''); ?></textarea>
                  </div>
                </div>

                <div class="form-actions">
                  <button type="button" class="btn btn-secondary" onclick="resetAgentForm()">
                    <i class="fas fa-times"></i> Cancel
                  </button>
                  <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Agent Profile
                  </button>
                </div>
              </form>
            </div>
            <?php endif; ?>

            <!-- Security Settings Section -->
            <div class="content-section" id="security">
              <form id="passwordForm">
                <input type="hidden" name="action" value="change_password">
                <div class="form-section">
                  <h3 class="section-title">Change Password</h3>
                  <div class="form-grid">
                    <div class="form-group">
                      <label class="form-label">Current Password *</label>
                      <input type="password" name="current_password" class="form-input" required>
                    </div>
                    <div class="form-group">
                      <label class="form-label">New Password *</label>
                      <input type="password" name="new_password" class="form-input" required minlength="8">
                      <small style="color: var(--text-light); margin-top: 5px;">Minimum 8 characters</small>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Confirm New Password *</label>
                      <input type="password" name="confirm_password" class="form-input" required>
                    </div>
                  </div>
                </div>

                <div class="form-actions">
                  <button type="button" class="btn btn-secondary" onclick="resetPasswordForm()">
                    <i class="fas fa-times"></i> Cancel
                  </button>
                  <button type="submit" class="btn btn-primary">
                    <i class="fas fa-shield-alt"></i> Update Password
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../../includes/js/common-scripts.js"></script>
  <script>
    // Define reset functions in global scope so they can be called by inline onclick handlers
    function resetProfileForm() {
      document.getElementById('profileForm').reset();
      // Restore original values
      location.reload();
    }

    function resetPasswordForm() {
      document.getElementById('passwordForm').reset();
    }

    function resetAgentForm() {
      location.reload();
    }

    document.addEventListener('DOMContentLoaded', function() {
      // Show initial response if any
      <?php if (!empty($response['message'])): ?>
      Swal.fire({
        title: <?php echo json_encode($response['success'] ? 'Success!' : 'Error!'); ?>,
        text: <?php echo json_encode($response['message']); ?>,
        icon: <?php echo json_encode($response['success'] ? 'success' : 'error'); ?>,
        confirmButtonColor: '#d60000'
      });
      <?php endif; ?>

      // Profile form submission
      document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        // Ensure the action is set for the server to identify the request type
        if (!formData.has('action')) {
          formData.append('action', 'update_profile');
        }

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Append the avatar file if one is selected
        const avatarFile = document.getElementById('avatarFile').files[0];
        if (avatarFile) {
            formData.append('avatar_file', avatarFile, avatarFile.name);
        }
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData, // FormData will correctly set Content-Type for file upload
            headers: {
              'X-Requested-With': 'XMLHttpRequest' // Ensure PHP identifies this as an AJAX request
            }
        })
        .then(response => response.json())
        .then(data => {
            Swal.fire({
                title: data.success ? 'Success!' : 'Error!',
                text: data.message,
                icon: data.success ? 'success' : 'error',
                confirmButtonColor: '#d60000'
            }).then(() => {
                if (data.success && data.type === 'profile') {
                    window.location.reload(); // Reload to see changes, including new avatar
                }
            });
        })
        .catch(error => {
            Swal.fire({
                title: 'Error!',
                text: 'An unexpected error occurred. Please try again. Details: ' + error.toString(),
                icon: 'error',
                confirmButtonColor: '#d60000'
            });
        })
        .finally(() => {
          // Restore button state
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        });
      });

      // Password form submission
      document.getElementById('passwordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Validate passwords match
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');
        
        if (newPassword !== confirmPassword) {
          Swal.fire({
            title: 'Error!',
            text: 'New password and confirmation do not match.',
            icon: 'error',
            confirmButtonColor: '#d60000'
          });
          return;
        }
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;
        
        fetch(window.location.href, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            Swal.fire({
              title: 'Success!',
              text: data.message,
              icon: 'success',
              confirmButtonColor: '#d60000'
            }).then(() => {
              // Clear password form
              resetPasswordForm();
            });
          } else {
            Swal.fire({
              title: 'Error!',
              text: data.message,
              icon: 'error',
              confirmButtonColor: '#d60000'
            });
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire({
            title: 'Error!',
            text: 'An unexpected error occurred. Please try again.',
            icon: 'error',
            confirmButtonColor: '#d60000'
          });
        })
        .finally(() => {
          // Restore button state
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        });
      });

      <?php if ($user_data['Role'] === 'SalesAgent'): ?>
      // Agent form submission
      document.getElementById('agentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;
        
        fetch(window.location.href, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => response.json())
        .then(data => { // Corrected: Added parentheses around data
          if (data.success) {
            Swal.fire({
              title: 'Success!',
              text: data.message,
              icon: 'success',
              confirmButtonColor: '#d60000'
            }).then(() => {
              // Reload page to show updated data
              window.location.reload();
            });
          } else {
            Swal.fire({
              title: 'Error!',
              text: data.message,
              icon: 'error',
              confirmButtonColor: '#d60000'
            });
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire({
            title: 'Error!',
            text: 'An unexpected error occurred. Please try again.',
            icon: 'error',
            confirmButtonColor: '#d60000'
          });
        })
        .finally(() => {
          // Restore button state
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        });
      });
      <?php endif; ?>

      // Avatar upload handling
      const avatarUploadButton = document.querySelector('.avatar-upload');
      const avatarFileInput = document.getElementById('avatarFile');
      const avatarDisplay = document.querySelector('.profile-avatar-large');

      if (avatarUploadButton && avatarFileInput && avatarDisplay) {
          avatarUploadButton.addEventListener('click', function() {
              avatarFileInput.click();
          });

          avatarFileInput.addEventListener('change', function(event) {
              const file = event.target.files[0];
              if (file) {
                  const reader = new FileReader();
                  reader.onload = function(e) {
                      // Update the avatar display with the new image preview
                      let imgElement = avatarDisplay.querySelector('img');
                      if (!imgElement) {
                          // If there was no image (e.g., initials were shown), create an img element
                          avatarDisplay.innerHTML = ''; // Clear initials or old image
                          imgElement = document.createElement('img');
                          imgElement.alt = "Avatar Preview";
                          imgElement.style.width = "100%";
                          imgElement.style.height = "100%";
                          imgElement.style.borderRadius = "50%";
                          imgElement.style.objectFit = "cover";
                          avatarDisplay.appendChild(imgElement);
                      }
                      imgElement.src = e.target.result;
                  }
                  reader.readAsDataURL(file);
                  // The file will be submitted with the profile form
              }
          });
      }

      // Profile navigation
      document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function() {
          // Remove active class from all nav items
          document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
          
          // Add active class to clicked item
          this.classList.add('active');
          
          // Hide all content sections
          document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
          
          // Show selected section
          const sectionId = this.getAttribute('data-section');
          document.getElementById(sectionId).classList.add('active');
          
          // Update content title
          const title = this.querySelector('span').textContent;
          document.getElementById('contentTitle').textContent = title;
        });
      });

      // Set active tab from URL hash or default to first tab
      function setActiveTabFromHash() {
          let sectionToActivate = 'personal'; // Default to personal information section
          if (window.location.hash) {
              const hash = window.location.hash.substring(1); // Remove #
              const navItem = document.querySelector(`.nav-item[data-section="${hash}"]`);
              if (navItem) {
                  sectionToActivate = hash;
              }
          }
          
          // Remove active class from all nav items and hide all content sections
          document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
          document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));

          // Activate the correct nav item and content section
          const activeNavItem = document.querySelector(`.nav-item[data-section="${sectionToActivate}"]`);
          const activeContentSection = document.getElementById(sectionToActivate);

          if (activeNavItem && activeContentSection) {
              activeNavItem.classList.add('active');
              activeContentSection.classList.add('active');
              document.getElementById('contentTitle').textContent = activeNavItem.querySelector('span').textContent;
          } else {
              // Fallback to default section if specified section doesn't exist
              const defaultNavItem = document.querySelector('.nav-item[data-section="personal"]');
              const defaultContentSection = document.getElementById('personal');
              if (defaultNavItem && defaultContentSection) {
                  defaultNavItem.classList.add('active');
                  defaultContentSection.classList.add('active');
                  document.getElementById('contentTitle').textContent = defaultNavItem.querySelector('span').textContent;
              }
          }
      }

      // Listen for hash changes to update active tab
      window.addEventListener('hashchange', setActiveTabFromHash);

      // Set initial state on page load
      setActiveTabFromHash();

    }); // End of DOMContentLoaded
  </script>
</body>
</html>
