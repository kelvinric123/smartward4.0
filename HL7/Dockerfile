# =============================================================================
# HL7 ADT Listener Container
# Python-based MLLP listener for ADT messages
# =============================================================================

FROM python:3.11-alpine

LABEL maintainer="SmartWard Team"
LABEL description="HL7 ADT Message Listener using MLLP protocol"

# Set working directory
WORKDIR /app/HL7

# Install system dependencies (for requests/SSL support and healthcheck)
RUN apk add --no-cache \
    bash \
    curl \
    netcat-openbsd \
    && rm -rf /var/cache/apk/*

# Copy requirements first for better caching
COPY HL7/requirements.txt .

# Install Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Copy HL7 listener files
COPY HL7/adt_listener.py .
COPY HL7/sample_adt_sender.py .
COPY HL7/test_sender.py .

# Create directories for logs and received messages
RUN mkdir -p logs received_messages

# Environment variables (can be overridden in docker-compose)
ENV HL7_HOST=0.0.0.0
ENV HL7_PORT=3000
ENV LARAVEL_API_URL=http://smartward:80/api/adt/message
ENV LARAVEL_API_KEY=

# Expose MLLP port
EXPOSE 3000

# Health check - verify the listener is running and accepting connections
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD nc -z localhost 3000 || exit 1

# Run the listener
CMD ["python", "adt_listener.py"]









