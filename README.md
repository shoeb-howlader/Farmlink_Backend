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

## 📡 WebRTC Coturn TURN/STUN Server (Production Ubuntu Setup)

### ❓ Why a TURN Server is Essential
In rural Bangladesh and developing agricultural corridors, mobile operators (Grameenphone, Banglalink, Robi, Teletalk) utilize **Carrier-Grade NAT (CGNAT)** and symmetric firewall topologies. This prevents direct peer-to-peer WebRTC connections between farmers on mobile networks and remote veterinary doctors or aquaculture consultants.

**Coturn** acts as a standardized STUN/TURN media relay (RFC 5766 / RFC 5389 / RFC 8489) that guarantees **100% video and audio call connection success** across restrictive firewalls and BDIX networks.

---

### 🖥️ Dedicated Standalone Ubuntu Server Setup (Not in Coolify Docker)

Running Coturn directly on a standalone Ubuntu host via `systemd` (completely outside Coolify's Docker environment) is the **industry-standard, high-performance architecture** for WebRTC relay servers.

> **Why outside Coolify/Docker?**
> Coturn allocates thousands of dynamic media relay UDP ports (`49152–65535`). Running this inside a Docker container creates huge Docker NAT/`docker-proxy` overhead, potential port allocation exhaustion, and packet latency. Running natively on Ubuntu gives the Linux kernel direct, line-rate UDP throughput for real-time video/audio.

#### 1. Install Coturn on Ubuntu
```bash
sudo apt update
sudo apt install -y coturn
```

#### 2. Configure `/etc/turnserver.conf`
Backup the default configuration and paste the production settings:
```bash
sudo cp /etc/turnserver.conf /etc/turnserver.conf.backup
sudo nano /etc/turnserver.conf
```

Paste the following configuration:
```ini
# ==============================================================================
# Coturn TURN/STUN Server Configuration for FarmLink
# Location: /etc/turnserver.conf
# ==============================================================================

# Listening endpoints (UDP & TCP)
listening-port=3478
tls-listening-port=5349

# Your Ubuntu Server's Public IP address (BDIX / VPS)
listening-ip=165.99.219.252
relay-ip=165.99.219.252
external-ip=165.99.219.252

# WebRTC Realm & Ephemeral REST API Authentication (RFC 5766)
realm=farmlinkcare.com
fingerprint
use-auth-secret
static-auth-secret=farmlink_bdix_turn_secret_2026_x89q4w
stale-nonce=600

# Dynamic UDP Relay Port Range
min-port=49152
max-port=65535

# Hardening & Anti-Abuse
no-cli
no-multicast-peers
no-tlsv1
no-tlsv1_1
no-rfc5780
no-stun-backward-compatibility
response-origin-only-with-rfc5780
user-quota=10
total-quota=1000

# Logging
syslog
verbose
```

#### 3. Enable the Coturn Daemon
Edit `/etc/default/coturn` to ensure the daemon is enabled:
```bash
sudo sed -i 's/#TURNSERVER_ENABLED=1/TURNSERVER_ENABLED=1/' /etc/default/coturn
```

#### 4. Firewall Rules (UFW / Security Groups)
Open the required WebRTC STUN, TURN, and UDP relay ports:
```bash
# STUN & TURN Default Ports
sudo ufw allow 3478/tcp
sudo ufw allow 3478/udp

# Secure TLS/DTLS TURNS Ports
sudo ufw allow 5349/tcp
sudo ufw allow 5349/udp

# Dynamic Media Relay UDP Range
sudo ufw allow 49152:65535/udp
sudo ufw reload
```

#### 5. Start and Verify the Service
```bash
sudo systemctl daemon-reload
sudo systemctl enable coturn
sudo systemctl restart coturn
sudo systemctl status coturn
```

---

### ⚙️ Connecting Laravel Backend to Coturn

Add the Coturn connection parameters to your Laravel `.env` file (e.g. on Coolify or host):

```env
# ------------------------------------------------------------------------------
# 12. WEBRTC AUDIO & VIDEO CALLING (Coturn TURN/STUN - Standalone Ubuntu BDIX Server)
# ------------------------------------------------------------------------------
COTURN_HOST=165.99.219.252           # Public IP or Domain of the standalone Ubuntu Coturn server
COTURN_PORT=3478                    # Default UDP/TCP port
COTURN_TLS_PORT=5349                # Secure TLS port
COTURN_REALM=farmlinkcare.com       # Matches 'realm' in /etc/turnserver.conf
COTURN_SECRET=farmlink_bdix_turn_secret_2026_x89q4w # Matches 'static-auth-secret'
COTURN_TTL=86400                    # Credential lifetime (seconds)
```

---

### 🔐 How Ephemeral Credentials Work

The backend implements standard **WebRTC REST API Ephemeral HMAC Authentication**:

1. A client initiates a telemedicine call and queries `GET /api/v1/chat/turn-credentials`.
2. Laravel constructs a time-limited username: `{$timestamp}:{$user_id}`.
3. Laravel generates an HMAC-SHA1 signature: `base64(hash_hmac('sha1', $username, $secret, true))`.
4. The client receives the `iceServers` array with valid STUN, TURN (UDP/TCP), and TURNS (TLS) URLs along with the temporary username and password.
5. Coturn validates the HMAC signature using the same `static-auth-secret` without requiring a persistent database connection.

---

### 🧪 Verifying Your TURN Server

#### 1. Test Endpoint via API:
```bash
curl -X GET "https://api.farmlinkcare.com/api/v1/chat/turn-credentials" \
     -H "Authorization: Bearer YOUR_SANCTUM_TOKEN" \
     -H "Accept: application/json"
```

Expected JSON response:
```json
{
  "success": true,
  "data": {
    "iceServers": [
      {
        "urls": [
          "stun:turn.farmlinkcare.com:3478",
          "stun:stun.l.google.com:19302"
        ]
      },
      {
        "urls": [
          "turn:turn.farmlinkcare.com:3478?transport=udp",
          "turn:turn.farmlinkcare.com:3478?transport=tcp",
          "turns:turn.farmlinkcare.com:5349?transport=tcp"
        ],
        "username": "1792121600:42",
        "credential": "generated-hmac-sha1-base64-token"
      }
    ],
    "ttl": 86400,
    "host": "turn.farmlinkcare.com",
    "port": 3478,
    "tls_port": 5349
  }
}
```

#### 2. Test Media Relay via WebRTC Trickle ICE:
1. Open the [WebRTC Trickle ICE Tool](https://webrtc.github.io/samples/src/content/peerconnection/trickle-ice/).
2. Remove standard Google STUN servers.
3. Add your TURN Server:
   - **TURN URI**: `turn:YOUR_SERVER_IP:3478?transport=udp`
   - **TURN username**: Value from API response
   - **TURN password**: Value from API response
4. Click **Gather candidates**.
5. When candidate type `relay` appears with your server's public IP and priority status, your TURN server is 100% operational!

---

## 📄 License

The FarmLink Backend is open-source software licensed under the [MIT License](LICENSE).

