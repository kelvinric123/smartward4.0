// The demo login's dashboard, put together the way the server puts the real
// one together (DoctorAppApiController::dashboard): every time moved to the
// demo session's "now", and each bed's figures (orders, I/O, oxygen, results
// waiting for review) worked out from the same demo chart the bed opens, so
// what the doctor does in a chart shows on the bed card at the next refresh.

import * as mock from './mockData';
import { demoBedFromChart } from './mockChart';
import { demoTime, stampOf, clockOf } from './demoClock';

/** A sample bed with its times moved to now, its vitals timestamped like the server's, and its extras. */
function current(raw) {
  const bed = JSON.parse(JSON.stringify(raw));

  if (bed.vitals?.recorded_at_label) {
    bed.vitals.recorded_at_label = stampOf(demoTime(bed.vitals.recorded_at_label));
  }
  bed.vitals_history = (bed.vitals_history ?? []).map((row) => {
    const at = demoTime(row.recorded_at_label);
    return { ...row, recorded_at: at.toISOString(), recorded_at_label: stampOf(at) };
  });
  bed.infusions = (bed.infusions ?? []).map((infusion) => ({
    ...infusion,
    last_updated_label: infusion.last_updated_label ? clockOf(demoTime(infusion.last_updated_label)) : null,
  }));

  const extras = mock.patientExtras[bed.patient_id] ?? {};
  bed.vip_status = extras.vip_status ?? null;
  bed.allergy_list = extras.allergy_list ?? [];

  return bed;
}

export function buildDemoDashboard(doctorName = mock.doctor.name) {
  const beds = mock.consultantBeds.map((raw) => demoBedFromChart(current(raw), doctorName));

  return {
    doctor: mock.doctor,
    wards: mock.wards,
    beds,
    summary: {
      ...mock.summary,
      critical_patients: beds.filter((b) => b.ews_has_vitals && b.ews != null && b.ews >= 5).length,
      pending_discharge: beds.filter((b) => b.is_pending_discharge).length,
      pending_reviews: beds.reduce((total, b) => total + (b.labs?.awaiting_review ?? 0), 0),
      pending_orders: beds.reduce((total, b) => total + (b.pending_orders ?? 0), 0),
    },
  };
}
