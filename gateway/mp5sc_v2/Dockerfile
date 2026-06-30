FROM python:3.11-alpine

LABEL maintainer="SmartWard Team"
LABEL description="Philips MP5sc Vital Sign Listener V2"

WORKDIR /app/mp5sc_v2

RUN apk add --no-cache \
    bash \
    curl \
    procps

COPY mp5sc_v2/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY mp5sc_v2/listener ./listener

ENV PYTHONUNBUFFERED=1
ENV API_BASE_URL=http://smartward:88/api/v1
ENV API_PASSPHRASE=qmedno1
ENV API_USERNAME=api@qmed.asia
ENV API_PASSWORD=88888888
ENV USE_API_DEVICES=true
ENV DEVICE_FETCH_INTERVAL=60
ENV POLL_INTERVAL=2
ENV REFRESH_INTERVAL=10
ENV SEND_INTERVAL=3
ENV SEND_BATCH_SIZE=20
ENV RETRY_BASE_SECONDS=5
ENV RETRY_MAX_SECONDS=300
ENV QUEUE_DB_PATH=/app/mp5sc_v2/listener/data/mp5sc_v2.sqlite
ENV DEBUG_MODE=false

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD pgrep -f "python.*main.py" || exit 1

CMD ["python", "listener/main.py"]
