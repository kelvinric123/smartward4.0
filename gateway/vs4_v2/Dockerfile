FROM python:3.11-alpine

LABEL maintainer="SmartWard Team"
LABEL description="Philips SureSigns VS4 HL7 Gateway V2 (store-and-forward)"

WORKDIR /app/vs4_v2

RUN apk add --no-cache \
    bash \
    curl \
    procps

COPY vs4_v2/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY vs4_v2/listener ./listener

ENV PYTHONUNBUFFERED=1
ENV API_BASE_URL=http://smartward:88/api/v1
ENV API_PASSPHRASE=qmedno1
ENV API_USERNAME=api@qmed.asia
ENV API_PASSWORD=88888888
ENV LISTEN_HOST=0.0.0.0
ENV LISTEN_PORT=4000
ENV USE_API_DEVICES=false
ENV DEVICE_FETCH_INTERVAL=60
ENV SEND_INTERVAL=3
ENV SEND_BATCH_SIZE=20
ENV RETRY_BASE_SECONDS=5
ENV RETRY_MAX_SECONDS=300
ENV QUEUE_DB_PATH=/app/vs4_v2/listener/data/vs4_v2.sqlite
ENV DEBUG_MODE=false

EXPOSE 4000

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD pgrep -f "python.*main.py" || exit 1

CMD ["python", "listener/main.py"]
