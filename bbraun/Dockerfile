# =============================================================================
# B.Braun HL7 Infusion Pump Listener Container
# Python-based MLLP listener for B.Braun infusion pump messages
# =============================================================================

FROM python:3.11-alpine

LABEL maintainer="SmartWard Team"
LABEL description="B.Braun HL7 Infusion Pump Listener using MLLP protocol"

# Set working directory
WORKDIR /app/bbraun

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    netcat-openbsd \
    && rm -rf /var/cache/apk/*

# Copy requirements first for better caching
COPY bbraun/requirements.txt .

# Install Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Copy listener source files
COPY bbraun/bbraun_hl7_listener.py .

# Create logs directory
RUN mkdir -p logs

# Environment variables (can be overridden in docker-compose)
# Listener Settings
ENV BBRAUN_HOST=0.0.0.0
ENV BBRAUN_PORT=5001

# Database Configuration (connects to smartward container's internal MySQL)
ENV DB_HOST=smartward
ENV DB_PORT=3306
ENV DB_NAME=smartward
ENV DB_USER=root
ENV DB_PASSWORD=smartward_secret

# Development Mode Settings (disabled by default)
ENV DEV_MODE=false
ENV DEV_ASSIGN_MRN=

# Python unbuffered for real-time logs in Docker
ENV PYTHONUNBUFFERED=1

# Expose MLLP port for B.Braun infusion pumps
EXPOSE 5001

# Health check - verify the listener is running and accepting connections
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD python -c "import socket; s=socket.socket(); s.connect(('localhost',5001)); s.close()" || exit 1

# Run the listener
CMD ["python", "bbraun_hl7_listener.py"]
