# =============================================================================
# ECG HTTP Upload Server Container
# Python-based HTTP server for ECG PDF/XML uploads from Philips TC35
# =============================================================================

FROM python:3.11-alpine

LABEL maintainer="SmartWard Team"
LABEL description="ECG HTTP Upload Server for receiving PDF/XML files"

# Set working directory
WORKDIR /app/ecg

# Install system dependencies (for healthcheck and general utilities)
RUN apk add --no-cache \
    bash \
    curl \
    netcat-openbsd \
    && rm -rf /var/cache/apk/*

# Copy ECG server files
COPY ecg/http_server.py .
COPY ecg/extract_ecg_pdf.py .

# Create directories for uploaded files and logs
RUN mkdir -p ftp_data store

# Environment variables (can be overridden in docker-compose)
ENV ECG_HOST=0.0.0.0
ENV ECG_PORT=8080
ENV ECG_USERNAME=admin
ENV ECG_PASSWORD=admin123

# Expose HTTP port
EXPOSE 8080

# Health check - verify the HTTP server is running
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:8080/ || exit 1

# Run the HTTP server
CMD ["python", "http_server.py"]

