# docker_hub_infusion

Build and push the infusion engine image to Docker Hub as
**`kelvinric/infusion_engine`** (the newer repository name; the older
`docker_infusion/build_and_push.bat` pushes the same image as
`kelvinric/infusion`).

The image is built from the shared [`../docker_infusion/Dockerfile`](../docker_infusion/Dockerfile)
with the `infusion_engine/` folder as build context — both scripts produce the
identical engine, only the Docker Hub repository differs.

## Usage

```bat
build_and_push.bat            :: -> kelvinric/infusion_engine:latest
build_and_push.bat v1.1.0     :: -> kelvinric/infusion_engine:v1.1.0 AND :latest
```

Requires `docker login` with an account that can push to `kelvinric/*`.

## Fresh deployment on a NEW server

Only one file is needed on the server: `deploy-compose.yml` (standalone, no
source code, no build). From this Windows machine, one command copies it AND
starts the stack (replace user@host):

```
ssh user@host "mkdir -p ~/infusion && cat > ~/infusion/docker-compose.yml && cd ~/infusion && docker compose pull && docker compose up -d" < deploy-compose.yml
```

Or step by step: `scp deploy-compose.yml user@host:~/infusion/docker-compose.yml`,
then on the server `cd ~/infusion && docker compose pull && docker compose up -d`.

If the Docker Hub repo is private, either `sudo docker login -u kelvinric`
once on the server first, or temporarily set the repository to public on
hub.docker.com while pulling (and back to private after).

Afterwards: check `curl http://127.0.0.1:6001/health`, open port 6000 (MLLP
from the gateway) and 6001 (REST/UI) in the firewall, and point SmartWard's
integration page at `http://<server-ip>:6001`.

## Pulling on the server

One-time per server (the `kelvinric/*` repositories are private):

```bash
sudo docker login -u kelvinric
```

Then either use the updater script (copy this folder to the server next to
`docker_infusion/`):

```bash
./update.sh            # pull :latest, persist INFUSION_IMAGE in .env, restart, health check
./update.sh v1.1.0     # pull a specific version
```

or do it manually in the server's `docker_infusion` folder:

```bash
echo INFUSION_IMAGE=kelvinric/infusion_engine:latest >> .env
docker compose pull && docker compose up -d
```

The `update.sh` script also persists the chosen tag into `.env`, so a later
plain `docker compose up -d` keeps running the same image, and prunes old
image layers afterwards.
