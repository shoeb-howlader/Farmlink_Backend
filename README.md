# FarmLink — Smart Aquaculture & Digital Agro-Platform (Backend)

<p align="center">
  <img src="https://raw.githubusercontent.com/lucide-icons/lucide/main/icons/shield-check.svg" width="64" height="64" alt="FarmLink Backend Logo" />
</p>

<p align="center">
  <b>Enterprise-grade, high-performance RESTful API, real-time WebSocket server, and telemedicine engine for FarmLink.</b><br>
  Built on Laravel 12, PHP 8.3+, Laravel Reverb, PostgreSQL, Sanctum, and Pest PHP.
</p>

<p align="center">
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&labelColor=2b0805" alt="Laravel 12" /></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php&labelColor=1a1c30" alt="PHP 8.3+" /></a>
  <a href="https://laravel.com/docs/reverb"><img src="https://img.shields.io/badge/Reverb-WebSockets-FF2D20?logo=laravel&labelColor=2b0805" alt="Laravel Reverb" /></a>
  <a href="https://www.postgresql.org"><img src="https://img.shields.io/badge/Database-PostgreSQL_/_MySQL-4169E1?logo=postgresql&labelColor=0f1f45" alt="PostgreSQL" /></a>
  <a href="https://pestphp.com"><img src="https://img.shields.io/badge/Tests-Pest_PHP_v4-9333EA?logo=php&labelColor=28093d" alt="Pest PHP" /></a>
  <a href="https://spatie.be"><img src="https://img.shields.io/badge/RBAC-Spatie_Permissions-FBBF24?labelColor=423102" alt="Spatie Permission" /></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-emerald?labelColor=064e3b" alt="License" /></a>
</p>

---

## 🌟 Overview

**FarmLink Backend** powers the complete operational backend for aquaculture farmers, veterinary practitioners, aquaculture consultants, field data entry operators (DEO), and administrators.

The service handles heavy-duty operational workloads:
- High-frequency pond water quality telemetry ingestion
- Real-time WebRTC signaling and peer-to-peer telemedicine calls
- Multi-role access control with granular permissions
- E-commerce and farm inputs inventory with POS invoicing
- Automated fraud detection for Cash on Delivery (COD)
- Dynamic shipping calculators with district-level overrides across all 64 districts of Bangladesh
- Payment processing via SSLCommerz (bKash, Nagad, Rocket, Cards)
- Automated multi-gateway SMS failover and Web Push notifications

---

## 🚀 Key Modules & Architecture

### 🛡️ Authentication & Role-Based Access Control (RBAC)
- Token-based API authentication via **Laravel Sanctum**.
- Strict permission scoping via **Spatie Laravel-Permission**:
  - `admin` (System administrators, master governance, audit trail)
  - `farmer` (Aquaculture farm & pond management, telemetry, shopping, booking)
  - `veterinary_doctor` (Clinical appointments, prescriptions, telemedicine consultations)
  - `consultant` (Pond visit logs, advisory records, water health diagnoses)
  - `data_entry_operator` (Field farmer onboarding, paper form digitization)
  - `staff` (Inventory management, POS sales counter, dispatch)

### ⚡ Real-Time Broadcasting (Laravel Reverb)
- Standalone WebSocket server powered by **Laravel Reverb**.
- Sub-second channel broadcasting for:
  - Critical pond telemetry alerts (e.g. Dissolved Oxygen drops below 3.0 mg/L)
  - Order status lifecycle transitions (`pending` → `confirmed` → `dispatched` → `delivered`)
  - Real-time in-app chat with cursor-based reverse pagination
  - Live administrator presence monitoring and active farmer count

### 📞 WebRTC Telemedicine Signaling
- Endpoints for SDP offer/answer exchanges and ICE candidate routing.
- Time-limited STUN/TURN server credentials generation (`turnserver.conf` integration) ensuring reliable peer-to-peer connections across restrictive mobile carrier CGNATs.

### 🐟 Pond Telemetry & Automated Health Scoring
- Time-series parameter records per pond:
  - **Dissolved Oxygen (DO)**, **pH Level**, **Water Temperature**, **Ammonia (NH3/NH4+)**, **Salinity**, **Water Transparency**.
- Automated health evaluation and historical analytics with fast composite database indexing.

### 💊 Digital Prescriptions & Clinical Dossiers
- Structured prescription creation with medication items, dosage instructions in Bengali/English, duration, and dietary recommendations.
- On-the-fly PDF generation utilizing **mPDF** and **Barryvdh DomPDF** for immediate farmer download and physical printing.

### 📦 E-Commerce, Logistics & Fraud Prevention
- **Product Inventory Management**: Categorized feed, fingerlings, equipment, and medicines with SKU and batch tracking.
- **Geographic Delivery Engine**: Automated lookup for Bangladesh's 8 Divisions, 64 Districts, Upazilas, and Unions via `devfaysal/laravel-bangladesh-geocode`.
- **District Rate Overrides**: Custom delivery fees and free-shipping thresholds per district with fallback to the global flat rate.
- **COD Fraud & Risk Scoring**:
  - Doorstep refusal threshold tracking.
  - Mandatory advance delivery fee enforcement on high-risk accounts or high-value cart orders.
  - Phone verification status check prior to automatic dispatch.

### 💳 Payment Gateways & Invoicing
- **SSLCommerz Integration**: Seamless hosted checkout supporting bKash, Nagad, Rocket, Visa, Mastercard, and internet banking with Instant Payment Notification (IPN) validation.
- **POS Counter & Invoices**: Direct point-of-sale checkout with printable thermal receipts.

### 🎨 Enterprise Governance & Audit Trail
- Dynamic brand customization endpoints (12 aquatic palettes, 7 bilingual typography presets, and appearance modes).
- Full audit logging on `Setting` mutations tracking the exact previous value, new value, timestamp, and administrator identity.

---

## 📂 Project Structure

```text
Farmlink_Backend/
├── app/
│   ├── Enums/                     # Status and type enums (OrderStatus, PaymentStatus, etc.)
│   ├── Events/                    # Broadcasted WebSocket events
│   ├── Http/
│   │   ├── Controllers/Api/V1/    # Versioned REST API Controllers
│   │   │   ├── Admin/             # Admin controls, settings, dashboards, audit logs
│   │   │   ├── Auth/              # Register, login, OTP verification, password reset
│   │   │   ├── Farmer/            # Farmer farms, ponds, telemetry, consultations
│   │   │   ├── Vet/               # Vet appointments, prescriptions, treatments
│   │   │   ├── Consultant/        # Advisory logs, visit reports
│   │   │   └── Deo/               # DEO farmer intake and digitization queues
│   │   ├── Middleware/            # Role verification, CORS, Sanctum guards
│   │   └── Requests/Api/V1/       # Form request validators with localized error messages
│   ├── Models/                    # Eloquent models with relations, scopes, and casts
│   ├── Notifications/             # Multi-channel notifications (Database, SMS, WebPush, Mail)
│   └── Services/                  # Business logic (Payment, SMS, Telemetry, FraudScorer)
├── config/                        # Laravel configuration files (cors, reverb, sanctum, etc.)
├── database/
│   ├── factories/                 # Model factories for testing
│   ├── migrations/                # Schema migrations with covering indexes
│   └── seeders/                   # Initial seeds (Roles, Super Admin, Locations, Taxonomies)
├── routes/
│   ├── api.php                    # RESTful API route definitions under `/api/v1`
│   ├── channels.php               # Private WebSocket broadcast channel authorizations
│   └── console.php                # Scheduled cron jobs (telemetry cleanups, backups)
├── tests/
│   ├── Feature/                   # 60+ Pest feature test suites
│   └── Unit/                      # Unit tests for helper logic and calculations
└── composer.json                  # PHP packages and dependencies
```

---

## 🛠️ Tech Stack & Requirements

| Component | Technology |
| :--- | :--- |
| **Runtime** | PHP `^8.3` (with `pdo_pgsql` / `pdo_mysql`, `bcmath`, `gd`, `intl`) |
| **Framework** | Laravel `12.x` / `13.x` |
| **Database** | PostgreSQL `15+` (recommended) or MySQL `8.0+` |
| **WebSockets** | Laravel Reverb `^1.12` |
| **Authentication** | Laravel Sanctum `^4.0` |
| **Permissions** | Spatie Laravel-Permission `^8.3` |
| **Geocode Data** | `devfaysal/laravel-bangladesh-geocode` |
| **PDF Generation** | mPDF `^8.3` / Barryvdh DomPDF `^3.1` |
| **Monitoring** | Sentry Laravel `^4.28` |
| **Backups** | Spatie Laravel-Backup `^10.3` |
| **Testing** | Pest PHP `^4.7` |

---

## ⚡ Getting Started

### 1. Prerequisites

Ensure you have installed:
- **PHP 8.3+** with extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `intl`, `mbstring`, `openssl`, `pcre`, `pdo_pgsql`/`pdo_mysql`, `session`, `tokenizer`, `xml`, `gd`.
- **Composer** (v2.6+)
- **PostgreSQL** or **MySQL**
- **Node.js** (for Reverb assets / local dev)

### 2. Installation & Setup

```bash
# Clone the repository
git clone https://github.com/shoeb-howlader/Farmlink_Backend.git
cd Farmlink_Backend

# Install PHP dependencies
composer install

# Copy environment template
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 3. Database Configuration & Migrations

Configure your `.env` file with database credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=farmlink_db
DB_USERNAME=farmlink_user
DB_PASSWORD=your_secure_password
```

Run database migrations along with the seeders:

```bash
# Migrate database and seed roles, admin user, and taxonomies
php artisan migrate --seed

# Create storage symbolic link
php artisan storage:link
```

### 4. Running the Development Services

Run the HTTP server, queue worker, and WebSocket server:

```bash
# Terminal 1: Laravel API Server
php artisan serve --port=8000

# Terminal 2: Laravel Reverb WebSocket Server
php artisan reverb:start --port=8080

# Terminal 3: Background Queue Worker
php artisan queue:work
```

The API will now be accessible at `http://localhost:8000/api/v1`.

---

## 🧪 Automated Testing

FarmLink features a comprehensive test suite written with **Pest PHP** covering authentication, role boundaries, payment reconciliation, telemetry thresholds, and settings governance.

```bash
# Run all Pest feature and unit tests
php artisan test

# Run a specific test suite
php artisan test --filter=SettingsAndCouponsTest

# Run tests with code coverage report
php artisan test --coverage

# Code style checking & formatting (Laravel Pint)
vendor/bin/pint --test
```

---

## 🔒 Production Deployment Best Practices

1. **Supervisor Process Management**:
   Configure `supervisord` to manage the background queue worker and Reverb server:
   ```ini
   [program:farmlink-reverb]
   command=php /path/to/artisan reverb:start
   autostart=true
   autorestart=true
   user=www-data
   numprocs=1
   ```
2. **Reverse Proxy (Nginx)**:
   Proxy `/api` and regular HTTP traffic to PHP-FPM, and upgrade WebSocket traffic targeting `/app` to Reverb on port 8080.
3. **Database Indexing**:
   Ensure PostgreSQL indexes on `ponds (farm_id, created_at)`, `orders (status, user_id)`, and `pond_telemetries (pond_id, recorded_at)` are actively maintained.
4. **CORS & Stateful Domains**:
   Configure `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS` in `.env` to whitelist only your production frontend domain.

---

## 📄 License

The FarmLink Backend is open-source software licensed under the [MIT License](LICENSE).
