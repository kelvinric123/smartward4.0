# rpa_cplus_smartward: C+ Bed Management -> SmartWard sync.
# Built and pushed by build_and_push.ps1 as kelvinric/rpa_cplus_smartward.
#
# Settings (C+ login, SmartWard URL and token) come from an env file at run
# time and are never in the image; see docker-compose.yml. The login state,
# cookie jar, logs and scratch live on the /data volume.
FROM python:3.12-slim

WORKDIR /app

# Every C+ call shells out to curl. tzdata so TZ=Asia/Kuala_Lumpur gives
# local times in the log and the right "today" for reservation requests.
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates tzdata \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# .dockerignore keeps .env, cookie jars, his_state.json, scratch/ and logs/ out.
COPY . .

ENV PYTHONUNBUFFERED=1 \
    TZ=Asia/Kuala_Lumpur \
    RPA_DATA_DIR=/data

VOLUME ["/data"]

# Sync now and every SYNC_INTERVAL_MINUTES. docker-compose.yml decides
# whether it may log in to C+ by itself (--allow-login).
CMD ["python", "-u", "sync_smartward.py"]
