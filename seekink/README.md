# Seekink E-Ink Display Admin System

Docker configuration for the Seekink E-Ink display management system.

## Prerequisites

- Docker and Docker Compose installed
- Network access to Docker Hub for pulling the image

## Quick Start

### 1. Pull the Docker Image

```bash
docker pull q546877436/seekink-ubuntu:1.0.2
```

### 2. Configure Environment

Edit `seekink.env` and update the following:

```env
# REQUIRED: Replace with your server's public/external IP address
SEEKINK_SERVER_IP=192.168.0.1

# The external port for web access (default: 8088)
SEEKINK_HOST_PORT=8088
```

> **Important:** The `SERVER_IP` and `HOST_PORT` must be set correctly for E-Ink device image updates to work.

### 3. Start the Container

```bash
cd seekink
docker-compose up -d
```

### 4. Access the Admin Panel

Open your browser and navigate to:
```
http://localhost:8088
```

**Default Login Credentials:**
- Username: `admin`
- Password: `admin123`

> ⚠️ **Security:** Change the default password after your first login!

## Port Configuration

| Port | Service | Description |
|------|---------|-------------|
| 8088 | Java | Web Admin Interface (external), maps to internal 8080 |
| 8003 | TCP | Device Communication Protocol |
| 1883 | MQTT | Message Queue (optional, for MQTT-enabled base stations) |

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `SEEKINK_SERVER_IP` | 192.168.0.1 | Public/external IP address (required for device updates) |
| `SEEKINK_HOST_PORT` | 8088 | External Java port (must match port mapping) |
| `SEEKINK_MQTT_ENABLE` | false | Enable MQTT only if base station supports it |
| `SEEKINK_JAVA_PORT` | 8088 | External mapping for Java port |
| `SEEKINK_TCP_PORT` | 8003 | External mapping for TCP port |
| `SEEKINK_MQTT_PORT` | 1883 | External mapping for MQTT port |
| `SEEKINK_CPU_LIMIT` | 2 | CPU limit for container |
| `SEEKINK_MEMORY_LIMIT` | 1G | Memory limit for container |

## Manual Docker Run (Alternative)

If you prefer not to use docker-compose:

```bash
docker run -itd \
  --name seekink-admin \
  -e SERVER_IP=192.168.0.1 \
  -e HOST_PORT=8088 \
  -e MQTT_ENABLE=false \
  -p 8088:8080 \
  -p 8003:8003 \
  -p 1883:1883 \
  q546877436/seekink-ubuntu:1.0.2
```

## Container Management

```bash
# View logs
docker logs -f smartward4-seekink

# Stop the container
docker-compose down

# Restart the container
docker-compose restart

# Remove container and volumes
docker-compose down -v
```

## Troubleshooting

### Image updates not working on devices
- Ensure `SERVER_IP` is set to the correct public/external IP address
- Ensure `HOST_PORT` matches the external port mapping (8088)
- Check that the firewall allows traffic on ports 8088, 8003, and 1883

### Cannot access admin panel
- Verify the container is running: `docker ps`
- Check container logs: `docker logs smartward4-seekink`
- Ensure port 8088 is not blocked by firewall

### MQTT connection issues
- Set `SEEKINK_MQTT_ENABLE=true` only if your base station supports MQTT
- Ensure port 1883 is accessible
