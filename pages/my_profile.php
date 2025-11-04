<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

$success_message = '';
$error_message = '';

// Check for redirect messages
if (isset($_SESSION['profile_success'])) {
    $success_message = $_SESSION['profile_success'];
    unset($_SESSION['profile_success']);
}

if (isset($_SESSION['profile_error'])) {
    $error_message = $_SESSION['profile_error'];
    unset($_SESSION['profile_error']);
}

// Fetch user data first
$stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

// Prepare profile image HTML for header
$profile_image_html = '';
if (!empty($user['ProfileImage'])) {
    $imageData = base64_encode($user['ProfileImage']);
    $imageMimeType = 'image/jpeg';
    $profile_image_html = '<img src="data:' . $imageMimeType . ';base64,' . $imageData . '" alt="User Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
} else {
    // Show initial if no profile image
    $profile_image_html = strtoupper(substr($displayName, 0, 1));
}

// Fetch customer information
$stmt_customer = $connect->prepare("SELECT * FROM customer_information WHERE account_id = ?");
$stmt_customer->execute([$_SESSION['user_id']]);
$customer_info = $stmt_customer->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $connect->beginTransaction();

        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $fileType = $_FILES['profile_image']['type'];
            $fileSize = $_FILES['profile_image']['size'];

            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("Invalid file type. Please upload JPEG, PNG, or GIF images only.");
            }

            if ($fileSize > 5 * 1024 * 1024) { // 5MB limit
                throw new Exception("File size too large. Please upload images smaller than 5MB.");
            }

            $profileImage = file_get_contents($_FILES['profile_image']['tmp_name']);

            // Update profile image in accounts table
            $stmt_image = $connect->prepare("UPDATE accounts SET ProfileImage = ?, UpdatedAt = NOW() WHERE Id = ?");
            $stmt_image->execute([$profileImage, $_SESSION['user_id']]);
        }

        // Handle other profile updates
        if (isset($_POST['saveChanges'])) {
            $updates = [];
            $params = [];

            // Update accounts table - map form fields to correct database columns
            if (!empty($_POST['firstname'])) {
                $updates[] = "FirstName = ?";
                $params[] = $_POST['firstname'];
            }

            if (!empty($_POST['lastname'])) {
                $updates[] = "LastName = ?";
                $params[] = $_POST['lastname'];
            }

            if (!empty($_POST['password'])) {
                $updates[] = "PasswordHash = ?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }

            if (!empty($updates)) {
                $updates[] = "UpdatedAt = NOW()";
                $params[] = $_SESSION['user_id'];

                $sql = "UPDATE accounts SET " . implode(", ", $updates) . " WHERE Id = ?";
                $stmt_acc = $connect->prepare($sql);
                $stmt_acc->execute($params);
            }

            // Handle customer information updates - fix field mapping
            if ($customer_info) {
                $customer_updates = [];
                $customer_params = [];

                if (!empty($_POST['mobile_number'])) {
                    $customer_updates[] = "mobile_number = ?";
                    $customer_params[] = $_POST['mobile_number'];
                }

                if (!empty($_POST['complete_address'])) {
                    $customer_updates[] = "complete_address = ?";
                    $customer_params[] = $_POST['complete_address'];
                }

                if (!empty($_POST['middlename'])) {
                    $customer_updates[] = "middlename = ?";
                    $customer_params[] = $_POST['middlename'];
                }

                // Also update firstname and lastname in customer_information table
                if (!empty($_POST['firstname'])) {
                    $customer_updates[] = "firstname = ?";
                    $customer_params[] = $_POST['firstname'];
                }

                if (!empty($_POST['lastname'])) {
                    $customer_updates[] = "lastname = ?";
                    $customer_params[] = $_POST['lastname'];
                }

                if (!empty($customer_updates)) {
                    $customer_updates[] = "updated_at = NOW()";
                    $customer_params[] = $_SESSION['user_id'];

                    $sql = "UPDATE customer_information SET " . implode(", ", $customer_updates) . " WHERE account_id = ?";
                    $stmt_cust = $connect->prepare($sql);
                    $stmt_cust->execute($customer_params);
                }
            }
        }

        $connect->commit();

        // Use Post-Redirect-Get pattern to prevent form resubmission
        $_SESSION['profile_success'] = "Profile updated successfully!";
        header("Location: my_profile.php");
        exit;
    } catch (Exception $e) {
        $connect->rollBack();
        $_SESSION['profile_error'] = "Update failed: " . $e->getMessage();
        header("Location: my_profile.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../includes/css/profile-styles.css?v=<?php echo file_exists(dirname(__DIR__) . '/includes/css/profile-styles.css') ? filemtime(dirname(__DIR__) . '/includes/css/profile-styles.css') : time(); ?>" rel="stylesheet">
</head>
<style>
.id-image-container {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: 10px;
}

.id-image {
  max-width: 600px; /* limit width */
  max-height: 350px; /* limit height */
  width: auto;
  height: auto;
  border-radius: 8px;
  box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
  object-fit: contain;
}
</style>
<body>
    <header class="header">
        <div class="logo-section">
            <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo">
            <div class="brand-text">MITSUBISHI MOTORS</div>
        </div>
        <div class="user-section">
            <div class="user-avatar"><?php echo $profile_image_html; ?></div>
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($displayName); ?>!</span>
            <button class="logout-btn" onclick="window.location.href='logout.php'"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </div>
    </header>

    <div class="container">
        <a href="customer.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <h1 class="page-title">My Profile & Settings</h1>

        <?php if (!empty($success_message)): ?>
            <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form class="profile-form" method="POST" enctype="multipart/form-data">
            <!-- Profile Image Section -->
            <div class="profile-image-section">
                <h2>Profile Picture</h2>
                <div class="profile-image-container">
                    <?php if (!empty($user['ProfileImage'])): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($user['ProfileImage']); ?>"
                            alt="Profile Picture" class="profile-image" id="mainProfileImage">
                    <?php else: ?>
                        <div class="profile-image-placeholder" id="mainProfilePlaceholder">
                            <?php echo strtoupper(substr($displayName, 0, 1)); ?>
                        </div>
                    <?php endif; ?>

                    <div class="image-upload-overlay" onclick="document.getElementById('profileImageInput').click()">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>

                <input type="file" id="profileImageInput" name="profile_image" class="image-upload-input"
                    accept="image/*" onchange="previewImageInMain(this)">

                <div class="upload-buttons" id="uploadButtons">
                    <button type="submit" class="upload-btn" name="upload_image">
                        <i class="fas fa-upload"></i> Update Profile
                    </button>
                    <button type="button" class="cancel-btn" onclick="cancelImageUpload()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="form-section">
                <h2>Account Information</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" value="<?php echo htmlspecialchars($user['Username'] ?? ''); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" value="<?php echo htmlspecialchars($user['Email'] ?? ''); ?>" disabled>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="account_status">Account Status
                            <span class="status-badge status-<?php echo strtolower($user['Status'] ?? 'pending'); ?>">
                                <?php echo ucfirst($user['Status'] ?? 'Pending'); ?>
                            </span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter new password" autocomplete="new-password" disabled>
                    </div>
                </div>
            </div>

            <?php if ($customer_info): ?>
                <div class="form-section">
                    <h2>Personal Information
                        <span class="status-badge status-<?php echo strtolower($customer_info['Status'] ?? 'pending'); ?>">
                            Verification: <?php echo ucfirst($customer_info['Status'] ?? 'Pending'); ?>
                        </span>
                    </h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstname">First Name</label>
                            <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($customer_info['firstname'] ?? ''); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="middlename">Middle Name</label>
                            <input type="text" id="middlename" name="middlename" value="<?php echo htmlspecialchars($customer_info['middlename'] ?? ''); ?>" disabled>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lastname">Last Name</label>
                            <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($customer_info['lastname'] ?? ''); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="suffix">Suffix</label>
                            <input type="text" id="suffix" value="<?php echo htmlspecialchars($customer_info['suffix'] ?? ''); ?>" disabled>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="birthday">Date of Birth</label>
                            <input type="date" id="birthday" value="<?php echo $customer_info['birthday'] ?? ''; ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="age">Age</label>
                            <input type="number" id="age" value="<?php echo $customer_info['age'] ?? ''; ?>" disabled>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" disabled>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($customer_info['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($customer_info['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="civil_status">Civil Status</label>
                            <select id="civil_status" disabled>
                                <option value="">Select Status</option>
                                <option value="Single" <?php echo ($customer_info['civil_status'] ?? '') == 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo ($customer_info['civil_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
                                <option value="Widowed" <?php echo ($customer_info['civil_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                <option value="Separated" <?php echo ($customer_info['civil_status'] ?? '') == 'Separated' ? 'selected' : ''; ?>>Separated</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nationality">Nationality</label>
                            <input type="text" id="nationality" value="<?php echo htmlspecialchars($customer_info['nationality'] ?? ''); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="mobile_number">Mobile Number</label>
                            <input type="tel" id="mobile_number" name="mobile_number" value="<?php echo htmlspecialchars($customer_info['mobile_number'] ?? ''); ?>" disabled>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="complete_address">Complete Address</label>
                            <input type="text" id="complete_address" name="complete_address" value="<?php echo htmlspecialchars($customer_info['complete_address'] ?? ''); ?>" disabled placeholder="House/Unit, Street, Barangay, City/Municipality, Province, ZIP">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Employment Information</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="employment_status">Employment Status</label>
                            <select id="employment_status" disabled>
                                <option value="">Select Status</option>
                                <option value="Employed" <?php echo ($customer_info['employment_status'] ?? '') == 'Employed' ? 'selected' : ''; ?>>Employed</option>
                                <option value="Self-Employed" <?php echo ($customer_info['employment_status'] ?? '') == 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                                <option value="Unemployed" <?php echo ($customer_info['employment_status'] ?? '') == 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                                <option value="Student" <?php echo ($customer_info['employment_status'] ?? '') == 'Student' ? 'selected' : ''; ?>>Student</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="company_name">Company Name</label>
                            <input type="text" id="company_name" value="<?php echo htmlspecialchars($customer_info['company_name'] ?? ''); ?>" disabled>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="position">Position</label>
                            <input type="text" id="position" value="<?php echo htmlspecialchars($customer_info['position'] ?? ''); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="monthly_income">Monthly Income (PHP)</label>
                            <input type="number" step="0.01" id="monthly_income" value="<?php echo $customer_info['monthly_income'] ?? ''; ?>" disabled>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Valid ID Information</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="valid_id_type">Valid ID Type</label>
                            <input type="text" id="valid_id_type" value="<?php echo htmlspecialchars($customer_info['valid_id_type'] ?? ''); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="valid_id_number">Valid ID Number</label>
                            <input type="text" id="valid_id_number" value="<?php echo htmlspecialchars($customer_info['valid_id_number'] ?? ''); ?>" disabled>
                        </div>
                    </div>
                    <?php if (!empty($customer_info['valid_id_image'])): ?>
                        <div class="form-group">
                            <label>Valid ID Image</label>
                            <div class="id-image-container">
                                <img src="data:image/jpeg;base64,<?php echo base64_encode($customer_info['valid_id_image']); ?>" alt="Valid ID" class="id-image">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="form-section">
                    <h2>Personal Information</h2>
                    <div style="text-align: center; padding: 40px; color: #ffd700;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 20px;"></i>
                        <h3>Verification Required</h3>
                        <p style="margin: 20px 0;">You need to complete your verification to view your full profile.</p>
                        <button type="button" class="form-btn" onclick="window.location.href='verification.php'">
                            <i class="fas fa-user-check"></i> Complete Verification
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Terms and Conditions Link -->
            <div style = 'background: #808080;' class="terms-link">
                <h3 style = "color: #ffffff;">Terms and Conditions</h3>
                <p style = "color: #ffffff;">Review our platform policies and user agreement</p>
                <button style = "color: #ffffff; background: #E60012;" type="button" class="view-terms-btn" onclick="openTermsModal()">
                    <i class="fas fa-file-contract" style = "color: #ffffff;" ></i> View Terms & Conditions
                </button>
                <div style="margin-top: 15px;">
                    <label style="display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 0.95rem;">
                        <input type="checkbox" id="termsAgreed" name="terms_agreed" <?php echo ($customer_info && $customer_info['Status'] == 'Approved') ? 'checked' : ''; ?>>
                        I have read and agree to the Terms and Conditions
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="form-btn" id="editBtn">Edit Profile</button>
                <button type="submit" class="form-btn" id="saveBtn" name="saveChanges" style="display:none;">Save Changes</button>
            </div>
        </form>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Terms & Conditions and Privacy Policies</h2>
                <span class="close" onclick="closeTermsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <h4 style="text-align: center;">Mitsubishi AutoXpress Terms and Conditions</h4>
                
                <h4>1. Account Usage and Responsibilities</h4>
				<p>When creating a Mitsubishi AutoXpress account, you agree to provide accurate, complete, and up-to-date personal information. You are responsible for keeping your username and password confidential and for all activities that occur under your account.
				<br><br>
                The system uses a <strong>One-Time Password (OTP)</strong> for account creation and password recovery. The OTP will be sent to your <strong>registered email within five (5) minutes</strong> after the request and will remain <strong>valid for ten (10) minutes</strong> from the time it is issued. Once expired, you will need to request a new OTP for verification.
                <br><br>
                Your account must only be used for legitimate purposes, such as browsing available car units and specifications, booking test drives or PMS (Preventive Maintenance Service) schedules, tracking amortization, uploading payment receipts, and communicating with sales agents or support staff through the chat module.
                <br><br>
                Any misuse of your account, including sharing login credentials, submitting false or misleading information, or engaging in unlawful activities, may result in account suspension or termination by the system administrator.
                </p>
                
                <h4>2. Privacy and Data Protection</h4>
				<p>Your privacy is our top priority. All personal data provided in this system is processed and stored in compliance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>. Personal data is encrypted and securely stored to prevent unauthorized access, loss, or misuse.
				<br><br>
                We will not share your personal information with third parties unless you give explicit consent, it is required by law, or it is necessary for legitimate business operations such as loan verification or dealership reporting.
                <br><br>
                You have the right to access, update, or request deletion of your personal information by contacting our Data Privacy Officer.
                </p>
                
                <h4>3. Inquiries, Test Drives, and Loan Applications</h4>
				<p>Vehicle details, prices, and availability displayed in the system are subject to change without prior notice. When you send an inquiry or book a test drive, your request is automatically forwarded to an available sales agent for confirmation.
				<br><br>
                Test drive appointments must be confirmed by the dealership. If a customer misses <strong>five (5)</strong> consecutive test drive appointments, their online booking privileges will be temporarily suspended and can only be reactivated by visiting the dealership in person.
                <br><br>
                Loan applications submitted through the platform may require credit evaluation. The system allows users to upload necessary documents, but the final approval, interest rate, and loan terms will be handled by Mitsubishi Motors San Pablo City or its authorized financing partners.
                </p>
                
                <h4>4. Payment Terms</h4>
				<p>Customers can upload proof of payment such as receipts or screenshots through the <strong>Amortization Module</strong>, including details such as mode of payment, transaction reference number, and date of transaction.
				<br><br>
                All uploaded payment receipts will be verified by the system administrator before being reflected in the customer’s account. You are responsible for ensuring that all payment details are accurate and complete before submission.
                <br><br>
                Late or missed payments may incur additional charges based on the dealership’s payment policy. You will receive email or system notifications for every successful transaction, verification update, or payment reminder.
                </p>
                
                <h4>5. Platform Usage Guidelines</h4>
				<p>All users are expected to communicate respectfully and professionally when using the Chat Support or interacting with sales agents. Customers may use the <strong>Chatbot for general inquiries</strong>, while <strong>Chat Support</strong> connects users to <strong>available sales agents</strong> through a queue-based assignment system.
				<br><br>
                    The <strong>Chatbot</strong> remains active and <strong>available 24/7</strong>, including non-working hours, to respond to general questions and assist customers with basic information. During <strong>business hours—Monday to Saturday, 8:00 AM to 5:00 PM, and Sunday, 9:00 AM to 5:00 PM</strong>—the Chat Support feature allows customers to be directly connected to a sales agent.
                <br><br>
                    If a customer’s inquiry is specific or beyond the Chatbot’s programmed responses, the conversation will be transferred to a sales agent for further assistance. This agent takeover can occur during business hours to ensure accurate and personalized support.
                <br><br>
                    Users are strictly prohibited from accessing or modifying other users’ data, attempting to bypass security measures, uploading harmful files, or using automated tools to overload the system.
                <br><br>
                    If you encounter any technical problems or system errors, please contact Customer Support immediately for assistance.
                </p>
                
                <h4>6. Limitation of Liability</h4>
				<p>The Mitsubishi AutoXpress system is provided on an “as-is” basis. While we strive to maintain smooth and reliable performance, we cannot guarantee uninterrupted or error-free operation at all times.
				<br><br>
                    Mitsubishi Motors San Pablo City and its developers are not liable for any damages or losses caused by internet connectivity issues, user negligence, device malfunctions, force majeure events such as natural disasters or power outages, or temporary system maintenance.
                <br><br>
                    The administration reserves the right to suspend or terminate accounts involved in fraudulent or abusive activities, modify system features or policies as necessary, and limit liability in accordance with applicable laws.
                </p>

                <h4>7. Customer Support and Dispute Resolution</h4>
				<p>For questions, complaints, or technical issues, you may contact our Customer Support Team at:
				<br>
                    📧 mitsubishiautoxpress@gmail.com<br>
                    📞 (049) 503-9693<br>
                Our team aims to respond to all concerns within <strong>24 to 48 hours</strong> during business days. If an issue cannot be resolved immediately, both parties agree to cooperate toward a fair and reasonable solution based on dealership policies and existing laws.
                </p>
                
                <h4>8. Changes to These Terms</h4>
				<p>Mitsubishi AutoXpress may update or modify these Terms and Conditions at any time to reflect system improvements, dealership policies, or regulatory changes. Updates will be communicated through system notifications, email announcements, or website postings.
				<br><br>
                    By continuing to use the system after these updates, you acknowledge and accept the revised Terms and Conditions.
                </p>
			    
			    <h4 style="text-align: center;">Mitsubishi AutoXpress Privacy Policy</h4>
			    
			    <h4>Data Privacy Policy Statement</h4>
			    <p>This Privacy Policy explains how Mitsubishi AutoXpress, a web-based car sales and service management system for Mitsubishi Motors San Pablo City, collects, uses, stores, and protects personal information in compliance with Republic Act No. 10173 (Data Privacy Act of 2012) and its Implementing Rules and Regulations (IRR).
			    <br><br>
                     Mitsubishi AutoXpress respects the privacy rights of all its users — customers, sales agents, and administrators. The system ensures that personal data is processed lawfully, fairly, and transparently for legitimate business purposes only.
                </p>
                     
				<h4>1. Effectivity and Changes</h4>
				<p>This Privacy Policy is effective as of October 2025.
				<br><br>
                   The developers or management of Mitsubishi Motors San Pablo City may update or modify this Privacy Policy as needed. Updates will be announced through the system’s website, email notifications, or other appropriate communication channels.
                <br><br>
                   Continued use of the system after notice of such updates means acceptance of the revised Privacy Policy.
                </p>

				<h4>2. Information We Collect</h4>
                <p>
                  When you create an account, schedule a test drive, apply for a loan, or book PMS (Preventive Maintenance Service),
                  the system may collect the following personal information:
                  <br><br>
                  <strong>Personal and Contact Information</strong>
                </p>
                <ul>
                  <li>Full name</li>
                  <li>Date of birth, age, gender, and civil status</li>
                  <li>Address and contact number (mobile/landline)</li>
                  <li>Email address</li>
                  <li>Profile photo (optional)</li>
                </ul>
                
                <p><strong>Account and Transaction Information</strong></p>
                <ul>
                  <li>Login credentials (username and password)</li>
                  <li>Vehicle information (model, plate number, mileage, etc.)</li>
                  <li>PMS and service history</li>
                  <li>Loan and payment details (mode of payment, transaction reference)</li>
                  <li>Uploaded payment receipts</li>
                </ul>
                
                <p><strong>System Usage Information</strong></p>
                <ul>
                  <li>Device information (IP address, browser type, login time)</li>
                  <li>System activity logs (test drive bookings, chat interactions, etc.)</li>
                  <li>Chat or support message history (for customer service quality)</li>
                </ul>
                
                <p><strong>Other Optional Data</strong></p>
                <ul>
                  <li>Feedback, surveys, or responses to system evaluation forms</li>
                </ul>


				<h4>3. Purpose of Data Collection</h4>
				<p>
                  The data we collect will be used for the following legitimate purposes:
                  <ol style="margin-top: 3px; margin-left: 20px; line-height: 1.6;">
                    <li>To create and manage customer, agent, and admin accounts within the Mitsubishi AutoXpress system.</li>
                    <li>To facilitate online transactions such as test drive booking, loan tracking, and PMS scheduling.</li>
                    <li>To record and track payment transactions and upload receipts for verification.</li>
                    <li>To monitor and manage service appointments and customer activities.</li>
                    <li>To improve the efficiency and accuracy of dealership operations.</li>
                    <li>To provide customer support through chat modules and automated chatbot assistance.</li>
                    <li>To generate statistical reports for business analysis and performance evaluation.</li>
                    <li>To comply with applicable legal requirements and safeguard the rights of users.</li>
                  </ol>
                </p>


				<h4>4. Retention of Information</h4>
				<p>Personal information will be retained only for as long as necessary to fulfill its purpose:.</p>
				<ul>
                  <li><strong>Active accounts:</strong> until the user deactivates or requests deletion.</li>
                  <li><strong>Transaction and service records:</strong> in accordance with legal and business requirements.</li>
                </ul>

				<h4>5. Sharing and Disclosure of Information</h4>
				<p>Mitsubishi AutoXpress may share information only under the following conditions:
				<ul>
                  <li>With <strong>authorized personnel</strong> (Admin, Sales Agents) for legitimate business functions.)</li>
                  <li>With <strong>Mitsubishi Motors San Pablo City management</strong> for internal reports and monitoring.</li>
                  <li>With <strong>third-party service providers</strong> (e.g., web hosting, email services) who are bound by confidentiality agreements.</li>
                  <li>When required by <strong>law, court order, or government regulation.</strong></li>
                </ul>
                We do <strong>not sell or share</strong> user data for third-party marketing
                </p>

				<h4>6. Data Security</h4>
				<p>We apply necessary <strong>technical, administrative, and physical</strong> measures to protect personal data, including:
				<ul>
                  <li>Secure login authentication and password encryption</li>
                  <li>Restricted access control for Admin and Agent accounts</li>
                  <li>Regular system monitoring and data backups</li>
                  <li>Secure Socket Layer (SSL) encryption for online transactions</li>
                  <li>Data anonymization and secure disposal after use</li>
                </ul>
                While we implement industry-standard protection, users are advised to keep their login details private and report any suspicious activity immediately.
				</p>

				<h4>7. Data Privacy Rights of Users</h4>
				<p>All system users are entitled to exercise the following rights under the Data Privacy Act of 2012:
				<ol style="margin-top: 3px; margin-left: 20px; line-height: 1.6;">
                    <li><strong>Right to be informed</strong> – to know how your data is collected and processed.</li>
                    <li><strong>Right to access</strong> – to request a copy of your personal information.</li>
                    <li><strong>Right to object</strong> – to refuse processing of your data for unauthorized purposes.</li>
                    <li><strong>Right to erasure</strong> – to request deletion of your account and data.</li>
                    <li><strong>Right to correct</strong> – to update or correct inaccurate information.</li>
                    <li><strong>Right to data portability</strong> – to obtain and reuse your data in another system.</li>
                  </ol>
                  Requests for exercising these rights may be sent through the contact details provided below.
				</p>
				
				<h4>8. Children’s Privacy</h4>
				<p>The Mitsubishi AutoXpress system is not intended for individuals under <strong>18 years old.</strong>
				<br><br>
                   We do not knowingly collect personal data from minors without consent from a parent or legal guardian. Any such data discovered will be deleted immediately.
                </p>
                
                <h4>9. Links to Other Sites</h4>
				<p>The system may contain links to other websites (e.g., Mitsubishi Motors official site, payment gateways).
				<br><br>
                   Mitsubishi AutoXpress is not responsible for the privacy practices of external sites. Users are encouraged to read the privacy policies of linked sites before providing personal data.
                </p>
                
                <h4>10. Contact Information</h4>
				<p style="line-height: 1.8;">
                  For questions, clarifications, or complaints related to this Privacy Policy, you may contact:
                  <strong>Data Privacy Officer – Mitsubishi AutoXpress (San Pablo City)</strong><br>
                  📍 Km 85.5 Maharlika Highway, Brgy. San Ignacio, San Pablo City, Laguna<br>
                  📧 <strong>Email:</strong> mitsubishiautoxpress@gmail.com<br>
                  📞 <strong>Contact Number:</strong> (049) 503-9693
                </p>

                <div class="last-updated-modal">
                    Last updated: October 2025
                </div>
            </div>
            <div class="modal-footer">
                <div class="terms-acceptance-modal">
                    <input type="checkbox" id="modalTermsAgreed">
                    <label for="modalTermsAgreed">I have read and agree to these Terms and Conditions</label>
                </div>
                <div class="modal-buttons">
                    <button class="modal-btn btn-accept" onclick="acceptTerms()">
                        <i class="fas fa-check"></i> Accept
                    </button>
                    <button class="modal-btn btn-close" onclick="closeTermsModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const editBtn = document.getElementById('editBtn');
        const saveBtn = document.getElementById('saveBtn');
        const inputs = document.querySelectorAll('.profile-form input, .profile-form select');

        editBtn.addEventListener('click', () => {
            inputs.forEach(input => {
                // Allow editing of specific fields - use name attribute for better identification
                const fieldName = input.getAttribute('name') || input.id;
                const editableFields = ['password', 'firstname', 'lastname', 'middlename', 'mobile_number', 'complete_address'];

                if (editableFields.includes(fieldName)) {
                    input.disabled = false;
                    // Add visual indication that field is editable
                    input.style.background = 'rgba(255, 255, 255, 0.15)';
                    input.style.borderColor = 'rgba(255, 215, 0, 0.4)';
                }
            });
            editBtn.style.display = 'none';
            saveBtn.style.display = 'inline-block';
        });

        // Function to reset edit mode
        function resetEditMode() {
            inputs.forEach(input => {
                const fieldName = input.getAttribute('name') || input.id;
                const editableFields = ['password', 'firstname', 'lastname', 'middlename', 'mobile_number', 'complete_address'];

                if (editableFields.includes(fieldName)) {
                    input.disabled = true;
                    input.style.background = 'rgba(255, 255, 255, 0.05)';
                    input.style.borderColor = 'rgba(255, 215, 0, 0.2)';
                }
            });
            editBtn.style.display = 'inline-block';
            saveBtn.style.display = 'none';
        }

        // Add cancel functionality
        saveBtn.addEventListener('click', (e) => {
            // Form will submit naturally and be handled by PHP
            return true;
        });

        // Handle profile image upload separately from edit mode
        function handleImageUpload() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.enctype = 'multipart/form-data';
            form.style.display = 'none';

            const fileInput = document.getElementById('profileImageInput').cloneNode(true);
            form.appendChild(fileInput);

            const submitBtn = document.createElement('input');
            submitBtn.type = 'submit';
            submitBtn.name = 'upload_image';
            form.appendChild(submitBtn);

            document.body.appendChild(form);
            form.submit();
        }

        let originalImageSrc = null;
        let hasPlaceholder = <?php echo empty($user['ProfileImage']) ? 'true' : 'false'; ?>;

        function previewImageInMain(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please upload only JPEG, PNG, or GIF images.');
                    input.value = '';
                    return;
                }

                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size too large. Please upload images smaller than 5MB.');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    // Store original state
                    if (!originalImageSrc) {
                        const mainImage = document.getElementById('mainProfileImage');
                        const placeholder = document.getElementById('mainProfilePlaceholder');

                        if (mainImage) {
                            originalImageSrc = mainImage.src;
                        } else if (placeholder) {
                            originalImageSrc = 'placeholder';
                        }
                    }

                    // Replace placeholder or image with preview
                    const container = document.querySelector('.profile-image-container');
                    const existingImage = document.getElementById('mainProfileImage');
                    const existingPlaceholder = document.getElementById('mainProfilePlaceholder');

                    if (existingImage) {
                        existingImage.src = e.target.result;
                    } else if (existingPlaceholder) {
                        // Replace placeholder with image
                        existingPlaceholder.style.display = 'none';
                        const newImage = document.createElement('img');
                        newImage.src = e.target.result;
                        newImage.alt = 'Profile Picture';
                        newImage.className = 'profile-image';
                        newImage.id = 'mainProfileImage';
                        container.insertBefore(newImage, container.querySelector('.image-upload-overlay'));
                    }

                    document.getElementById('uploadButtons').style.display = 'flex';
                };
                reader.readAsDataURL(file);
            }
        }

        function cancelImageUpload() {
            document.getElementById('profileImageInput').value = '';
            document.getElementById('uploadButtons').style.display = 'none';

            // Restore original state
            if (originalImageSrc) {
                const mainImage = document.getElementById('mainProfileImage');
                const placeholder = document.getElementById('mainProfilePlaceholder');

                if (originalImageSrc === 'placeholder') {
                    if (mainImage) {
                        mainImage.remove();
                    }
                    if (placeholder) {
                        placeholder.style.display = 'flex';
                    }
                } else if (mainImage) {
                    mainImage.src = originalImageSrc;
                } else if (placeholder) {
                    // Restore image if it was replaced
                    placeholder.style.display = 'none';
                    const container = document.querySelector('.profile-image-container');
                    const newImage = document.createElement('img');
                    newImage.src = originalImageSrc;
                    newImage.alt = 'Profile Picture';
                    newImage.className = 'profile-image';
                    newImage.id = 'mainProfileImage';
                    container.insertBefore(newImage, container.querySelector('.image-upload-overlay'));
                }

                originalImageSrc = null;
            }
        }

        function openTermsModal() {
            const modal = document.getElementById('termsModal');
            const modalCheckbox = document.getElementById('modalTermsAgreed');
            const formCheckbox = document.getElementById('termsAgreed');

            // Sync modal checkbox with form checkbox
            modalCheckbox.checked = formCheckbox.checked;

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function closeTermsModal() {
            const modal = document.getElementById('termsModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore background scrolling
        }

        function acceptTerms() {
            const modalCheckbox = document.getElementById('modalTermsAgreed');
            const formCheckbox = document.getElementById('termsAgreed');

            if (modalCheckbox.checked) {
                formCheckbox.checked = true;
                closeTermsModal();
            } else {
                alert('Please check the agreement checkbox to accept the terms.');
            }
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('termsModal');
            if (event.target == modal) {
                closeTermsModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeTermsModal();
            }
        });
    </script>
</body>

</html>