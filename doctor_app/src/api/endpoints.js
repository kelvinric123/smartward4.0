// Placeholder API client. All functions currently return mock data from
// src/data/mockData.js. When the Laravel backend is ready, point BASE_URL
// to the QMed Smart Ward API and replace each function's body with a fetch.
//
// Auth model: consultant logs in -> receives a token -> all subsequent calls
// include `Authorization: Bearer <token>`. The backend scopes the response to
// the beds (and wards) under that consultant's care.
//
// Backend (Laravel) endpoints we plan to wire to:
//
//   POST  {BASE_URL}/auth/consultant/login
//         body:  { username, password }
//         resp:  { token, doctor: { id, name, title, specialty }, wards: [...] }
//
//   GET   {BASE_URL}/consultants/{doctorId}/dashboard
//         resp:  { doctor, summary, wards: [...], beds: [...] }
//
//   GET   {BASE_URL}/consultants/{doctorId}/wards
//         resp:  [ { id, ward_name, bed_count, ... } ]
//
//   GET   {BASE_URL}/consultants/{doctorId}/wards/{wardId}/beds
//         resp:  [ bed, bed, ... ]   (same shape as mockData.consultantBeds)
//
//   GET   {BASE_URL}/beds/{bedId}
//         resp:  full bed object (patient, vitals, infusions, notes, orders)
//
//   GET   {BASE_URL}/beds/{bedId}/vitals?range=24h
//         resp:  [ { recorded_at, pulse_rate, systolic_bp, diastolic_bp,
//                    spo2, respiratory_rate, temperature, ews } ]
//         used by:  VitalsTrendModal (tap "LATEST VITALS")
//
//   GET   {BASE_URL}/beds/{bedId}/consultant-notes
//         resp:  [ { id, text, author, created_at } ]
//   POST  {BASE_URL}/beds/{bedId}/consultant-notes
//         body:  { text }
//         used by:  ConsultantNotesSection (Add / view notes)
//
//   POST  {BASE_URL}/auth/logout
//
// Keep the function signatures stable so screens don't need changes when we
// swap mock data for real fetches.

import * as mock from '../data/mockData';

export const BASE_URL = 'https://smartward.example.com/api/v1'; // TODO: set real Laravel host
export const API_TIMEOUT_MS = 15000;

export async function loginConsultant({ username, password }) {
  // TODO: replace with:
  //   const res = await fetch(`${BASE_URL}/auth/consultant/login`, { ... });
  //   return res.json();
  await fakeDelay(450);
  return {
    token: 'mock-token-consultant-001',
    doctor: mock.doctor,
    wards: mock.wards,
  };
}

export async function fetchDoctorDashboard(doctorId, token) {
  // TODO: GET `${BASE_URL}/consultants/${doctorId}/dashboard`
  await fakeDelay(250);
  return {
    doctor: mock.doctor,
    summary: mock.summary,
    wards: mock.wards,
    beds: mock.consultantBeds,
  };
}

export async function fetchWards(doctorId, token) {
  // TODO: GET `${BASE_URL}/consultants/${doctorId}/wards`
  await fakeDelay(150);
  return mock.wards;
}

export async function fetchBedsForWard(doctorId, wardId, token) {
  // TODO: GET `${BASE_URL}/consultants/${doctorId}/wards/${wardId}/beds`
  await fakeDelay(150);
  if (wardId == null || wardId === 'all') return mock.consultantBeds;
  return mock.consultantBeds.filter((b) => b.ward_id === wardId);
}

export async function logout(token) {
  // TODO: POST `${BASE_URL}/auth/logout`
  await fakeDelay(100);
  return { ok: true };
}

function fakeDelay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
