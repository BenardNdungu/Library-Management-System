<?php
/**
 * Application Constants
 * Library Management System
 */

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_LIBRARIAN', 'librarian');
define('ROLE_MEMBER', 'member');

// User statuses
define('USER_STATUS_ACTIVE', 'active');
define('USER_STATUS_INACTIVE', 'inactive');

// Member statuses
define('MEMBER_STATUS_ACTIVE', 'active');
define('MEMBER_STATUS_INACTIVE', 'inactive');
define('MEMBER_STATUS_SUSPENDED', 'suspended');

// Book copy statuses
define('COPY_AVAILABLE', 'Available');
define('COPY_BORROWED', 'Borrowed');
define('COPY_RESERVED', 'Reserved');
define('COPY_LOST', 'Lost');
define('COPY_DAMAGED', 'Damaged');
define('COPY_MAINTENANCE', 'Maintenance');

// Loan statuses
define('LOAN_BORROWED', 'Borrowed');
define('LOAN_RETURNED', 'Returned');
define('LOAN_OVERDUE', 'Overdue');
define('LOAN_LOST', 'Lost');

// Reservation statuses
define('RES_PENDING', 'Pending');
define('RES_READY', 'Ready');
define('RES_COMPLETED', 'Completed');
define('RES_CANCELLED', 'Cancelled');
define('RES_EXPIRED', 'Expired');

// Fine statuses
define('FINE_UNPAID', 'Unpaid');
define('FINE_PAID', 'Paid');
define('FINE_WAIVED', 'Waived');

// Payment methods
define('PAYMENT_CASH', 'Cash');
define('PAYMENT_CARD', 'Card');
define('PAYMENT_BANK_TRANSFER', 'Bank Transfer');
define('PAYMENT_MOBILE_MONEY', 'Mobile Money');
define('PAYMENT_OTHER', 'Other');

// Notification types
define('NOTIFY_INFO', 'info');
define('NOTIFY_SUCCESS', 'success');
define('NOTIFY_WARNING', 'warning');
define('NOTIFY_ERROR', 'error');

// Setting keys
define('SETTING_LIBRARY_NAME', 'library_name');
define('SETTING_LIBRARY_ADDRESS', 'library_address');
define('SETTING_LIBRARY_PHONE', 'library_phone');
define('SETTING_LIBRARY_EMAIL', 'library_email');
define('SETTING_MAX_BOOKS_PER_MEMBER', 'max_books_per_member');
define('SETTING_DEFAULT_LOAN_PERIOD', 'default_loan_period');
define('SETTING_MAX_RENEWAL_COUNT', 'max_renewal_count');
define('SETTING_DAILY_FINE_RATE', 'daily_fine_rate');
define('SETTING_RESERVATION_PERIOD', 'reservation_period');
define('SETTING_CURRENCY', 'currency');
define('SETTING_DATE_FORMAT', 'date_format');

// Default values for settings
define('DEFAULT_MAX_BOOKS', 5);
define('DEFAULT_LOAN_PERIOD', 14);
define('DEFAULT_MAX_RENEWALS', 2);
define('DEFAULT_DAILY_FINE', 1.00);
define('DEFAULT_RESERVATION_PERIOD', 3);
define('DEFAULT_CURRENCY', '$');
define('DEFAULT_DATE_FORMAT', 'Y-m-d');