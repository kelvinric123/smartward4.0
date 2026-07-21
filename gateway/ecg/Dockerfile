FROM python:3.11-alpine

LABEL maintainer="SmartWard Team"
LABEL description="ECG Gateway (TC35 store-and-forward to SmartWard)"

WORKDIR /app/ecg_gateway

RUN apk add --no-cache \
    bash \
    curl \
    procps

COPY ecg/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY ecg/listener ./listener

ENV PYTHONUNBUFFERED=1
ENV API_BASE_URL=http://smartward:88/api/v1
ENV API_PASSPHRASE=qmedno1
ENV API_USERNAME=api@qmed.asia
ENV API_PASSWORD=88888888
ENV LISTEN_HOST=0.0.0.0
ENV LISTEN_PORT=3050
ENV ECG_USERNAME=admin
ENV ECG_PASSWORD=admin123
ENV QUEUE_DB_PATH=/app/ecg_gateway/listener/data/ecg_gateway.sqlite
ENV FILES_DIR=/app/ecg_gateway/listener/data/files
ENV DEBUG_MODE=false

EXPOSE 3050

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD curl -f http://localhost:3050/ || exit 1

CMD ["python", "listener/main.py"]
