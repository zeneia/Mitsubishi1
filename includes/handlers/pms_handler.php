<?php
require_once dirname(__DIR__) . '/database/db_conn.php';

class PMSHandler {
    private $pdo;
    
    public function __construct($pdo = null) {
        global $connect;
        $this->pdo = $pdo ?: $connect;
    }
    
    /**
     * Get PMS statistics for dashboard
     */
    public function getPMSStatistics() {
        try {
            $stats = [];
            
            // Total PMS sessions completed
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM car_pms_records WHERE request_status = 'Completed'");
            $stmt->execute();
            $stats['total_completed'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // PMS sessions this month
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as this_month FROM car_pms_records WHERE request_status = 'Completed' AND MONTH(pms_date) = MONTH(CURDATE()) AND YEAR(pms_date) = YEAR(CURDATE())");
            $stmt->execute();
            $stats['this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['this_month'];
            
            // Active clients with PMS
            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT customer_id) as active_clients FROM car_pms_records WHERE request_status IN ('Completed', 'Scheduled', 'Approved')");
            $stmt->execute();
            $stats['active_clients'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_clients'];
            
            // Average service duration (placeholder calculation)
            $stats['avg_duration'] = 2.1; // This would need actual time tracking
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error getting PMS statistics: " . $e->getMessage());
            return ['total_completed' => 0, 'this_month' => 0, 'active_clients' => 0, 'avg_duration' => 0];
        }
    }
    
    /**
     * Get all PMS records with customer information
     */
    public function getAllPMSRecords($filters = []) {
        try {
            // Set a reasonable timeout for the query
            $this->pdo->setAttribute(PDO::ATTR_TIMEOUT, 30);

            $query = "SELECT
                        p.pms_id,
                        p.customer_id,
                        p.plate_number,
                        p.model,
                        p.transmission,
                        p.engine_type,
                        p.color,
                        p.current_odometer,
                        p.pms_info,
                        p.pms_date,
                        p.next_pms_due,
                        p.request_status,
                        p.scheduled_date,
                        p.approved_by,
                        p.approved_at,
                        p.created_at,
                        p.updated_at,
                        p.service_notes_findings,
                        IF(p.uploaded_receipt IS NOT NULL, 1, 0) as has_receipt,
                        c.firstname,
                        c.lastname,
                        c.middlename,
                        c.mobile_number,
                        c.Status as customer_status,
                        TRIM(CONCAT(IFNULL(c.firstname, ''), ' ', IFNULL(c.middlename, ''), ' ', IFNULL(c.lastname, ''))) as full_name,
                        a.Email,
                        CONCAT(COALESCE(approver.FirstName, ''), ' ', COALESCE(approver.LastName, '')) as approved_by_name
                      FROM car_pms_records p
                      LEFT JOIN customer_information c ON p.customer_id = c.account_id
                      LEFT JOIN accounts a ON c.account_id = a.Id
                      LEFT JOIN accounts approver ON p.approved_by = approver.Id";

            $whereConditions = [];
            $params = [];

            // Apply filters
            if (!empty($filters['customer_search'])) {
                // Trim and clean the search input to handle pasted text with hidden characters
                $searchTerm = trim($filters['customer_search']);
                // Remove any line breaks, tabs, and extra whitespace
                $searchTerm = preg_replace('/\s+/', ' ', $searchTerm);

                // Split search term into words for multi-word search
                $searchWords = explode(' ', $searchTerm);
                $searchConditions = [];

                foreach ($searchWords as $index => $word) {
                    if (!empty(trim($word))) {
                        $paramKey = 'search_' . $index;
                        $searchConditions[] = "(c.firstname LIKE :{$paramKey} OR c.lastname LIKE :{$paramKey} OR p.plate_number LIKE :{$paramKey} OR p.model LIKE :{$paramKey} OR CONCAT(c.firstname, ' ', c.lastname) LIKE :{$paramKey})";
                        $params[$paramKey] = '%' . trim($word) . '%';
                    }
                }

                if (!empty($searchConditions)) {
                    $whereConditions[] = '(' . implode(' AND ', $searchConditions) . ')';
                }
            }

            // Odometer range filter (e.g., 20000 filters 20000-25000)
            if (!empty($filters['odometer_filter'])) {
                $odometerValue = intval($filters['odometer_filter']);
                if ($odometerValue > 0) {
                    $whereConditions[] = "p.current_odometer >= :odometer_min AND p.current_odometer <= :odometer_max";
                    $params['odometer_min'] = $odometerValue;
                    $params['odometer_max'] = $odometerValue + 5000;
                }
            }

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $whereConditions[] = "p.request_status = :status";
                $params['status'] = $filters['status'];
            }

            if (!empty($filters['completion_period']) && $filters['completion_period'] !== 'all') {
                switch ($filters['completion_period']) {
                    case 'last7days':
                        $whereConditions[] = "p.pms_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                        break;
                    case 'last30days':
                        $whereConditions[] = "p.pms_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                        break;
                    case 'last3months':
                        $whereConditions[] = "p.pms_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                        break;
                    case 'last6months':
                        $whereConditions[] = "p.pms_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                        break;
                    case 'thisyear':
                        $whereConditions[] = "YEAR(p.pms_date) = YEAR(CURDATE())";
                        break;
                }
            }

            if (!empty($whereConditions)) {
                $query .= " WHERE " . implode(" AND ", $whereConditions);
            }

            $query .= " ORDER BY p.created_at DESC LIMIT 1000";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Ensure all required fields exist with default values
            foreach ($results as &$record) {
                $record['approved_at'] = $record['approved_at'] ?? null;

                // Handle approved_by_name - check if it's empty or just whitespace
                $approvedByName = trim($record['approved_by_name'] ?? '');
                $record['approved_by_name'] = !empty($approvedByName) ? $approvedByName : 'N/A';

                // Handle full_name - check if it's empty or just whitespace
                $fullName = trim($record['full_name'] ?? '');
                $record['full_name'] = !empty($fullName) ? $fullName : 'N/A';

                $record['mobile_number'] = $record['mobile_number'] ?? 'N/A';
                $record['has_receipt'] = $record['has_receipt'] ?? 0;
                $record['firstname'] = $record['firstname'] ?? '';
                $record['lastname'] = $record['lastname'] ?? '';
                $record['middlename'] = $record['middlename'] ?? '';
                $record['Email'] = $record['Email'] ?? '';
            }

            return $results;
        } catch (PDOException $e) {
            error_log("Error getting PMS records: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            return [];
        }
    }
    
    /**
     * Get PMS history for a specific customer
     */
    public function getCustomerPMSHistory($customerId) {
        try {
            $query = "SELECT
                        p.*,
                        CASE WHEN p.uploaded_receipt IS NOT NULL THEN 1 ELSE 0 END as has_receipt,
                        COALESCE(
                            sa.display_name,
                            CONCAT(COALESCE(approver.FirstName, ''), ' ', COALESCE(approver.LastName, '')),
                            'N/A'
                        ) as approved_by_name,
                        TIMESTAMPDIFF(HOUR, p.scheduled_date, p.updated_at) as duration_hours
                      FROM car_pms_records p
                      LEFT JOIN accounts approver ON p.approved_by = approver.Id
                      LEFT JOIN sales_agent_profiles sa ON approver.Id = sa.account_id
                      WHERE p.customer_id = :customer_id
                      ORDER BY p.pms_date DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['customer_id' => $customerId]);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ensure all required fields exist with default values
            foreach ($results as &$record) {
                $record['approved_at'] = $record['approved_at'] ?? null;
                $record['approved_by_name'] = $record['approved_by_name'] ?? 'N/A';
                $record['service_notes_findings'] = $record['service_notes_findings'] ?? '';
                $record['has_receipt'] = $record['has_receipt'] ?? 0;
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("Error getting customer PMS history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get customer session statistics
     */
    public function getCustomerSessionStats($customerId) {
        try {
            $stats = [];
            
            // Total sessions
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM car_pms_records WHERE customer_id = :customer_id AND request_status = 'Completed'");
            $stmt->execute(['customer_id' => $customerId]);
            $stats['total_sessions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Total service time (placeholder)
            $stats['total_time'] = $stats['total_sessions'] * 2.3; // Average per session
            
            // Average session time
            $stats['avg_time'] = $stats['total_sessions'] > 0 ? $stats['total_time'] / $stats['total_sessions'] : 0;
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error getting customer session stats: " . $e->getMessage());
            return ['total_sessions' => 0, 'total_time' => 0, 'avg_time' => 0];
        }
    }
    
    /**
     * Track new PMS session
     */
    public function trackNewSession($data) {
        try {
            $this->pdo->beginTransaction();

            // Sanitize odometer value
            $odometer = isset($data['current_odometer']) ? preg_replace('/\D/', '', $data['current_odometer']) : '0';
            $odometer = $odometer === '' ? 0 : intval($odometer);

            $query = "INSERT INTO car_pms_records (
                        customer_id, plate_number, model, current_odometer,
                        pms_info, pms_date, request_status, approved_by,
                        approved_at, service_notes_findings, created_at
                      ) VALUES (
                        :customer_id, :plate_number, :model, :current_odometer,
                        :pms_info, :pms_date, 'Completed', :approved_by,
                        NOW(), :service_notes, NOW()
                      )";

            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute([
                'customer_id' => $data['customer_id'],
                'plate_number' => $data['plate_number'] ?? '',
                'model' => $data['model'] ?? '',
                'current_odometer' => $odometer,
                'pms_info' => $data['service_type'],
                'pms_date' => $data['service_date'],
                'approved_by' => $data['approved_by'],
                'service_notes' => $data['service_notes'] ?? ''
            ]);
            
            if ($result) {
                $this->pdo->commit();
                return ['success' => true, 'message' => 'PMS session recorded successfully'];
            } else {
                throw new Exception('Failed to insert PMS record');
            }
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error tracking new session: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error recording PMS session: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update PMS record status
     */
    public function updatePMSStatus($pmsId, $status, $data = []) {
        try {
            $updateFields = ['request_status = :status', 'updated_at = NOW()'];
            $params = ['pms_id' => $pmsId, 'status' => $status];

            if ($status === 'Approved' && isset($data['approved_by'])) {
                $updateFields[] = 'approved_by = :approved_by';
                $updateFields[] = 'approved_at = NOW()';
                $params['approved_by'] = $data['approved_by'];
            }

            if ($status === 'Scheduled' && isset($data['scheduled_date'])) {
                $updateFields[] = 'scheduled_date = :scheduled_date';
                $params['scheduled_date'] = $data['scheduled_date'];

                // Also set approved_by and approved_at for scheduled status
                if (isset($data['approved_by'])) {
                    $updateFields[] = 'approved_by = :approved_by';
                    $updateFields[] = 'approved_at = NOW()';
                    $params['approved_by'] = $data['approved_by'];
                }
            }

            if ($status === 'Rejected' && isset($data['rejection_reason'])) {
                $updateFields[] = 'rejection_reason = :rejection_reason';
                $params['rejection_reason'] = $data['rejection_reason'];
            }

            if ($status === 'Completed') {
                // Handle completion-specific fields
                if (isset($data['pms_date'])) {
                    $updateFields[] = 'pms_date = :pms_date';
                    $params['pms_date'] = $data['pms_date'];
                }

                if (isset($data['service_notes_findings'])) {
                    $updateFields[] = 'service_notes_findings = :service_notes_findings';
                    $params['service_notes_findings'] = $data['service_notes_findings'];
                }

                if (isset($data['next_pms_due'])) {
                    $updateFields[] = 'next_pms_due = :next_pms_due';
                    $params['next_pms_due'] = $data['next_pms_due'];
                }

                // Set approved_by and approved_at for completed status
                if (isset($data['approved_by'])) {
                    $updateFields[] = 'approved_by = :approved_by';
                    $updateFields[] = 'approved_at = NOW()';
                    $params['approved_by'] = $data['approved_by'];
                }
            }

            $query = "UPDATE car_pms_records SET " . implode(', ', $updateFields) . " WHERE pms_id = :pms_id";

            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute($params);

            if ($result) {
                return ['success' => true, 'message' => 'PMS status updated successfully'];
            } else {
                throw new Exception('Failed to update PMS status');
            }
        } catch (Exception $e) {
            error_log("Error updating PMS status: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating PMS status: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get customers for dropdown/selection
     */
    public function getCustomers() {
        try {
            $query = "SELECT 
                        c.cusID,
                        c.firstname,
                        c.lastname,
                        c.middlename,
                        c.mobile_number,
                        a.Email,
                        CONCAT(c.firstname, ' ', IFNULL(c.middlename, ''), ' ', c.lastname) as full_name
                      FROM customer_information c
                      LEFT JOIN accounts a ON c.account_id = a.Id
                      WHERE c.Status = 'Approved'
                      ORDER BY c.firstname, c.lastname";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting customers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get sales agents for approval
     */
    public function getSalesAgents() {
        try {
            $query = "SELECT 
                        sa.agent_profile_id,
                        sa.display_name,
                        sa.agent_id_number,
                        a.FirstName,
                        a.LastName
                      FROM sales_agent_profiles sa
                      LEFT JOIN accounts a ON sa.account_id = a.Id
                      WHERE sa.status = 'Active'
                      ORDER BY sa.display_name";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting sales agents: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update PMS record details
     */
    public function updatePMSRecord($pmsId, $data) {
        try {
            $updateFields = [];
            $params = ['pms_id' => $pmsId];
            
            // Only allow updating specific fields (not approval fields)
            if (isset($data['pms_info'])) {
                $updateFields[] = 'pms_info = :pms_info';
                $params['pms_info'] = $data['pms_info'];
            }
            
            if (isset($data['pms_date'])) {
                $updateFields[] = 'pms_date = :pms_date';
                $params['pms_date'] = $data['pms_date'];
            }
            
            if (isset($data['current_odometer'])) {
                // Sanitize odometer value - remove any non-numeric characters
                $odometer = preg_replace('/\D/', '', $data['current_odometer']);
                $odometer = $odometer === '' ? 0 : intval($odometer);

                $updateFields[] = 'current_odometer = :current_odometer';
                $params['current_odometer'] = $odometer;
            }
            
            if (isset($data['service_notes_findings'])) {
                $updateFields[] = 'service_notes_findings = :service_notes_findings';
                $params['service_notes_findings'] = $data['service_notes_findings'];
            }
            
            if (isset($data['next_pms_due'])) {
                $updateFields[] = 'next_pms_due = :next_pms_due';
                $params['next_pms_due'] = $data['next_pms_due'];
            }
            
            if (isset($data['model'])) {
                $updateFields[] = 'model = :model';
                $params['model'] = $data['model'];
            }
            
            if (isset($data['plate_number'])) {
                $updateFields[] = 'plate_number = :plate_number';
                $params['plate_number'] = $data['plate_number'];
            }
            
            if (isset($data['color'])) {
                $updateFields[] = 'color = :color';
                $params['color'] = $data['color'];
            }
            
            if (isset($data['transmission'])) {
                $updateFields[] = 'transmission = :transmission';
                $params['transmission'] = $data['transmission'];
            }
            
            if (empty($updateFields)) {
                throw new Exception('No fields to update');
            }
            
            // Always update the updated_at timestamp
            $updateFields[] = 'updated_at = NOW()';
            
            $query = "UPDATE car_pms_records SET " . implode(', ', $updateFields) . " WHERE pms_id = :pms_id";
            
            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                return ['success' => true, 'message' => 'PMS record updated successfully'];
            } else {
                throw new Exception('Failed to update PMS record');
            }
        } catch (Exception $e) {
            error_log("Error updating PMS record: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating PMS record: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get single PMS record by ID
     */
    public function getPMSRecordById($pmsId) {
        try {
            $query = "SELECT
                        p.*,
                        CASE WHEN p.uploaded_receipt IS NOT NULL THEN 1 ELSE 0 END as has_receipt,
                        c.firstname,
                        c.lastname,
                        c.middlename,
                        c.mobile_number,
                        CONCAT(c.firstname, ' ', IFNULL(c.middlename, ''), ' ', c.lastname) as full_name,
                        COALESCE(
                            sa.display_name,
                            CONCAT(COALESCE(approver.FirstName, ''), ' ', COALESCE(approver.LastName, '')),
                            'N/A'
                        ) as approved_by_name
                      FROM car_pms_records p
                      LEFT JOIN customer_information c ON p.customer_id = c.account_id
                      LEFT JOIN accounts approver ON p.approved_by = approver.Id
                      LEFT JOIN sales_agent_profiles sa ON approver.Id = sa.account_id
                      WHERE p.pms_id = :pms_id";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['pms_id' => $pmsId]);
            
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($record) {
                return ['success' => true, 'data' => $record];
            } else {
                return ['success' => false, 'message' => 'PMS record not found'];
            }
        } catch (PDOException $e) {
            error_log("Error getting PMS record: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error getting PMS record'];
        }
    }
}
?>
