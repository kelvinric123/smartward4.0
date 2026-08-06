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

## Deploying on the server

Point the stack at the new repository via the compose env:

```
INFUSION_IMAGE=kelvinric/infusion_engine:latest
```

then `docker compose pull && docker compose up -d` in the server's
`docker_infusion` folder.
