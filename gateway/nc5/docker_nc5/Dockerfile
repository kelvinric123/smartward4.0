# =============================================================================
# Comen NC5 gateway - HL7/MLLP listener + store-and-forward to SmartWard
# MLLP in (2576) -> patient query answered from SmartWard -> vitals queued and
# POSTed to /api/v1/vital-signs.
#
# Standalone image, versioned and deployed independently of the SmartWard
# stack. Build context is gateway/nc5/ (run from docker_nc5/):
#   docker build -f Dockerfile -t kelvinric/nc5:latest ..
# =============================================================================

FROM python:3.12-alpine

LABEL maintainer="SmartWard Team"
LABEL description="Comen NC5 HL7 gateway: patient query + vital signs to SmartWard"

ENV PYTHONUNBUFFERED=1 \
    TZ=Asia/Kuala_Lumpur \
    LISTEN_HOST=0.0.0.0 \
    LISTEN_PORT=2576 \
    QUEUE_DB_PATH=/data/nc5.sqlite \
    RAW_DIR=/data/raw

# tzdata so TZ applies: measured_at is charted in hospital local time
RUN apk add --no-cache tzdata

WORKDIR /app/nc5

COPY requirements.txt /app/nc5/requirements.txt
RUN pip install --no-cache-dir -r requirements.txt

COPY listener/ /app/nc5/listener/

# Non-root; /data holds the outbox queue (the only state that must survive a
# restart - unsent readings live there while SmartWard is unreachable)
RUN addgroup -S nc5 && adduser -S nc5 -G nc5 \
    && mkdir -p /data/raw && chown -R nc5:nc5 /data /app

USER nc5

VOLUME /data

EXPOSE 2576

# Prove the listener is actually accepting MLLP connections, not just that the
# process exists. Loopback connects are not logged as monitors (see main.py).
HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD python -c "import os,socket;socket.create_connection(('127.0.0.1',int(os.environ.get('LISTEN_PORT','2576'))),3).close()" || exit 1

CMD ["python", "-u", "listener/main.py"]
