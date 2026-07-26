# docker_nc5

Docker packaging for the Comen NC5 gateway. A **standalone stack** — its own
image, network and volume — deployed on the SmartWard NAS (`192.168.0.88`) only
when an NC5 is in use, and removable without touching the SmartWard stack.

| Container | Image | Purpose |
|---|---|---|
| `nc5-gateway` | `kelvinric/nc5` | HL7/MLLP in (:2576), patient query answered from SmartWard, vitals queued and POSTed to `/api/v1/vital-signs` |

| Port | Purpose |
|---|---|
| 2576 | HL7 MLLP in — point the NC5's HL7 export **and** patient query here |

The NC5 opens one long-lived connection to 2576 and uses it for both jobs. The
gateway talks back to SmartWard over plain HTTP, like any other API client — no
shared database, no shared docker network.

```
   NC5 monitor  --HL7/MLLP 2576-->  nc5-gateway  --HTTP 18080-->  SmartWard
   (ward LAN)                       (NAS, own stack)              (NAS)
```

## Deploy on the NAS

Everything has a working default, so no configuration file is required.

1. **Build and push the image** from the dev machine (the NAS only pulls):

   ```bat
   build_and_push.bat            :: kelvinric/nc5:latest
   build_and_push.bat v1.0.0     :: kelvinric/nc5:v1.0.0
   ```

2. **Let the NAS pull private images.** UGOS's GUI registry credential is not
   used by project pulls — do this once per NAS over SSH:

   ```bash
   ssh drtai@192.168.0.88
   sudo docker login -u kelvinric
   ```

3. **Create the project.** In UGOS → Docker → Projects, add a new project using
   this `docker-compose.yml`. Or from a shell on the NAS:

   ```bash
   docker compose pull && docker compose up -d
   ```

4. **Point the NC5 at it.** On the monitor, set the HL7 server / patient query
   destination to **192.168.0.88 port 2576**.

5. **Confirm.** Within ~30 seconds the gateway appears at
   <http://192.168.0.88:18080/vital-sign-integration> under **Qmed Gateways** as
   `NC5-01`, with queue depth and connected monitors in its heartbeat.

To retire it: `docker compose down` (queued readings are kept) — SmartWard and
every other gateway are unaffected.

## Baked-in credentials

| Setting | Value | Where it comes from |
|---|---|---|
| `API_BASE_URL` | `http://192.168.0.88:18080/api/v1` | SmartWard's published HTTP port on the NAS |
| `API_PASSPHRASE` | `qmedno1` | `VITAL_SIGN_API_PASSPHRASE` in SmartWard's env |
| `API_USERNAME` / `API_PASSWORD` | `api@qmed.asia` / `88888888` | Vital Sign Integration → API Users |
| `GATEWAY_ID` | `NC5-01` | Creates/identifies the Qmed Gateways row |

If this NAS uses a different passphrase or API user, override
`NC5_API_PASSPHRASE` / `NC5_API_USERNAME` / `NC5_API_PASSWORD` (see
`docker.env`). A wrong credential shows up immediately in the log as
`HTTP 401` on the heartbeat.

If the NAS ever changes IP, only `NC5_API_BASE_URL` needs updating.

## Run locally

Build and run without pushing anything:

```bash
docker build -f Dockerfile -t kelvinric/nc5:latest ..
docker compose up -d
docker logs -f nc5-gateway
```

Send simulated NC5 traffic from the repo (`gateway/nc5/`):

```bash
python test_sender.py 127.0.0.1 2576 <an-MRN-in-SmartWard>
```

You should see the query answered with the patient's real name/ward/bed, then
`Reading from ... [queued]` followed by `Sent ...`.

## Configure

Defaults work as-is. To override, copy `docker.env` to `.env` next to the
compose file. Every setting is documented there and in
`../listener/.env.example`.

Common ones:

* `NC5_RECORD_INTERVAL` — how often continuous vitals are charted (default 300s;
  a completed NIBP is always charted immediately).
* `NC5_PATIENT_NAME_ORDER` — how SmartWard's single name field becomes HL7
  `Family^Given` on the monitor's display.
* `NC5_SAVE_RAW=true` — archive every received HL7 message under `/data/raw`
  in the volume when a monitor is behaving unexpectedly.
* `NC5_GATEWAY_ID` — run a second NC5 gateway by giving it a different id (and
  a different host port).

## Logs / maintenance

```bash
docker logs -f nc5-gateway
docker exec -it nc5-gateway ls -la /data     # queue + raw archive
docker compose down          # queued readings survive in the nc5_data volume
docker compose down -v       # ... this deletes unsent readings too
```

The queue is the thing to protect: if SmartWard is down, readings accumulate in
`/data/nc5.sqlite` and are delivered when it returns. `docker compose down -v`
throws them away.

## Updating

```bat
build_and_push.bat v1.1.0
```

then on the NAS pull the new tag (`NC5_IMAGE=kelvinric/nc5:v1.1.0` in `.env`, or
re-pull `:latest`) and recreate the project. The volume — and anything still
queued in it — survives.
