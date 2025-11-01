<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');
include_once(dirname(__DIR__) . '/pages/header_ex.php');

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
	header("Location: login.php");
	exit;
}

// Get vehicle ID from URL
$vehicle_id = $_GET['vehicle_id'] ?? null;
if (!$vehicle_id) {
	header("Location: car_menu.php");
	exit;
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

$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Loan Requirements - Mitsubishi Motors</title>
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

		.user-section {
			font-size: 1.3rem;	
			display: flex;
			align-items: center;
			gap: 20px;
		}

		.container {
			max-width: 1200px;
			margin: 0 auto;
			padding: 40px 30px;
		}

		.page-header {
			text-align: center;
			margin-bottom: 40px;
		}

		.page-title {
			font-size: 2.8rem;
			margin-bottom: 20px;
			background: linear-gradient(45deg, #E60012, #E60012, #E60012);
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

		.requirements-container {
			background: rgba(102, 102, 102, 0.92);
			border-radius: 20px;
			padding: 40px;
			backdrop-filter: blur(20px);
			box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
			border: 1px solid rgba(255, 215, 0, 0.1);
			margin-bottom: 40px;
		}

		.requirements-title {
			text-align: center;
			font-size: 2rem;
			color: #ffd700;
			margin-bottom: 30px;
			font-weight: 700;
		}

		.requirements-subtitle {
			text-align: center;
			margin-bottom: 40px;
			color: #ccc;
			font-size: 1.1rem;
		}

		.loan-requirements {
			background: rgba(0, 0, 0, 0.4);
			border-radius: 15px;
			padding: 30px;
			border-left: 4px solid #ffd700;
		}

		.requirements-header {
			display: flex;
			align-items: center;
			gap: 15px;
			margin-bottom: 30px;
			color: #ffd700;
			font-size: 1.5rem;
			font-weight: 700;
		}

		/* Applicant Type Selector Styles */
		.applicant-type-selector {
			margin-bottom: 30px;
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
		}

		.type-btn:hover {
			background: rgba(255, 215, 0, 0.2);
			transform: translateY(-2px);
			box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
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

		.requirement-item {
			display: flex;
			align-items: flex-start;
			gap: 15px;
			margin-bottom: 25px;
			padding: 20px;
			background: rgba(255, 255, 255, 0.05);
			border-radius: 10px;
			border: 1px solid rgba(255, 215, 0, 0.1);
		}

		.requirement-icon {
			color: #ffd700;
			font-size: 1.5rem;
			margin-top: 5px;
			flex-shrink: 0;
		}

		.requirement-content h4 {
			color: #ffd700;
			margin-bottom: 8px;
			font-size: 1.1rem;
		}

		.requirement-content p {
			color: #ccc;
			line-height: 1.6;
		}

		.requirement-content .note {
			color: #ff6b6b;
			font-style: italic;
			margin-top: 5px;
		}

		.action-buttons {
			display: flex;
			gap: 20px;
			justify-content: center;
			margin-top: 40px;
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

		.btn-proceed {
			background: #E60012;
			color: #ffffff;
			align-text: center;
			box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
		}

		.btn-proceed:hover {
			transform: translateY(-3px);
			box-shadow: 0 8px 25px rgba(255, 215, 0, 0.5);
		}

		.btn-back {
			background: rgba(255, 255, 255, 0.1);
			color: #000000;
			border: 2px solid #000000;
		}

		.btn-back:hover {
			background: #ffd700;
			color: #1a1a1a;
		}

		@media (max-width: 768px) {
			.container {
				padding: 20px 15px;
			}

			.page-title {
				font-size: 2rem;
			}

			.requirements-container {
				padding: 20px;
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
	</style>
</head>

<body>


	<div class="container">
		<div class="page-header">
			<h1 class="page-title">Loan Application Requirements</h1>
		</div>

		<div class="vehicle-info">
			<h3 style="color: #ffd700; margin-bottom: 10px;">Selected Vehicle</h3>
			<h2><?php echo htmlspecialchars($vehicle['model_name']); ?></h2>
			<?php if (!empty($vehicle['variant'])): ?>
				<p style="color: #ccc;"><?php echo htmlspecialchars($vehicle['variant']); ?></p>
			<?php endif; ?>
			<?php if ($vehicle['base_price']): ?>
				<p style="color: #ffd700; font-size: 1.2rem; font-weight: bold; margin-top: 10px;">
					₱<?php echo number_format($vehicle['base_price'], 2); ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="requirements-container">
			<h2 class="requirements-title">Loan Application Requirements</h2>
			<p class="requirements-subtitle">Please select your applicant type to view the specific requirements for your loan application.</p>

			<!-- Applicant Type Selection -->
			<div class="applicant-type-selector">
				<h3 style="color: #ffd700; margin-bottom: 20px;">Select Your Applicant Type:</h3>
				<div class="type-buttons">
					<button class="type-btn active" data-type="EMPLOYED">
						<i class="fas fa-briefcase"></i>
						<span>EMPLOYED</span>
					</button>
					<button class="type-btn" data-type="BUSINESS">
						<i class="fas fa-store"></i>
						<span>BUSINESS</span>
					</button>
					<button class="type-btn" data-type="OFW">
						<i class="fas fa-globe"></i>
						<span>OFW</span>
					</button>
				</div>
			</div>

			<!-- Requirements for EMPLOYED -->
			<div class="loan-requirements" id="requirements-EMPLOYED">
				<div class="requirements-header">
					<i class="fas fa-briefcase"></i>
					<span>Requirements for EMPLOYED Applicants</span>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-id-card"></i></div>
					<div class="requirement-content">
						<h4>2 Valid IDs (Gov't Issued)</h4>
						<p>Two (2) government-issued IDs with photos and signatures</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-certificate"></i></div>
					<div class="requirement-content">
						<h4>COEC or 3 Months Latest Payslip</h4>
						<p>Certificate of Employment and Compensation OR Latest 3 months payslips</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-file-invoice"></i></div>
					<div class="requirement-content">
						<h4>ITR (2316)</h4>
						<p>Income Tax Return form 2316 for employed individuals</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-receipt"></i></div>
					<div class="requirement-content">
						<h4>Proof of Billing (Original)</h4>
						<p>Original utility bills or other proof of billing documents</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-money-check"></i></div>
					<div class="requirement-content">
						<h4>ADA/PDC</h4>
						<p>Authorized Dealer Agreement / Post Dated Checks</p>
					</div>
				</div>
			</div>

			<!-- Requirements for BUSINESS -->
			<div class="loan-requirements" id="requirements-BUSINESS" style="display: none;">
				<div class="requirements-header">
					<i class="fas fa-store"></i>
					<span>Requirements for BUSINESS Applicants</span>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-id-card"></i></div>
					<div class="requirement-content">
						<h4>2 Valid IDs (Gov't Issued)</h4>
						<p>Two (2) government-issued IDs with photos and signatures</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-university"></i></div>
					<div class="requirement-content">
						<h4>Bank Statement (Latest 3 Months)</h4>
						<p>Latest 3 months bank statements for business account</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-file-invoice"></i></div>
					<div class="requirement-content">
						<h4>ITR (1701)</h4>
						<p>Income Tax Return form 1701 for business owners</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-stamp"></i></div>
					<div class="requirement-content">
						<h4>DTI Permit</h4>
						<p>Department of Trade and Industry business permit</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-receipt"></i></div>
					<div class="requirement-content">
						<h4>Proof of Billing (Original)</h4>
						<p>Original utility bills or other proof of billing documents</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-money-check"></i></div>
					<div class="requirement-content">
						<h4>ADA/PDC</h4>
						<p>Authorized Dealer Agreement / Post Dated Checks</p>
					</div>
				</div>
			</div>

			<!-- Requirements for OFW -->
			<div class="loan-requirements" id="requirements-OFW" style="display: none;">
				<div class="requirements-header">
					<i class="fas fa-globe"></i>
					<span>Requirements for OFW Applicants</span>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-id-card"></i></div>
					<div class="requirement-content">
						<h4>2 Valid IDs (Gov't Issued)</h4>
						<p>Two (2) government-issued IDs with photos and signatures</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-money-bill-transfer"></i></div>
					<div class="requirement-content">
						<h4>Proof of Remittance (Latest 3 Months)</h4>
						<p>Latest 3 months proof of money remittance to Philippines</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-file-contract"></i></div>
					<div class="requirement-content">
						<h4>Latest Contract</h4>
						<p>Current overseas employment contract</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-file-signature"></i></div>
					<div class="requirement-content">
						<h4>SPA</h4>
						<p>Special Power of Attorney for authorized representative</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-receipt"></i></div>
					<div class="requirement-content">
						<h4>Proof of Billing (Original)</h4>
						<p>Original utility bills or other proof of billing documents</p>
					</div>
				</div>

				<div class="requirement-item">
					<div class="requirement-icon"><i class="fas fa-money-check"></i></div>
					<div class="requirement-content">
						<h4>ADA/PDC</h4>
						<p>Authorized Dealer Agreement / Post Dated Checks</p>
					</div>
				</div>
			</div>

			<div style="margin-top: 30px; padding: 20px; background: rgba(255, 215, 0, 0.1); border-radius: 15px; border: 1px solid rgba(255, 215, 0, 0.3);">
				<h3 style="color: #ffd700; margin-bottom: 15px;"><i class="fas fa-info-circle"></i> Additional Information</h3>
				<p style="color: #ccc; margin-bottom: 10px;"><strong>FREE 5 YEARS EXTENDED WARRANTY FOR TRITON & XFORCE</strong></p>
				<p style="color: #ccc; margin-bottom: 10px;">*CONDITION MAY APPLY FOR SFM SAN PABLO AND SFM LIPA ONLY</p>
				<p style="color: #ccc; margin-bottom: 10px;"><strong>OPEN FOR:</strong></p>
				<p style="color: #ccc; margin-bottom: 10px;">CASH/FINANCING/COMPANY PO/BANK PO/TRANSFER APPROVAL FROM OTHER BANK/DEALER</p>
				<p style="color: #ffd700; font-weight: bold;"><strong>BANK ACCREDITED:</strong> MMFP - MITSUBISHI FINANCING</p>
			</div>
		</div>

		<div class="action-buttons">
			<a href="car_details.php?id=<?php echo $vehicle_id; ?>" class="btn btn-back">
				<i class="fas fa-arrow-left"></i> Back to Vehicle
			</a>
			<a href="#" id="proceedBtn" class="btn btn-proceed">
				<i class="fas fa-arrow-right"></i> Proceed to Loan Form
			</a>
		</div>
	</div>
<script>
		// JavaScript for applicant type selector
		document.addEventListener('DOMContentLoaded', function() {
			const typeButtons = document.querySelectorAll('.type-btn');
			const requirementSections = document.querySelectorAll('.loan-requirements');

			typeButtons.forEach(button => {
				button.addEventListener('click', function() {
					// Remove active class from all buttons
					typeButtons.forEach(btn => btn.classList.remove('active'));
					// Add active class to clicked button
					this.classList.add('active');

					// Hide all requirement sections
					requirementSections.forEach(section => {
						section.style.display = 'none';
					});

					// Show the selected requirement section
					const selectedType = this.getAttribute('data-type');
					const targetSection = document.getElementById('requirements-' + selectedType);
					if (targetSection) {
						targetSection.style.display = 'block';
					}
				});
			});

			// Handle proceed button click
			document.getElementById('proceedBtn').addEventListener('click', function(e) {
				e.preventDefault();
				const activeButton = document.querySelector('.type-btn.active');
				const selectedType = activeButton ? activeButton.getAttribute('data-type') : 'EMPLOYED';
				window.location.href = `loan_excel_form.php?vehicle_id=<?php echo $vehicle_id; ?>&applicant_type=${selectedType}`;
			});
		});
	</script>
</body>

</html>