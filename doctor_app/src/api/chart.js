// The client the patient chart talks to: the server for a real session, or an
// in-memory copy with sample data for a demo login. Both have the same methods
// and answer the same shapes ({ success, message, chart }), so the chart
// screen never needs to know which one it has.

import { fetchPatientChart, createOrder, cancelOrder } from './endpoints';
import { createDemoChartClient } from '../data/mockChart';

function createLiveChartClient(patientId) {
  return {
    load: ({ ioDay } = {}) => fetchPatientChart(patientId, { ioDay }),
    createOrder: (body) => createOrder(patientId, body),
    cancelOrder: (orderId, reason) => cancelOrder(patientId, orderId, reason),
  };
}

export function createChartClient({ demo, bed, doctorName }) {
  return demo ? createDemoChartClient(bed, doctorName) : createLiveChartClient(bed.patient_id);
}
