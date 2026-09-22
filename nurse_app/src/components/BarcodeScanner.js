// Full-screen barcode scanner (expo-camera). `read(data, type)` decides what a
// scan means: { value } closes the scanner and hands the value back,
// { error } is shown while the camera keeps looking - so a wrong barcode on a
// busy blood bag label never lands in the form.
//
// Render it inside the sheet that uses it: a modal opened from inside another
// modal is what iOS expects. The camera is only mounted while the scanner is
// open (one camera preview at a time).

import React, { useEffect, useRef, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Modal,
  TouchableOpacity,
  ActivityIndicator,
  Platform,
  Linking,
} from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, radius } from '../theme';

// Blood bags: Code 128 (ISBT 128) and Data Matrix; older bags Codabar; lab
// and crossmatch labels vary, so the common 1D and 2D types too.
const BARCODE_TYPES = ['code128', 'datamatrix', 'codabar', 'code39', 'qr', 'pdf417', 'code93', 'itf14'];

// The same code stays in view for a while: say what is wrong with it once
const REPEAT_MS = 2500;

export default function BarcodeScanner({ visible, title, hint, read, onScanned, onClose }) {
  const insets = useSafeAreaInsets();
  const [permission, requestPermission] = useCameraPermissions();
  const [torch, setTorch] = useState(false);
  const [notice, setNotice] = useState(null);
  const [cameraError, setCameraError] = useState(null);
  const [refused, setRefused] = useState(false);
  const done = useRef(false);
  const last = useRef({ data: null, at: 0 });
  const asked = useRef(false);

  // Fresh state each time the scanner opens
  useEffect(() => {
    if (!visible) return;
    done.current = false;
    asked.current = false;
    last.current = { data: null, at: 0 };
    setNotice(null);
    setCameraError(null);
    setRefused(false);
    setTorch(false);
  }, [visible]);

  async function ask() {
    try {
      const result = await requestPermission();
      setRefused(!result?.granted);
    } catch {
      setRefused(true); // a browser without a camera can reject outright
    }
  }

  // Ask for the camera straight away the first time, instead of an extra tap
  useEffect(() => {
    if (visible && permission && !permission.granted && permission.canAskAgain && !asked.current) {
      asked.current = true;
      ask();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible, permission]);

  function handle({ type, data }) {
    if (done.current || !data) return;
    const now = Date.now();
    if (last.current.data === data && now - last.current.at < REPEAT_MS) return;
    last.current = { data, at: now };

    const result = read(data, type);
    if (result.error) {
      setNotice(result.error);
      return;
    }
    done.current = true;
    onScanned(result.value, result);
  }

  const granted = !!permission?.granted;
  const blocked = permission && !permission.granted && !permission.canAskAgain;
  const native = Platform.OS !== 'web';

  return (
    <Modal visible={visible} animationType="slide" statusBarTranslucent onRequestClose={onClose}>
      <View style={styles.root}>
        {visible && granted && !cameraError ? (
          <CameraView
            style={StyleSheet.absoluteFill}
            facing="back"
            enableTorch={torch}
            barcodeScannerSettings={{ barcodeTypes: BARCODE_TYPES }}
            onBarcodeScanned={handle}
            onMountError={(e) => setCameraError(e?.message || 'The camera could not start.')}
          />
        ) : null}

        <View style={[styles.overlay, { paddingTop: insets.top + 12, paddingBottom: insets.bottom + 16 }]}>
          <View style={styles.topBar}>
            <TouchableOpacity style={styles.roundBtn} onPress={onClose} activeOpacity={0.8}>
              <Text style={styles.roundBtnText}>✕</Text>
            </TouchableOpacity>
            <Text style={styles.title} numberOfLines={1}>{title}</Text>
            {native && granted && !cameraError ? (
              <TouchableOpacity
                style={[styles.roundBtn, torch && styles.roundBtnOn]}
                onPress={() => setTorch((on) => !on)}
                activeOpacity={0.8}
              >
                <Text style={[styles.torchText, torch && { color: colors.slate900 }]}>{torch ? 'ON' : 'LIGHT'}</Text>
              </TouchableOpacity>
            ) : (
              <View style={styles.roundSpacer} />
            )}
          </View>

          <View style={styles.middle}>
            {granted && !cameraError ? (
              <>
                <View style={styles.frame}>
                  <View style={[styles.corner, styles.cornerTL]} />
                  <View style={[styles.corner, styles.cornerTR]} />
                  <View style={[styles.corner, styles.cornerBL]} />
                  <View style={[styles.corner, styles.cornerBR]} />
                  <View style={styles.scanLine} />
                </View>
                <Text style={styles.hint}>{hint}</Text>
              </>
            ) : (
              <View style={styles.card}>
                {!permission ? (
                  <>
                    <ActivityIndicator color={colors.cyan700} />
                    <Text style={styles.cardText}>Checking camera access…</Text>
                  </>
                ) : cameraError ? (
                  <>
                    <Text style={styles.cardTitle}>The camera did not start</Text>
                    <Text style={styles.cardText}>
                      {cameraError}
                      {Platform.OS === 'web' ? ' In a browser the camera needs https or localhost.' : ''}
                    </Text>
                  </>
                ) : blocked ? (
                  <>
                    <Text style={styles.cardTitle}>Camera access is off</Text>
                    <Text style={styles.cardText}>
                      Turn on the camera for this app in the phone's settings to scan, or type the number instead.
                    </Text>
                    {native ? (
                      <TouchableOpacity style={styles.cardBtn} onPress={() => Linking.openSettings()} activeOpacity={0.85}>
                        <Text style={styles.cardBtnText}>Open settings</Text>
                      </TouchableOpacity>
                    ) : null}
                  </>
                ) : refused ? (
                  <>
                    <Text style={styles.cardTitle}>The camera was not allowed</Text>
                    <Text style={styles.cardText}>
                      Without it the code cannot be read. Try again and allow the camera, or type the number instead.
                    </Text>
                    <TouchableOpacity style={styles.cardBtn} onPress={ask} activeOpacity={0.85}>
                      <Text style={styles.cardBtnText}>Try again</Text>
                    </TouchableOpacity>
                  </>
                ) : (
                  <>
                    <Text style={styles.cardTitle}>Allow the camera</Text>
                    <Text style={styles.cardText}>The camera is only used to read barcodes. Nothing is recorded.</Text>
                    <TouchableOpacity style={styles.cardBtn} onPress={ask} activeOpacity={0.85}>
                      <Text style={styles.cardBtnText}>Allow camera</Text>
                    </TouchableOpacity>
                  </>
                )}
              </View>
            )}
          </View>

          <View style={styles.bottom}>
            {notice ? (
              <View style={styles.notice}>
                <Text style={styles.noticeText}>{notice}</Text>
              </View>
            ) : null}
            <TouchableOpacity style={styles.typeBtn} onPress={onClose} activeOpacity={0.85}>
              <Text style={styles.typeBtnText}>Type it instead</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const FRAME_BORDER = 'rgba(255,255,255,0.95)';

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#000' },
  overlay: { ...StyleSheet.absoluteFillObject, justifyContent: 'space-between', paddingHorizontal: 16, pointerEvents: 'box-none' },
  topBar: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  roundBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(15,23,42,0.6)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.25)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  roundBtnOn: { backgroundColor: '#fde68a', borderColor: '#fde68a' },
  roundBtnText: { color: '#fff', fontSize: 18, fontWeight: '800' },
  roundSpacer: { width: 44, height: 44 },
  torchText: { color: '#fff', fontSize: 9, fontWeight: '800', letterSpacing: 0.8 },
  title: { flex: 1, textAlign: 'center', color: '#fff', fontSize: 16, fontWeight: '800' },
  middle: { flex: 1, alignItems: 'center', justifyContent: 'center', pointerEvents: 'box-none' },
  // Wide and short: most blood bag barcodes are 1D
  frame: { width: '86%', maxWidth: 420, aspectRatio: 2.2, justifyContent: 'center' },
  corner: { position: 'absolute', width: 34, height: 34, borderColor: FRAME_BORDER },
  cornerTL: { top: 0, left: 0, borderTopWidth: 4, borderLeftWidth: 4, borderTopLeftRadius: 14 },
  cornerTR: { top: 0, right: 0, borderTopWidth: 4, borderRightWidth: 4, borderTopRightRadius: 14 },
  cornerBL: { bottom: 0, left: 0, borderBottomWidth: 4, borderLeftWidth: 4, borderBottomLeftRadius: 14 },
  cornerBR: { bottom: 0, right: 0, borderBottomWidth: 4, borderRightWidth: 4, borderBottomRightRadius: 14 },
  scanLine: { marginHorizontal: 18, height: 2, borderRadius: 1, backgroundColor: 'rgba(244,63,94,0.9)' },
  hint: {
    marginTop: 18,
    maxWidth: 360,
    textAlign: 'center',
    color: '#fff',
    fontSize: 13,
    fontWeight: '700',
    textShadowColor: 'rgba(0,0,0,0.8)',
    textShadowRadius: 6,
  },
  card: {
    width: '100%',
    maxWidth: 380,
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    padding: 18,
    alignItems: 'center',
    gap: 8,
  },
  cardTitle: { color: colors.slate900, fontSize: 16, fontWeight: '800', textAlign: 'center' },
  cardText: { color: colors.slate600, fontSize: 13, lineHeight: 18, textAlign: 'center' },
  cardBtn: {
    marginTop: 6,
    backgroundColor: colors.cyan700,
    borderRadius: radius.md,
    paddingHorizontal: 18,
    paddingVertical: 11,
  },
  cardBtnText: { color: '#fff', fontSize: 14, fontWeight: '800' },
  bottom: { gap: 10, alignItems: 'center' },
  notice: {
    width: '100%',
    maxWidth: 440,
    backgroundColor: colors.amber50,
    borderColor: colors.amber500,
    borderWidth: 1,
    borderRadius: radius.md,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  noticeText: { color: colors.amber700, fontSize: 13, fontWeight: '700', textAlign: 'center' },
  typeBtn: {
    width: '100%',
    maxWidth: 440,
    backgroundColor: 'rgba(255,255,255,0.95)',
    borderRadius: radius.md,
    paddingVertical: 13,
    alignItems: 'center',
  },
  typeBtnText: { color: colors.slate900, fontSize: 14, fontWeight: '800' },
});
