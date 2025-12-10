# ECG HTTP Upload Server

HTTP server that receives ECG files and automatically extracts PDF reports. **Optimized for medical device compatibility.**

## Quick Start

1. **Start the server:**
   ```bash
   python http_server.py
   ```

2. **Configure your ECG machine:**
   ```
   URL:      http://192.168.0.100:8080/
   Username: admin
   Password: admin123
   Method:   HTTP POST
   ```

3. **Files are saved to:** `ftp_data/`

## Medical Device Compatibility

The server sends **Apache-compatible HTTP responses** that ECG machines expect:

- ✅ Proper Content-Length headers
- ✅ Connection: close for device compatibility  
- ✅ Cache-Control headers for medical devices
- ✅ Apache server identification
- ✅ Standard "File uploaded successfully" response
- ✅ CORS support for modern devices

## What It Does

- 🏥 **Receives XML files** from ECG machine via HTTP POST
- 🔍 **Automatically detects** embedded PDF reports in XML
- 📄 **Extracts PDFs** so you can open them with WPS Office/Adobe Reader
- 💾 **Saves both formats**: XML (for data) + PDF (for viewing)
- ✅ **Medical device compatible** HTTP responses

## Files

**Essential Files:**
- `http_server.py` - Main HTTP server (medical device optimized)
- `start_http_server.bat` - Quick start (double-click)
- `extract_ecg_pdf.py` - Manual PDF extraction tool
- `ftp_data/` - All uploaded files and extracted PDFs

## Server Response Format

**Success Response:**
```http
HTTP/1.1 200 OK
Content-Type: text/plain
Content-Length: 25
Connection: close
Cache-Control: no-cache
Pragma: no-cache
Server: Apache/2.4.0

File uploaded successfully
```

This format is specifically designed to eliminate "bad reply" errors from ECG machines.

## Manual PDF Extraction

If you have existing XML files:
```bash
python extract_ecg_pdf.py
```

## Troubleshooting

**If ECG shows "bad reply" error:**
1. Restart the server: `python http_server.py`
2. Ensure ECG uses HTTP POST (not PUT/GET)
3. Check server logs for specific errors
4. Verify network connectivity

**Server Details:**
- Port: 8080
- Authentication: admin/admin123
- Supported methods: POST, PUT, OPTIONS
- CORS enabled for device compatibility

---

**Your ECG upload system with medical device compatibility!** 🏥