// html5-qrcode for the Vital Signs page's QR scanner, bundled so it works on a
// hospital network with no internet. The page loads this only when the camera
// is opened, and uses the global Html5Qrcode, as the CDN script gave it.
import { Html5Qrcode } from 'html5-qrcode';

window.Html5Qrcode = Html5Qrcode;
