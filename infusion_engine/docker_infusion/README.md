# docker_infusion

Docker packaging for the Infusion Engine — two containers:

| Container | Image | Purpose |
|---|---|---|
| `infusion-engine` | `kelvinric/infusion` | MLLP HL7 in (:6000), REST API + management UI (:6001) |
| `infusion-db` | `postgres:16-alpine` | PostgreSQL storage (internal only, volume `infusion_pgdata`) |

| Port | Purpose |
|---|---|
| 6000 | HL7 MLLP in — point the pump gateway (NUC) here |
| 6001 | REST API + management page — point SmartWard / your browser here |

Management page: **http://localhost:6001/** (pumps dashboard, patient lookup
by MRN, HL7 message log, demo data controls, API docs).

The engine source lives in the parent folder (`../engine/`). Without `INFUSION_DB_HOST`
the engine falls back to embedded SQLite — Postgres is only wired up here in
the compose stack.

## Run

```bash
docker compose up -d --build
```

Check:

```bash
curl http://localhost:6001/health
```

Send the test samples from the host (from `..`):

```bash
python -m tests.send_samples 127.0.0.1 6000
curl http://localhost:6001/api/pumps
```

## Configure

Defaults work out of the box. To override, copy `docker.env` to `.env`
next to the compose file (ports, DB password, `INFUSION_API_KEY`, retention,
timezone, demo on/off).

## Build & push image

```bat
build_and_push.bat            :: kelvinric/infusion:latest
build_and_push.bat v1.1.0     :: kelvinric/infusion:v1.1.0
```

## Database access

Postgres is not published to the host by default. To browse it, uncomment the
`6432:5432` port mapping in `docker-compose.yml`, or:

```bash
docker exec -it infusion-db psql -U infusion -d infusion -c "SELECT COUNT(*) FROM messages;"
```

## Logs / maintenance

```bash
docker logs -f infusion-engine
docker logs -f infusion-db
docker compose down            # data survives in the infusion_pgdata volume
docker compose down -v         # ... this deletes the database too
```
