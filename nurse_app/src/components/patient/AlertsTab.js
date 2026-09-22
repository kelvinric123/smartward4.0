// Unanswered bedside alerts for this patient - patient calls, EWS alerts and
// infusion alerts - with "answered", as on the ward dashboard's alert bar.

import React, { useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors } from '../../theme';
import { Button, Card, EmptyNote, SectionTitle, Tag, shared, tone } from '../ui';

const SEVERITY_TONE = { urgent: 'critical', warning: 'warning', normal: 'info' };

export default function AlertsTab({ chart, perform }) {
  const alerts = chart.alerts;
  const [busyId, setBusyId] = useState(null);
  const [error, setError] = useState(null);

  async function answer(alert) {
    setBusyId(alert.id);
    setError(null);
    const result = await perform((c) => c.respondAlert(alert.id));
    setBusyId(null);
    if (!result.ok) setError(result.error);
  }

  return (
    <View>
      <SectionTitle eyebrow="BEDSIDE ALERTS" title={`Waiting (${alerts.pending.length})`} />
      {error ? <Text style={[shared.error, { marginTop: 0, marginBottom: 8 }]}>{error}</Text> : null}

      {alerts.pending.length === 0 ? (
        <Card>
          <EmptyNote text="No unanswered calls or alerts for this patient." />
        </Card>
      ) : (
        alerts.pending.map((alert) => {
          const t = tone(SEVERITY_TONE[alert.severity] ?? 'info');
          return (
            <Card key={alert.id} style={{ borderColor: t.border, backgroundColor: t.bg }}>
              <View style={styles.head}>
                <Tag label={alert.type_label.toUpperCase()} toneName={SEVERITY_TONE[alert.severity] ?? 'info'} solid />
                {alert.ews_score != null ? <Tag label={`EWS ${alert.ews_score}`} toneName="critical" /> : null}
                <Text style={[shared.meta, { marginLeft: 'auto' }]}>
                  {alert.time_label} · {alert.minutes_ago < 60 ? `${alert.minutes_ago} min ago` : `${Math.floor(alert.minutes_ago / 60)} h ago`}
                </Text>
              </View>
              <Text style={[styles.message, { color: t.text }]}>{alert.message}</Text>
              <Button
                label="Mark answered"
                kind="dark"
                small
                style={{ marginTop: 10, alignSelf: 'flex-start' }}
                busy={busyId === alert.id}
                onPress={() => answer(alert)}
              />
            </Card>
          );
        })
      )}

      {alerts.recent.length > 0 ? (
        <>
          <SectionTitle eyebrow="RECENTLY" title="Answered" />
          {alerts.recent.map((alert) => (
            <Card key={alert.id}>
              <View style={styles.head}>
                <Tag label={alert.type_label.toUpperCase()} toneName="muted" />
                <Text style={[shared.meta, { marginLeft: 'auto' }]}>{alert.time_label}</Text>
              </View>
              <Text style={styles.recentMessage}>{alert.message}</Text>
              <Text style={shared.meta}>
                {alert.responded_by} · {alert.responded_label}
              </Text>
            </Card>
          ))}
        </>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  message: { marginTop: 8, fontSize: 14, fontWeight: '700', lineHeight: 20 },
  recentMessage: { marginTop: 6, color: colors.slate700, fontSize: 13 },
});
