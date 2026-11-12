#!/bin/sh
# Read-only trace: ADT messages vs SmartWard bed assignment for some beds.
# Nothing is written: SELECTs against the DB, plus grep/ls in the two containers.
#
# Run on the Docker host (use sudo if docker needs it):
#   sh adt_bed_trace.sh D522 D516 > adt_trace_D522_D516.txt 2>&1
# Options (env): DAYS=30  APP=<app container>  ADT=<listener container>
#   Containers are found automatically: hub-smartward4 / hub-smartward4-adt (docker_smartward_hub)
#   or smartward4 / smartward4-adt (docker_swoole, e.g. PHKL).

set -u
DAYS=${DAYS:-30}
running() { docker ps --format '{{.Names}}' | grep -qx "$1"; }
if [ -z "${APP:-}" ]; then running hub-smartward4 && APP=hub-smartward4 || APP=smartward4; fi
if [ -z "${ADT:-}" ]; then running hub-smartward4-adt && ADT=hub-smartward4-adt || ADT=smartward4-adt; fi
[ $# -gt 0 ] || set -- D522 D516

case $DAYS in ''|*[!0-9]*) echo "DAYS must be a number"; exit 1 ;; esac
IN=""; RX=""
for b in "$@"; do
    case $b in ''|*[!A-Za-z0-9_-]*) echo "Bad bed code: $b"; exit 1 ;; esac
    IN="${IN:+$IN,}'$b'"
    RX="${RX:+$RX|}$b"
done

# mysql/mariadb client inside the app container, using that container's own DB env
MYSQL='MYSQL_PWD="${DB_PASSWORD:-$(sed -n "s/^DB_PASSWORD=//p" /app/.env)}" $(command -v mariadb || command -v mysql) -u"${DB_USERNAME:-root}" "${DB_DATABASE:-smartward}"'
sql() { docker exec -i "$APP" sh -c "$MYSQL $*"; }

# Every MRN that touched these beds: in ADT messages, on the bed now, or in SmartWard's admission log
MRNS="SELECT patient_mrn AS mrn FROM adt_message_logs WHERE created_at >= NOW() - INTERVAL $DAYS DAY AND raw_message REGEXP '$RX'
  UNION SELECT mrn FROM patients WHERE bed_number IN ($IN) OR target_bed_number IN ($IN)
  UNION SELECT p.mrn FROM patients p JOIN beds b ON b.patient_id = p.id WHERE b.bed_number IN ($IN)
  UNION SELECT mrn FROM admission_logs WHERE bed_number IN ($IN) AND created_at >= NOW() - INTERVAL $DAYS DAY"

# One ADT log row, flattened
COLS="l.id, l.created_at AS received_at, l.message_datetime AS msh_time, l.event_type AS ev, l.status,
  l.patient_mrn AS mrn, LEFT(l.patient_name, 22) AS name, l.visit_number AS visit,
  l.assigned_location AS pv1_3_location,
  JSON_UNQUOTE(JSON_EXTRACT(l.parsed_data, '\$.pv1.prior_location')) AS pv1_6_ward,
  JSON_UNQUOTE(JSON_EXTRACT(l.parsed_data, '\$.pv1.bed_status')) AS bed_status,
  JSON_UNQUOTE(JSON_EXTRACT(l.action_taken, '\$.assigned_ward')) AS to_ward,
  JSON_UNQUOTE(JSON_EXTRACT(l.action_taken, '\$.assigned_bed')) AS to_bed,
  JSON_UNQUOTE(JSON_EXTRACT(l.action_taken, '\$.old_bed')) AS old_bed,
  LEFT(COALESCE(l.error_message, JSON_UNQUOTE(JSON_EXTRACT(l.action_taken, '\$.reason')), ''), 70) AS error_or_reason,
  LEFT(CAST(l.action_taken AS CHAR), 140) AS action_taken,
  l.bed_id_ref, LEFT(l.source_ip, 22) AS source_ip"

echo "##### ADT bed trace for: $*   window: last $DAYS days   run: $(date '+%Y-%m-%d %H:%M:%S')"
echo "containers: app=$APP listener=$ADT"
echo
echo "== 0. Deployed ADT bed logic (line numbers of the key branches) =="
docker exec "$APP" sh -c 'grep -n "function findAndAssignBed\|Direct lookup by bed_number\|Initiating DISCHARGE\|function handleA03Discharge" /app/app/Http/Controllers/AdtApiController.php'
echo

sql -t <<SQL
SELECT '== 1. Bed rows with these codes, all wards (ADT matches bed_number OR bed_id with NO ward filter; first row wins) ==' AS section;
SELECT b.id AS bed_pk, b.bed_number, b.bed_id, b.is_active, b.status, w.ward_code, w.ward_name, w.is_active AS ward_active,
       b.patient_id, p.mrn AS occupant_mrn, LEFT(p.name, 25) AS occupant, p.status AS occupant_status, b.updated_at
FROM beds b
LEFT JOIN wards w ON w.id = b.ward_id
LEFT JOIN patients p ON p.id = b.patient_id
WHERE b.bed_number IN ($IN) OR b.bed_id IN ($IN)
ORDER BY b.bed_number, b.id;

SELECT '== 2a. ADT bed mappings for these codes ==' AS section;
SELECT m.id, m.adt_bed_code, m.adt_bed_name, m.is_active, m.bed_id AS bed_pk, b.bed_number, w.ward_code
FROM adt_bed_mappings m
LEFT JOIN beds b ON b.id = m.bed_id
LEFT JOIN wards w ON w.id = b.ward_id
WHERE m.adt_bed_code IN ($IN) OR b.bed_number IN ($IN);

SELECT '== 2b. ADT ward mappings (HIS ward code -> SmartWard ward) ==' AS section;
SELECT m.adt_ward_code, m.adt_ward_name, m.is_active, w.id AS ward_pk, w.ward_code, w.ward_name, w.is_active AS ward_active
FROM adt_ward_mappings m
LEFT JOIN wards w ON w.id = m.ward_id
ORDER BY m.adt_ward_code;

SELECT '== 3. Patients pointing at these beds. The ward dashboard places patients by patients.ward_id + bed_number (is_active=1); beds.patient_id is rewritten from that on every dashboard load ==' AS section;
SELECT p.id, p.mrn, LEFT(p.name, 25) AS name, p.status, p.is_active, w.ward_code, p.bed_number, p.target_bed_number,
       p.visit_number, p.admitted_at, p.pending_discharge_at, p.discharged_at, p.updated_at
FROM patients p
LEFT JOIN wards w ON w.id = p.ward_id
WHERE p.bed_number IN ($IN) OR p.target_bed_number IN ($IN)
   OR p.id IN (SELECT patient_id FROM beds WHERE bed_number IN ($IN) AND patient_id IS NOT NULL)
ORDER BY p.bed_number, p.updated_at;

SELECT '== 4. ADT messages that mention these beds (PV1-3 location, PV1-6 ward name, bed status), oldest first ==' AS section;
SELECT $COLS
FROM adt_message_logs l
WHERE l.created_at >= NOW() - INTERVAL $DAYS DAY
  AND (l.raw_message REGEXP '$RX' OR l.bed_id_ref IN (SELECT id FROM beds WHERE bed_number IN ($IN)))
ORDER BY l.id;

SELECT '== 5. Full ADT history of every MRN involved, per patient (look for missing/failed/ignored A03, A11, A12, A17, A13) ==' AS section;
SELECT $COLS
FROM adt_message_logs l
WHERE l.created_at >= NOW() - INTERVAL $DAYS DAY
  AND l.patient_mrn IN (SELECT mrn FROM ($MRNS) x)
ORDER BY l.patient_mrn, l.id;

SELECT '== 6. SmartWard admission log for these beds / MRNs (source adt vs manual, and who) ==' AS section;
SELECT a.id, a.created_at, a.action, a.source, LEFT(u.name, 20) AS by_user, w.ward_code, a.bed_number, a.mrn,
       LEFT(a.patient_name, 22) AS name, a.admitted_at, LEFT(a.notes, 90) AS notes
FROM admission_logs a
LEFT JOIN users u ON u.id = a.user_id
LEFT JOIN wards w ON w.id = a.ward_id
WHERE a.created_at >= NOW() - INTERVAL $DAYS DAY
  AND (a.bed_number IN ($IN) OR a.mrn IN (SELECT mrn FROM ($MRNS) x))
ORDER BY a.id;

SELECT '== 7a. Hospital-wide: beds holding more than one active patient (dashboard shows only one of them) ==' AS section;
SELECT w.ward_code, p.bed_number, COUNT(*) AS patients,
       GROUP_CONCAT(CONCAT(p.mrn, ' ', p.status, ' upd ', p.updated_at) ORDER BY p.updated_at SEPARATOR ' | ') AS who
FROM patients p
JOIN wards w ON w.id = p.ward_id
WHERE p.is_active = 1 AND p.bed_number IS NOT NULL AND p.bed_number <> ''
GROUP BY w.ward_code, p.bed_number
HAVING COUNT(*) > 1;

SELECT '== 7b. Hospital-wide: bed codes that resolve to more than one active bed (ADT may pick the wrong ward) ==' AS section;
SELECT b.bed_number, COUNT(*) AS beds, GROUP_CONCAT(CONCAT(w.ward_code, '#', b.id) SEPARATOR ', ') AS found_in
FROM beds b
JOIN wards w ON w.id = b.ward_id
WHERE b.is_active = 1
GROUP BY b.bed_number
HAVING COUNT(*) > 1;
SELECT b1.bed_number AS code, CONCAT(w1.ward_code, '#', b1.id) AS bed_by_number, CONCAT(w2.ward_code, '#', b2.id) AS bed_by_bed_id
FROM beds b1
JOIN beds b2 ON b2.bed_id = b1.bed_number AND b2.id <> b1.id
JOIN wards w1 ON w1.id = b1.ward_id
JOIN wards w2 ON w2.id = b2.ward_id
WHERE b1.is_active = 1 AND b2.is_active = 1;

SELECT '== 7c. Hospital-wide ADT outcome by event, last $DAYS days (ignored = SmartWard has no handler for that event) ==' AS section;
SELECT event_type, status, COUNT(*) AS n, MAX(created_at) AS latest, LEFT(MAX(error_message), 80) AS sample_error
FROM adt_message_logs
WHERE created_at >= NOW() - INTERVAL $DAYS DAY
GROUP BY event_type, status
ORDER BY event_type, status;

SELECT '== 7d. ADT replays run from the /adt page in the window (they re-apply old messages) ==' AS section;
SELECT COUNT(*) AS replayed_rows, MIN(created_at) AS first_replay, MAX(created_at) AS last_replay
FROM adt_message_logs
WHERE created_at >= NOW() - INTERVAL $DAYS DAY AND source_ip LIKE 'REPLAY%';
SQL

# MRNs for the listener-side checks (only plain codes are used in the greps below)
MRN_LIST=$(echo "SELECT DISTINCT mrn FROM ($MRNS) x WHERE mrn <> '';" | sql -N -B | grep -E '^[A-Za-z0-9_-]+$')
MRNRX=$(echo $MRN_LIST | tr ' ' '|')
[ -n "$MRNRX" ] || MRNRX='^$'

echo
echo "== 8. Raw HL7 files at the listener that mention the beds (file = time_event_MRN_seq) =="
docker exec -e RX="$RX" -e DAYS="$DAYS" "$ADT" sh -c '
  cd /app/HL7/received_messages || exit 0
  find . -name "*.hl7" -mtime -"$DAYS" | xargs -r grep -lE "$RX" | sort | while read -r f; do
    echo "--- $f"
    tr "\r" "\n" < "$f" | grep -E "^(EVN|PV1)\|" | cut -c1-200
  done'

echo
echo "== 9. Every message the listener received for the involved MRNs (compare with section 5: a file here with no DB row = never reached SmartWard) =="
for m in $MRN_LIST; do
    echo "--- MRN $m"
    docker exec -e M="$m" -e DAYS="$DAYS" "$ADT" sh -c \
        'cd /app/HL7/received_messages 2>/dev/null && find . -name "*_${M}_*.hl7" -mtime -"$DAYS" | sort'
done

echo
echo "== 10. Listener -> SmartWard delivery failures (message got an AE ack; no retry) =="
docker exec -e MRNRX="$MRNRX" "$ADT" sh -c '
  L=/app/HL7/logs/hl7_adt_all.log; [ -f "$L" ] || { echo "no $L"; exit 0; }
  awk "/Message saved to/ {f=\$NF} /Laravel API error|Cannot connect to Laravel|Laravel API request timeout|Error sending to Laravel/ {print f \" <= \" substr(\$0, 1, 220)}" "$L" > /tmp/adt_fail.txt
  echo "failures per day:"; cut -d/ -f2 /tmp/adt_fail.txt | cut -c1-8 | sort | uniq -c | tail -n 40
  echo "failures for the involved MRNs:"; grep -E "_($MRNRX)_" /tmp/adt_fail.txt | tail -n 50
  rm -f /tmp/adt_fail.txt'

echo
echo "== 11. SmartWard laravel.log ADT warnings/errors for these beds / MRNs =="
docker exec -e RX="$RX" -e MRNRX="$MRNRX" "$APP" sh -c \
    'grep -hE "ADT " /app/storage/logs/laravel*.log 2>/dev/null | grep -E "$RX|$MRNRX" | cut -c1-400 | tail -n 100'

echo
echo "##### done"
