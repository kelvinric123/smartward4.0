# =============================================================================
# Infusion Engine - standalone HL7 infusion data service
# MLLP in (6000) -> parse/store (PostgreSQL) -> REST API + management UI (6001)
#
# Build context is infusion_engine/ (run from docker_infusion/):
#   docker build -f Dockerfile -t kelvinric/infusion:latest ..
# =============================================================================

FROM python:3.12-alpine

LABEL maintainer="SmartWard Team"
LABEL description="Infusion engine: receives pump HL7 (MLLP), stores + parses, serves REST API"

ENV PYTHONUNBUFFERED=1 \
    TZ=Asia/Kuala_Lumpur \
    INFUSION_MLLP_HOST=0.0.0.0 \
    INFUSION_MLLP_PORT=6000 \
    INFUSION_API_HOST=0.0.0.0 \
    INFUSION_API_PORT=6001 \
    INFUSION_DB_PATH=/data/infusion.db \
    INFUSION_LOG_DIR=/data/logs

# tzdata so the TZ env var works (timestamps in local hospital time)
RUN apk add --no-cache tzdata

WORKDIR /app

COPY requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r requirements.txt

COPY engine/ /app/engine/

# Non-root user; /data is only used by the SQLite fallback + file logs
RUN addgroup -S infusion && adduser -S infusion -G infusion \
    && mkdir -p /data/logs && chown -R infusion:infusion /data /app

USER infusion

VOLUME /data

# 6000 = MLLP HL7 in, 6001 = REST API + management page
EXPOSE 6000 6001

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD wget -qO- http://127.0.0.1:6001/health >/dev/null 2>&1 || exit 1

CMD ["python", "-m", "engine.main"]
