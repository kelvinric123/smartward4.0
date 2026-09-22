// Nursing plan - the ward dashboard's Nursing Plan tab on the phone, in two
// views:
//
//   This shift  what is due before the shift ends, gathered from the chart
//               (doses, orders, assessments, HGT, I/O, blood units, infusions,
//               trips out, care plan evaluations, vitals). Timed tasks in time
//               order, the rest for the whole shift. A task opens the tab that
//               deals with it.
//   Care plan   nursing diagnoses with a goal and interventions, evaluated
//               each shift (met / partly met / not met), edited, resolved or
//               discontinued; new ones from the library or in the nurse's own
//               words, with suggestions from the record.
//
// The same plan the ward dashboard shows (NursingCarePlan on the server).

import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity } from 'react-native';
import { colors, radius } from '../../theme';
import Sheet from '../Sheet';
import { Button, Card, Chip, EmptyNote, SectionTitle, Tag, shared, tone } from '../ui';

const CATEGORY_COLORS = {
  medication: '#8b5cf6',
  order: colors.amber500,
  assessment: '#d946ef',
  hgt: '#ec4899',
  fluid: '#06b6d4',
  transfusion: colors.rose600,
  infusion: '#0ea5e9',
  movement: '#f97316',
  discharge: colors.emerald600,
  careplan: '#6366f1',
  vitals: colors.emerald500,
};
const OUTCOME_TONES = { met: 'good', partly_met: 'warning', not_met: 'critical' };
const INDIGO = '#4f46e5';
const INTERVENTIONS_SHOWN = 3;

/** One intervention per line, as the server keeps them. */
function linesOf(text) {
  return text
    .split(/\r\n|\r|\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .slice(0, 20);
}

export default function NursingPlanTab({ chart, perform, goTab }) {
  const plan = chart.nursing_plan;
  const [view, setView] = useState('shift');
  const [adding, setAdding] = useState(null); // { key } of the template to start from, or {} for own words
  const [evaluating, setEvaluating] = useState(null);
  const [editing, setEditing] = useState(null);
  const [closing, setClosing] = useState(null);

  if (!plan) {
    return (
      <Card>
        <EmptyNote text="The nursing plan needs a newer SmartWard server. Ask the ward admin to update it." />
      </Card>
    );
  }

  const shift = plan.shift;
  const care = plan.care_plan;

  function openTask(task) {
    if (task.tab === 'plan') setView('plan');
    else if (task.tab) goTab(task.tab);
  }

  return (
    <View>
      <SectionTitle
        eyebrow="NURSING PLAN"
        title={view === 'shift' ? 'Due this shift' : 'Nursing care plan'}
        right={view === 'plan' ? <Button label="+ Add" small onPress={() => setAdding({})} /> : null}
      />

      <View style={styles.switcher}>
        <SwitchButton
          label="This shift"
          active={view === 'shift'}
          count={shift.counts.total}
          countColor={shift.counts.overdue ? colors.rose600 : colors.slate500}
          onPress={() => setView('shift')}
        />
        <SwitchButton
          label="Care plan"
          active={view === 'plan'}
          count={care.due_evaluations || care.active.length}
          countColor={care.due_evaluations ? INDIGO : colors.slate500}
          onPress={() => setView('plan')}
        />
      </View>

      {view === 'shift' ? <ShiftView shift={shift} onOpen={openTask} /> : null}
      {view === 'plan' ? (
        <CarePlanView
          care={care}
          onAdd={(key) => setAdding(key ? { key } : {})}
          onEvaluate={setEvaluating}
          onEdit={setEditing}
          onClose={setClosing}
        />
      ) : null}

      <AddSheet
        start={adding}
        templates={care.templates}
        onClose={() => setAdding(null)}
        onDone={() => setView('plan')}
        perform={perform}
      />
      <EvaluateSheet item={evaluating} outcomes={care.outcomes} onClose={() => setEvaluating(null)} perform={perform} />
      <EditSheet item={editing} onClose={() => setEditing(null)} perform={perform} />
      <CloseSheet item={closing} onClose={() => setClosing(null)} perform={perform} />
    </View>
  );
}

function SwitchButton({ label, active, count, countColor, onPress }) {
  return (
    <TouchableOpacity activeOpacity={0.85} onPress={onPress} style={[styles.switchBtn, active && styles.switchBtnActive]}>
      <Text style={[styles.switchText, active && styles.switchTextActive]}>{label}</Text>
      {count ? (
        <View style={[styles.switchCount, { backgroundColor: countColor }]}>
          <Text style={styles.switchCountText}>{count > 99 ? '99+' : count}</Text>
        </View>
      ) : null}
    </TouchableOpacity>
  );
}

// ------------------------------------------------------------ this shift

function ShiftView({ shift, onOpen }) {
  const { timed, standing, counts } = shift;

  return (
    <View>
      <View style={styles.shiftCard}>
        <View style={{ flex: 1 }}>
          <Text style={styles.shiftEyebrow}>{`${shift.shift.name} shift`.toUpperCase()}</Text>
          <Text style={styles.shiftTime}>{shift.shift.time}</Text>
          {shift.shift.nurse ? <Text style={styles.shiftNurse}>Rostered: {shift.shift.nurse}</Text> : null}
        </View>
        <View style={{ alignItems: 'flex-end' }}>
          <Text style={styles.shiftCount}>{counts.total}</Text>
          <Text style={styles.shiftCountLabel}>{counts.total === 1 ? 'task' : 'tasks'}</Text>
          {counts.overdue ? <Tag label={`${counts.overdue} OVERDUE`} toneName="critical" solid /> : null}
        </View>
      </View>

      {timed.length === 0 && standing.length === 0 ? (
        <Card>
          <EmptyNote text="Nothing due this shift. All caught up." />
        </Card>
      ) : null}

      {timed.length ? (
        <Card title="BY TIME">
          {timed.map((task, i) => (
            <TaskRow key={i} task={task} onOpen={onOpen} last={i === timed.length - 1} />
          ))}
        </Card>
      ) : null}

      {standing.length ? (
        <Card title="DURING THE SHIFT">
          {standing.map((task, i) => (
            <StandingRow key={i} task={task} onOpen={onOpen} />
          ))}
        </Card>
      ) : null}

      <Text style={[shared.meta, { marginBottom: 8 }]}>
        Built from the chart: doses, orders, assessments, HGT, I/O, blood units, infusions, trips out, the care plan and
        vital signs. Record each in its own tab and the list updates.
      </Text>
    </View>
  );
}

function TaskRow({ task, onOpen, last }) {
  const Wrap = task.tab ? TouchableOpacity : View;
  const titleTone = task.tone === 'critical' ? colors.rose700 : task.tone === 'warning' ? colors.amber700 : colors.slate900;
  return (
    <Wrap activeOpacity={0.7} onPress={() => onOpen(task)} style={[styles.taskRow, !last && styles.taskRowLine]}>
      <Text style={[styles.taskTime, task.overdue && { color: colors.rose700, fontWeight: '800' }]}>{task.time_label}</Text>
      <View style={[styles.taskDot, { backgroundColor: CATEGORY_COLORS[task.category] ?? colors.slate300 }]} />
      <View style={{ flex: 1 }}>
        <Text style={[styles.taskTitle, { color: titleTone }]}>{task.title}</Text>
        {task.detail ? <Text style={styles.taskDetail}>{task.detail}</Text> : null}
        {task.badge ? (
          <View style={{ marginTop: 4 }}>
            <Tag label={task.badge.toUpperCase()} toneName={task.overdue ? 'critical' : 'muted'} solid={task.overdue} />
          </View>
        ) : null}
      </View>
      {task.tab ? <Text style={styles.chevron}>›</Text> : null}
    </Wrap>
  );
}

function StandingRow({ task, onOpen }) {
  const t = tone(task.tone === 'default' ? 'muted' : task.tone);
  const Wrap = task.tab ? TouchableOpacity : View;
  return (
    <Wrap activeOpacity={0.75} onPress={() => onOpen(task)} style={[styles.standing, { backgroundColor: t.bg, borderColor: t.border }]}>
      <View style={[styles.taskDot, { backgroundColor: CATEGORY_COLORS[task.category] ?? t.solid }]} />
      <View style={{ flex: 1 }}>
        <Text style={[styles.taskTitle, { color: task.tone === 'default' ? colors.slate900 : t.text }]}>{task.title}</Text>
        {task.detail ? <Text style={[styles.taskDetail, task.tone !== 'default' && { color: t.text }]}>{task.detail}</Text> : null}
      </View>
      {task.badge ? <Tag label={task.badge.toUpperCase()} toneName={task.tone === 'default' ? 'muted' : task.tone} /> : null}
      {task.tab ? <Text style={[styles.chevron, { color: t.text }]}>›</Text> : null}
    </Wrap>
  );
}

// ------------------------------------------------------------- care plan

function CarePlanView({ care, onAdd, onEvaluate, onEdit, onClose }) {
  const [showClosed, setShowClosed] = useState(false);

  return (
    <View>
      {care.suggestions.length ? (
        <Card title="SUGGESTED FROM THE RECORD" style={{ borderColor: '#c7d2fe', backgroundColor: '#f5f7ff' }}>
          {care.suggestions.map((s) => (
            <View key={s.key} style={styles.suggestion}>
              <View style={{ flex: 1 }}>
                <Text style={styles.suggestionTitle}>{s.diagnosis}</Text>
                <Text style={styles.suggestionReason}>{s.reason}</Text>
              </View>
              <Button label="Add" small kind="outline" onPress={() => onAdd(s.key)} />
            </View>
          ))}
        </Card>
      ) : null}

      {care.active.length === 0 ? (
        <Card>
          <EmptyNote text="No nursing diagnosis in the plan yet. Add one from the library or in your own words." />
        </Card>
      ) : (
        care.active.map((item, i) => (
          <PlanItem key={item.id} item={item} number={i + 1} onEvaluate={onEvaluate} onEdit={onEdit} onClose={onClose} />
        ))
      )}

      <Button label="+ Add a nursing diagnosis" kind="outline" style={{ marginBottom: 12 }} onPress={() => onAdd(null)} />

      {care.closed.length ? (
        <Card padded={false}>
          <TouchableOpacity activeOpacity={0.8} onPress={() => setShowClosed((v) => !v)} style={styles.closedHead}>
            <Text style={styles.closedHeadText}>Closed this stay ({care.closed.length})</Text>
            <Text style={styles.chevron}>{showClosed ? '⌃' : '⌄'}</Text>
          </TouchableOpacity>
          {showClosed
            ? care.closed.map((item) => (
                <View key={item.id} style={styles.closedRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.closedTitle}>{item.diagnosis}</Text>
                    <Text style={shared.meta}>
                      {item.status_label} {item.resolved_label}
                      {item.resolved_by ? ` by ${item.resolved_by}` : ''}
                    </Text>
                    {item.resolve_note ? <Text style={styles.closedNote}>{item.resolve_note}</Text> : null}
                  </View>
                  <Tag label={item.status_label.toUpperCase()} toneName={item.status === 'resolved' ? 'good' : 'muted'} />
                </View>
              ))
            : null}
        </Card>
      ) : null}
    </View>
  );
}

function PlanItem({ item, number, onEvaluate, onEdit, onClose }) {
  const [allInterventions, setAllInterventions] = useState(false);
  const [history, setHistory] = useState(false);
  const shown = allInterventions ? item.interventions : item.interventions.slice(0, INTERVENTIONS_SHOWN);
  const hidden = item.interventions.length - shown.length;
  const latest = item.latest;

  return (
    <Card style={!item.evaluated_this_shift ? { borderColor: '#c7d2fe' } : null}>
      <View style={styles.itemHead}>
        <Text style={styles.itemTitle}>
          {number}. {item.diagnosis}
        </Text>
        {item.evaluated_this_shift ? (
          <Tag label="✓ THIS SHIFT" toneName="good" />
        ) : (
          <View style={styles.dueTag}>
            <Text style={styles.dueTagText}>TO EVALUATE</Text>
          </View>
        )}
      </View>
      <Text style={styles.itemMeta}>
        {item.category_label}
        {item.related_to ? ` · related to ${item.related_to}` : ''}
      </Text>

      <View style={styles.goal}>
        <Text style={styles.goalLabel}>GOAL</Text>
        <Text style={styles.goalText}>{item.goal}</Text>
      </View>

      {item.interventions.length ? (
        <View style={{ marginTop: 8 }}>
          {shown.map((line, i) => (
            <View key={i} style={styles.bulletRow}>
              <Text style={styles.bullet}>•</Text>
              <Text style={styles.bulletText}>{line}</Text>
            </View>
          ))}
          {hidden > 0 || allInterventions ? (
            <TouchableOpacity onPress={() => setAllInterventions((v) => !v)} activeOpacity={0.7}>
              <Text style={styles.more}>{allInterventions ? 'Show less' : `+ ${hidden} more`}</Text>
            </TouchableOpacity>
          ) : null}
        </View>
      ) : null}

      {latest ? (
        <View style={styles.latest}>
          <Tag label={latest.outcome_label.toUpperCase()} toneName={OUTCOME_TONES[latest.outcome] ?? 'muted'} />
          <Text style={[shared.meta, { flex: 1 }]}>
            {[latest.shift_label, latest.time_label, latest.by].filter(Boolean).join(' · ')}
            {latest.note ? `\n${latest.note}` : ''}
          </Text>
        </View>
      ) : (
        <Text style={[shared.meta, { marginTop: 8 }]}>
          Not evaluated yet · added {item.started_label}
          {item.created_by ? ` by ${item.created_by}` : ''}
        </Text>
      )}

      {item.history.length > 1 ? (
        <>
          <TouchableOpacity onPress={() => setHistory((v) => !v)} activeOpacity={0.7}>
            <Text style={styles.more}>{history ? 'Hide earlier evaluations' : `Earlier evaluations (${item.history.length - 1})`}</Text>
          </TouchableOpacity>
          {history
            ? item.history.slice(1).map((e) => (
                <View key={e.id} style={styles.historyRow}>
                  <Tag label={e.outcome_label.toUpperCase()} toneName={OUTCOME_TONES[e.outcome] ?? 'muted'} />
                  <Text style={[shared.meta, { flex: 1 }]}>
                    {[e.shift_label, e.time_label, e.by].filter(Boolean).join(' · ')}
                    {e.note ? `\n${e.note}` : ''}
                  </Text>
                </View>
              ))
            : null}
        </>
      ) : null}

      <View style={styles.itemActions}>
        <Button
          label={item.evaluated_this_shift ? 'Re-evaluate' : 'Evaluate'}
          kind={item.evaluated_this_shift ? 'outline' : 'primary'}
          small
          flex
          onPress={() => onEvaluate(item)}
        />
        <Button label="Edit" kind="secondary" small onPress={() => onEdit(item)} />
        <Button label="Close" kind="secondary" small onPress={() => onClose(item)} />
      </View>
    </Card>
  );
}

// ---------------------------------------------------------------- sheets

function AddSheet({ start, templates, onClose, onDone, perform }) {
  const visible = start !== null;
  const [key, setKey] = useState(null);
  const [diagnosis, setDiagnosis] = useState('');
  const [relatedTo, setRelatedTo] = useState('');
  const [goal, setGoal] = useState('');
  const [interventions, setInterventions] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  function pick(templateKey) {
    const t = templates.find((x) => x.key === templateKey);
    setKey(t ? t.key : null);
    setDiagnosis(t ? t.diagnosis : '');
    setRelatedTo(t ? t.related_to ?? '' : '');
    setGoal(t ? t.goal : '');
    setInterventions(t ? t.interventions.join('\n') : '');
    setError(null);
  }

  // Each opening starts from the suggestion tapped, or blank
  useEffect(() => {
    if (visible) pick(start?.key ?? null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible, start?.key]);

  async function save() {
    if (!diagnosis.trim()) {
      setError('Name the nursing diagnosis, or pick one from the list.');
      return;
    }
    if (!goal.trim()) {
      setError('Write the goal for this diagnosis.');
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) =>
      c.addCarePlanItem({
        template_key: key,
        diagnosis: diagnosis.trim(),
        related_to: relatedTo.trim() || null,
        goal: goal.trim(),
        interventions: linesOf(interventions),
      })
    );
    setBusy(false);
    if (result.ok) {
      onClose();
      onDone?.();
    } else {
      setError(result.error);
    }
  }

  return (
    <Sheet
      visible={visible}
      busy={busy}
      onClose={onClose}
      eyebrow="NURSING CARE PLAN"
      title="Add a nursing diagnosis"
      subtitle="Start from the library and adjust it to this patient, or write your own."
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={onClose} disabled={busy} />
          <Button label="Add to plan" flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>START FROM</Text>
      <View style={shared.chipWrap}>
        <Chip small label="My own words" selected={key === null} onPress={() => pick(null)} toneName="muted" />
        {templates.map((t) => (
          <Chip
            key={t.key}
            small
            label={t.in_plan ? `${t.diagnosis} (in plan)` : t.diagnosis}
            selected={key === t.key}
            disabled={t.in_plan}
            onPress={() => pick(t.key)}
          />
        ))}
      </View>

      <Text style={shared.label}>NURSING DIAGNOSIS</Text>
      <TextInput
        style={shared.input}
        value={diagnosis}
        onChangeText={setDiagnosis}
        maxLength={255}
        placeholder="e.g. Impaired physical mobility"
        placeholderTextColor={colors.mutedSoft}
      />
      <Text style={shared.label}>RELATED TO (OPTIONAL)</Text>
      <TextInput
        style={shared.input}
        value={relatedTo}
        onChangeText={setRelatedTo}
        maxLength={1000}
        placeholder="e.g. Post-op right hip"
        placeholderTextColor={colors.mutedSoft}
      />
      <Text style={shared.label}>GOAL</Text>
      <TextInput
        style={[shared.input, { minHeight: 60, textAlignVertical: 'top' }]}
        value={goal}
        onChangeText={setGoal}
        maxLength={1000}
        multiline
        placeholder="What should be true, and by when"
        placeholderTextColor={colors.mutedSoft}
      />
      <Text style={shared.label}>INTERVENTIONS (ONE PER LINE)</Text>
      <TextInput
        style={[shared.input, shared.textarea, { minHeight: 120 }]}
        value={interventions}
        onChangeText={setInterventions}
        multiline
        placeholder={'e.g. Physio twice daily\nSit out for meals'}
        placeholderTextColor={colors.mutedSoft}
      />
      <Text style={shared.hint}>{linesOf(interventions).length} of 20 interventions</Text>
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

function EvaluateSheet({ item, outcomes, onClose, perform }) {
  const [outcome, setOutcome] = useState(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (item) {
      setOutcome(null);
      setNote('');
      setError(null);
    }
  }, [item]);

  async function save() {
    if (!outcome) {
      setError('Choose whether the goal was met.');
      return;
    }
    if (outcome === 'not_met' && !note.trim()) {
      setError('Say briefly why - the next shift works from it.');
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) => c.evaluateCarePlanItem(item.id, { outcome, note: note.trim() || null }));
    setBusy(false);
    if (result.ok) onClose();
    else setError(result.error);
  }

  return (
    <Sheet
      visible={!!item}
      busy={busy}
      onClose={onClose}
      eyebrow="EVALUATE THIS SHIFT"
      title={item?.diagnosis ?? ''}
      subtitle={item ? `Goal: ${item.goal}` : ''}
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={onClose} disabled={busy} />
          <Button label="Save evaluation" flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>WAS THE GOAL MET?</Text>
      <View style={shared.chipWrap}>
        {Object.entries(outcomes).map(([value, label]) => (
          <Chip key={value} label={label} selected={outcome === value} onPress={() => setOutcome(value)} toneName={OUTCOME_TONES[value]} />
        ))}
      </View>
      <Text style={shared.label}>{outcome === 'not_met' ? 'NOTE (REQUIRED)' : 'NOTE (OPTIONAL)'}</Text>
      <TextInput
        style={[shared.input, shared.textarea]}
        value={note}
        onChangeText={setNote}
        maxLength={1000}
        multiline
        placeholder={outcome === 'not_met' ? 'Why not, and what changes next shift' : 'e.g. Walked to the toilet with a frame'}
        placeholderTextColor={colors.mutedSoft}
      />
      {item?.evaluated_this_shift ? (
        <Text style={shared.hint}>Already evaluated this shift: saving replaces that evaluation.</Text>
      ) : null}
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

function EditSheet({ item, onClose, perform }) {
  const [relatedTo, setRelatedTo] = useState('');
  const [goal, setGoal] = useState('');
  const [interventions, setInterventions] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (item) {
      setRelatedTo(item.related_to ?? '');
      setGoal(item.goal);
      setInterventions(item.interventions.join('\n'));
      setError(null);
    }
  }, [item]);

  async function save() {
    if (!goal.trim()) {
      setError('Write the goal for this diagnosis.');
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) =>
      c.updateCarePlanItem(item.id, {
        related_to: relatedTo.trim() || null,
        goal: goal.trim(),
        interventions: linesOf(interventions),
      })
    );
    setBusy(false);
    if (result.ok) onClose();
    else setError(result.error);
  }

  return (
    <Sheet
      visible={!!item}
      busy={busy}
      onClose={onClose}
      eyebrow="NURSING CARE PLAN"
      title={item ? `Edit: ${item.diagnosis}` : ''}
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={onClose} disabled={busy} />
          <Button label="Save" flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>RELATED TO (OPTIONAL)</Text>
      <TextInput
        style={shared.input}
        value={relatedTo}
        onChangeText={setRelatedTo}
        maxLength={1000}
        placeholderTextColor={colors.mutedSoft}
      />
      <Text style={shared.label}>GOAL</Text>
      <TextInput
        style={[shared.input, { minHeight: 60, textAlignVertical: 'top' }]}
        value={goal}
        onChangeText={setGoal}
        maxLength={1000}
        multiline
        placeholderTextColor={colors.mutedSoft}
      />
      <Text style={shared.label}>INTERVENTIONS (ONE PER LINE)</Text>
      <TextInput
        style={[shared.input, shared.textarea, { minHeight: 140 }]}
        value={interventions}
        onChangeText={setInterventions}
        multiline
        placeholderTextColor={colors.mutedSoft}
      />
      <Text style={shared.hint}>{linesOf(interventions).length} of 20 interventions</Text>
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

function CloseSheet({ item, onClose, perform }) {
  const [status, setStatus] = useState('resolved');
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (item) {
      setStatus('resolved');
      setNote('');
      setError(null);
    }
  }, [item]);

  async function save() {
    if (status === 'discontinued' && !note.trim()) {
      setError('Say briefly why it is discontinued.');
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) => c.closeCarePlanItem(item.id, { status, note: note.trim() || null }));
    setBusy(false);
    if (result.ok) onClose();
    else setError(result.error);
  }

  return (
    <Sheet
      visible={!!item}
      busy={busy}
      onClose={onClose}
      eyebrow="NURSING CARE PLAN"
      title={item ? `Close: ${item.diagnosis}` : ''}
      subtitle="Closed diagnoses stay on record under Closed this stay."
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={onClose} disabled={busy} />
          <Button
            label={status === 'resolved' ? 'Mark resolved' : 'Discontinue'}
            kind={status === 'resolved' ? 'success' : 'dark'}
            flex
            busy={busy}
            onPress={save}
          />
        </>
      }
    >
      <Text style={shared.label}>WHY IS IT CLOSING?</Text>
      <View style={shared.chipWrap}>
        <Chip label="Resolved: goal reached" selected={status === 'resolved'} onPress={() => setStatus('resolved')} toneName="good" />
        <Chip label="Discontinued" selected={status === 'discontinued'} onPress={() => setStatus('discontinued')} toneName="muted" />
      </View>
      <Text style={shared.label}>{status === 'discontinued' ? 'NOTE (REQUIRED)' : 'NOTE (OPTIONAL)'}</Text>
      <TextInput
        style={shared.input}
        value={note}
        onChangeText={setNote}
        maxLength={255}
        placeholder={status === 'discontinued' ? 'e.g. No longer relevant after surgery' : 'e.g. Mobilising independently'}
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

const styles = StyleSheet.create({
  switcher: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: colors.slate200,
    borderRadius: radius.md,
    padding: 4,
    gap: 4,
    marginBottom: 12,
  },
  switchBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    borderRadius: 10,
    paddingVertical: 9,
  },
  switchBtnActive: { backgroundColor: colors.slate900 },
  switchText: { color: colors.slate600, fontSize: 13, fontWeight: '800' },
  switchTextActive: { color: '#fff' },
  switchCount: { minWidth: 20, height: 20, paddingHorizontal: 5, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  switchCountText: { color: '#fff', fontSize: 11, fontWeight: '800' },
  shiftCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: colors.slate950,
    borderRadius: radius.lg,
    padding: 14,
    marginBottom: 12,
  },
  shiftEyebrow: { color: colors.cyan100, fontSize: 10, fontWeight: '800', letterSpacing: 2 },
  shiftTime: { marginTop: 3, color: '#fff', fontSize: 18, fontWeight: '800' },
  shiftNurse: { marginTop: 3, color: '#cbd5e1', fontSize: 12 },
  shiftCount: { color: '#fff', fontSize: 26, fontWeight: '800', lineHeight: 28 },
  shiftCountLabel: { color: '#cbd5e1', fontSize: 11, marginBottom: 4 },
  taskRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 10, paddingVertical: 9 },
  taskRowLine: { borderBottomWidth: 1, borderBottomColor: colors.slate100 },
  taskTime: { minWidth: 44, paddingTop: 1, color: colors.slate500, fontSize: 12, fontWeight: '700', fontVariant: ['tabular-nums'] },
  taskDot: { width: 8, height: 8, borderRadius: 4, marginTop: 5 },
  taskTitle: { color: colors.slate900, fontSize: 13, fontWeight: '700' },
  taskDetail: { marginTop: 2, color: colors.slate500, fontSize: 11, lineHeight: 15 },
  chevron: { color: colors.slate300, fontSize: 20, fontWeight: '800', lineHeight: 22 },
  standing: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderWidth: 1,
    borderRadius: radius.md,
    paddingHorizontal: 10,
    paddingVertical: 9,
    marginTop: 6,
  },
  suggestion: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 8,
    borderTopWidth: 1,
    borderTopColor: '#e0e7ff',
  },
  suggestionTitle: { color: '#3730a3', fontSize: 13, fontWeight: '800' },
  suggestionReason: { marginTop: 1, color: '#4f46e5', fontSize: 11 },
  itemHead: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 8 },
  itemTitle: { flex: 1, color: colors.slate900, fontSize: 15, fontWeight: '800' },
  itemMeta: { marginTop: 3, color: colors.slate500, fontSize: 11 },
  dueTag: {
    alignSelf: 'flex-start',
    borderRadius: radius.pill,
    paddingHorizontal: 8,
    paddingVertical: 2,
    backgroundColor: '#e0e7ff',
    borderWidth: 1,
    borderColor: '#c7d2fe',
  },
  dueTagText: { color: '#3730a3', fontSize: 10, fontWeight: '800', letterSpacing: 0.3 },
  goal: { marginTop: 10, backgroundColor: colors.slate50, borderRadius: radius.sm, padding: 10 },
  goalLabel: { color: colors.muted, fontSize: 9, fontWeight: '800', letterSpacing: 1.4 },
  goalText: { marginTop: 2, color: colors.slate900, fontSize: 13, fontWeight: '600', lineHeight: 18 },
  bulletRow: { flexDirection: 'row', gap: 6, marginTop: 3 },
  bullet: { color: colors.slate500, fontSize: 12, lineHeight: 17 },
  bulletText: { flex: 1, color: colors.slate700, fontSize: 12, lineHeight: 17 },
  more: { marginTop: 6, color: colors.cyan700, fontSize: 12, fontWeight: '800' },
  latest: {
    marginTop: 10,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
  },
  historyRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 8, marginTop: 6 },
  itemActions: { flexDirection: 'row', gap: 8, marginTop: 12 },
  closedHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 12, paddingVertical: 12 },
  closedHeadText: { color: colors.slate700, fontSize: 13, fontWeight: '800' },
  closedRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
  },
  closedTitle: { color: colors.slate900, fontSize: 13, fontWeight: '700' },
  closedNote: { marginTop: 2, color: colors.slate600, fontSize: 11 },
});
