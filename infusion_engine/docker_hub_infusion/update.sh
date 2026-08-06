#!/bin/sh
# ===========================================================================
# Server-side updater: pull the kelvinric/infusion_engine image and restart
# the stack. Run on the deployment server (Linux), safe to re-run any time.
# Usage:
#   ./update.sh            -> kelvinric/infusion_engine:latest
#   ./update.sh v1.1.0     -> kelvinric/infusion_engine:v1.1.0
# Requires: docker login done once for the private kelvinric/* repositories.
# ===========================================================================
set -e

TAG="${1:-latest}"
IMAGE="kelvinric/infusion_engine:$TAG"
COMPOSE_DIR="$(cd "$(dirname "$0")/../docker_infusion" && pwd)"

echo "Pulling $IMAGE"
docker pull "$IMAGE"

cd "$COMPOSE_DIR"

# Persist the image choice so plain "docker compose up -d" keeps using it
if [ -f .env ] && grep -q '^INFUSION_IMAGE=' .env; then
    sed -i "s|^INFUSION_IMAGE=.*|INFUSION_IMAGE=$IMAGE|" .env
else
    echo "INFUSION_IMAGE=$IMAGE" >> .env
fi

echo "Restarting the stack with $IMAGE"
docker compose up -d

docker image prune -f >/dev/null

echo "Done. Engine health:"
sleep 3
curl -s http://127.0.0.1:6001/health && echo "" || echo "(engine not answering yet - check: docker logs infusion-engine)"
