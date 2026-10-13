// QR code generator for the AI Nurse Schedule's Share dialog, so a view-only
// link can be scanned from a screen or a printout. Bundled rather than taken
// from a CDN, so it works on a hospital network with no internet. The dialog
// uses the global QRCode and hides its QR codes when this has not loaded.
import QRCode from 'qrcode';

window.QRCode = QRCode;
