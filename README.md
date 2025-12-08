# SmartWard 4.0

A comprehensive hospital ward management system built with Laravel and Swoole for high-performance patient monitoring, bed management, and HL7 ADT integration.

## Features

- **Patient Management** - Admit, discharge, and transfer patients
- **Bed Management** - Real-time bed status and occupancy tracking
- **Vital Signs Monitoring** - Integration with medical devices via API
- **Infusion Pump Integration** - Monitor IV infusions in real-time
- **HL7 ADT Integration** - Receive ADT messages from Hospital Information Systems
- **LDAP Authentication** - Enterprise single sign-on support
- **High Performance** - Built on Laravel Octane with Swoole

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         SmartWard System                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────────────┐         ┌──────────────────────┐          │
│  │    smartward4        │         │   smartward4-adt     │          │
│  │    (Laravel App)     │◄───────►│   (HL7 Listener)     │          │
│  │                      │         │                      │          │
│  │  • PHP 8.3 + Swoole  │         │  • Python 3.11       │          │
│  │  • MySQL 8.0         │         │  • MLLP Protocol     │          │
│  │  • Redis 7           │         │  • Port 3000         │          │
│  │  • Port 80           │         │                      │          │
│  └──────────────────────┘         └──────────────────────┘          │
│           │                                  ▲                       │
│           │                                  │                       │
│           ▼                                  │                       │
│  ┌──────────────────────┐         ┌──────────────────────┐          │
│  │   Web Browser        │         │   HIS System         │          │
│  │   (Dashboard)        │         │   (HL7 Messages)     │          │
│  └──────────────────────┘         └──────────────────────┘          │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

## Project Structure

```
smartward4/
├── app/                    # Laravel application code
├── config/                 # Configuration files
├── database/               # Migrations and seeders
├── resources/views/        # Blade templates
├── routes/                 # Route definitions
│
├── docker_swoole/          # Docker deployment files
│   ├── Dockerfile          # Main Laravel container (PHP, MySQL, Redis)
│   ├── docker-compose.yml  # Orchestrates all containers
│   ├── docker.env          # Environment configuration
│   └── conf/               # Configuration files for services
│
├── HL7/                    # HL7 ADT Integration
│   ├── Dockerfile          # Python ADT listener container
│   ├── adt_listener.py     # MLLP server for HL7 messages
│   ├── requirements.txt    # Python dependencies
│   └── sample_adt_sender.py # Test utility
│
└── electron/               # Desktop application (optional)
```

## Docker Deployment

### Prerequisites

- Docker Engine 20.10+
- Docker Compose 2.0+

### Quick Start

```bash
# Navigate to docker directory
cd docker_swoole

# Start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down
```

### Container Details

| Container | Service | Port | Description |
|-----------|---------|------|-------------|
| `smartward4` | Laravel + MySQL + Redis | 80 (web), 3307 (mysql), 6380 (redis) | Main application |
| `smartward4-adt` | Python HL7 Listener | 3000 | Receives HL7 ADT messages |

### Inter-Container Communication

The containers communicate via Docker's internal network using service names:

- Laravel connects to ADT listener: `adt:3000`
- ADT listener calls Laravel API: `http://smartward:80/api/adt/message`

### Environment Configuration

Edit `docker_swoole/docker.env` for configuration:

```env
# Application
APP_NAME=SmartWard
APP_ENV=production
APP_URL=http://localhost

# Database (internal)
DB_DATABASE=smartward
DB_USERNAME=root
DB_PASSWORD=smartward_secret

# Performance
OCTANE_WORKERS=16
OCTANE_TASK_WORKERS=16

# ADT Integration (Docker service name)
ADT_HOST=adt
ADT_PORT=3000
```

### Useful Commands

```bash
# Rebuild containers after code changes
docker-compose up -d --build

# View specific container logs
docker-compose logs -f smartward
docker-compose logs -f adt

# Access Laravel container shell
docker exec -it smartward4 bash

# Run artisan commands
docker exec -it smartward4 php artisan migrate
docker exec -it smartward4 php artisan cache:clear

# Check container status
docker-compose ps
```

## Local Development

### Requirements

- PHP 8.2+
- Composer
- MySQL 8.0+
- Redis (optional)
- Node.js 18+ (for assets)

### Setup

```bash
# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate --seed

# Build assets
npm run build

# Start development server
php artisan serve
# Or with Octane
php artisan octane:start
```

## HL7 ADT Integration

The system receives HL7 v2.x ADT messages via MLLP protocol:

### Supported Events

| Event | Description |
|-------|-------------|
| A01 | Patient Admit |
| A02 | Patient Transfer |
| A03 | Patient Discharge |

### Testing ADT Messages

```bash
# From the HL7 directory
python sample_adt_sender.py --host localhost --port 3000 --event A01
```

### Configuration

Configure ADT mappings in the web interface:
- **Hospital Mapping** - Map HIS facility codes to SmartWard hospitals
- **Ward Mapping** - Map HIS ward codes to SmartWard wards
- **Bed Mapping** - Map HIS bed codes to SmartWard beds
- **Doctor Mapping** - Map HIS doctor codes to SmartWard consultants

## API Endpoints

### Vital Signs API

```
POST /api/v1/vital-signs
Authorization: Bearer <token>
```

### ADT Message API

```
POST /api/adt/message
Content-Type: application/json
```

## Troubleshooting

### ADT Shows "Offline" in Docker

Ensure the `ADT_HOST` environment variable is set to `adt` (Docker service name):

```env
ADT_HOST=adt
ADT_PORT=3000
```

### Cannot Connect to Database

Check that MySQL is running inside the container:

```bash
docker exec -it smartward4 mysql -u root -p
```

### Clear Cache After Config Changes

```bash
docker exec -it smartward4 php artisan config:clear
docker exec -it smartward4 php artisan cache:clear
```

## License

Proprietary - All rights reserved.
