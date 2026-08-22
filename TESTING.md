# Library Management System - Testing Guide

## Test Checklist

### Authentication Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| AUTH-01 | Login with valid credentials | Redirect to dashboard | ✓ |
| AUTH-02 | Login with invalid credentials | Show error message | ✓ |
| AUTH-03 | Login with inactive account | Show error message | ✓ |
| AUTH-04 | Access protected page without login | Redirect to login | ✓ |
| AUTH-05 | Session timeout | Redirect to login with expired message | ✓ |
| AUTH-06 | Logout | Destroy session, redirect to login | ✓ |
| AUTH-07 | Change password with correct current password | Success message | ✓ |
| AUTH-08 | Change password with incorrect current password | Error message | ✓ |
| AUTH-09 | Forgot password with valid email | Success message | ✓ |
| AUTH-10 | Forgot password with invalid email | Same success message (security) | ✓ |

### Role-Based Access Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| ROLE-01 | Admin access to admin pages | Allowed | ✓ |
| ROLE-02 | Admin access to user management | Allowed | ✓ |
| ROLE-03 | Admin access to settings | Allowed | ✓ |
| ROLE-04 | Admin access to audit logs | Allowed | ✓ |
| ROLE-05 | Librarian access to admin pages | Denied/Redirected | ✓ |
| ROLE-06 | Librarian access to book management | Allowed | ✓ |
| ROLE-07 | Librarian access to member management | Allowed | ✓ |
| ROLE-08 | Librarian access to loans | Allowed | ✓ |
| ROLE-09 | Member access to admin pages | Denied/Redirected | ✓ |
| ROLE-10 | Member access to catalog | Allowed | ✓ |
| ROLE-11 | Member access to profile | Allowed | ✓ |
| ROLE-12 | Member access to borrowed books | Allowed | ✓ |

### Book Management Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| BOOK-01 | Create book with valid data | Success, book added | ✓ |
| BOOK-02 | Create book without title | Error message | ✓ |
| BOOK-03 | Create book with cover image | Image uploaded | ✓ |
| BOOK-04 | Edit book details | Changes saved | ✓ |
| BOOK-05 | Delete book without copies | Success | ✓ |
| BOOK-06 | Delete book with copies | Error or force delete | ✓ |
| BOOK-07 | Search books by title | Results displayed | ✓ |
| BOOK-08 | Search books by ISBN | Results displayed | ✓ |
| BOOK-09 | Search books by author | Results displayed | ✓ |
| BOOK-10 | Filter books by category | Filtered results | ✓ |
| BOOK-11 | Filter books by availability | Filtered results | ✓ |
| BOOK-12 | Pagination works | Pages navigable | ✓ |

### Author Management Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| AUTH-01 | Create author | Success | ✓ |
| AUTH-02 | Edit author | Changes saved | ✓ |
| AUTH-03 | Delete author without books | Success | ✓ |
| AUTH-04 | Delete author with books | Error message | ✓ |
| AUTH-05 | Search authors | Results displayed | ✓ |
| AUTH-06 | View author books | List displayed | ✓ |

### Category Management Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| CAT-01 | Create category | Success | ✓ |
| CAT-02 | Create duplicate category | Error message | ✓ |
| CAT-03 | Edit category | Changes saved | ✓ |
| CAT-04 | Delete category without books | Success | ✓ |
| CAT-05 | Delete category with books | Error message | ✓ |
| CAT-06 | Search categories | Results displayed | ✓ |

### Member Management Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| MEM-01 | Register member with valid data | Success, member number generated | ✓ |
| MEM-02 | Register member without email | Error message | ✓ |
| MEM-03 | Register member with duplicate email | Error message | ✓ |
| MEM-04 | Register member with duplicate username | Error message | ✓ |
| MEM-05 | Register member with profile image | Image uploaded | ✓ |
| MEM-06 | Edit member details | Changes saved | ✓ |
| MEM-07 | Toggle member status | Status updated | ✓ |
| MEM-08 | Delete member | Success | ✓ |
| MEM-09 | View member profile | All info displayed | ✓ |
| MEM-10 | View member loans history | Loans listed | ✓ |
| MEM-11 | View member fines | Fines listed | ✓ |
| MEM-12 | Search members | Results displayed | ✓ |

### Loan Management Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| LOAN-01 | Issue book to valid member | Success, loan created | ✓ |
| LOAN-02 | Issue book to inactive member | Error message | ✓ |
| LOAN-03 | Issue book exceeding limit | Error message | ✓ |
| LOAN-04 | Issue book with outstanding fines | Error message | ✓ |
| LOAN-05 | Issue book with unavailable copy | Error message | ✓ |
| LOAN-06 | Issue same copy twice | Error message | ✓ |
| LOAN-07 | View all loans | List displayed | ✓ |
| LOAN-08 | Filter loans by status | Filtered results | ✓ |
| LOAN-09 | Renew loan | Due date extended | ✓ |
| LOAN-10 | Renew loan exceeding max renewals | Error message | ✓ |

### Return Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| RET-01 | Return book on time | Success, no fine | ✓ |
| RET-02 | Return overdue book | Fine calculated | ✓ |
| RET-03 | Return book with fine payment | Fine marked paid | ✓ |
| RET-04 | Return book with waived fine | Fine waived | ✓ |
| RET-05 | Return already returned book | Error message | ✓ |
| RET-06 | Search by accession number | Loan found | ✓ |
| RET-07 | Search by barcode | Loan found | ✓ |

### Reservation Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| RES-01 | Reserve unavailable book | Success, pending | ✓ |
| RES-02 | Reserve already reserved book | Error message | ✓ |
| RES-03 | Cancel reservation | Status updated | ✓ |
| RES-04 | Mark reservation as ready | Status updated, notification sent | ✓ |
| RES-05 | Complete reservation | Status updated | ✓ |
| RES-06 | View member reservations | List displayed | ✓ |

### Fine & Payment Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| FIN-01 | View fines list | List displayed | ✓ |
| FIN-02 | Filter fines by status | Filtered results | ✓ |
| FIN-03 | Waive fine | Status updated | ✓ |
| FIN-04 | Record payment | Success, fine marked paid | ✓ |
| FIN-05 | Record payment exceeding fine | Error message | ✓ |
| FIN-06 | View payment receipt | Receipt displayed | ✓ |
| FIN-07 | Print receipt | Print dialog | ✓ |

### Notification Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| NOT-01 | Notification on loan creation | Notification created | ✓ |
| NOT-02 | Notification on return | Notification created | ✓ |
| NOT-03 | Notification on fine creation | Notification created | ✓ |
| NOT-04 | Notification on payment | Notification created | ✓ |
| NOT-05 | Notification on reservation ready | Notification created | ✓ |
| NOT-06 | Mark notification as read | Status updated | ✓ |
| NOT-07 | Mark all as read | All updated | ✓ |
| NOT-08 | Unread count in navbar | Count displayed | ✓ |

### Report Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| REP-01 | Books report | Data displayed | ✓ |
| REP-02 | Books by category report | Data displayed | ✓ |
| REP-03 | Books by author report | Data displayed | ✓ |
| REP-04 | Available books report | Data displayed | ✓ |
| REP-05 | Borrowed books report | Data displayed | ✓ |
| REP-06 | Members report | Data displayed | ✓ |
| REP-07 | Active members report | Data displayed | ✓ |
| REP-08 | New members report | Data displayed | ✓ |
| REP-09 | Current loans report | Data displayed | ✓ |
| REP-10 | Loan history report | Data displayed | ✓ |
| REP-11 | Overdue books report | Data displayed | ✓ |
| REP-12 | Most borrowed report | Data displayed | ✓ |
| REP-13 | Outstanding fines report | Data displayed | ✓ |
| REP-14 | Paid fines report | Data displayed | ✓ |
| REP-15 | Fine collection report | Data displayed | ✓ |
| REP-16 | Pending reservations report | Data displayed | ✓ |
| REP-17 | Export CSV | File downloaded | ✓ |
| REP-18 | Print report | Print dialog | ✓ |

### Security Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| SEC-01 | SQL injection attempt | Query fails safely | ✓ |
| SEC-02 | XSS attempt in input | Input sanitized | ✓ |
| SEC-03 | CSRF token missing | Request rejected | ✓ |
| SEC-04 | CSRF token invalid | Request rejected | ✓ |
| SEC-05 | File upload with invalid type | Rejected | ✓ |
| SEC-06 | File upload exceeding size | Rejected | ✓ |
| SEC-07 | Access uploads directory | Forbidden | ✓ |
| SEC-08 | Access config directory | Forbidden | ✓ |

### Responsive Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| RSP-01 | Desktop view (1920x1080) | All elements visible | ✓ |
| RSP-02 | Laptop view (1366x768) | All elements visible | ✓ |
| RSP-03 | Tablet view (768x1024) | Responsive layout | ✓ |
| RSP-04 | Mobile view (375x667) | Responsive layout, sidebar | ✓ |
| RSP-05 | Mobile view (414x896) | Responsive layout | ✓ |

### Performance Tests

| Test ID | Test Description | Expected Result | Status |
|---------|------------------|-----------------|--------|
| PERF-01 | Dashboard load time | < 3 seconds | ✓ |
| PERF-02 | Book list with 100+ books | < 2 seconds | ✓ |
| PERF-03 | Search with AJAX | < 500ms | ✓ |
| PERF-04 | Report generation | < 5 seconds | ✓ |
| PERF-05 | CSV export | < 10 seconds | ✓ |

## Test Execution Instructions

1. **Setup Test Environment**
   - Install XAMPP/WAMP/LAMP
   - Import database.sql
   - Configure database credentials

2. **Run Manual Tests**
   - Follow the test checklist
   - Mark each test as Pass/Fail
   - Document any issues found

3. **Automated Testing**
   - Use PHPUnit for unit tests (if implemented)
   - Use Selenium for UI tests (if implemented)

4. **Test Data**
   - Use provided seed data
   - Create additional test data as needed

## Issue Tracking Template

```markdown
### Issue Report

**Test ID:** [ID]
**Severity:** [Critical/High/Medium/Low]
**Description:** [What happened]
**Expected:** [What should have happened]
**Steps to Reproduce:**
1. [Step 1]
2. [Step 2]
3. [Step 3]
**Screenshots:** [Attach if applicable]
**Environment:** [Browser, OS, Device]