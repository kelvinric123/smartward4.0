import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Modal,
  TextInput,
  Keyboard,
  Platform,
  ScrollView,
} from 'react-native';
import { colors, radius } from '../theme';
import Pill from './Pill';
import { addNote, getNotes, subscribe, formatRelative } from '../data/notesStore';

function useKeyboardHeight() {
  const [height, setHeight] = useState(0);
  useEffect(() => {
    const showEvt = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvt = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';
    const showSub = Keyboard.addListener(showEvt, (e) => {
      setHeight(e?.endCoordinates?.height ?? 0);
    });
    const hideSub = Keyboard.addListener(hideEvt, () => setHeight(0));
    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, []);
  return height;
}

export default function ConsultantNotesSection({ bedId, author }) {
  const [notes, setNotes] = useState(() => getNotes(bedId));
  const [adding, setAdding] = useState(false);
  const [draft, setDraft] = useState('');
  const kbHeight = useKeyboardHeight();

  useEffect(() => {
    setNotes(getNotes(bedId));
    const unsub = subscribe(() => setNotes(getNotes(bedId)));
    return () => unsub();
  }, [bedId]);

  function save() {
    const note = addNote(bedId, draft, author);
    if (note) {
      setDraft('');
      setAdding(false);
    }
  }

  return (
    <View style={styles.section}>
      <View style={styles.header}>
        <Text style={styles.title}>CONSULTANT NOTES</Text>
        <View style={{ flexDirection: 'row', gap: 6, alignItems: 'center' }}>
          <Pill
            label={`${notes.length} ${notes.length === 1 ? 'note' : 'notes'}`}
            bg={colors.slate100}
            color={colors.slate600}
          />
          <TouchableOpacity
            style={styles.addBtn}
            onPress={() => setAdding(true)}
            activeOpacity={0.85}
          >
            <Text style={styles.addBtnText}>+ Add</Text>
          </TouchableOpacity>
        </View>
      </View>

      {notes.length === 0 ? (
        <Text style={styles.empty}>No consultant notes yet. Tap "+ Add" to record your impression.</Text>
      ) : (
        notes.map((n) => (
          <View key={n.id} style={styles.noteRow}>
            <View style={styles.noteHeader}>
              <Text style={styles.noteAuthor} numberOfLines={1}>{n.author}</Text>
              <Text style={styles.noteTime}>{formatRelative(n.created_at)}</Text>
            </View>
            <Text style={styles.noteText}>{n.text}</Text>
          </View>
        ))
      )}

      <Modal
        visible={adding}
        animationType="slide"
        transparent
        statusBarTranslucent
        hardwareAccelerated
        onRequestClose={() => { setAdding(false); setDraft(''); }}
      >
        <View style={styles.backdrop}>
          <TouchableOpacity
            style={styles.backdropTap}
            activeOpacity={1}
            onPress={() => { Keyboard.dismiss(); setAdding(false); setDraft(''); }}
          />
          <View style={[styles.sheet, { marginBottom: kbHeight }]}>
            <View style={styles.sheetHandle} />
            <ScrollView
              keyboardShouldPersistTaps="handled"
              contentContainerStyle={{ padding: 18 }}
              showsVerticalScrollIndicator={false}
            >
              <Text style={styles.sheetEyebrow}>NEW CONSULTANT NOTE</Text>
              <Text style={styles.sheetTitle}>Record your impression</Text>
              <Text style={styles.sheetMeta}>
                Notes are saved against this bed and visible on next view.
                In production, notes are persisted to the QMed Smart Ward backend.
              </Text>

              <TextInput
                style={styles.textarea}
                value={draft}
                onChangeText={setDraft}
                multiline
                placeholder="e.g. Continue antibiotics. Repeat CXR tomorrow. Family informed of plan."
                placeholderTextColor={colors.mutedSoft}
                textAlignVertical="top"
                blurOnSubmit={false}
                underlineColorAndroid="transparent"
              />

              <View style={styles.actionRow}>
                <TouchableOpacity
                  style={[styles.btn, styles.btnCancel]}
                  onPress={() => { Keyboard.dismiss(); setAdding(false); setDraft(''); }}
                  activeOpacity={0.85}
                >
                  <Text style={styles.btnCancelText}>Cancel</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.btn, styles.btnSave, !draft.trim() && { opacity: 0.5 }]}
                  disabled={!draft.trim()}
                  onPress={() => { Keyboard.dismiss(); save(); }}
                  activeOpacity={0.85}
                >
                  <Text style={styles.btnSaveText}>Save Note</Text>
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  section: {
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: 12,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  title: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2,
    color: colors.muted,
  },
  addBtn: {
    backgroundColor: colors.blue700,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: radius.pill,
  },
  addBtnText: {
    color: '#fff',
    fontSize: 11,
    fontWeight: '800',
  },
  empty: {
    color: colors.muted,
    fontSize: 12,
  },
  noteRow: {
    backgroundColor: colors.blue50,
    borderRadius: radius.md,
    padding: 10,
    marginTop: 8,
    borderLeftWidth: 3,
    borderLeftColor: colors.blue600,
  },
  noteHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  noteAuthor: {
    fontSize: 11,
    fontWeight: '800',
    color: colors.blue700,
    flex: 1,
    paddingRight: 8,
  },
  noteTime: {
    fontSize: 10,
    color: colors.muted,
  },
  noteText: {
    fontSize: 13,
    color: colors.slate900,
    lineHeight: 19,
  },

  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(2,6,23,0.55)',
    justifyContent: 'flex-end',
  },
  backdropTap: {
    flex: 1,
  },
  sheet: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
  },
  sheetHandle: {
    alignSelf: 'center',
    width: 44,
    height: 4,
    borderRadius: 2,
    backgroundColor: colors.slate300,
    marginTop: 8,
  },
  sheetEyebrow: {
    color: colors.blue700,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.2,
  },
  sheetTitle: {
    marginTop: 2,
    color: colors.slate900,
    fontSize: 18,
    fontWeight: '800',
  },
  sheetMeta: {
    marginTop: 6,
    color: colors.muted,
    fontSize: 12,
    lineHeight: 17,
  },
  textarea: {
    marginTop: 14,
    minHeight: 110,
    maxHeight: 180,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: 12,
    fontSize: 14,
    color: colors.slate900,
  },
  actionRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 16,
  },
  btn: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: radius.lg,
    alignItems: 'center',
  },
  btnCancel: {
    backgroundColor: colors.slate100,
  },
  btnCancelText: {
    color: colors.slate700,
    fontSize: 14,
    fontWeight: '800',
  },
  btnSave: {
    backgroundColor: colors.blue700,
  },
  btnSaveText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '800',
  },
});
