SmartWard 4.0 - Pantai Hospital Melaka
Built 2026-09-27 17:44 by "build hospital config.bat" (docker_smartward_hub).

Run it on the hospital's server (Docker installed):
  1. Copy this whole folder to the server.
  2. Open a command prompt in the folder and run:
       docker login            (the images are private on Docker Hub)
       docker compose pull
       docker compose up -d
  3. Open http://192.168.0.22:18080

Stop:            docker compose down
Update images:   docker compose pull, then docker compose up -d

Ports on the server: http 18080, https 18443, app 8888,
MySQL 3307, Redis 6380, HL7 3000, ECG 3051,
B.Braun 5001, LDAP 5000.

To change a setting: edit hospital.env, run "build hospital config.bat" again on the
build PC (it offers these values as the defaults), copy the new docker-compose.yml
to the server, and run "docker compose up -d" there.
