/**
 * Simple HL7 v2 MLLP listener for Comen NC5 patient monitor.
 *
 * - Listens on TCP port 2555 (all interfaces)
 * - Unwraps MLLP framing: <VT 0x0B> message <FS 0x1C><CR 0x0D>
 * - Logs every inbound message to console and ./logs/hl7-YYYY-MM-DD.log
 * - Replies with a general ACK (MSA|AA)
 * - If the monitor sends a patient query (QRY^A19), replies with an
 *   ADR^A19 containing the patient demographics from patients.json
 */

const net = require('net');
const fs = require('fs');
const path = require('path');

const PORT = 2555;
const HOST = '0.0.0.0';

const VT = 0x0b; // start of MLLP block
const FS = 0x1c; // end of MLLP block
const CR = 0x0d; // carriage return (segment separator / end of frame)

const LOG_DIR = path.join(__dirname, 'logs');
fs.mkdirSync(LOG_DIR, { recursive: true });

// ---------------------------------------------------------------- utilities

function now() {
  return new Date();
}

function hl7Timestamp(d = now()) {
  const p = (n, w = 2) => String(n).padStart(w, '0');
  return (
    d.getFullYear() +
    p(d.getMonth() + 1) +
    p(d.getDate()) +
    p(d.getHours()) +
    p(d.getMinutes()) +
    p(d.getSeconds())
  );
}

function logLine(text) {
  const d = now();
  const stamp = d.toISOString();
  const line = `[${stamp}] ${text}`;
  console.log(line);
  const file = path.join(LOG_DIR, `hl7-${stamp.slice(0, 10)}.log`);
  fs.appendFileSync(file, line + '\n');
}

function logMessage(direction, msg) {
  // Show each segment on its own line so the log is readable
  const pretty = msg.replace(/\r/g, '\n  ').trimEnd();
  logLine(`${direction}\n  ${pretty}`);
}

// ------------------------------------------------------------ patient store

const PATIENTS_FILE = path.join(__dirname, 'patients.json');

function loadPatients() {
  try {
    return JSON.parse(fs.readFileSync(PATIENTS_FILE, 'utf8'));
  } catch (e) {
    logLine(`WARNING: could not read patients.json: ${e.message}`);
    return {};
  }
}

// -------------------------------------------------------------- HL7 helpers

function parseSegments(raw) {
  return raw.split(/\r/).filter(Boolean).map((seg) => seg.split('|'));
}

function getField(segments, segName, index) {
  const seg = segments.find((s) => s[0] === segName);
  return seg ? (seg[index] || '') : '';
}

let ackCounter = 0;

/** Build a plain ACK for any inbound message. */
function buildAck(segments) {
  const sendingApp = getField(segments, 'MSH', 2);
  const sendingFac = getField(segments, 'MSH', 3);
  const controlId = getField(segments, 'MSH', 9);
  const version = getField(segments, 'MSH', 11) || '2.3.1';
  const msgId = `ACK${hl7Timestamp()}${++ackCounter}`;

  return [
    `MSH|^~\\&|HL7Listener|Server|${sendingApp}|${sendingFac}|${hl7Timestamp()}||ACK|${msgId}|P|${version}`,
    `MSA|AA|${controlId}`,
  ].join('\r') + '\r';
}

/**
 * Build an ORF^R04 response to the Comen NC5's QRY^R02 patient query.
 * The patient ID is in QRD-8 (Who Subject Filter).
 */
function buildOrfR04(segments) {
  const sendingApp = getField(segments, 'MSH', 2);
  const sendingFac = getField(segments, 'MSH', 3);
  const controlId = getField(segments, 'MSH', 9);
  const version = getField(segments, 'MSH', 11) || '2.6';
  const qrdSeg = segments.find((s) => s[0] === 'QRD');
  const qrd = qrdSeg ? qrdSeg.join('|') : 'QRD';
  const qrfs = segments.filter((s) => s[0] === 'QRF').map((s) => s.join('|'));

  // QRD-8 may be a composite (id^name^...) — take the first component
  const whoFilter = qrdSeg ? (qrdSeg[8] || '') : '';
  const patientId = whoFilter.split('^')[0].trim();

  const patients = loadPatients();
  const patient = patients[patientId];
  const msgId = `ORF${hl7Timestamp()}${++ackCounter}`;

  const msh = `MSH|^~\\&|HL7Listener|Server|${sendingApp}|${sendingFac}|${hl7Timestamp()}||ORF^R04|${msgId}|P|${version}`;

  if (!patient) {
    logLine(`QUERY: patient id "${patientId}" NOT FOUND in patients.json`);
    return [
      msh,
      `MSA|AE|${controlId}|Patient not found`,
      qrd,
    ].join('\r') + '\r';
  }

  logLine(`QUERY: patient id "${patientId}" -> ${patient.lastName}, ${patient.firstName}`);

  // PID mirrors the monitor's own style: id in PID-3 and PID-18,
  // name in PID-5 (Last^First), DOB PID-7 (YYYYMMDD), sex PID-8
  const pidFields = new Array(19).fill('');
  pidFields[0] = 'PID';
  pidFields[3] = patientId;
  pidFields[5] = `${patient.lastName || ''}^${patient.firstName || ''}`;
  pidFields[7] = patient.dob || '';
  pidFields[8] = patient.sex || '';
  pidFields[18] = patientId;
  const pid = pidFields.join('|');

  const pv1 = `PV1||I|${patient.ward || ''}^^${patient.bed || ''}`;

  return [msh, `MSA|AA|${controlId}`, qrd, ...qrfs, pid, pv1].join('\r') + '\r';
}

/**
 * Build an ADR^A19 response to a QRY^A19 patient query
 * (Mindray ADT Net Query Interface — e.g. VS8/VS9, eGateway, CMS).
 * Per Mindray PDS Programmer's Guide: MSH, MSA (with text), echoed QRD,
 * PID, PV1 (bed as ^^Dept&Bed&0&0&0), optional OBX height/weight.
 */
function buildAdrA19(segments) {
  const controlId = getField(segments, 'MSH', 9);
  const qrdSeg = segments.find((s) => s[0] === 'QRD');
  const qrd = qrdSeg ? qrdSeg.join('|') : 'QRD';

  const whoFilter = qrdSeg ? (qrdSeg[8] || '') : '';
  const patientId = whoFilter.split('^')[0].trim();

  const patients = loadPatients();
  const patient = patients[patientId];
  const msgId = `ADR${hl7Timestamp()}${++ackCounter}`;

  const msh = `MSH|^~\\&|HL7Listener|ADTServer|||||ADR^A19|${msgId}|P|2.3.1`;

  if (!patient) {
    logLine(`QUERY (A19): patient id "${patientId}" NOT FOUND in patients.json`);
    return [
      msh,
      `MSA|AA|${controlId}|The patient is not found!`,
      qrd,
    ].join('\r') + '\r';
  }

  logLine(`QUERY (A19): patient id "${patientId}" -> ${patient.lastName}, ${patient.firstName}`);

  const pid = `PID|||${patientId}||${patient.lastName || ''}^${patient.firstName || ''}||${patient.dob || ''}|${patient.sex || ''}`;
  const pv1 = `PV1||I|^^${patient.ward || ''}&${patient.bed || ''}&0&0&0`;

  const lines = [msh, `MSA|AA|${controlId}|The Patient is Found`, qrd, pid, pv1];
  if (patient.height) lines.push(`OBX||NM|52^Height||${patient.height}||||||F`);
  if (patient.weight) lines.push(`OBX||NM|51^Weight||${patient.weight}||||||F`);

  return lines.join('\r') + '\r';
}

/** Decide how to respond to an inbound message. */
function handleMessage(raw) {
  const segments = parseSegments(raw);
  const msgType = getField(segments, 'MSH', 8); // e.g. QRY^R02, ADT^A08, ORU^R01

  logLine(`Message type: ${msgType || '(unknown)'}`);

  if (msgType.startsWith('QRY^A19')) {
    return buildAdrA19(segments); // Mindray-style query
  }
  if (msgType.startsWith('QRY')) {
    return buildOrfR04(segments); // Comen NC5 QRY^R02
  }
  return buildAck(segments);
}

// -------------------------------------------------------------- TCP server

const server = net.createServer((socket) => {
  const peer = `${socket.remoteAddress}:${socket.remotePort}`;
  logLine(`CONNECTED ${peer}`);

  let buffer = Buffer.alloc(0);

  socket.on('data', (chunk) => {
    buffer = Buffer.concat([buffer, chunk]);

    // Extract complete MLLP frames: VT ... FS CR
    for (;;) {
      const start = buffer.indexOf(VT);
      const end = buffer.indexOf(FS);
      if (start === -1 || end === -1 || end < start) break;

      const raw = buffer.toString('utf8', start + 1, end);
      // Consume frame plus trailing CR if present
      let consumed = end + 1;
      if (buffer[consumed] === CR) consumed += 1;
      buffer = buffer.subarray(consumed);

      logMessage(`RECEIVED from ${peer}:`, raw);

      try {
        const response = handleMessage(raw);
        logMessage(`SENT to ${peer}:`, response);
        socket.write(Buffer.concat([
          Buffer.from([VT]),
          Buffer.from(response, 'utf8'),
          Buffer.from([FS, CR]),
        ]));
      } catch (e) {
        logLine(`ERROR handling message: ${e.stack}`);
      }
    }
  });

  socket.on('close', () => logLine(`DISCONNECTED ${peer}`));
  socket.on('error', (e) => logLine(`SOCKET ERROR ${peer}: ${e.message}`));
});

server.listen(PORT, HOST, () => {
  logLine(`HL7 listener running on ${HOST}:${PORT} (waiting for the NC5 at 192.168.0.155)`);
});
