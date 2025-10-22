<?php
include_once __DIR__ . '/db_conn.php';

class CustomerOperations {
    private $pdo;
    
    /**
     * Cache for schema checks
     */
    private $schemaCache = [];
    
    public function __construct() {
        global $connect;
        $this->pdo = $connect;
    }
    
    /**
     * Checks if a column exists on a table (cached per request)
     */
    private function hasColumn(string $table, string $column): bool {
        $key = strtolower($table) . '.' . strtolower($column);
        if (array_key_exists($key, $this->schemaCache)) {
            return $this->schemaCache[$key];
        }
        try {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :col");
            $stmt->execute([':col' => $column]);
            $exists = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
            $this->schemaCache[$key] = $exists;
            return $exists;
        } catch (PDOException $e) {
            // If SHOW COLUMNS fails (permissions), safely assume it may not exist so we avoid filtering on it
            $this->schemaCache[$key] = false;
            return false;
        }
    }
    
    /**
     * Get customer information by account ID
     */
    public function getCustomerByAccountId($account_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ci.*, a.Username, a.Email, a.Role 
                FROM customer_information ci 
                LEFT JOIN accounts a ON ci.account_id = a.Id 
                WHERE ci.account_id = ?
            ");
            $stmt->execute([$account_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting customer info: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if customer information exists for an account
     */
    public function hasCustomerInfo($account_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM customer_information WHERE account_id = ?");
            $stmt->execute([$account_id]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get list of active sales agent account IDs
     */
    public function getActiveSalesAgentIds() {
        try {
            // Normalize role: accept both 'SalesAgent' and 'Sales Agent'
            $sql = "SELECT Id FROM `accounts` WHERE REPLACE(LOWER(Role), ' ', '') = 'salesagent'";
            // Only filter by IsDisabled if the column exists (live server may be missing the migration)
            if ($this->hasColumn('accounts', 'IsDisabled')) {
                $sql .= " AND COALESCE(IsDisabled, 0) = 0";
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log('getActiveSalesAgentIds error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a random active sales agent ID. Optionally exclude an agent ID.
     */
    public function getRandomActiveSalesAgentId($excludeAgentId = null) {
        $ids = $this->getActiveSalesAgentIds();
        if (!$ids) return null;
        if ($excludeAgentId !== null) {
            $ids = array_values(array_filter($ids, function($id) use ($excludeAgentId) { return intval($id) !== intval($excludeAgentId); }));
        }
        if (!$ids) return null;
        $idx = random_int(0, count($ids) - 1);
        return intval($ids[$idx]);
    }

    /**
     * Assign a random active sales agent to a customer by account ID if none assigned yet.
     * Returns assigned agent_id or null if not assigned.
     */
    public function assignRandomAgentToCustomerByAccountId($accountId) {
        try {
            // Check if already assigned
            $stmt = $this->pdo->prepare("SELECT agent_id FROM customer_information WHERE account_id = :aid FOR UPDATE");
            $stmt->execute([':aid' => $accountId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null; // no customer row
            if (!empty($row['agent_id'])) return intval($row['agent_id']); // already assigned

            $agentId = $this->getRandomActiveSalesAgentId();
            if ($agentId === null) return null;

            $upd = $this->pdo->prepare("UPDATE customer_information SET agent_id = :agentId, updated_at = CURRENT_TIMESTAMP WHERE account_id = :aid");
            $upd->execute([':agentId' => $agentId, ':aid' => $accountId]);
            return $agentId;
        } catch (PDOException $e) {
            error_log('assignRandomAgentToCustomerByAccountId error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Assign a random active sales agent to a customer by customer ID if none assigned yet.
     */
    public function assignRandomAgentToCustomerByCustomerId($customerId) {
        try {
            $stmt = $this->pdo->prepare("SELECT agent_id, account_id FROM customer_information WHERE cusID = :cid FOR UPDATE");
            $stmt->execute([':cid' => $customerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            if (!empty($row['agent_id'])) return intval($row['agent_id']);

            $agentId = $this->getRandomActiveSalesAgentId();
            if ($agentId === null) return null;
            $upd = $this->pdo->prepare("UPDATE customer_information SET agent_id = :agentId, updated_at = CURRENT_TIMESTAMP WHERE cusID = :cid");
            $upd->execute([':agentId' => $agentId, ':cid' => $customerId]);
            return $agentId;
        } catch (PDOException $e) {
            error_log('assignRandomAgentToCustomerByCustomerId error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Reassign all customers from a given (disabled/deleted) agent to random active agents.
     * Returns number of customers reassigned.
     */
    public function reassignCustomersFromAgent($fromAgentId) {
        try {
            // Get customer list assigned to this agent
            $stmt = $this->pdo->prepare("SELECT cusID FROM customer_information WHERE agent_id = :aid");
            $stmt->execute([':aid' => $fromAgentId]);
            $customers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!$customers) return 0;

            $activeAgents = $this->getActiveSalesAgentIds();
            // Exclude the fromAgentId in case it is still in the list (e.g., disabling but query ran before flag persisted elsewhere)
            $activeAgents = array_values(array_filter($activeAgents, function($id) use ($fromAgentId) { return intval($id) !== intval($fromAgentId); }));
            if (!$activeAgents) {
                // No available agents: unassign all customers from this agent
                $inTrans = $this->pdo->inTransaction();
                if (!$inTrans) $this->pdo->beginTransaction();
                try {
                    $upd = $this->pdo->prepare("UPDATE customer_information SET agent_id = NULL, updated_at = CURRENT_TIMESTAMP WHERE agent_id = :aid");
                    $upd->execute([':aid' => $fromAgentId]);
                    if (!$inTrans) $this->pdo->commit();
                    return $upd->rowCount();
                } catch (PDOException $e) {
                    if (!$inTrans && $this->pdo->inTransaction()) $this->pdo->rollBack();
                    throw $e;
                }
            }

            $inTrans = $this->pdo->inTransaction();
            if (!$inTrans) $this->pdo->beginTransaction();
            try {
                $upd = $this->pdo->prepare("UPDATE customer_information SET agent_id = :newAid, updated_at = CURRENT_TIMESTAMP WHERE cusID = :cid");
                $count = 0;
                $totalAgents = count($activeAgents);
                foreach ($customers as $cid) {
                    // Pick a random active agent for each customer
                    $randIndex = random_int(0, $totalAgents - 1);
                    $newAid = intval($activeAgents[$randIndex]);
                    $upd->execute([':newAid' => $newAid, ':cid' => $cid]);
                    $count++;
                }
                if (!$inTrans) $this->pdo->commit();
                return $count;
            } catch (PDOException $e) {
                if (!$inTrans && $this->pdo->inTransaction()) $this->pdo->rollBack();
                throw $e;
            }
        } catch (PDOException $e) {
            error_log('reassignCustomersFromAgent error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * List customer accounts with assigned sales agent information.
     * Supports search over customer name/username/email and safe sorting.
     *
     * @param string|null $search
     * @param string $sortBy One of: CreatedAt, Username, AgentName
     * @param string $sortOrder ASC|DESC
     * @return array
     */
    public function listCustomerAccountsWithAgent($search = null, $sortBy = 'CreatedAt', $sortOrder = 'DESC') {
        try {
            $sql = "SELECT 
                        a.Id AS AccountId,
                        a.Username,
                        a.Email,
                        a.FirstName,
                        a.LastName,
                        a.LastLoginAt,
                        a.CreatedAt,
                        COALESCE(a.IsDisabled, 0) AS IsDisabled,
                        ci.cusID,
                        ci.agent_id,
                        CONCAT(agent.FirstName, ' ', agent.LastName) AS AgentName,
                        agent.Username AS AgentUsername
                    FROM `accounts` a
                    LEFT JOIN customer_information ci ON ci.account_id = a.Id
                    LEFT JOIN `accounts` agent ON agent.Id = ci.agent_id
                    WHERE a.Role = 'Customer'";

            $params = [];
            if ($search) {
                $sql .= " AND (a.FirstName LIKE :q OR a.LastName LIKE :q OR a.Username LIKE :q OR a.Email LIKE :q)";
                $params[':q'] = "%{$search}%";
            }

            // Safe sort mapping
            $allowedSort = [
                'CreatedAt' => 'a.CreatedAt',
                'Username' => 'a.Username',
                'AgentName' => 'AgentName'
            ];
            $allowedOrder = ['ASC','DESC'];
            $sortBy = $allowedSort[$sortBy] ?? 'a.CreatedAt';
            $sortOrder = in_array(strtoupper($sortOrder), $allowedOrder, true) ? strtoupper($sortOrder) : 'DESC';
            $sql .= " ORDER BY $sortBy $sortOrder";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('listCustomerAccountsWithAgent error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get list of active (not disabled) Sales Agent accounts with names
     * @return array of [Id, FirstName, LastName, Username]
     */
    public function getActiveSalesAgents() {
        try {
            $sql = "SELECT Id, FirstName, LastName, Username FROM `accounts` WHERE REPLACE(LOWER(Role), ' ', '') = 'salesagent'";
            if ($this->hasColumn('accounts', 'IsDisabled')) {
                $sql .= " AND COALESCE(IsDisabled, 0) = 0";
            }
            $sql .= " ORDER BY FirstName, LastName";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('getActiveSalesAgents error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Assign a specific Sales Agent to a customer by account ID
     */
    public function setCustomerAgentByAccountId($accountId, $agentId) {
        try {
            // Validate target account exists and is a Customer
            $chk = $this->pdo->prepare("SELECT Id FROM `accounts` WHERE Id = :aid AND REPLACE(LOWER(Role), ' ', '') = 'customer'");
            $chk->execute([':aid' => $accountId]);
            if (!$chk->fetch(PDO::FETCH_ASSOC)) return false;

            // Validate agent is active SalesAgent
            $sql = "SELECT Id FROM `accounts` WHERE Id = :gid AND REPLACE(LOWER(Role), ' ', '') = 'salesagent'";
            if ($this->hasColumn('accounts', 'IsDisabled')) {
                $sql .= " AND COALESCE(IsDisabled, 0) = 0";
            }
            $ag = $this->pdo->prepare($sql);
            $ag->execute([':gid' => $agentId]);
            if (!$ag->fetch(PDO::FETCH_ASSOC)) {
                // Diagnostic log to help identify mismatched role/disabled state in production
                try {
                    $dbg = $this->pdo->prepare("SELECT Role, /* IsDisabled may not exist on some servers */ 0 AS _placeholder FROM `accounts` WHERE Id = :gid");
                    // If IsDisabled exists, fetch it too for richer diagnostics
                    if ($this->hasColumn('accounts', 'IsDisabled')) {
                        $dbg = $this->pdo->prepare("SELECT Role, IsDisabled FROM `accounts` WHERE Id = :gid");
                    }
                    $dbg->execute([':gid' => $agentId]);
                    $info = $dbg->fetch(PDO::FETCH_ASSOC);
                    error_log('setCustomerAgentByAccountId validation failed for agentId=' . (int)$agentId . ' info=' . json_encode($info));
                } catch (PDOException $e2) {
                    // ignore
                }
                return false;
            }

            // Check if customer_information row exists for this account_id
            $checkStmt = $this->pdo->prepare("SELECT cusID FROM customer_information WHERE account_id = :aid");
            $checkStmt->execute([':aid' => $accountId]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing customer record
                $upd = $this->pdo->prepare("UPDATE customer_information SET agent_id = :gid, updated_at = CURRENT_TIMESTAMP WHERE account_id = :aid");
                return $upd->execute([':gid' => $agentId, ':aid' => $accountId]);
            } else {
                // Insert new customer record
                $ins = $this->pdo->prepare("INSERT INTO customer_information (account_id, agent_id, updated_at) VALUES (:aid, :gid, CURRENT_TIMESTAMP)");
                return $ins->execute([':aid' => $accountId, ':gid' => $agentId]);
            }
        } catch (PDOException $e) {
            error_log('setCustomerAgentByAccountId error: ' . $e->getMessage());
            return false;
        }
    }
}
?>
