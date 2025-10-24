<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
	header("Location: login.php");
	exit;
}

// Get vehicle ID and applicant type from URL
$vehicle_id = $_GET['vehicle_id'] ?? null;
$applicant_type = $_GET['applicant_type'] ?? 'EMPLOYED'; // Default to EMPLOYED

if (!$vehicle_id) {
	header("Location: car_menu.php");
	exit;
}

// Validate applicant type
if (!in_array($applicant_type, ['EMPLOYED', 'BUSINESS', 'OFW'])) {
	$applicant_type = 'EMPLOYED';
}

// Fetch vehicle details
$stmt = $connect->prepare("SELECT * FROM vehicles WHERE id = ?");
$stmt->execute([$vehicle_id]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
	header("Location: car_menu.php");
	exit;
}

// Fetch user details
$stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch customer information if exists
$stmt = $connect->prepare("SELECT * FROM customer_information WHERE account_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer_info = $stmt->fetch(PDO::FETCH_ASSOC);

$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

// Interest rates based on financing term (annual rates)
$interest_rates = [
	3 => 8.5,   // 3 months - 8.5% annual
	6 => 9.0,   // 6 months - 9.0% annual
	12 => 10.5, // 12 months - 10.5% annual
	24 => 12.0, // 24 months - 12.0% annual
	36 => 13.5, // 36 months - 13.5% annual
	48 => 15.0, // 48 months - 15.0% annual
	60 => 16.5  // 60 months - 16.5% annual
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Loan Application Form - Mitsubishi Motors</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
			font-family: 'Inter', 'Segoe UI', sans-serif;
		}

		body {
			background:#ffffff;
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
		}

		.header {
			background: #ffffff;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #CC0000;
            position: relative;
            z-index: 10;
		}

		.logo-section {
			display: flex;
			align-items: center;
			gap: 20px;
		}

		.logo {
			width: 60px;
			height: auto;
			filter: drop-shadow(0 0 3px rgba(204, 48, 27, 0.93));
		}

		.brand-text {
			font-size: 1.4rem;
            font-weight: 700;
            color: #E60012;
		}

		.container {
			max-width: 1200px;
			margin: 0 auto;
			padding: 40px 30px;
		}

		.page-header {
			text-align: center;
			margin-bottom: 30px;
		}

		.page-title {
			font-size: 2.5rem;
			margin-bottom: 20px;
			background: #e60013c9;
			-webkit-background-clip: text;
			-webkit-text-fill-color: transparent;
			background-clip: text;
			font-weight: 800;
		}

		.vehicle-info {
			background: rgba(102, 102, 102, 0.92);
			padding: 20px;
			border-radius: 15px;
			text-align: center;
			margin-bottom: 40px;
			backdrop-filter: blur(20px);
		}

		.applicant-type-container {
			background: rgba(102, 102, 102, 0.92);
			padding: 20px;
			border-radius: 15px;
			text-align: center;
			margin-bottom: 40px;
			backdrop-filter: blur(20px);
			border: 1px solid rgba(255, 215, 0, 0.2);
			text-align: center;
		}

		.type-buttons {
			display: flex;
			justify-content: center;
			gap: 20px;
			flex-wrap: wrap;
		}

		.type-btn {
			background: rgba(255, 255, 255, 0.1);
			border: 2px solid #ffd700;
			color: #ffd700;
			padding: 15px 25px;
			border-radius: 10px;
			cursor: pointer;
			transition: all 0.3s ease;
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 8px;
			font-weight: bold;
			min-width: 120px;
			text-decoration: none;
		}

		.type-btn:hover {
			background: rgba(255, 215, 0, 0.2);
			transform: translateY(-2px);
			box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
			color: #ffd700;
		}

		.type-btn.active {
			background: linear-gradient(135deg, #ffd700, #ffed4e);
			color: #1a1a1a;
			box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
		}

		.type-btn i {
			font-size: 1.5em;
		}

		.type-btn span {
			font-size: 0.9em;
		}

		.form-container {
			background: rgba(102, 102, 102, 0.92);
			padding: 20px;
			border-radius: 15px;

			margin-bottom: 40px;
			backdrop-filter: blur(20px);
			box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
			border: 1px solid rgba(255, 215, 0, 0.1);
			margin-bottom: 30px;
		}

		.form-section {
			margin-bottom: 40px;
		}

		.form-section h3 {
			color: #ffd700;
			margin-bottom: 20px;
			font-size: 1.4rem;
			border-bottom: 2px solid rgba(255, 215, 0, 0.3);
			padding-bottom: 10px;
		}

		.form-row {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 20px;
			margin-bottom: 20px;
		}

		.form-group {
			display: flex;
			flex-direction: column;
		}

		.form-label {
			color: #ffd700;
			margin-bottom: 8px;
			font-weight: 600;
			font-size: 0.95rem;
		}

		.form-input, .form-select, .form-textarea {
			background: rgba(179, 169, 169, 0.1);
			border: 1px solid rgba(255, 215, 0, 0.3);
			border-radius: 8px;
			padding: 12px 15px;
			color: white;
			font-size: 1rem;
			transition: all 0.3s ease;
		}

		.form-select option {
			background: #ffffff;
			color: #1a1a1a;
			padding: 8px 12px;
			font-size: 1rem;
		}

		.form-select option:hover {
			background: #f0f0f0;
			color: #000000;
		}

		.form-input:focus, .form-select:focus, .form-textarea:focus {
			outline: none;
			border-color: #ffd700;
			box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
			background: rgba(223, 196, 196, 0.15);
		}



        .form-textarea::placeholder {
            color: #c4c4c4ff;  /* Light gray */
            opacity: 1;
        }

		.form-file {
			background: rgba(255, 255, 255, 0.1);
			border: 1px solid rgba(255, 215, 0, 0.3);
			border-radius: 8px;
			padding: 12px 15px;
			color: white;
			font-size: 1rem;
			transition: all 0.3s ease;
			cursor: pointer;
		}

		.form-file::-webkit-file-upload-button {
			background: linear-gradient(135deg, #ffd700, #ffed4e);
			color: #1a1a1a;
			border: none;
			padding: 8px 16px;
			border-radius: 6px;
			cursor: pointer;
			margin-right: 10px;
			font-size: 0.9rem;
			font-weight: 600;
			transition: all 0.3s ease;
		}

		.form-file::-webkit-file-upload-button:hover {
			background: linear-gradient(135deg, #e6c200, #e6d445);
			transform: translateY(-1px);
			box-shadow: 0 3px 10px rgba(255, 215, 0, 0.3);
		}

		.form-file:focus {
			outline: none;
			border-color: #ffd700;
			box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
			background: rgba(255, 255, 255, 0.15);
		}

		.form-input::placeholder {
			color: rgba(255, 255, 255, 0.6);
		}

		.form-help {
			display: block;
			color: #ccc;
			font-size: 12px;
			margin-top: 5px;
			font-style: italic;
		}

		.required {
			color: #dc143c;
			font-weight: bold;
		}

		.form-input:read-only {
			background: rgba(255, 255, 255, 0.05);
			color: rgba(255, 255, 255, 0.8);
			cursor: not-allowed;
		}

		.payment-plan-container {
			background: rgba(255, 215, 0, 0.1);
			border: 1px solid #ffd700;
			border-radius: 15px;
			padding: 25px;
			margin: 20px 0;
		}

		.payment-options {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
			gap: 15px;
			margin-bottom: 20px;
		}

		.payment-option {
			background: rgba(255, 255, 255, 0.1);
			border: 2px solid rgba(255, 215, 0, 0.3);
			border-radius: 10px;
			padding: 15px;
			text-align: center;
			cursor: pointer;
			transition: all 0.3s ease;
		}

		.payment-option:hover {
			border-color: #ffd700;
			background: rgba(255, 215, 0, 0.1);
		}

		.payment-option.selected {
			background: linear-gradient(135deg, #ffd700, #ffed4e);
			color: #1a1a1a;
			border-color: #ffd700;
			box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
		}

		.payment-option input[type="radio"] {
			display: none;
		}

		.payment-summary {
			background: rgba(0, 0, 0, 0.3);
			border-radius: 10px;
			padding: 20px;
			margin-top: 20px;
		}

		.summary-row {
			display: flex;
			justify-content: space-between;
			margin-bottom: 10px;
			padding: 8px 0;
			border-bottom: 1px solid rgba(255, 255, 255, 0.1);
		}

		.summary-row:last-child {
			border-bottom: none;
			font-weight: bold;
			font-size: 1.1rem;
			color: #ffd700;
		}

		.action-buttons {
			display: flex;
			gap: 20px;
			justify-content: center;
			margin-top: 30px;
		}

		.btn {
			padding: 15px 30px;
			border: none;
			border-radius: 15px;
			cursor: pointer;
			font-weight: 700;
			font-size: 1rem;
			text-transform: uppercase;
			letter-spacing: 1px;
			transition: all 0.3s ease;
			text-decoration: none;
			display: inline-block;
		}

		.btn-submit {
			background: #E60012;
			color: white;
			box-shadow: 0 4px 15px rgba(182, 33, 33, 0.4);
		}

		.btn-submit:hover {
			transform: translateY(-3px);
			box-shadow: 0 8px 25px rgba(39, 174, 96, 0.5);
		}

		.btn-back {
			background: rgba(255, 255, 255, 0.1);
			color: #ffd700;
			border: 2px solid #ffd700;
		}

		.btn-back:hover {
			background: #ffd700;
			color: #1a1a1a;
		}

		.required {
			color: #ff6b6b;
		}

		.form-help {
			font-size: 0.85rem;
			color: rgba(255, 255, 255, 0.7);
			margin-top: 5px;
		}

		@media (max-width: 768px) {
			.container {
				padding: 15px;
			}

			.page-title {
				font-size: 2rem;
			}

			.form-container {
				padding: 20px;
			}

			.form-row {
				grid-template-columns: 1fr;
			}

			.payment-options {
				grid-template-columns: repeat(2, 1fr);
			}

			.action-buttons {
				flex-direction: column;
				align-items: center;
			}

			.btn {
				width: 100%;
				max-width: 300px;
			}

			
		}

		    /* Tablet */
    @media (max-width: 1024px) {
        .container {
            max-width: 95%;
        }
    }

    /* Phones */
    @media (max-width: 768px) {
        .header {
            flex-direction: column;
            gap: 15px;
            padding: 15px 20px;
        }

        .user-section {
            flex-direction: column;
            gap: 12px;
            text-align: center;
            width: 100%;
        }

        .container {
            padding: 20px 15px;
        }

        .form-container {
            padding: 20px;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Large Desktops */
    @media (min-width: 1200px) {
        .container {
            max-width: 1100px;
        }

        .inquiry-card {
            max-width: 100%;
        }

        .form-grid {
            grid-template-columns: repeat(2, 1fr);
        }
	</style>
</head>

<body>
	<header class="header">
		<div class="logo-section">
			<img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo">
			<div class="brand-text">MITSUBISHI MOTORS</div>
		</div>
		<div class="user-section">
			<span style="color: #E60012; font-weight: 600;">Welcome, <?php echo htmlspecialchars($displayName); ?>!</span>
		</div>
	</header>

	<div class="container">
		<div class="page-header">
			<h1 class="page-title">Loan Application Form</h1>
		</div>

		<div class="vehicle-info">
			<h3 style="color: #ffd700; margin-bottom: 10px;">Vehicle: <?php echo htmlspecialchars($vehicle['model_name']); ?></h3>
			<?php if (!empty($vehicle['variant'])): ?>
				<p style="color: #ccc;"><?php echo htmlspecialchars($vehicle['variant']); ?></p>
			<?php endif; ?>
			<p style="color: #ffd700; margin-top: 10px;"><i class="fas fa-user-tag"></i> Applicant Type: <strong><?php echo htmlspecialchars($applicant_type); ?></strong></p>
			<p style="color: #fff; margin-top: 10px; font-size: 1.2rem;"><i class="fas fa-tag"></i> Base Price: <strong>₱<?php echo number_format($vehicle['base_price'], 2); ?></strong></p>
		</div>

		<!-- Applicant Type Selection -->
		<div class="applicant-type-container">
			<h3 style="color: #ffd700; margin-bottom: 15px; text-align: center;">Change Applicant Type (Optional)</h3>
			<div class="type-buttons">
				<a href="?vehicle_id=<?php echo $vehicle_id; ?>&applicant_type=EMPLOYED" class="type-btn <?php echo $applicant_type === 'EMPLOYED' ? 'active' : ''; ?>">
					<i class="fas fa-briefcase"></i>
					<span>EMPLOYED</span>
				</a>
				<a href="?vehicle_id=<?php echo $vehicle_id; ?>&applicant_type=BUSINESS" class="type-btn <?php echo $applicant_type === 'BUSINESS' ? 'active' : ''; ?>">
					<i class="fas fa-store"></i>
					<span>BUSINESS</span>
				</a>
				<a href="?vehicle_id=<?php echo $vehicle_id; ?>&applicant_type=OFW" class="type-btn <?php echo $applicant_type === 'OFW' ? 'active' : ''; ?>">
					<i class="fas fa-globe"></i>
					<span>OFW</span>
				</a>
			</div>
		</div>

		<form id="loanApplicationForm" class="form-container">
			<input type="hidden" name="vehicle_id" value="<?php echo $vehicle_id; ?>">
			<input type="hidden" name="applicant_type" value="<?php echo $applicant_type; ?>">
			<input type="hidden" name="base_price" value="<?php echo $vehicle['base_price']; ?>">

			<!-- Personal Information Section -->
			<div class="form-section">
				<h3><i class="fas fa-user"></i> Personal Information</h3>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">First Name <span class="required">*</span></label>
						<input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($customer_info['firstname'] ?? $user['FirstName'] ?? ''); ?>" required>
					</div>
					<div class="form-group">
						<label class="form-label">Last Name <span class="required">*</span></label>
						<input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($customer_info['lastname'] ?? $user['LastName'] ?? ''); ?>" required>
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Middle Name</label>
						<input type="text" name="middle_name" class="form-input" value="<?php echo htmlspecialchars($customer_info['middlename'] ?? ''); ?>">
					</div>
					<div class="form-group">
						<label class="form-label">Suffix</label>
						<input type="text" name="suffix" class="form-input" value="<?php echo htmlspecialchars($customer_info['suffix'] ?? ''); ?>" placeholder="Jr., Sr., III">
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Email Address <span class="required">*</span></label>
						<input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['Email'] ?? ''); ?>" required>
					</div>
					<div class="form-group">
						<label class="form-label">Mobile Number <span class="required">*</span></label>
						<input type="tel" name="mobile_number" class="form-input" value="<?php echo htmlspecialchars($customer_info['mobile_number'] ?? ''); ?>" required
						oninput="this.value = this.value.replace(/[^0-9+]/g, '')"
						onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />
						</div>
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Date of Birth <span class="required">*</span></label>
						<input type="date" name="date_of_birth" class="form-input" value="<?php echo $customer_info['birthday'] ?? $user['DateOfBirth'] ?? ''; ?>" required>
					</div>
					<div class="form-group">
						<label class="form-label">Age</label>
						<input type="number" name="age" class="form-input" value="<?php echo $customer_info['age'] ?? ''; ?>" readonly>
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Gender <span class="required">*</span></label>
						<select name="gender" class="form-select" required>
							<option value="">Select Gender</option>
							<option value="Male" <?php echo ($customer_info['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
							<option value="Female" <?php echo ($customer_info['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
							<option value="Other" <?php echo ($customer_info['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
						</select>
					</div>
					<div class="form-group">
						<label class="form-label">Civil Status <span class="required">*</span></label>
						<select name="civil_status" class="form-select" required>
							<option value="">Select Civil Status</option>
							<option value="Single" <?php echo ($customer_info['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
							<option value="Married" <?php echo ($customer_info['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
							<option value="Divorced" <?php echo ($customer_info['civil_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
							<option value="Widowed" <?php echo ($customer_info['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
						</select>
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Nationality</label>
						<input type="text" name="nationality" class="form-input" value="<?php echo htmlspecialchars($customer_info['nationality'] ?? 'Filipino'); ?>">
					</div>
					<div class="form-group">
						<label class="form-label">Complete Address <span class="required">*</span></label>
						<textarea name="address" class="form-textarea" rows="3" required placeholder="Complete home address"></textarea>
					</div>
				</div>
			</div>

			<!-- Employment Information Section -->
			<div class="form-section">
				<h3><i class="fas fa-briefcase"></i> Employment Information</h3>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Employment Status <span class="required">*</span></label>
						<select name="employment_status" class="form-select" required>
							<option value="">Select Employment Status</option>
							<option value="Employed" <?php echo ($customer_info['employment_status'] ?? '') === 'Employed' ? 'selected' : ''; ?>>Employed</option>
							<option value="Self-Employed" <?php echo ($customer_info['employment_status'] ?? '') === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
							<option value="Business Owner" <?php echo ($customer_info['employment_status'] ?? '') === 'Business Owner' ? 'selected' : ''; ?>>Business Owner</option>
							<option value="OFW" <?php echo ($customer_info['employment_status'] ?? '') === 'OFW' ? 'selected' : ''; ?>>OFW</option>
							<option value="Retired" <?php echo ($customer_info['employment_status'] ?? '') === 'Retired' ? 'selected' : ''; ?>>Retired</option>
						</select>
					</div>
					<div class="form-group">
						<label class="form-label">Company/Employer Name <span class="required">*</span></label>
						<input type="text" name="company_name" class="form-input" value="<?php echo htmlspecialchars($customer_info['company_name'] ?? ''); ?>" required>
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Position/Job Title <span class="required">*</span></label>
						<input type="text" name="position" class="form-input" value="<?php echo htmlspecialchars($customer_info['position'] ?? ''); ?>" required>
					</div>
					<div class="form-group">
						<label class="form-label">Years of Employment</label>
						<input type="number" name="years_employed" class="form-input" min="0" step="0.5"
						onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Monthly Income <span class="required">*</span></label>
						<input type="number" name="monthly_income" class="form-input" value="<?php echo $customer_info['monthly_income'] ?? ''; ?>" min="0" step="0.01" required>
					</div>
					<div class="form-group">
						<label class="form-label">Other Income Sources</label>
						<input type="number" name="other_income" class="form-input" min="0" step="0.01" placeholder="Additional income (optional)"
						onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Company Address</label>
						<textarea name="company_address" class="form-textarea" rows="2" placeholder="Complete company address"></textarea>
					</div>
					<div class="form-group">
						<label class="form-label">Company Contact Number</label>
						<input type="tel" name="company_contact" class="form-input"
						oninput="this.value = this.value.replace(/[^0-9+]/g, '')"
						onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />
					</div>
				</div>
			</div>

			<!-- Vehicle Information Section (Pre-filled) -->
			<div class="form-section">
				<h3><i class="fas fa-car"></i> Vehicle Information</h3>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Model Name</label>
						<input type="text" name="vehicle_model" class="form-input" value="<?php echo htmlspecialchars($vehicle['model_name']); ?>" readonly>
					</div>
					<div class="form-group">
						<label class="form-label">Variant</label>
						<input type="text" name="vehicle_variant" class="form-input" value="<?php echo htmlspecialchars($vehicle['variant'] ?? ''); ?>" readonly>
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Year Model</label>
						<input type="text" name="year_model" class="form-input" value="<?php echo htmlspecialchars($vehicle['year_model'] ?? ''); ?>" readonly>
					</div>
					<div class="form-group">
						<label class="form-label">Engine Type</label>
						<input type="text" name="engine_type" class="form-input" value="<?php echo htmlspecialchars($vehicle['engine_type'] ?? ''); ?>" readonly>
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Transmission</label>
						<input type="text" name="transmission" class="form-input" value="<?php echo htmlspecialchars($vehicle['transmission'] ?? ''); ?>" readonly>
					</div>
					<div class="form-group">
						<label class="form-label">Base Price</label>
						<input type="text" name="display_price" class="form-input" value="₱<?php echo number_format($vehicle['base_price'], 2); ?>" readonly>
					</div>
				</div>
			</div>

			<!-- Payment Plan Section -->
			<div class="form-section">
				<h3><i class="fas fa-calculator"></i> Payment Plan</h3>
				<div class="payment-plan-container">
					<div class="form-row">
						<div class="form-group">
							<label class="form-label">Down Payment (₱) <span class="required">*</span></label>
							<input type="number" name="down_payment" id="downPayment" class="form-input" min="0" step="0.01" required>
							<small class="form-help">Minimum: ₱<?php echo number_format($vehicle['base_price'] * 0.2, 2); ?> (20%)</small>
						</div>
						<div class="form-group">
							<label class="form-label">Financing Term <span class="required">*</span></label>
							<div class="payment-options">
								<?php foreach ($interest_rates as $months => $rate): ?>
									<label class="payment-option">
										<input type="radio" name="financing_term" value="<?php echo $months; ?>" data-rate="<?php echo $rate; ?>">
										<div>
											<strong><?php echo $months; ?> Months</strong><br>
											<small><?php echo $rate; ?>% Annual</small>
										</div>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<div class="payment-summary" id="paymentSummary" style="display: none;">
						<h4 style="color: #ffd700; margin-bottom: 15px;">Payment Summary</h4>
						<div class="summary-row">
							<span>Vehicle Price:</span>
							<span id="vehiclePrice">₱<?php echo number_format($vehicle['base_price'], 2); ?></span>
						</div>
						<div class="summary-row">
							<span>Down Payment:</span>
							<span id="displayDownPayment">₱0.00</span>
						</div>
						<div class="summary-row">
							<span>Amount to Finance:</span>
							<span id="amountToFinance">₱0.00</span>
						</div>
						<div class="summary-row">
							<span>Interest Rate:</span>
							<span id="interestRate">0%</span>
						</div>
						<div class="summary-row">
							<span>Total Interest:</span>
							<span id="totalInterest">₱0.00</span>
						</div>
						<div class="summary-row">
							<span>Total Amount Payable:</span>
							<span id="totalPayable">₱0.00</span>
						</div>
						<div class="summary-row">
							<span>Monthly Payment:</span>
							<span id="monthlyPayment">₱0.00</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Document Upload Section -->
			<div class="form-section">
				<h3><i class="fas fa-upload"></i> Required Documents</h3>
				<p style="color: #ffd700; margin-bottom: 20px;">Please upload the following documents based on your applicant type:</p>
				
				<!-- Always Required Documents -->
				<div class="form-group">
					<label class="form-label">Valid ID <span class="required">*</span></label>
					<input type="file" name="valid_id" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Upload a clear copy of your valid government-issued ID</small>
				</div>

				<?php if ($applicant_type === 'EMPLOYED'): ?>
				<!-- Employed Required Documents -->
				<div class="form-group">
					<label class="form-label">Certificate of Employment with Compensation (COEC) / Latest Payslip <span class="required">*</span></label>
					<input type="file" name="coec_payslip" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Certificate of Employment with Compensation or latest payslip</small>
				</div>

				<div class="form-group">
					<label class="form-label">ITR / BIR Form 2316 <span class="required">*</span></label>
					<input type="file" name="itr_2316" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Income Tax Return or BIR Form 2316</small>
				</div>

				<div class="form-group">
					<label class="form-label">Proof of Billing <span class="required">*</span></label>
					<input type="file" name="proof_billing" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Recent utility bill or billing statement</small>
				</div>

				<!-- Optional Documents for Employed -->
				<div class="form-group">
					<label class="form-label">ADA / Post-Dated Checks (Optional)</label>
					<input type="file" name="ada_pdc" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Auto Debit Arrangement or Post-Dated Checks</small>
				</div>

				<div class="form-group">
					<label class="form-label">Employment Certificate (Optional)</label>
					<input type="file" name="employment_certificate" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Additional employment verification document</small>
				</div>

				<div class="form-group">
					<label class="form-label">Additional Payslip (Optional)</label>
					<input type="file" name="payslip" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Additional payslip for verification</small>
				</div>

				<div class="form-group">
					<label class="form-label">Company ID (Optional)</label>
					<input type="file" name="company_id" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Company identification card</small>
				</div>

				<?php elseif ($applicant_type === 'BUSINESS'): ?>
				<!-- Business Required Documents -->
				<div class="form-group">
					<label class="form-label">Bank Statement <span class="required">*</span></label>
					<input type="file" name="bank_statement" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Latest 3-6 months bank statement</small>
				</div>

				<div class="form-group">
					<label class="form-label">ITR / BIR Form 1701 <span class="required">*</span></label>
					<input type="file" name="itr_1701" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Income Tax Return or BIR Form 1701</small>
				</div>

				<div class="form-group">
					<label class="form-label">DTI Permit / Business Registration <span class="required">*</span></label>
					<input type="file" name="dti_permit" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">DTI permit or business registration documents</small>
				</div>

				<div class="form-group">
					<label class="form-label">Proof of Billing <span class="required">*</span></label>
					<input type="file" name="proof_billing" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Recent utility bill or billing statement</small>
				</div>

				<!-- Optional Documents for Business -->
				<div class="form-group">
					<label class="form-label">ADA / Post-Dated Checks (Optional)</label>
					<input type="file" name="ada_pdc" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Auto Debit Arrangement or Post-Dated Checks</small>
				</div>

				<?php elseif ($applicant_type === 'OFW'): ?>
				<!-- OFW Required Documents -->
				<div class="form-group">
					<label class="form-label">Proof of Remittance <span class="required">*</span></label>
					<input type="file" name="proof_remittance" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Bank remittance records or money transfer receipts</small>
				</div>

				<div class="form-group">
					<label class="form-label">Latest Employment Contract <span class="required">*</span></label>
					<input type="file" name="latest_contract" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Current overseas employment contract</small>
				</div>

				<div class="form-group">
					<label class="form-label">Special Power of Attorney (SPA) <span class="required">*</span></label>
					<input type="file" name="spa" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Notarized Special Power of Attorney</small>
				</div>

				<div class="form-group">
					<label class="form-label">Proof of Billing <span class="required">*</span></label>
					<input type="file" name="proof_billing" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Recent utility bill or billing statement</small>
				</div>

				<!-- Optional Documents for OFW -->
				<div class="form-group">
					<label class="form-label">ADA / Post-Dated Checks (Optional)</label>
					<input type="file" name="ada_pdc" class="form-file" accept=".pdf,.jpg,.jpeg,.png">
					<small class="form-help">Auto Debit Arrangement or Post-Dated Checks</small>
				</div>
				<?php endif; ?>
			</div>

			<!-- Additional Information Section -->
			<div class="form-section">
				<h3><i class="fas fa-info-circle"></i> Additional Information</h3>
				<div class="form-row">
					<div class="form-group">
						<label class="form-label">Purpose of Loan</label>
						<select name="loan_purpose" class="form-select">
							<option value="Personal Use">Personal Use</option>
							<option value="Business Use">Business Use</option>
							<option value="Family Use">Family Use</option>
							<option value="Investment">Investment</option>
						</select>
					</div>
					<div class="form-group">
						<label class="form-label">Preferred Color</label>
						<input type="text" name="preferred_color" class="form-input" placeholder="e.g., White, Black, Silver">
					</div>
				</div>

			</div>

			<div class="action-buttons">
				<a href="loan_requirements.php?vehicle_id=<?php echo $vehicle_id; ?>" class="btn btn-back">
					<i class="fas fa-arrow-left"></i> Back to Requirements
				</a>
				<button type="submit" class="btn btn-submit">
					<i class="fas fa-paper-plane"></i> Submit Application
				</button>
			</div>
		</form>
	</div>

	<script>
		const vehiclePrice = <?php echo $vehicle['base_price']; ?>;
		const minDownPayment = vehiclePrice * 0.2;

		// Auto-calculate age from date of birth
		document.querySelector('input[name="date_of_birth"]').addEventListener('change', function() {
			const birthDate = new Date(this.value);
			const today = new Date();
			const age = Math.floor((today - birthDate) / (365.25 * 24 * 60 * 60 * 1000));
			document.querySelector('input[name="age"]').value = age;
		});

		// Set minimum down payment
		document.getElementById('downPayment').min = minDownPayment;
		document.getElementById('downPayment').value = minDownPayment;

		// Payment calculation functions
		async function calculatePayment() {
			console.log('calculatePayment called');
			const downPayment = parseFloat(document.getElementById('downPayment').value) || 0;
			const selectedTerm = document.querySelector('input[name="financing_term"]:checked');
			
			console.log('Payment calculation inputs:', {
				downPayment: downPayment,
				minDownPayment: minDownPayment,
				selectedTerm: selectedTerm ? selectedTerm.value : 'none'
			});
			
			if (!selectedTerm || downPayment < minDownPayment) {
				console.log('Payment calculation skipped - missing term or insufficient down payment');
				document.getElementById('paymentSummary').style.display = 'none';
				return;
			}

			const months = parseInt(selectedTerm.value);
			const annualRate = parseFloat(selectedTerm.dataset.rate);
			const amountToFinance = vehiclePrice - downPayment;

			try {
				// Use centralized payment calculator API
				const response = await fetch('../includes/payment_calculator.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({
						action: 'calculate',
						vehicle_price: vehiclePrice,
						down_payment: downPayment,
						financing_term: months
					})
				});

				const result = await response.json();

				if (result.success) {
					const monthlyPayment = result.data.monthly_payment;
					const totalPayable = result.data.total_amount;
					const totalInterest = totalPayable - vehiclePrice;

					// Update display
					document.getElementById('displayDownPayment').textContent = '₱' + downPayment.toLocaleString('en-US', {minimumFractionDigits: 2});
					document.getElementById('amountToFinance').textContent = '₱' + amountToFinance.toLocaleString('en-US', {minimumFractionDigits: 2});
					document.getElementById('interestRate').textContent = annualRate + '% Annual';
					document.getElementById('totalInterest').textContent = '₱' + totalInterest.toLocaleString('en-US', {minimumFractionDigits: 2});
					document.getElementById('totalPayable').textContent = '₱' + totalPayable.toLocaleString('en-US', {minimumFractionDigits: 2});
					document.getElementById('monthlyPayment').textContent = '₱' + monthlyPayment.toLocaleString('en-US', {minimumFractionDigits: 2});

					document.getElementById('paymentSummary').style.display = 'block';
				} else {
					throw new Error(result.message || 'Payment calculation failed');
				}
			} catch (error) {
				console.error('Payment calculation error:', error);
				// Fallback to basic calculation
				const monthlyRate = annualRate / 100 / 12;
				const monthlyPayment = amountToFinance * (monthlyRate * Math.pow(1 + monthlyRate, months)) / (Math.pow(1 + monthlyRate, months) - 1);
				const totalPayable = (monthlyPayment * months) + downPayment;
				const totalInterest = totalPayable - vehiclePrice;

				// Update display with fallback calculation
				document.getElementById('displayDownPayment').textContent = '₱' + downPayment.toLocaleString('en-US', {minimumFractionDigits: 2});
				document.getElementById('amountToFinance').textContent = '₱' + amountToFinance.toLocaleString('en-US', {minimumFractionDigits: 2});
				document.getElementById('interestRate').textContent = annualRate + '% Annual';
				document.getElementById('totalInterest').textContent = '₱' + totalInterest.toLocaleString('en-US', {minimumFractionDigits: 2});
				document.getElementById('totalPayable').textContent = '₱' + totalPayable.toLocaleString('en-US', {minimumFractionDigits: 2});
				document.getElementById('monthlyPayment').textContent = '₱' + monthlyPayment.toLocaleString('en-US', {minimumFractionDigits: 2});

				document.getElementById('paymentSummary').style.display = 'block';
			}
		}

		// Event listeners for payment calculation
		document.getElementById('downPayment').addEventListener('input', async function() {
			console.log('Down payment changed:', this.value);
			await calculatePayment();
		});
		
		const financingTerms = document.querySelectorAll('input[name="financing_term"]');
		console.log('Found financing term options:', financingTerms.length);
		
		financingTerms.forEach((radio, index) => {
			console.log(`Setting up listener for term ${index + 1}:`, radio.value);
			radio.addEventListener('change', async function() {
				console.log('Financing term selected:', this.value);
				// Update selected styling
				document.querySelectorAll('.payment-option').forEach(option => {
					option.classList.remove('selected');
				});
				this.closest('.payment-option').classList.add('selected');
				await calculatePayment();
			});
			
			// Also add click listener to the label for better UX
			const label = radio.closest('.payment-option');
			label.addEventListener('click', async function() {
				console.log('Payment option clicked:', radio.value);
				if (!radio.checked) {
					radio.checked = true;
					radio.dispatchEvent(new Event('change'));
				}
			});
		});

		// Form submission
		document.getElementById('loanApplicationForm').addEventListener('submit', async function(e) {
			console.log('🚀 Form submission started');
			e.preventDefault();

			// Validate down payment
			const downPayment = parseFloat(document.getElementById('downPayment').value);
			console.log('💰 Down payment validation:', {
				downPayment: downPayment,
				minDownPayment: minDownPayment,
				isValid: downPayment >= minDownPayment
			});
			
			if (downPayment < minDownPayment) {
				console.error('❌ Down payment validation failed');
				alert(`Down payment must be at least ₱${minDownPayment.toLocaleString('en-US', {minimumFractionDigits: 2})} (20% of vehicle price)`);
				return;
			}

			// Validate financing term selection
			const selectedTerm = document.querySelector('input[name="financing_term"]:checked');
			console.log('📅 Financing term validation:', {
				selectedTerm: selectedTerm,
				value: selectedTerm ? selectedTerm.value : null,
				rate: selectedTerm ? selectedTerm.dataset.rate : null
			});
			
			if (!selectedTerm) {
				console.error('❌ Financing term validation failed');
				alert('Please select a financing term.');
				return;
			}

			// Skipping client-side document requirement validation to allow submission without all documents
			console.log('📁 Skipping document upload requirement validation by design.');

			// Collect form data
			const formData = new FormData(this);
			console.log('📋 Form data collected, entries count:', [...formData.entries()].length);
			
			// Add calculated values using centralized calculator
			const months = parseInt(selectedTerm.value);
			const annualRate = parseFloat(selectedTerm.dataset.rate);
			let calculatedValues; // Declare variable outside try block
			
			try {
				// Use centralized payment calculator API
				const response = await fetch('../includes/payment_calculator.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({
						action: 'calculate',
						vehicle_price: vehiclePrice,
						down_payment: downPayment,
						financing_term: months
					})
				});

				const result = await response.json();

				if (!result.success) {
					throw new Error(result.message || 'Payment calculation failed');
				}

				calculatedValues = {
					monthly_payment: result.data.monthly_payment.toFixed(2),
					total_amount: result.data.total_amount.toFixed(2),
					interest_rate: result.data.interest_rate_percent,
					customer_id: <?php echo $_SESSION['user_id']; ?>
				};
			} catch (error) {
				console.error('Payment calculation error during form submission:', error);
				// Try centralized payment calculator API as fallback
				try {
					const response = await fetch('../includes/payment_calculator.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json'
						},
						body: JSON.stringify({
							action: 'calculate',
							vehicle_price: vehiclePrice,
							down_payment: downPayment,
							financing_term: months
						})
					});
					
					const result = await response.json();
					if (result.success) {
						calculatedValues = {
							monthly_payment: result.data.monthly_payment.toFixed(2),
							total_amount: result.data.total_amount.toFixed(2),
							interest_rate: result.data.interest_rate,
							customer_id: <?php echo $_SESSION['user_id']; ?>
						};
					} else {
						throw new Error('API calculation failed');
					}
				} catch (apiError) {
					console.error('API fallback failed:', apiError);
					// Final fallback to basic calculation
					const monthlyRate = annualRate / 100 / 12;
					const amountToFinance = vehiclePrice - downPayment;
					const monthlyPayment = amountToFinance * (monthlyRate * Math.pow(1 + monthlyRate, months)) / (Math.pow(1 + monthlyRate, months) - 1);
					
					calculatedValues = {
						monthly_payment: monthlyPayment.toFixed(2),
						total_amount: (monthlyPayment * months + downPayment).toFixed(2),
						interest_rate: annualRate,
						customer_id: <?php echo $_SESSION['user_id']; ?>
					};
				}
			}
			
			console.log('🧮 Calculated values:', calculatedValues);
			
			formData.append('monthly_payment', calculatedValues.monthly_payment);
			formData.append('total_amount', calculatedValues.total_amount);
			formData.append('interest_rate', calculatedValues.interest_rate);
			formData.append('customer_id', calculatedValues.customer_id);

			// Show loading
			const submitBtn = this.querySelector('button[type="submit"]');
			const originalText = submitBtn.innerHTML;
			submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
			submitBtn.disabled = true;
			
			console.log('🔄 UI updated to loading state');

			// Submit to backend
			console.log('🌐 Starting API request to submit_loan_with_documents.php');
			
			fetch('../api/submit_loan_with_documents.php', {
				method: 'POST',
				body: formData
			})
			.then(response => {
				console.log('📡 API Response received:', {
					status: response.status,
					statusText: response.statusText,
					ok: response.ok,
					headers: Object.fromEntries(response.headers.entries())
				});
				
				if (!response.ok) {
					console.error('❌ HTTP Error:', response.status, response.statusText);
				}
				
				return response.text().then(text => {
					console.log('📝 Raw response text:', text);
					try {
						return JSON.parse(text);
					} catch (e) {
						console.error('❌ JSON Parse Error:', e);
						console.error('Raw response that failed to parse:', text);
						throw new Error('Invalid JSON response: ' + text.substring(0, 100));
					}
				});
			})
			.then(data => {
				console.log('✅ Parsed response data:', data);
				
				if (data.success) {
					console.log('🎉 Application submitted successfully:', data.application_id);
					// Show success toast instead of alert, then redirect shortly
					(function() {
						const toast = document.createElement('div');
						toast.textContent = 'Loan application submitted successfully! Application ID: ' + data.application_id;
						toast.style.position = 'fixed';
						toast.style.top = '20px';
						toast.style.right = '20px';
						toast.style.backgroundColor = '#28a745';
						toast.style.color = 'white';
						toast.style.padding = '10px 15px';
						toast.style.borderRadius = '4px';
						toast.style.zIndex = '10000';
						toast.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
						document.body.appendChild(toast);
						const redirectUrl = 'my_inquiries.php#loans';
						console.log('🔔 Showing toast and redirecting shortly to:', redirectUrl);
						setTimeout(() => {
							if (toast.parentNode) toast.parentNode.removeChild(toast);
							window.location.href = redirectUrl;
						}, 5000);
					})();
				} else {
					console.error('❌ Application submission failed:', data.message);
					alert('Error submitting application: ' + (data.message || 'Unknown error'));
					submitBtn.innerHTML = originalText;
					submitBtn.disabled = false;
				}
			})
			.catch(error => {
				console.error('💥 Fetch Error:', error);
				console.error('Error details:', {
					name: error.name,
					message: error.message,
					stack: error.stack
				});
				alert('Error submitting application. Please try again.');
				submitBtn.innerHTML = originalText;
				submitBtn.disabled = false;
			});
		});

		// Initialize calculation on page load
		(async () => {
			await calculatePayment();
		})();
	</script>
</body>

</html>