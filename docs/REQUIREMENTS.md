# REQUIREMENTS — XKHM

## Functional Requirements

### FR-001: Client Area
- Client registration and login
- Dashboard with service overview
- View orders, invoices, payments
- Manage products/addons
- View support tickets
- Manage domain names
- Update contact information
- Change password

### FR-002: Billing & Invoicing
- Generate invoices
- Process payments (via configured gateways)
- View payment history
- Credit notes
- Invoice PDF generation

### FR-003: Domain Management
- Domain availability checker
- Domain registration (via registrar modules)
- Domain renewal
- DNS management
- WHOIS lookup

### FR-004: Support Ticketing
- Submit tickets
- View ticket history
- Reply to tickets (admin)
- Ticket categories and departments
- Ticket status tracking

### FR-005: Knowledgebase
- Browse articles by category
- Search knowledgebase
- View individual articles
- Rating/comments (if present on live)

### FR-006: Affiliates
- Affiliate registration
- Track referrals
- View earnings and commissions
- Withdrawal requests

### FR-007: Announcements
- View announcements
- RSS feed

### FR-008: Server Status
- Display server/service status
- Network issues display

### FR-009: SSL Configuration
- SSL certificate ordering
- SSL status monitoring

### FR-010: API
- WHMCS-compatible API
- Client-area API endpoints
- Admin API endpoints

### FR-011: Admin Panel
- Client management
- Order management
- Invoice management
- Product/service management
- Module configuration
- Email template management
- System settings

## Non-Functional Requirements

### NFR-001: Database Compatibility
- Must connect to the same MySQL database (`linuxkonect`)
- Must use the same table schema
- Must use the same DB credentials from `configuration.php`
- Must handle existing data without migration

### NFR-002: Configuration Compatibility
- Must read the same `configuration.php` format
- Must support `$cc_encryption_hash` for CC data
- Must support `$templates_compiledir`
- Must support `$customadminpath`

### NFR-003: Language
- Portuguese (Brazilian) UI — match the live service's language pack

### NFR-004: Performance
- Equal or better performance than the original PHP 5.6 service
- Under PHP 8.4

### NFR-005: Security
- No ionCube dependency
- Modern PHP security practices
- CSRF protection
- XSS protection
- SQL injection prevention (use prepared statements)

## Out of Scope

- Modifying the live service
- Adding features not present on `xk.linuxkafe.com`
- Reversing the ionCube Loader algorithm
- Full WHMCS feature set (only deployed features)
