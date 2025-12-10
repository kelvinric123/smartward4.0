# =============================================================================
# MP5sc Vital Sign Listener Container
# Python-based listener for Philips IntelliVue MP5sc medical monitors
# =============================================================================

FROM python:3.11-alpine

LABEL maintainer="SmartWard Team"
LABEL description="Philips MP5sc Vital Sign Monitor Listener"

# Set working directory
WORKDIR /app/mp5sc_listener

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    procps \
    && rm -rf /var/cache/apk/*

# Copy requirements first for better caching
COPY mp5sc_listener/listener/requirements.txt .

# Install Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Copy listener source files
COPY mp5sc_listener/listener/vital_sign_listener.py .
COPY mp5sc_listener/listener/src ./src

# Create logs directory
RUN mkdir -p logs

# Environment variables (can be overridden in docker-compose)
# API Server Configuration
ENV API_BASE_URL=http://smartward:88/api/v1
ENV API_PASSPHRASE=qmedno1
ENV API_USERNAME=api@qmed.asia
ENV API_PASSWORD=88888888

# Monitor Device Configuration
ENV MONITOR_IP=192.168.0.5

# Polling Configuration
ENV POLL_INTERVAL=2
ENV REFRESH_INTERVAL=10

# Debug Mode
ENV DEBUG_MODE=false

# No port to expose - this is an outbound UDP client connecting to monitor

# Health check - verify the Python process is running
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD pgrep -f "python.*vital_sign_listener" || exit 1

# Run the listener
CMD ["python", "vital_sign_listener.py"]

