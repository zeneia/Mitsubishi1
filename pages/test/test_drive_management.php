<?php
include_once('../../includes/init.php');

// Check if user is logged in and has proper role
if (!isLoggedIn() || !hasRole('SalesAgent')) {
	header('Location: ../login.php');
	exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	header('Content-Type: application/json');

	try {
		switch ($_POST['action']) {
			case 'get_pending_requests':
				$stmt = $pdo->prepare("
                    SELECT tdr.*, a.FirstName, a.LastName, a.Email 
                    FROM test_drive_requests tdr 
                    LEFT JOIN accounts a ON tdr.account_id = a.Id 
                    WHERE tdr.status = 'Pending' 
                    ORDER BY tdr.requested_at DESC
                ");
				$stmt->execute();
				$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
				echo json_encode(['success' => true, 'data' => $results]);
				break;

			case 'get_approved_requests':
				$stmt = $pdo->prepare("
                    SELECT tdr.*, a.FirstName, a.LastName, a.Email 
                    FROM test_drive_requests tdr 
                    LEFT JOIN accounts a ON tdr.account_id = a.Id 
                    WHERE tdr.status = 'Approved' 
                    ORDER BY tdr.selected_date ASC
                ");
				$stmt->execute();
				$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
				echo json_encode(['success' => true, 'data' => $results]);
				break;

			case 'get_completed_requests':
				$stmt = $pdo->prepare("
                    SELECT tdr.*, a.FirstName, a.LastName, a.Email 
                    FROM test_drive_requests tdr 
                    LEFT JOIN accounts a ON tdr.account_id = a.Id 
                    WHERE tdr.status = 'Completed' 
                    ORDER BY tdr.approved_at DESC
                ");
				$stmt->execute();
				$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
				echo json_encode(['success' => true, 'data' => $results]);
				break;

			case 'approve_request':
				$request_id = $_POST['request_id'];
				$instructor = $_POST['instructor'] ?? '';
				$notes = $_POST['notes'] ?? '';

				// Generate gate pass number
				$gate_pass_number = 'MAG-' . strtoupper(substr(md5(time() . $request_id), 0, 8));

				$stmt = $pdo->prepare("
                    UPDATE test_drive_requests
                    SET status = 'Approved',
                        approved_at = NOW(),
                        approved_by = ?,
                        instructor_agent = ?,
                        notes = ?,
                        gate_pass_number = ?,
                        gatepass_generated_at = NOW()
                    WHERE id = ?
                ");
				$stmt->execute([$_SESSION['user_id'], $instructor, $notes, $gate_pass_number, $request_id]);

				// --- Notification Logic (In-app) ---
				require_once '../../includes/api/notification_api.php';
				$stmt2 = $pdo->prepare("SELECT account_id, selected_date FROM test_drive_requests WHERE id = ?");
				$stmt2->execute([$request_id]);
				$row = $stmt2->fetch(PDO::FETCH_ASSOC);
				if ($row && $row['account_id']) {
					createNotification($row['account_id'], null, 'Test Drive Approved', 'Your test drive request (ID: ' . $request_id . ') has been approved for ' . $row['selected_date'] . '.', 'test_drive', $request_id);
				}
				createNotification(null, 'Admin', 'Test Drive Approved', 'Test drive request (ID: ' . $request_id . ') has been approved.', 'test_drive', $request_id);
				// --- End Notification Logic ---

				// Send email and SMS notifications
				try {
					require_once '../../includes/services/NotificationService.php';
					$notificationService = new NotificationService($pdo);
					$notificationService->sendTestDriveApprovalNotification($request_id);
				} catch (Exception $notifError) {
					// Log error but don't fail the approval
					error_log("Test drive approval notification error: " . $notifError->getMessage());
				}

				echo json_encode(['success' => true, 'message' => 'Request approved successfully']);
				break;

			case 'reject_request':
				$request_id = $_POST['request_id'];
				$notes = $_POST['notes'] ?? '';

				$stmt = $pdo->prepare("
                    UPDATE test_drive_requests
                    SET status = 'Rejected',
                        notes = ?
                    WHERE id = ?
                ");
				$stmt->execute([$notes, $request_id]);

				// Send email and SMS notifications
				try {
					require_once '../../includes/services/NotificationService.php';
					$notificationService = new NotificationService($pdo);
					$notificationService->sendTestDriveRejectionNotification($request_id, $notes);
				} catch (Exception $notifError) {
					// Log error but don't fail the rejection
					error_log("Test drive rejection notification error: " . $notifError->getMessage());
				}

				echo json_encode(['success' => true, 'message' => 'Request rejected']);
				break;

			case 'complete_request':
				$request_id = $_POST['request_id'];
				$completion_notes = $_POST['completion_notes'] ?? '';

				$stmt = $pdo->prepare("
                    UPDATE test_drive_requests 
                    SET status = 'Completed', 
                        notes = CONCAT(COALESCE(notes, ''), '\nCompleted: ', ?) 
                    WHERE id = ?
                ");
				$stmt->execute([$completion_notes, $request_id]);
				echo json_encode(['success' => true, 'message' => 'Test drive marked as completed']);
				break;

			case 'get_rejected_requests':
				$stmt = $pdo->prepare("
                    SELECT tdr.*, a.FirstName, a.LastName, a.Email 
                    FROM test_drive_requests tdr 
                    LEFT JOIN accounts a ON tdr.account_id = a.Id 
                    WHERE tdr.status = 'Rejected' 
                    ORDER BY tdr.requested_at DESC
                ");
				$stmt->execute();
				$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
				echo json_encode(['success' => true, 'data' => $results]);
				break;

			case 'undo_rejection':
				$request_id = $_POST['request_id'];

				$stmt = $pdo->prepare("
                    UPDATE test_drive_requests 
                    SET status = 'Pending', 
                        notes = CONCAT(COALESCE(notes, ''), '\nRejection undone at ', NOW()) 
                    WHERE id = ? AND status = 'Rejected'
                ");
				$stmt->execute([$request_id]);

				if ($stmt->rowCount() > 0) {
					// --- Notification Logic ---
					require_once '../../includes/api/notification_api.php';
					$stmt2 = $pdo->prepare("SELECT account_id, selected_date FROM test_drive_requests WHERE id = ?");
					$stmt2->execute([$request_id]);
					$row = $stmt2->fetch(PDO::FETCH_ASSOC);
					if ($row && $row['account_id']) {
						createNotification($row['account_id'], null, 'Test Drive Restored', 'Your test drive request (ID: ' . $request_id . ') has been restored and is now pending review.', 'test_drive', $request_id);
					}
					createNotification(null, 'Admin', 'Test Drive Restored', 'Test drive request (ID: ' . $request_id . ') rejection has been undone.', 'test_drive', $request_id);
					// --- End Notification Logic ---

					echo json_encode(['success' => true, 'message' => 'Rejection undone successfully. Request is now pending.']);
				} else {
					echo json_encode(['success' => false, 'message' => 'Request not found or not rejected']);
				}
				break;

			default:
				echo json_encode(['success' => false, 'message' => 'Invalid action']);
		}
	} catch (Exception $e) {
		echo json_encode(['success' => false, 'message' => $e->getMessage()]);
	}
	exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Test Drive Management - Testing</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="../../includes/css/common-styles.css" rel="stylesheet">
	<link href="../../includes/css/dashboard-styles.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
	<div class="container" style="padding: 20px;">
		<h1>Test Drive Management - Testing Page</h1>

		<div class="interface-container" style="display: block;">
			<div class="interface-header">
				<h2 class="interface-title">
					<i class="fas fa-car"></i>
					Test Drive Booking Review
				</h2>
			</div>

			<div class="tab-navigation">
				<button class="tab-button active" data-tab="testDrive-pending">Pending Requests</button>
				<button class="tab-button" data-tab="testDrive-approved">Approved Bookings</button>
				<button class="tab-button" data-tab="testDrive-completed">Completed Drives</button>
				<button class="tab-button" data-tab="testDrive-rejected">Rejected Bookings</button>
			</div>

			<div class="tab-content active" id="testDrive-pending">
				<div class="info-cards" id="testDriveStats">
					<div class="info-card">
						<div class="info-card-title">Pending Reviews</div>
						<div class="info-card-value" id="pendingCount">-</div>
					</div>
					<div class="info-card">
						<div class="info-card-title">Urgent Requests</div>
						<div class="info-card-value" id="urgentCount">-</div>
					</div>
					<div class="info-card">
						<div class="info-card-title">Today's Requests</div>
						<div class="info-card-value" id="todayCount">-</div>
					</div>
				</div>

				<div class="table-container">
					<table class="data-table">
						<thead>
							<tr>
								<th>Request ID</th>
								<th>Customer</th>
								<th>Contact</th>
								<th>Preferred Date</th>
								<th>Time Slot</th>
								<th>Priority</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody id="pendingRequestsTable">
							<tr>
								<td colspan="7" class="text-center">Loading...</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<div class="tab-content" id="testDrive-approved">
				<div class="table-container">
					<table class="data-table">
						<thead>
							<tr>
								<th>Request ID</th>
								<th>Customer</th>
								<th>Date & Time</th>
								<th>Location</th>
								<th>Instructor</th>
								<th>Approved Date</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody id="approvedRequestsTable">
							<tr>
								<td colspan="7" class="text-center">Loading...</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<div class="tab-content" id="testDrive-completed">
				<div class="table-container">
					<table class="data-table">
						<thead>
							<tr>
								<th>Request ID</th>
								<th>Customer</th>
								<th>Date Completed</th>
								<th>Location</th>
								<th>Instructor</th>
								<th>Notes</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody id="completedRequestsTable">
							<tr>
								<td colspan="7" class="text-center">Loading...</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<div class="tab-content" id="testDrive-rejected">
				<div class="table-container">
					<table class="data-table">
						<thead>
							<tr>
								<th>Request ID</th>
								<th>Customer</th>
								<th>Contact</th>
								<th>Preferred Date</th>
								<th>Time Slot</th>
								<th>Rejection Notes</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody id="rejectedRequestsTable">
							<tr>
								<td colspan="7" class="text-center">Loading...</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<script src="../../includes/js/common-scripts.js"></script>
	<script>
		// Test Drive Management JavaScript
		document.addEventListener('DOMContentLoaded', function() {
			// Tab switching functionality
			document.querySelectorAll('.tab-button').forEach(function(button) {
				button.addEventListener('click', function() {
					// Remove active class from all buttons and content
					document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
					document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

					// Add active class to clicked button and corresponding content
					this.classList.add('active');
					const tabId = this.getAttribute('data-tab');
					document.getElementById(tabId).classList.add('active');

					// Load data for the active tab
					loadTabData(tabId);
				});
			});

			// Load initial data
			loadTabData('testDrive-pending');
		});

		function loadTabData(tabId) {
			let action = '';
			let tableId = '';

			switch (tabId) {
				case 'testDrive-pending':
					action = 'get_pending_requests';
					tableId = 'pendingRequestsTable';
					break;
				case 'testDrive-approved':
					action = 'get_approved_requests';
					tableId = 'approvedRequestsTable';
					break;
				case 'testDrive-completed':
					action = 'get_completed_requests';
					tableId = 'completedRequestsTable';
					break;
				case 'testDrive-rejected':
					action = 'get_rejected_requests';
					tableId = 'rejectedRequestsTable';
					break;
			}

			fetch('', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: `action=${action}`
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						renderTableData(tableId, data.data, tabId);
						updateStats(data.data, tabId);
					} else {
						console.error('Error loading data:', data.message);
					}
				})
				.catch(error => {
					console.error('Fetch error:', error);
				});
		}

		function renderTableData(tableId, data, tabType) {
			const tbody = document.getElementById(tableId);

			if (data.length === 0) {
				tbody.innerHTML = '<tr><td colspan="7" class="text-center">No records found</td></tr>';
				return;
			}

			let html = '';
			data.forEach(item => {
				html += renderTableRow(item, tabType);
			});
			tbody.innerHTML = html;
		}

		function renderTableRow(item, tabType) {
			const customerName = item.FirstName && item.LastName ?
				`${item.FirstName} ${item.LastName}` : item.customer_name;

			switch (tabType) {
				case 'testDrive-pending':
					const priority = getPriority(item.selected_date);
					return `
                        <tr>
                            <td>TD-${item.id.toString().padStart(4, '0')}</td>
                            <td>${customerName}<br><small>${item.Email || 'N/A'}</small></td>
                            <td>${item.mobile_number}</td>
                            <td>${formatDate(item.selected_date)}</td>
                            <td>${item.selected_time_slot}</td>
                            <td><span class="status ${priority.class}">${priority.label}</span></td>
                            <td class="table-actions">
                                <button class="btn btn-small btn-primary" onclick="reviewRequest(${item.id})">Review</button>
                            </td>
                        </tr>
                    `;

				case 'testDrive-approved':
					return `
                        <tr>
                            <td>TD-${item.id.toString().padStart(4, '0')}</td>
                            <td>${customerName}</td>
                            <td>${formatDate(item.selected_date)} ${item.selected_time_slot}</td>
                            <td>${item.test_drive_location}</td>
                            <td>${item.instructor_agent || 'Not assigned'}</td>
                            <td>${formatDate(item.approved_at)}</td>
                            <td class="table-actions">
                                <button class="btn btn-small btn-success" onclick="markComplete(${item.id})">Mark Complete</button>
                            </td>
                        </tr>
                    `;

				case 'testDrive-completed':
				return `
                        <tr>
                            <td>TD-${item.id.toString().padStart(4, '0')}</td>
                            <td>${customerName}</td>
                            <td>${formatDate(item.selected_date)}</td>
                            <td>${item.test_drive_location}</td>
                            <td>${item.instructor_agent || 'N/A'}</td>
                            <td>${item.notes ? item.notes.substring(0, 50) + '...' : 'No notes'}</td>
                            <td class="table-actions">
                                <button class="btn btn-small btn-outline" onclick="viewDetails(${item.id})">View Details</button>
                            </td>
                        </tr>
                    `;

			case 'testDrive-rejected':
				return `
                        <tr>
                            <td>TD-${item.id.toString().padStart(4, '0')}</td>
                            <td>${customerName}<br><small>${item.Email || 'N/A'}</small></td>
                            <td>${item.mobile_number}</td>
                            <td>${formatDate(item.selected_date)}</td>
                            <td>${item.selected_time_slot}</td>
                            <td>${item.notes ? item.notes.substring(0, 50) + '...' : 'No reason provided'}</td>
                            <td class="table-actions">
                                <button class="btn btn-small btn-warning" onclick="undoRejection(${item.id})">Undo Rejection</button>
                            </td>
                        </tr>
                    `;
		}
		}

		function updateStats(data, tabType) {
			if (tabType === 'testDrive-pending') {
				const today = new Date().toISOString().split('T')[0];
				const urgent = data.filter(item => {
					const daysDiff = Math.ceil((new Date(item.selected_date) - new Date()) / (1000 * 60 * 60 * 24));
					return daysDiff <= 2;
				}).length;
				const todayRequests = data.filter(item => item.requested_at.split(' ')[0] === today).length;

				document.getElementById('pendingCount').textContent = data.length;
				document.getElementById('urgentCount').textContent = urgent;
				document.getElementById('todayCount').textContent = todayRequests;
			}
		}

		function getPriority(selectedDate) {
			const daysDiff = Math.ceil((new Date(selectedDate) - new Date()) / (1000 * 60 * 60 * 24));

			if (daysDiff < 0) return {
				label: 'Overdue',
				class: 'overdue'
			};
			if (daysDiff <= 1) return {
				label: 'Urgent',
				class: 'urgent'
			};
			if (daysDiff <= 3) return {
				label: 'High',
				class: 'high'
			};
			return {
				label: 'Normal',
				class: 'normal'
			};
		}

		function formatDate(dateString) {
			if (!dateString) return 'N/A';
			return new Date(dateString).toLocaleDateString('en-US', {
				year: 'numeric',
				month: 'short',
				day: 'numeric'
			});
		}

		function reviewRequest(requestId) {
			Swal.fire({
				title: 'Review Test Drive Request',
				html: `
                    <div style="text-align: left;">
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Action:</label>
                            <select id="reviewAction" class="swal2-select" style="width: 100%;">
                                <option value="">Select action</option>
                                <option value="approve">Approve Request</option>
                                <option value="reject">Reject Request</option>
                            </select>
                        </div>
                        <div id="instructorField" style="margin-bottom: 15px; display: none;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Assign Instructor:</label>
                            <input id="instructor" type="text" class="swal2-input" placeholder="Enter instructor name" style="margin: 0;">
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Notes:</label>
                            <textarea id="reviewNotes" class="swal2-textarea" placeholder="Add any notes or comments" style="margin: 0;"></textarea>
                        </div>
                    </div>
                `,
				showCancelButton: true,
				confirmButtonText: 'Submit',
				cancelButtonText: 'Cancel',
				preConfirm: () => {
					const action = document.getElementById('reviewAction').value;
					const instructor = document.getElementById('instructor').value;
					const notes = document.getElementById('reviewNotes').value;

					if (!action) {
						Swal.showValidationMessage('Please select an action');
						return false;
					}

					return {
						action,
						instructor,
						notes
					};
				}
			}).then((result) => {
				if (result.isConfirmed) {
					const {
						action,
						instructor,
						notes
					} = result.value;

					const formData = new FormData();
					formData.append('action', action === 'approve' ? 'approve_request' : 'reject_request');
					formData.append('request_id', requestId);
					formData.append('notes', notes);
					if (action === 'approve') {
						formData.append('instructor', instructor);
					}

					fetch('', {
							method: 'POST',
							body: formData
						})
						.then(response => response.json())
						.then(data => {
							if (data.success) {
								Swal.fire('Success!', data.message, 'success');
								loadTabData('testDrive-pending');
							} else {
								Swal.fire('Error!', data.message, 'error');
							}
						})
						.catch(error => {
							Swal.fire('Error!', 'An error occurred while processing the request', 'error');
						});
				}
			});

			// Show/hide instructor field based on action
			document.getElementById('reviewAction').addEventListener('change', function() {
				const instructorField = document.getElementById('instructorField');
				if (this.value === 'approve') {
					instructorField.style.display = 'block';
				} else {
					instructorField.style.display = 'none';
				}
			});
		}

		function markComplete(requestId) {
			Swal.fire({
				title: 'Mark Test Drive as Completed',
				html: `
                    <div style="text-align: left;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Completion Notes:</label>
                        <textarea id="completionNotes" class="swal2-textarea" placeholder="Add completion notes, feedback, or observations"></textarea>
                    </div>
                `,
				showCancelButton: true,
				confirmButtonText: 'Mark Complete',
				cancelButtonText: 'Cancel',
				preConfirm: () => {
					return document.getElementById('completionNotes').value;
				}
			}).then((result) => {
				if (result.isConfirmed) {
					const formData = new FormData();
					formData.append('action', 'complete_request');
					formData.append('request_id', requestId);
					formData.append('completion_notes', result.value);

					fetch('', {
							method: 'POST',
							body: formData
						})
						.then(response => response.json())
						.then(data => {
							if (data.success) {
								Swal.fire('Success!', data.message, 'success');
								loadTabData('testDrive-approved');
							} else {
								Swal.fire('Error!', data.message, 'error');
							}
						})
						.catch(error => {
							Swal.fire('Error!', 'An error occurred while processing the request', 'error');
						});
				}
			});
		}

		function viewDetails(requestId) {
			// This will show detailed information about the completed test drive
			Swal.fire('Details', 'Detailed view functionality will be implemented next', 'info');
		}

		function undoRejection(requestId) {
			Swal.fire({
				title: 'Undo Rejection',
				text: 'Are you sure you want to undo the rejection for this test drive request? It will be moved back to pending status.',
				icon: 'question',
				showCancelButton: true,
				confirmButtonText: 'Yes, Undo Rejection',
				cancelButtonText: 'Cancel',
				confirmButtonColor: '#f39c12',
				cancelButtonColor: '#d33'
			}).then((result) => {
				if (result.isConfirmed) {
					const formData = new FormData();
					formData.append('action', 'undo_rejection');
					formData.append('request_id', requestId);

					fetch('', {
							method: 'POST',
							body: formData
						})
						.then(response => response.json())
						.then(data => {
							if (data.success) {
								Swal.fire('Success!', data.message, 'success');
								// Refresh both rejected and pending tabs
								loadTabData('testDrive-rejected');
								// Switch to pending tab to show the restored request
								document.querySelector('[data-tab="testDrive-pending"]').click();
							} else {
								Swal.fire('Error!', data.message, 'error');
							}
						})
						.catch(error => {
							Swal.fire('Error!', 'An error occurred while processing the request', 'error');
						});
				}
			});
		}
	</script>
</body>

</html>