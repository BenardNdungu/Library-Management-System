<?php
/**
 * Common Functions
 * Library Management System
 */

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitizeInput(string $data): string {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email
 * @param string $email
 * @return bool
 */
function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate a random token
 * @param int $length
 * @return string
 */
function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Generate unique member number
 * @param PDO $pdo
 * @return string
 */
function generateMemberNumber(PDO $pdo): string {
    $year = date('Y');
    $prefix = 'LIB-' . $year . '-';
    
    // Get the last member number for this year
    $stmt = $pdo->prepare("SELECT member_number FROM members WHERE member_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetch();
    
    if ($last) {
        $lastNumber = intval(substr($last['member_number'], -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return $prefix . $newNumber;
}

/**
 * Generate accession number
 * @param PDO $pdo
 * @return string
 */
function generateAccessionNumber(PDO $pdo): string {
    $prefix = 'LIB-ACC-';
    
    $stmt = $pdo->prepare("SELECT accession_number FROM book_copies WHERE accession_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetch();
    
    if ($last) {
        $lastNumber = intval(substr($last['accession_number'], -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return $prefix . $newNumber;
}

/**
 * Get setting value
 * @param PDO $pdo
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getSetting(PDO $pdo, string $key, $default = null) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    if ($result) {
        return $result['setting_value'];
    }
    
    return $default;
}

/**
 * Update setting
 * @param PDO $pdo
 * @param string $key
 * @param mixed $value
 * @return bool
 */
function updateSetting(PDO $pdo, string $key, $value): bool {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

/**
 * Calculate fine amount
 * @param int $overdueDays
 * @param float $dailyRate
 * @return float
 */
function calculateFine(int $overdueDays, float $dailyRate): float {
    if ($overdueDays <= 0) {
        return 0;
    }
    return round($overdueDays * $dailyRate, 2);
}

/**
 * Calculate overdue days
 * @param string $dueDate
 * @return int
 */
function calculateOverdueDays(string $dueDate): int {
    $today = new DateTime();
    $due = new DateTime($dueDate);
    
    if ($today <= $due) {
        return 0;
    }
    
    return $today->diff($due)->days;
}

/**
 * Format date
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate(string $date, string $format = 'Y-m-d'): string {
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Format currency
 * @param float $amount
 * @param string $currency
 * @return string
 */
function formatCurrency(float $amount, string $currency = '$'): string {
    return $currency . number_format($amount, 2);
}

/**
 * Get user role display name
 * @param string $role
 * @return string
 */
function getRoleDisplayName(string $role): string {
    $roles = [
        'admin' => 'Administrator',
        'librarian' => 'Librarian',
        'member' => 'Member'
    ];
    return $roles[$role] ?? $role;
}

/**
 * Get status display name
 * @param string $status
 * @param string $type
 * @return string
 */
function getStatusDisplayName(string $status, string $type = 'user'): string {
    $statuses = [
        'user' => [
            'active' => 'Active',
            'inactive' => 'Inactive'
        ],
        'member' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'suspended' => 'Suspended'
        ],
        'copy' => [
            'Available' => 'Available',
            'Borrowed' => 'Borrowed',
            'Reserved' => 'Reserved',
            'Lost' => 'Lost',
            'Damaged' => 'Damaged',
            'Maintenance' => 'Maintenance'
        ],
        'loan' => [
            'Borrowed' => 'Borrowed',
            'Returned' => 'Returned',
            'Overdue' => 'Overdue',
            'Lost' => 'Lost'
        ],
        'fine' => [
            'Unpaid' => 'Unpaid',
            'Paid' => 'Paid',
            'Waived' => 'Waived'
        ]
    ];
    
    return $statuses[$type][$status] ?? $status;
}

/**
 * Get status badge HTML
 * @param string $status
 * @param string $type
 * @return string
 */
function getStatusBadge(string $status, string $type = 'user'): string {
    $classes = [
        'active' => 'badge-success',
        'inactive' => 'badge-danger',
        'suspended' => 'badge-warning',
        'Available' => 'badge-success',
        'Borrowed' => 'badge-warning',
        'Reserved' => 'badge-info',
        'Lost' => 'badge-danger',
        'Damaged' => 'badge-danger',
        'Maintenance' => 'badge-secondary',
        'Returned' => 'badge-success',
        'Overdue' => 'badge-danger',
        'Unpaid' => 'badge-danger',
        'Paid' => 'badge-success',
        'Waived' => 'badge-secondary'
    ];
    
    $display = getStatusDisplayName($status, $type);
    $class = $classes[$status] ?? 'badge-secondary';
    
    return '<span class="badge ' . $class . '">' . $display . '</span>';
}

/**
 * Redirect to URL
 * @param string $url
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Check if request is AJAX
 * @return bool
 */
function isAjax(): bool {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Return JSON response
 * @param array $data
 * @param int $statusCode
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get current user IP
 * @return string
 */
function getClientIP(): string {
    $ip = '';
    
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    // Handle comma-separated IPs
    if (strpos($ip, ',') !== false) {
        $ips = explode(',', $ip);
        $ip = trim($ips[0]);
    }
    
    return $ip;
}

/**
 * Create audit log entry
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $action
 * @param string|null $tableName
 * @param int|null $recordId
 * @param string|null $description
 */
function createAuditLog(PDO $pdo, ?int $userId, string $action, ?string $tableName = null, ?int $recordId = null, ?string $description = null): void {
    $ip = getClientIP();
    
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $tableName, $recordId, $description, $ip]);
}

/**
 * Create notification
 * @param PDO $pdo
 * @param int $userId
 * @param string $title
 * @param string $message
 * @param string $type
 * @return int
 */
function createNotification(PDO $pdo, int $userId, string $title, string $message, string $type = 'info'): int {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $title, $message, $type]);
    return (int) $pdo->lastInsertId();
}

/**
 * Check if member can borrow
 * @param PDO $pdo
 * @param int $memberId
 * @return array [canBorrow => bool, message => string]
 */
function canMemberBorrow(PDO $pdo, int $memberId): array {
    // Check member status
    $stmt = $pdo->prepare("SELECT m.*, u.status as user_status FROM members m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
    
    if (!$member) {
        return ['canBorrow' => false, 'message' => 'Member not found'];
    }
    
    if ($member['status'] !== 'active') {
        return ['canBorrow' => false, 'message' => 'Member is not active'];
    }
    
    if ($member['user_status'] !== 'active') {
        return ['canBorrow' => false, 'message' => 'User account is not active'];
    }
    
    // Check current loans
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM loans WHERE member_id = ? AND status IN ('Borrowed', 'Overdue')");
    $stmt->execute([$memberId]);
    $result = $stmt->fetch();
    $currentLoans = $result['count'];
    
    // Get max books per member
    $maxBooks = getSetting($pdo, 'max_books_per_member', DEFAULT_MAX_BOOKS);
    
    if ($currentLoans >= $maxBooks) {
        return ['canBorrow' => false, 'message' => 'Member has reached the borrowing limit of ' . $maxBooks . ' books'];
    }
    
    // Check for outstanding fines
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM fines WHERE member_id = ? AND status = 'Unpaid'");
    $stmt->execute([$memberId]);
    $result = $stmt->fetch();
    if ($result['total'] > 0) {
        return ['canBorrow' => false, 'message' => 'Member has outstanding fines of ' . formatCurrency($result['total'])];
    }
    
    return ['canBorrow' => true, 'message' => 'Member can borrow'];
}

/**
 * Get member current loans count
 * @param PDO $pdo
 * @param int $memberId
 * @return int
 */
function getMemberCurrentLoans(PDO $pdo, int $memberId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM loans WHERE member_id = ? AND status IN ('Borrowed', 'Overdue')");
    $stmt->execute([$memberId]);
    $result = $stmt->fetch();
    return (int) $result['count'];
}

/**
 * Get member outstanding fines
 * @param PDO $pdo
 * @param int $memberId
 * @return float
 */
function getMemberOutstandingFines(PDO $pdo, int $memberId): float {
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM fines WHERE member_id = ? AND status = 'Unpaid'");
    $stmt->execute([$memberId]);
    $result = $stmt->fetch();
    return (float) ($result['total'] ?? 0);
}

/**
 * Check if book copy is available
 * @param PDO $pdo
 * @param int $copyId
 * @return bool
 */
function isCopyAvailable(PDO $pdo, int $copyId): bool {
    $stmt = $pdo->prepare("SELECT status FROM book_copies WHERE id = ?");
    $stmt->execute([$copyId]);
    $result = $stmt->fetch();
    return $result && $result['status'] === 'Available';
}

/**
 * Get book details with copies count
 * @param PDO $pdo
 * @param int $bookId
 * @return array|null
 */
function getBookDetails(PDO $pdo, int $bookId): ?array {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               c.name as category_name,
               a.name as author_name,
               p.name as publisher_name,
               (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND status = 'Available') as available_copies_count,
               (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id) as total_copies_count
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN authors a ON b.author_id = a.id
        LEFT JOIN publishers p ON b.publisher_id = p.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bookId]);
    return $stmt->fetch();
}

/**
 * Get member details with statistics
 * @param PDO $pdo
 * @param int $memberId
 * @return array|null
 */
function getMemberDetails(PDO $pdo, int $memberId): ?array {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name, u.email, u.phone, u.username, u.profile_image, u.status as user_status,
               (SELECT COUNT(*) FROM loans WHERE member_id = m.id AND status IN ('Borrowed', 'Overdue')) as current_loans,
               (SELECT COUNT(*) FROM loans WHERE member_id = m.id AND status = 'Returned') as total_loans,
               (SELECT COUNT(*) FROM fines WHERE member_id = m.id AND status = 'Unpaid') as outstanding_fines_count,
               (SELECT COALESCE(SUM(amount), 0) FROM fines WHERE member_id = m.id AND status = 'Unpaid') as outstanding_fines_amount
        FROM members m
        JOIN users u ON m.user_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$memberId]);
    return $stmt->fetch();
}