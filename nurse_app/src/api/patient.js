// Patient chart API: one patient's consultant orders, I/O chart, medication
// doses, infusions, blood transfusion, alerts and nursing plan
// (NurseAppPatientController).
//
// Every action answers { success, message, patient }, where `patient` is the
// whole refreshed chart, so the screen simply redraws from it.
//
//   GET  /api/nurse/patients/{id}?io_day=Y-m-d        -> { patient }
//   POST /api/nurse/patients/{id}/orders              { instruction, urgency, consultant_id }
//   POST /api/nurse/patients/{id}/orders/handover     { to: 'next'|'current', order_ids?, note? }
//   POST /api/nurse/patients/{id}/orders/{o}/complete { outcome_note? }
//   POST /api/nurse/patients/{id}/orders/{o}/cancel   { outcome_note }
//   POST /api/nurse/patients/{id}/io/entries          { direction, category, volume_ml, description?, minutes_ago? }
//   POST /api/nurse/patients/{id}/io/entries/{e}/void { void_reason }
//   POST /api/nurse/patients/{id}/io/plan             { intake_limit_ml?, urine_min_ml_per_hour?, notes? }
//   POST /api/nurse/patients/{id}/io/assessments      { edema_grade, edema_sites[], signs[], weight_kg?, notes?, minutes_ago? }
//   POST /api/nurse/patients/{id}/medications/{m}/doses { status, notes?, minutes_ago? }
//   POST /api/nurse/patients/{id}/transfusions        { unit_number, product_type, unit_blood_group?, patient_blood_group?,
//                                                       crossmatch_reference?, unit_expires_at?, volume_ml?, prescribed_minutes?, notes? }
//   POST /api/nurse/patients/{id}/transfusions/{t}/checklist { step, action: 'confirm'|'undo' }
//   POST /api/nurse/patients/{id}/transfusions/{t}/start
//   POST /api/nurse/patients/{id}/transfusions/{t}/finish    { outcome: 'completed'|'stopped', stop_reason? }
//   POST /api/nurse/patients/{id}/care-plan           { template_key?, diagnosis?, related_to?, goal?, interventions[]? }
//   POST /api/nurse/patients/{id}/care-plan/{i}/update   { related_to?, goal?, interventions[]? }
//   POST /api/nurse/patients/{id}/care-plan/{i}/evaluate { outcome: 'met'|'partly_met'|'not_met', note? }
//   POST /api/nurse/patients/{id}/care-plan/{i}/close    { status: 'resolved'|'discontinued', note? }
//   POST /api/nurse/notifications/{n}/respond
//
// Times are sent as "minutes ago", never as clock times, so a phone on the
// wrong time zone cannot misfile an entry.

import { apiFetch } from './endpoints';
import { createDemoPatientClient } from '../data/mockPatient';

function post(path, body) {
  return apiFetch(path, { method: 'POST', body: body ?? {} });
}

function createLivePatientClient(patientId) {
  const base = `/api/nurse/patients/${patientId}`;

  return {
    load: ({ ioDay } = {}) =>
      apiFetch(`${base}${ioDay ? `?io_day=${encodeURIComponent(ioDay)}` : ''}`),

    addOrder: (body) => post(`${base}/orders`, body),
    completeOrder: (orderId, body) => post(`${base}/orders/${orderId}/complete`, body),
    cancelOrder: (orderId, body) => post(`${base}/orders/${orderId}/cancel`, body),
    handoverOrders: (body) => post(`${base}/orders/handover`, body),

    addIoEntry: (body) => post(`${base}/io/entries`, body),
    voidIoEntry: (entryId, body) => post(`${base}/io/entries/${entryId}/void`, body),
    saveIoPlan: (body) => post(`${base}/io/plan`, body),
    addIoAssessment: (body) => post(`${base}/io/assessments`, body),

    recordDose: (medicationId, body) => post(`${base}/medications/${medicationId}/doses`, body),

    addTransfusion: (body) => post(`${base}/transfusions`, body),
    transfusionCheck: (unitId, body) => post(`${base}/transfusions/${unitId}/checklist`, body),
    startTransfusion: (unitId) => post(`${base}/transfusions/${unitId}/start`),
    finishTransfusion: (unitId, body) => post(`${base}/transfusions/${unitId}/finish`, body),

    addCarePlanItem: (body) => post(`${base}/care-plan`, body),
    updateCarePlanItem: (itemId, body) => post(`${base}/care-plan/${itemId}/update`, body),
    evaluateCarePlanItem: (itemId, body) => post(`${base}/care-plan/${itemId}/evaluate`, body),
    closeCarePlanItem: (itemId, body) => post(`${base}/care-plan/${itemId}/close`, body),

    respondAlert: (alertId) => post(`/api/nurse/notifications/${alertId}/respond`),
  };
}

/**
 * The client the patient screen talks to: the server for a real session, or
 * an in-memory copy with sample data for a demo login. Both answer the same
 * shapes, so the screen never needs to know which it has.
 */
export function createPatientClient(session, bed) {
  return session?.demo ? createDemoPatientClient(bed) : createLivePatientClient(bed.patient_id);
}
