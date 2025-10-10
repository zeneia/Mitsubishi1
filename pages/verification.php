<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
	header("Location: login.php");
	exit;
}

$account_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';
$show_form = true;

// Check if user has already submitted verification and it's approved
$stmt_check = $connect->prepare("SELECT Status FROM customer_information WHERE account_id = ?");
$stmt_check->execute([$account_id]);
$customer_info = $stmt_check->fetch(PDO::FETCH_ASSOC);

if ($customer_info && $customer_info['Status'] == 'Approved') {
	header("Location: customer.php");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
	// Preserve form data for re-population
	$form_data = [
		'firstname' => $_POST['firstname'] ?? '',
		'middlename' => $_POST['middlename'] ?? '',
		'lastname' => $_POST['lastname'] ?? '',
		'suffix' => $_POST['suffix'] ?? '',
		'birthday' => $_POST['birthday'] ?? '',
		'age' => $_POST['age'] ?? '',
		'gender' => $_POST['gender'] ?? '',
		'civil_status' => $_POST['civil_status'] ?? '',
		'nationality' => $_POST['nationality'] ?? '',
		'mobile_number' => $_POST['mobile_number'] ?? '',
		'employment_status' => $_POST['employment_status'] ?? '',
		'company_name' => $_POST['company_name'] ?? '',
		'position' => $_POST['position'] ?? '',
		'monthly_income' => $_POST['monthly_income'] ?? '',
		'complete_address' => $_POST['complete_address'] ?? '',
		'valid_id_type' => $_POST['valid_id_type'] ?? '',
		'valid_id_number' => $_POST['valid_id_number'] ?? ''
	];

	if (empty($_POST['terms'])) {
		$error_message = "You must accept the Privacy and Terms to continue.";
	} else if (empty($_POST['complete_address'])) {
		$error_message = "Please enter your complete address.";
	} else if (empty($_FILES['valid_id_image']['tmp_name'])) {
		$error_message = "Please upload a valid ID image.";
	} else if ($_FILES['valid_id_image']['error'] !== UPLOAD_ERR_OK) {
		$error_message = "Error uploading file. Please try again.";
	} else if ($_FILES['valid_id_image']['size'] > 5 * 1024 * 1024) { // 5MB limit
		$error_message = "File size too large. Please upload an image smaller than 5MB.";
	} else if (isset($_POST['age']) && (int)$_POST['age'] < 18) {
		$error_message = "You must be 18 years or older to register.";
	} else {
		// Validate file type
		$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
		$file_type = $_FILES['valid_id_image']['type'];
		if (!in_array($file_type, $allowed_types)) {
			$error_message = "Invalid file type. Please upload a JPEG, PNG, or GIF image.";
		} else {
			try {
				$connect->beginTransaction();

				$lastname = $_POST['lastname'];
				$firstname = $_POST['firstname'];
				$middlename = $_POST['middlename'] ?? null;
				$suffix = $_POST['suffix'] ?? null;
				$nationality = $_POST['nationality'];
				$birthday = $_POST['birthday'];
				$age = $_POST['age'];
				$gender = $_POST['gender'];
				$civil_status = $_POST['civil_status'];
				$mobile_number = $_POST['mobile_number'];
				$complete_address = $_POST['complete_address'];
				$employment_status = $_POST['employment_status'];
				$company_name = $_POST['company_name'] ?? null;
				$position = $_POST['position'] ?? null;
				$monthly_income = !empty($_POST['monthly_income']) ? $_POST['monthly_income'] : null;
				$valid_id_type = $_POST['valid_id_type'];
				$valid_id_number = $_POST['valid_id_number'];

				// Read the file content
				$valid_id_image = file_get_contents($_FILES['valid_id_image']['tmp_name']);
				if ($valid_id_image === false) {
					throw new Exception("Failed to read uploaded file.");
				}

				// Update accounts table
				$stmt_acc = $connect->prepare("UPDATE accounts SET FirstName = ?, LastName = ?, DateOfBirth = ? WHERE Id = ?");
				$stmt_acc->execute([$firstname, $lastname, $birthday, $account_id]);

				// Check if customer info exists to decide between INSERT and UPDATE
				$stmt_check_again = $connect->prepare("SELECT cusID FROM customer_information WHERE account_id = ?");
				$stmt_check_again->execute([$account_id]);

				if ($stmt_check_again->fetch()) {
					$sql_cust = "UPDATE customer_information SET lastname = ?, firstname = ?, middlename = ?, suffix = ?, nationality = ?, birthday = ?, age = ?, gender = ?, civil_status = ?, mobile_number = ?, complete_address = ?, employment_status = ?, company_name = ?, position = ?, monthly_income = ?, valid_id_type = ?, valid_id_image = ?, valid_id_number = ?, Status = 'Pending', customer_type = 'Walk In', updated_at = NOW() WHERE account_id = ?";
					$stmt_cust = $connect->prepare($sql_cust);
					$stmt_cust->execute([$lastname, $firstname, $middlename, $suffix, $nationality, $birthday, $age, $gender, $civil_status, $mobile_number, $complete_address, $employment_status, $company_name, $position, $monthly_income, $valid_id_type, $valid_id_image, $valid_id_number, $account_id]);
				} else {
					$sql_cust = "INSERT INTO customer_information (account_id, lastname, firstname, middlename, suffix, nationality, birthday, age, gender, civil_status, mobile_number, complete_address, employment_status, company_name, position, monthly_income, valid_id_type, valid_id_image, valid_id_number, Status, customer_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Walk In')";
					$stmt_cust = $connect->prepare($sql_cust);
					$stmt_cust->execute([$account_id, $lastname, $firstname, $middlename, $suffix, $nationality, $birthday, $age, $gender, $civil_status, $mobile_number, $complete_address, $employment_status, $company_name, $position, $monthly_income, $valid_id_type, $valid_id_image, $valid_id_number]);
				}

				$connect->commit();
				header("Location: customer.php");
				exit;
			} catch (Exception $e) {
				$connect->rollBack();
				$error_message = "Submission failed: " . $e->getMessage();
			}
		}
	}
} else {
	// Initialize empty form data for first load
	$form_data = [
		'firstname' => '',
		'middlename' => '',
		'lastname' => '',
		'suffix' => '',
		'birthday' => '',
		'age' => '',
		'gender' => '',
		'civil_status' => '',
		'nationality' => '',
		'mobile_number' => '',
		'complete_address' => '',
		'employment_status' => '',
		'company_name' => '',
		'position' => '',
		'monthly_income' => '',
		'valid_id_type' => '',
		'valid_id_number' => ''
	];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Customer Verification</title>
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
			font-family: 'Segoe UI', sans-serif;
		}

		html,
		body {
			width: 100%;
			margin: 0;
			padding: 0;
		}

		body {
			background: #dbdbdbff;
			background-attachment: fixed;
			color: white;
			zoom: 90%;
			padding: 40px 20px;
		}

		.container {
			width: 100%;
			max-width: 800px;
			margin: 0 auto;
		}

		.verification-box {
			background-color: #5f5c5cb0;
	
			padding: 28px 24px;
			border-radius: 15px;
			box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
			text-align: center;
			margin-top: 50px;
			margin-bottom: 50px;
		}

		h2 {
			font-size: 1.5rem;
			margin-bottom: 10px;
		}

		form {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
			gap: 15px 20px;
			text-align: left;
			margin-top: 20px;
		}

		.form-group {
			display: flex;
			flex-direction: column;
			gap: 5px;
		}

		.full-width {
			grid-column: 1 / -1;
		}

		label {
			font-size: 0.9rem;
		}

		input,
		select {
			padding: 10px 12px;
			border: none;
			border-radius: 5px;
			font-size: 1rem;
			background: #fff;
			color: #333;
			outline: none;
			transition: box-shadow 0.2s;
		}

		input:focus,
		select:focus {
			box-shadow: 0 0 0 2px #b80000;
		}

		button {
			padding: 12px;
			font-size: 1rem;
			border: none;
			background-color: #d60000;
			color: white;
			border-radius: 8px;
			font-weight: bold;
			cursor: pointer;
			transition: background-color 0.3s ease;
			grid-column: 1 / -1;
			margin-top: 10px;
		}

		button:hover {
			background-color: #b30000;
		}

		button.return-btn {
			background-color: #ffd700;
			color: #b80000;
			font-weight: bold;
		}

		button.return-btn:hover {
			background-color: #ffed4e;
		}

		.message {
			padding: 15px;
			margin-bottom: 20px;
			border-radius: 5px;
			text-align: center;
			font-size: 1rem;
		}

		.error {
			background-color: #ffdddd;
			color: #d8000c;
		}

		.success {
			background-color: #d4edda;
			color: #155724;
		}

		.terms-group {
			flex-direction: row;
			align-items: center;
			gap: 10px;
		}

		.terms-group input[type="checkbox"] {
			width: auto;
			flex-shrink: 0;
		}

		.terms-group label {
			font-size: 0.9rem;
		}

		.terms-group a {
			color: #ffd700;
			text-decoration: none;
		}

		.terms-group a:hover {
			text-decoration: underline;
		}

		/* Modal Styles */
		.modal {
			display: none;
			position: fixed;
			z-index: 1000;
			left: 0;
			top: 0;
			width: 100%;
			height: 100%;
			background-color: rgba(0, 0, 0, 0.8);
			backdrop-filter: blur(5px);
			
		}

		.modal-content {
			background: linear-gradient(135deg, #1a1a1a, #2d1b1b);
			margin: 2% auto;
			padding: 0;
			border-radius: 15px;
			width: 90%;
			max-width: 700px;
			max-height: 95vh;
			overflow: hidden;
			box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
			border: 1px solid rgba(85, 85, 85, 0.95);
			display: flex;
			flex-direction: column;
		}

		.modal-header {
			background: #5f5d5dff;
			padding: 15px 20px;
			border-bottom: 2px solid rgba(255, 215, 0, 0.2);
			display: flex;
			justify-content: space-between;
			align-items: center;
			flex-shrink: 0;
		}

		.modal-header h2 {
			color: #ffd700;
			margin: 0;
			font-size: 1.5rem;
		}

		.close {
			color: #ffd700;
			font-size: 1.8rem;
			font-weight: bold;
			cursor: pointer;
			transition: all 0.3s ease;
			flex-shrink: 0;
		}

		.close:hover {
			transform: scale(1.1);
		}

		.modal-body {
			background: #ffffff;
			padding: 20px;
			overflow-y: auto;
			line-height: 1.6;
			color: #000000Be;
			font-size: 1rem;
			flex: 1;
			min-height: 0;
		}

		.modal-body h4 {
			color: #f51919cc;
			margin: 20px 0 10px 0;
			font-size: 1.3rem;
		}

		.modal-body h4:first-child {
			margin-top: 0;
		}

		.modal-body ul {
			margin: 10px 0 15px 20px;
		}

		.modal-body li {
			margin-bottom: 8px;
		}

		/* Custom Scrollbar Styles */
		::-webkit-scrollbar {
			width: 8px;
			height: 8px;
		}

		::-webkit-scrollbar-track {
			background: rgba(255, 255, 255, 0.05);
			border-radius: 4px;
		}

		::-webkit-scrollbar-thumb {
			background: rgba(255, 215, 0, 0.3);
			border-radius: 4px;
			transition: all 0.3s ease;
		}

		::-webkit-scrollbar-thumb:hover {
			background: rgba(10, 10, 10, 0.5);
		}

		::-webkit-scrollbar-corner {
			background: rgba(255, 255, 255, 0.05);
		}

		/* Firefox Scrollbar */
		* {
			scrollbar-width: thin;
			scrollbar-color: rgba(255, 215, 0, 0.3) rgba(255, 255, 255, 0.05);
		}

		@media (max-width: 768px) {
			.modal-content {
				width: 95%;
				margin: 2% auto;
				max-height: 98vh;
			}

			.modal-header {
				padding: 12px 15px;
			}

			.modal-header h2 {
				font-size: 1.3rem;
			}

			.modal-body {
				padding: 15px;
			}
		}

		@media (max-width: 480px) {
			.modal-content {
				width: 98%;
				margin: 1% auto;
				max-height: 99vh;
			}

			.modal-header {
				padding: 10px 12px;
			}

			.modal-header h2 {
				font-size: 1.1rem;
			}

			.modal-body {
				padding: 12px;
			}
		}

		/* Extra Small Devices (max-width: 575px) */
		@media (max-width: 575px) {

			html,
			body {
				zoom: 90%;
			}

			.login-box {
				padding: 14px 6vw;
				width: 95vw;
				max-width: 95vw;
				min-width: unset;
			}

			.logo {
				width: 60px;
			}

			h2 {
				font-size: 1.1rem;
			}

			form {
				gap: 7px;
				width: 100%;
				/* Remove max-width restriction */
			}

			input {
				padding: 8px 10px;
				font-size: 0.95rem;
			}

			button {
				font-size: 0.95rem;
				padding: 9px;
			}
		}

		/* Small Devices (min-width: 576px) and (max-width: 767px) */
		@media (min-width: 576px) and (max-width: 767px) {

			html,
			body {
				zoom: 85%;
			}

			.login-box {
				padding: 30px;
				max-width: 350px;
			}

			.logo {
				width: 70px;
			}

			h2 {
				font-size: 1.25rem;
			}
		}

		/* Medium Devices (min-width: 768px) and (max-width: 991px) */
		@media (min-width: 768px) and (max-width: 991px) {

			html,
			body {
				zoom: 85%;
			}

			.login-box {
				padding: 35px;
				max-width: 380px;
			}

			.logo {
				width: 75px;
			}

			h2 {
				font-size: 1.4rem;
			}
		}

		/* Large Devices (min-width: 992px) and (max-width: 1199px) */
		@media (min-width: 992px) and (max-width: 1199px) {

			html,
			body {
				zoom: 80%;
			}

			.login-box {
				padding: 40px;
				max-width: 400px;
			}

			.logo {
				width: 80px;
			}

			h2 {
				font-size: 1.5rem;
			}
		}

		/* Large Devices (min-width: 992px) and (max-width: 1199px) */
		@media (min-width: 1199px) and (max-width: 1440px) {

			html,
			body {
				zoom: 85%;
			}

			.login-box {
				padding: 40px;
				max-width: 400px;
			}

			.logo {
				width: 80px;
			}

			h2 {
				font-size: 1.5rem;
			}
		}
	</style>
</head>

<body>
	<div class="container">
		<div class="verification-box">
			<h2>Customer Verification</h2>

			<?php if (!empty($error_message)): ?>
				<div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
			<?php endif; ?>

			<?php if (!empty($success_message)): ?>
				<div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
			<?php endif; ?>

			<?php if ($show_form): ?>
				<p style="font-size: 0.9rem; color: #eee;">Please fill out the form below to verify your account. All fields with * are required.</p>
				<form method="POST" enctype="multipart/form-data" autocomplete="off">
					<div class="form-group">
						<label for="firstname">First Name *</label>
						<input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($form_data['firstname']); ?>" required>
					</div>
					<div class="form-group">
						<label for="middlename">Middle Name</label>
						<input type="text" id="middlename" name="middlename" value="<?php echo htmlspecialchars($form_data['middlename']); ?>">
					</div>
					<div class="form-group">
						<label for="lastname">Last Name *</label>
						<input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($form_data['lastname']); ?>" required>
					</div>
					<div class="form-group">
						<label for="suffix">Suffix</label>
						<input type="text" id="suffix" name="suffix" placeholder="e.g. Jr., Sr., III" value="<?php echo htmlspecialchars($form_data['suffix']); ?>">
					</div>
					<div class="form-group">
						<label for="birthday">Date of Birth *</label>
						<input type="date" id="birthday" name="birthday" value="<?php echo htmlspecialchars($form_data['birthday']); ?>" required onchange="calculateAge()">
					</div>
					<div class="form-group">
						<label for="age">Age *</label>
						<input type="number" id="age" name="age" value="<?php echo htmlspecialchars($form_data['age']); ?>" readonly required>
						<span id="age-error" style="color: #ffd700; font-size: 0.8rem; display: none; margin-top: 4px;">You must be 18 or older to register.</span>
					</div>
					<div class="form-group">
						<label for="gender">Gender *</label>
						<select id="gender" name="gender" required>
							<option value="" disabled <?php echo empty($form_data['gender']) ? 'selected' : ''; ?>>Select Gender</option>
							<option value="Male" <?php echo $form_data['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
							<option value="Female" <?php echo $form_data['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
						</select>
					</div>
					<div class="form-group">
						<label for="civil_status">Civil Status *</label>
						<select id="civil_status" name="civil_status" required>
							<option value="" disabled <?php echo empty($form_data['civil_status']) ? 'selected' : ''; ?>>Select Status</option>
							<option value="Single" <?php echo $form_data['civil_status'] === 'Single' ? 'selected' : ''; ?>>Single</option>
							<option value="Married" <?php echo $form_data['civil_status'] === 'Married' ? 'selected' : ''; ?>>Married</option>
							<option value="Widowed" <?php echo $form_data['civil_status'] === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
							<option value="Separated" <?php echo $form_data['civil_status'] === 'Separated' ? 'selected' : ''; ?>>Separated</option>
						</select>
					</div>
					<div class="form-group">
						<label for="nationality">Nationality *</label>
						<input type="text" id="nationality" name="nationality" value="<?php echo htmlspecialchars($form_data['nationality']); ?>" required>
					</div>
					<div class="form-group">
						<label for="mobile_number">Mobile Number *</label>
						<input type="tel" id="mobile_number" name="mobile_number" value="<?php echo htmlspecialchars($form_data['mobile_number']); ?>" required placeholder="e.g. 09123456789">
					</div>
					<div class="form-group full-width">
						<label for="complete_address">Complete Address *</label>
						<input type="text" id="complete_address" name="complete_address" value="<?php echo htmlspecialchars($form_data['complete_address']); ?>" required placeholder="House/Unit, Street, Barangay, City/Municipality, Province, ZIP">
					</div>
					<div class="form-group">
						<label for="employment_status">Employment Status *</label>
						<select id="employment_status" name="employment_status" required>
							<option value="" disabled <?php echo empty($form_data['employment_status']) ? 'selected' : ''; ?>>Select Status</option>
							<option value="Employed" <?php echo $form_data['employment_status'] === 'Employed' ? 'selected' : ''; ?>>Employed</option>
							<option value="Self-Employed" <?php echo $form_data['employment_status'] === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
							<option value="Unemployed" <?php echo $form_data['employment_status'] === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
							<option value="Student" <?php echo $form_data['employment_status'] === 'Student' ? 'selected' : ''; ?>>Student</option>
						</select>
					</div>
					<div class="form-group">
						<label for="company_name">Company Name</label>
						<input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($form_data['company_name']); ?>">
					</div>
					<div class="form-group">
						<label for="position">Position</label>
						<input type="text" id="position" name="position" value="<?php echo htmlspecialchars($form_data['position']); ?>">
					</div>
					<div class="form-group">
						<label for="monthly_income">Monthly Income (PHP)</label>
						<input type="number" step="0.01" id="monthly_income" name="monthly_income" value="<?php echo htmlspecialchars($form_data['monthly_income']); ?>">
					</div>
					<div class="form-group">
						<label for="valid_id_type">Valid ID Type *</label>
						<select id="valid_id_type" name="valid_id_type" required>
							<option value="" disabled <?php echo empty($form_data['valid_id_type']) ? 'selected' : ''; ?>>Select ID Type</option>
							<option value="Driver's License" <?php echo $form_data['valid_id_type'] === "Driver's License" ? 'selected' : ''; ?>>Driver's License</option>
							<option value="Passport" <?php echo $form_data['valid_id_type'] === 'Passport' ? 'selected' : ''; ?>>Passport</option>
							<option value="UMID" <?php echo $form_data['valid_id_type'] === 'UMID' ? 'selected' : ''; ?>>UMID</option>
							<option value="SSS ID" <?php echo $form_data['valid_id_type'] === 'SSS ID' ? 'selected' : ''; ?>>SSS ID</option>
							<option value="PhilHealth ID" <?php echo $form_data['valid_id_type'] === 'PhilHealth ID' ? 'selected' : ''; ?>>PhilHealth ID</option>
							<option value="TIN ID" <?php echo $form_data['valid_id_type'] === 'TIN ID' ? 'selected' : ''; ?>>TIN ID</option>
							<option value="Voter's ID" <?php echo $form_data['valid_id_type'] === "Voter's ID" ? 'selected' : ''; ?>>Voter's ID</option>
							<option value="Postal ID" <?php echo $form_data['valid_id_type'] === 'Postal ID' ? 'selected' : ''; ?>>Postal ID</option>
							<option value="PRC ID" <?php echo $form_data['valid_id_type'] === 'PRC ID' ? 'selected' : ''; ?>>PRC ID</option>
							<option value="National ID (PhilSys)" <?php echo $form_data['valid_id_type'] === 'National ID (PhilSys)' ? 'selected' : ''; ?>>National ID (PhilSys)</option>
						</select>
					</div>
					<div class="form-group">
						<label for="valid_id_number">Valid ID Number *</label>
						<input type="text" id="valid_id_number" name="valid_id_number" value="<?php echo htmlspecialchars($form_data['valid_id_number']); ?>" required>
					</div>
					<div class="form-group full-width">
						<label for="valid_id_image">Upload Valid ID Image * (Max 5MB, JPEG/PNG/GIF only)</label>
						<input type="file" id="valid_id_image" name="valid_id_image" accept="image/jpeg,image/jpg,image/png,image/gif" required>
					</div>

					<div class="form-group full-width terms-group">
						<input type="checkbox" id="terms" name="terms" value="accepted" onchange="updateSubmitButtonState()">
						<label for="terms">
							I accept the <a href="#" onclick="openTermsModal(); return false;">Privacy and Terms</a> of Mitsubishi Motors Philippines.
						</label>
					</div>

					<button type="submit" id="submit-btn" disabled>Submit for Verification</button>
					<button type="button" class="return-btn" onclick="window.location.href='landingpage.php'">Return to Landing Page</button>
				</form>
			<?php else: ?>
				<button onclick="window.location.href='logout.php'" style="padding: 12px; font-size: 1rem; border: none; background-color: #d60000; color: white; border-radius: 8px; font-weight: bold; cursor: pointer; transition: background-color 0.3s ease; width: 100%; margin-top: 10px;">Logout</button>
			<?php endif; ?>
		</div>
	</div>

	<!-- Terms and Conditions Modal -->
	<div id="termsModal" class="modal">
		<div class="modal-content">
			<div class="modal-header">
				<h2>Terms and Conditions</h2>
				<span class="close" onclick="closeTermsModal()">&times;</span>
			</div>
			<div class="modal-body">
				<h4>1. Account Usage and Responsibilities</h4>
				<p>Creating and using a Mitsubishi Motors account, you agree to keep your account information correct and up-to-date. You also promise to keep your username and password safe and not share them with anyone. You agree to use this account only for legitimate car-related activities, such as browsing, reserving, or purchasing a vehicle. Misusing your account or using it for illegal activities is not allowed.</p>

				<h4>2. Privacy and Data Protection</h4>
				<p>Your privacy is important to us. All personal information you provide to us is encrypted to protect it. We take extra measures to ensure your data is safe. We will never share your personal information with other companies or people unless we have your permission. You also have the right to access your information at any time, update it if it changes, or delete it if you no longer want us to have it.</p>

				<h4>3. Inquiries and Loans</h4>
				<p>When you use the platform to inquire about vehicles or apply for loans, please be aware that prices and availability are subject to change. Your inquiries will be processed and shared with the dealership, who will provide you with the most accurate information. If you choose to apply for a loan through our platform, the loan approval process may include credit checks. The final terms of any loan or vehicle inquiry, including interest rates, loan amounts, and repayment plans, will be confirmed directly by the dealership or financing partner.</p>

				<h4>4. Payment Terms</h4>
				<p>When making payments, you agree to pay on time according to the payment plan you’ve agreed to. If payments are late, extra fees may be charged. You will receive email notifications and confirmation receipts for every payment you make, so you always know where you stand.</p>

				<h4>5. Platform Usage Guidelines</h4>
				<p>We ask that you use respectful and polite language when interacting on our platform. Don’t try to bypass any security systems, and if you notice any technical problems, let us know right away. Please follow the instructions from our support team to ensure your experience on the platform is as smooth as possible.</p>

				<h4>6. Limitation of Liability</h4>
				<p>While we strive to provide the best service possible, Mitsubishi Motors Philippines cannot be held responsible for any issues caused by things beyond our control, like technical problems, errors, or other unforeseen circumstances. We also reserve the right to change these terms, suspend accounts if they’re misused, or limit our liability if issues arise.</p>

				<h4>7. Customer Support and Dispute Resolution</h4>
				<p>If you face any issues or have questions about your account, feel free to reach out to our customer support team. We’re here to assist you and will try to resolve your concerns as quickly as possible. If we can't fix the problem right away, we’ll work with you to find a fair solution.</p>

				<p style="text-align: center; margin-top: 20px; font-size: 0.8rem; color: #aaa;">Last updated: January 2025</p>
			</div>
		</div>
	</div>

	<script>
		function updateSubmitButtonState() {
			const ageInput = document.getElementById('age');
			const termsChecked = document.getElementById('terms').checked;
			const submitButton = document.getElementById('submit-btn');

			const age = parseInt(ageInput.value, 10);
			const isAgeValid = !isNaN(age) && age >= 18;

			if (isAgeValid && termsChecked) {
				submitButton.disabled = false;
			} else {
				submitButton.disabled = true;
			}
		}

		function calculateAge() {
			const birthdayInput = document.getElementById('birthday');
			const ageInput = document.getElementById('age');
			const ageError = document.getElementById('age-error');

			if (birthdayInput.value) {
				const birthDate = new Date(birthdayInput.value);
				const today = new Date();
				let age = today.getFullYear() - birthDate.getFullYear();
				const m = today.getMonth() - birthDate.getMonth();
				if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
					age--;
				}
				ageInput.value = age >= 0 ? age : 0;

				if (age < 18) {
					ageError.style.display = 'block';
				} else {
					ageError.style.display = 'none';
				}
			} else {
				ageInput.value = '';
				ageError.style.display = 'none';
			}
			updateSubmitButtonState();
		}

		function openTermsModal() {
			document.getElementById('termsModal').style.display = 'block';
			document.body.style.overflow = 'hidden';
		}

		function closeTermsModal() {
			document.getElementById('termsModal').style.display = 'none';
			document.body.style.overflow = 'auto';
		}

		// Close modal when clicking outside
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