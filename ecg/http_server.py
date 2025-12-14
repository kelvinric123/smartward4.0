#!/usr/bin/env python3
"""
ECG HTTP Upload Server
Receives ECG PDF/XML uploads from ECG machines (e.g., Philips TC35)
Supports HTTP POST and PUT with Basic Authentication

Environment Variables:
  ECG_HOST     - Server host (default: 0.0.0.0)
  ECG_PORT     - Server port (default: 8080)
  ECG_USERNAME - Basic auth username (default: admin)
  ECG_PASSWORD - Basic auth password (default: admin123)
"""

import os
import sys
import cgi
import io
import logging
from http.server import HTTPServer, BaseHTTPRequestHandler
from datetime import datetime
import base64

# =============================================================================
# Logging Configuration
# =============================================================================
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s | %(levelname)-8s | %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
    stream=sys.stdout
)
logger = logging.getLogger('ECG-Server')

# =============================================================================
# Configuration from Environment Variables
# =============================================================================
ECG_HOST = os.environ.get('ECG_HOST', '0.0.0.0')
ECG_PORT = int(os.environ.get('ECG_PORT', 8080))
ECG_USERNAME = os.environ.get('ECG_USERNAME', 'admin')
ECG_PASSWORD = os.environ.get('ECG_PASSWORD', 'admin123')


class ECGUploadHandler(BaseHTTPRequestHandler):
    """Handle HTTP POST uploads from ECG machine"""
    
    # Basic authentication credentials (from environment)
    USERNAME = ECG_USERNAME
    PASSWORD = ECG_PASSWORD
    
    def handle(self):
        """Override handle to suppress BrokenPipeError exceptions"""
        try:
            super().handle()
        except (BrokenPipeError, ConnectionResetError):
            # Client disconnected - this is normal and expected
            pass
    
    def detect_file_type(self, data):
        """Detect actual file type from content"""
        if len(data) < 10:
            return ".bin"
        
        # Check for PDF signature
        if data[:4] == b'%PDF':
            return ".pdf"
        
        # Check for XML (UTF-8)
        if data[:5] == b'<?xml':
            return ".xml"
        
        # Check for XML (UTF-16 with BOM)
        if data[:2] == b'\xff\xfe' and b'<' in data[:20] and b'?' in data[:20]:
            try:
                text = data[:200].decode('utf-16-le', errors='ignore')
                if '<?xml' in text or '<restingecgdata' in text:
                    return ".xml"
            except:
                pass
        
        # Check for XML (UTF-16 BE with BOM)
        if data[:2] == b'\xfe\xff':
            try:
                text = data[:200].decode('utf-16-be', errors='ignore')
                if '<?xml' in text or '<restingecgdata' in text:
                    return ".xml"
            except:
                pass
        
        # Check for HTML
        if b'<html' in data[:1000].lower() or b'<!doctype' in data[:1000].lower():
            return ".html"
        
        # Check for JSON
        try:
            text = data[:100].decode('utf-8', errors='ignore')
            if text.strip().startswith('{') or text.strip().startswith('['):
                return ".json"
        except:
            pass
        
        # Default to .bin for unknown types
        return ".bin"
    
    def extract_pdf_from_xml(self, xml_data):
        """Extract PDF from ECG XML if present"""
        try:
            # Try to decode as UTF-16 or UTF-8
            if xml_data[:2] == b'\xff\xfe':
                xml_content = xml_data.decode('utf-16-le')
            elif xml_data[:2] == b'\xfe\xff':
                xml_content = xml_data.decode('utf-16-be')
            else:
                xml_content = xml_data.decode('utf-8')
            
            # Parse XML
            import xml.etree.ElementTree as ET
            root = ET.fromstring(xml_content)
            
            # Look for StudyData element
            study_data = root.find('.//StudyData')
            if study_data is not None and study_data.text and study_data.text.strip():
                pdf_data = study_data.text.strip()
                
                # Check if it's base64 PDF
                if pdf_data.startswith('JVBERi'):  # %PDF in base64
                    pdf_bytes = base64.b64decode(pdf_data)
                    
                    if pdf_bytes.startswith(b'%PDF'):
                        return pdf_bytes
            
            return None
        except Exception:
            return None
    
    def _parse_multipart_form(self):
        """Parse multipart form data from web browser upload"""
        try:
            content_type = self.headers.get('Content-Type')
            
            # Parse the multipart form data
            form = cgi.FieldStorage(
                fp=self.rfile,
                headers=self.headers,
                environ={
                    'REQUEST_METHOD': 'POST',
                    'CONTENT_TYPE': content_type,
                }
            )
            
            # Look for file field
            if 'file' in form:
                file_item = form['file']
                if file_item.file:
                    file_data = file_item.file.read()
                    filename = file_item.filename if file_item.filename else None
                    return file_data, filename
            
            return None, None
        except Exception as e:
            logger.error(f"Error parsing multipart form: {e}")
            return None, None
    
    def do_POST(self):
        """Handle POST request with file upload"""
        # Check authentication
        if not self.check_auth():
            self.send_auth_required()
            return
        
        try:
            # Get content length
            content_length = int(self.headers.get('Content-Length', 0))
            
            if content_length == 0:
                self.send_error(400, "No content")
                return
            
            # Check if this is multipart form data (from web form)
            content_type = self.headers.get('Content-Type', '')
            is_web_form = 'multipart/form-data' in content_type
            
            if is_web_form:
                # Handle multipart form upload (web browser)
                post_data, filename = self._parse_multipart_form()
                if post_data is None:
                    self._redirect_with_status('error', error='No file in form data')
                    return
            else:
                # Handle raw POST data (ECG machine)
                post_data = self.rfile.read(content_length)
                filename = None
                
                # Try to get filename from headers or path
                if 'Content-Disposition' in self.headers:
                    content_disp = self.headers.get('Content-Disposition')
                    if 'filename=' in content_disp:
                        filename = content_disp.split('filename=')[1].strip('"')
            
            # Generate filename
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            
            if not filename:
                # Use path or default name
                path_parts = self.path.strip('/').split('/')
                if path_parts and path_parts[-1]:
                    filename = path_parts[-1]
                else:
                    filename = f"ecg_upload_{timestamp}"
            
            # Detect actual file type from content
            file_extension = self.detect_file_type(post_data)
            
            # Check if it's an XML with embedded PDF and extract it
            extracted_pdf = None
            if file_extension == '.xml':
                extracted_pdf = self.extract_pdf_from_xml(post_data)
            
            # Remove any existing extension and add the correct one
            base_name = os.path.splitext(filename)[0]
            filename = f"{base_name}{file_extension}"
            
            # Save to ftp_data directory
            save_dir = os.path.join(os.path.dirname(__file__), "ftp_data")
            if not os.path.exists(save_dir):
                os.makedirs(save_dir)
            
            filepath = os.path.join(save_dir, filename)
            
            # Write file
            with open(filepath, 'wb') as f:
                f.write(post_data)
            
            # If we extracted a PDF from XML, also save the PDF
            if extracted_pdf:
                pdf_filename = f"{base_name}_extracted.pdf"
                pdf_filepath = os.path.join(save_dir, pdf_filename)
                
                with open(pdf_filepath, 'wb') as f:
                    f.write(extracted_pdf)
                
                logger.info(f"PDF extracted from XML: {pdf_filename} ({len(extracted_pdf):,} bytes)")
            
            file_size = len(post_data)
            
            # Detect file type for display
            file_type = "Unknown"
            if filename.endswith('.pdf'):
                file_type = "PDF"
            elif filename.endswith('.xml'):
                file_type = "XML (ECG Data)"
            elif filename.endswith('.html'):
                file_type = "HTML"
            elif filename.endswith('.json'):
                file_type = "JSON"
            
            # Log success
            logger.info("=" * 60)
            logger.info("FILE RECEIVED via HTTP POST")
            logger.info(f"  Source IP  : {self.client_address[0]}")
            logger.info(f"  Filename   : {filename}")
            logger.info(f"  File Type  : {file_type}")
            logger.info(f"  File Size  : {file_size:,} bytes")
            logger.info(f"  Saved To   : {filepath}")
            logger.info(f"  Status     : SUCCESS")
            logger.info("=" * 60)
            
            # Send appropriate response based on source
            if is_web_form:
                # Redirect web browser to show success status
                self._redirect_with_status('success', filename=filename)
            else:
                # Send ECG-compatible success response for ECG machines
                self._send_ecg_success_response()
            
        except Exception as e:
            logger.error("=" * 60)
            logger.error("FILE UPLOAD FAILED via HTTP POST")
            logger.error(f"  Source IP  : {self.client_address[0]}")
            logger.error(f"  Error      : {str(e)}")
            logger.error(f"  Status     : FAILED")
            logger.error("=" * 60)
            if 'is_web_form' in locals() and is_web_form:
                self._redirect_with_status('error', error=str(e))
            else:
                self._send_ecg_error_response()
    
    def do_PUT(self):
        """Handle PUT request (WebDAV style)"""
        # Check authentication
        if not self.check_auth():
            self.send_auth_required()
            return
        
        try:
            # Get content length
            content_length = int(self.headers.get('Content-Length', 0))
            
            if content_length == 0:
                self.send_error(400, "No content")
                return
            
            # Read the data
            file_data = self.rfile.read(content_length)
            
            # Get filename from URL path
            filename = os.path.basename(self.path)
            if not filename or filename == '/':
                timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                filename = f"ecg_upload_{timestamp}"
            
            # Detect actual file type
            file_extension = self.detect_file_type(file_data)
            base_name = os.path.splitext(filename)[0]
            filename = f"{base_name}{file_extension}"
            
            # Save to ftp_data directory
            save_dir = os.path.join(os.path.dirname(__file__), "ftp_data")
            if not os.path.exists(save_dir):
                os.makedirs(save_dir)
            
            filepath = os.path.join(save_dir, filename)
            
            # Write file
            with open(filepath, 'wb') as f:
                f.write(file_data)
            
            file_size = len(file_data)
            
            # Detect file type for display
            file_type = "Unknown"
            if filename.endswith('.pdf'):
                file_type = "PDF"
            elif filename.endswith('.xml'):
                file_type = "XML (ECG Data)"
            elif filename.endswith('.html'):
                file_type = "HTML"
            elif filename.endswith('.json'):
                file_type = "JSON"
            
            # Log success
            logger.info("=" * 60)
            logger.info("FILE RECEIVED via HTTP PUT")
            logger.info(f"  Source IP  : {self.client_address[0]}")
            logger.info(f"  Filename   : {filename}")
            logger.info(f"  File Type  : {file_type}")
            logger.info(f"  File Size  : {file_size:,} bytes")
            logger.info(f"  Saved To   : {filepath}")
            logger.info(f"  Status     : SUCCESS")
            logger.info("=" * 60)
            
            # Send ECG-compatible success response
            self._send_ecg_success_response()
            
        except Exception as e:
            logger.error("=" * 60)
            logger.error("FILE UPLOAD FAILED via HTTP PUT")
            logger.error(f"  Source IP  : {self.client_address[0]}")
            logger.error(f"  Error      : {str(e)}")
            logger.error(f"  Status     : FAILED")
            logger.error("=" * 60)
            self._send_ecg_error_response()
    
    def check_auth(self):
        """Check HTTP Basic Authentication"""
        auth_header = self.headers.get('Authorization')
        
        if not auth_header:
            return False
        
        try:
            # Parse "Basic base64string"
            auth_type, credentials = auth_header.split(' ', 1)
            
            if auth_type.lower() != 'basic':
                return False
            
            # Decode credentials
            decoded = base64.b64decode(credentials).decode('utf-8')
            username, password = decoded.split(':', 1)
            
            # Check credentials
            return username == self.USERNAME and password == self.PASSWORD
            
        except Exception:
            return False
    
    def _safe_write(self, data):
        """Safely write to socket, handling BrokenPipeError gracefully"""
        try:
            self.wfile.write(data)
            self.wfile.flush()
        except BrokenPipeError:
            # Client disconnected before receiving response - this is normal
            pass
        except ConnectionResetError:
            # Connection was reset by peer - also normal
            pass

    def _redirect_with_status(self, status, filename=None, error=None):
        """Redirect to main page with status message for web browser uploads"""
        try:
            import urllib.parse
            params = {'status': status}
            if filename:
                params['file'] = filename
            if error:
                params['error'] = error
            query = urllib.parse.urlencode(params)
            
            self.send_response(302)
            self.send_header('Location', f'/?{query}')
            self.send_header('Connection', 'close')
            self.end_headers()
        except (BrokenPipeError, ConnectionResetError):
            pass
    
    def _send_ecg_success_response(self):
        """
        Send HTTP/1.1 200 OK with "OK" body for Philips TC35 (IDEP protocol).
        Philips IntelliVue Data Export Protocol format.
        """
        # HTTP/1.1 with "OK" body - IDEP protocol
        body = b"OK"
        raw_response = (
            b"HTTP/1.1 200 OK\r\n"
            b"Content-Type: text/plain\r\n"
            b"Content-Length: 2\r\n"
            b"Connection: close\r\n"
            b"\r\n"
        ) + body
        self._safe_write(raw_response)
    
    def _send_ecg_error_response(self):
        """Send HTTP/1.1 error response"""
        body = b"ERROR"
        raw_response = (
            b"HTTP/1.1 500 Internal Server Error\r\n"
            b"Content-Type: text/plain\r\n"
            b"Content-Length: 5\r\n"
            b"Connection: close\r\n"
            b"\r\n"
        ) + body
        self._safe_write(raw_response)
    
    def send_auth_required(self):
        """Send 401 authentication required response"""
        try:
            self.send_response(401)
            self.send_header('WWW-Authenticate', 'Basic realm="ECG Upload Server"')
            self.send_header('Content-Type', 'text/plain; charset=utf-8')
            self.send_header('Content-Length', '13')
            self.send_header('Connection', 'close')
            self.send_header('Server', 'ECG-Upload-Server/1.0')
            self.end_headers()
            self.wfile.write(b'Unauthorized')
        except (BrokenPipeError, ConnectionResetError):
            pass
    
    def do_OPTIONS(self):
        """Handle OPTIONS requests (CORS preflight)"""
        try:
            self.send_response(200)
            self.send_header('Access-Control-Allow-Origin', '*')
            self.send_header('Access-Control-Allow-Methods', 'POST, PUT, OPTIONS')
            self.send_header('Access-Control-Allow-Headers', 'Authorization, Content-Type')
            self.send_header('Connection', 'close')
            self.send_header('Server', 'Apache/2.4.0')
            self.end_headers()
        except (BrokenPipeError, ConnectionResetError):
            pass
    
    def do_GET(self):
        """Handle GET requests - serve upload form"""
        try:
            # Check for status parameter (after upload redirect)
            status = None
            filename = None
            error_msg = None
            
            if '?' in self.path:
                query = self.path.split('?')[1]
                params = {}
                for param in query.split('&'):
                    if '=' in param:
                        key, value = param.split('=', 1)
                        params[key] = value
                status = params.get('status')
                filename = params.get('file', '').replace('%20', ' ')
                error_msg = params.get('error', '').replace('%20', ' ')
            
            self.send_response(200)
            self.send_header('Content-Type', 'text/html; charset=utf-8')
            self.send_header('Connection', 'close')
            self.send_header('Server', 'Apache/2.4.0')
            self.end_headers()
            
            # Build status message HTML
            status_html = ""
            if status == "success":
                status_html = f'''
                <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border: 1px solid #10b981; border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.1);">
                    <div style="display: flex; align-items: center;">
                        <div style="background: #10b981; border-radius: 50%; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; margin-right: 16px;">
                            <svg width="24" height="24" fill="none" stroke="white" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <h3 style="margin: 0 0 4px 0; color: #065f46; font-size: 18px; font-weight: 600;">Upload Successful!</h3>
                            <p style="margin: 0; color: #047857; font-size: 14px;">File saved: <code style="background: rgba(16, 185, 129, 0.2); padding: 2px 8px; border-radius: 4px;">{filename}</code></p>
                        </div>
                    </div>
                </div>
                '''
            elif status == "error":
                status_html = f'''
                <div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border: 1px solid #ef4444; border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.1);">
                    <div style="display: flex; align-items: center;">
                        <div style="background: #ef4444; border-radius: 50%; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; margin-right: 16px;">
                            <svg width="24" height="24" fill="none" stroke="white" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <div>
                            <h3 style="margin: 0 0 4px 0; color: #991b1b; font-size: 18px; font-weight: 600;">Upload Failed</h3>
                            <p style="margin: 0; color: #b91c1c; font-size: 14px;">{error_msg or 'An error occurred during upload'}</p>
                        </div>
                    </div>
                </div>
                '''
            
            html = f'''<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECG Upload Server</title>
    <style>
        * {{ margin: 0; padding: 0; box-sizing: border-box; }}
        body {{
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }}
        .container {{
            max-width: 600px;
            margin: 0 auto;
        }}
        .card {{
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }}
        .header {{
            background: linear-gradient(135deg, #f43f5e 0%, #ec4899 100%);
            padding: 32px;
            text-align: center;
        }}
        .header h1 {{
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }}
        .header p {{
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }}
        .icon {{
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }}
        .content {{
            padding: 32px;
        }}
        .upload-area {{
            border: 3px dashed #e5e7eb;
            border-radius: 16px;
            padding: 40px 24px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #fafafa;
        }}
        .upload-area:hover {{
            border-color: #f43f5e;
            background: #fff5f7;
        }}
        .upload-area.dragover {{
            border-color: #10b981;
            background: #ecfdf5;
        }}
        .upload-icon {{
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            color: #9ca3af;
        }}
        .upload-text {{
            color: #374151;
            font-size: 16px;
            margin-bottom: 8px;
        }}
        .upload-hint {{
            color: #9ca3af;
            font-size: 13px;
        }}
        input[type="file"] {{
            display: none;
        }}
        .btn {{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 32px;
            background: linear-gradient(135deg, #f43f5e 0%, #ec4899 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 24px;
        }}
        .btn:hover {{
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(244, 63, 94, 0.4);
        }}
        .btn:disabled {{
            background: #d1d5db;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }}
        .info-section {{
            margin-top: 24px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
        }}
        .info-section h3 {{
            color: #374151;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }}
        .info-grid {{
            display: grid;
            gap: 8px;
        }}
        .info-item {{
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }}
        .info-label {{
            color: #6b7280;
        }}
        .info-value {{
            color: #111827;
            font-weight: 500;
        }}
        .info-value code {{
            background: #e5e7eb;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }}
        .selected-file {{
            margin-top: 16px;
            padding: 12px 16px;
            background: #ecfdf5;
            border: 1px solid #10b981;
            border-radius: 8px;
            display: none;
            align-items: center;
        }}
        .selected-file.show {{
            display: flex;
        }}
        .file-icon {{
            width: 32px;
            height: 32px;
            background: #10b981;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }}
        .file-info {{
            flex: 1;
        }}
        .file-name {{
            color: #065f46;
            font-weight: 500;
            font-size: 14px;
        }}
        .file-size {{
            color: #047857;
            font-size: 12px;
        }}
        .loading {{
            display: none;
        }}
        .loading.show {{
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
        }}
        @keyframes spin {{
            to {{ transform: rotate(360deg); }}
        }}
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="icon">
                    <svg width="48" height="48" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <h1>ECG Upload Server</h1>
                <p>Upload ECG files (XML or PDF) from your ECG machine</p>
            </div>
            
            <div class="content">
                {status_html}
                
                <form id="uploadForm" method="POST" enctype="multipart/form-data">
                    <div class="upload-area" id="dropZone" onclick="document.getElementById('fileInput').click()">
                        <svg class="upload-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                        </svg>
                        <p class="upload-text">Click to select or drag and drop</p>
                        <p class="upload-hint">Supports XML and PDF files</p>
                    </div>
                    
                    <input type="file" id="fileInput" name="file" accept=".xml,.pdf,.XML,.PDF">
                    
                    <div class="selected-file" id="selectedFile">
                        <div class="file-icon">
                            <svg width="16" height="16" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="file-info">
                            <div class="file-name" id="fileName"></div>
                            <div class="file-size" id="fileSize"></div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn" id="uploadBtn" disabled>
                        <span class="loading" id="loadingSpinner"></span>
                        <span id="btnText">Select a file to upload</span>
                    </button>
                </form>
                
                <div class="info-section">
                    <h3>Server Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Server URL</span>
                            <span class="info-value"><code>http://localhost:8080/</code></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Authentication</span>
                            <span class="info-value">Basic Auth</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Username</span>
                            <span class="info-value"><code>admin</code></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Password</span>
                            <span class="info-value"><code>admin123</code></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Storage</span>
                            <span class="info-value"><code>ecg/store/</code></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const selectedFile = document.getElementById('selectedFile');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const uploadBtn = document.getElementById('uploadBtn');
        const btnText = document.getElementById('btnText');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const uploadForm = document.getElementById('uploadForm');
        
        // Drag and drop handlers
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {{
            dropZone.addEventListener(eventName, preventDefaults, false);
        }});
        
        function preventDefaults(e) {{
            e.preventDefault();
            e.stopPropagation();
        }}
        
        ['dragenter', 'dragover'].forEach(eventName => {{
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
        }});
        
        ['dragleave', 'drop'].forEach(eventName => {{
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
        }});
        
        dropZone.addEventListener('drop', (e) => {{
            const files = e.dataTransfer.files;
            if (files.length) {{
                fileInput.files = files;
                handleFileSelect(files[0]);
            }}
        }});
        
        fileInput.addEventListener('change', (e) => {{
            if (e.target.files.length) {{
                handleFileSelect(e.target.files[0]);
            }}
        }});
        
        function handleFileSelect(file) {{
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            selectedFile.classList.add('show');
            uploadBtn.disabled = false;
            btnText.textContent = 'Upload File';
        }}
        
        function formatFileSize(bytes) {{
            if (bytes < 1024) return bytes + ' bytes';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }}
        
        uploadForm.addEventListener('submit', (e) => {{
            uploadBtn.disabled = true;
            loadingSpinner.classList.add('show');
            btnText.textContent = 'Uploading...';
        }});
    </script>
</body>
</html>'''
            self.wfile.write(html.encode())
        except (BrokenPipeError, ConnectionResetError):
            # Client disconnected before receiving response - this is normal
            pass
    
    def log_message(self, format, *args):
        """Custom log format - show ECG machine requests"""
        msg = format % args
        if "POST" in msg or "PUT" in msg:
            logger.debug(f"HTTP Request: {msg}")
        # Suppress other HTTP logging noise (GET, healthchecks, etc.)


def main():
    """Main entry point for ECG HTTP Upload Server"""
    
    # Create upload directories
    base_dir = os.path.dirname(__file__) or '.'
    upload_dir = os.path.join(base_dir, "ftp_data")
    store_dir = os.path.join(base_dir, "store")
    
    for directory in [upload_dir, store_dir]:
        if not os.path.exists(directory):
            os.makedirs(directory)
            logger.info(f"Created directory: {directory}")
    
    # Create server
    server = HTTPServer((ECG_HOST, ECG_PORT), ECGUploadHandler)
    
    # Startup banner
    logger.info("=" * 70)
    logger.info("ECG HTTP UPLOAD SERVER")
    logger.info("=" * 70)
    logger.info(f"Server Status    : STARTING")
    logger.info(f"Server Address   : http://{ECG_HOST}:{ECG_PORT}")
    logger.info(f"Upload Directory : {upload_dir}")
    logger.info(f"Store Directory  : {store_dir}")
    logger.info("-" * 70)
    logger.info("AUTHENTICATION")
    logger.info(f"  Username       : {ECG_USERNAME}")
    logger.info(f"  Password       : {'*' * len(ECG_PASSWORD)}")
    logger.info("-" * 70)
    logger.info("SUPPORTED METHODS")
    logger.info(f"  HTTP POST      : Enabled")
    logger.info(f"  HTTP PUT       : Enabled")
    logger.info("-" * 70)
    logger.info("ECG MACHINE CONFIGURATION")
    logger.info(f"  URL            : http://<SERVER_IP>:{ECG_PORT}/")
    logger.info(f"  Method         : POST or PUT")
    logger.info(f"  Auth Type      : Basic Authentication")
    logger.info("=" * 70)
    logger.info("Server Status    : READY - Waiting for uploads...")
    logger.info("=" * 70)
    
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        logger.info("")
        logger.info("=" * 70)
        logger.info("Server Status    : SHUTTING DOWN")
        logger.info("=" * 70)
        server.shutdown()
        logger.info("Server Status    : STOPPED")


if __name__ == "__main__":
    main()

