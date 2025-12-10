#!/usr/bin/env python3
"""
Simple HTTP Server for ECG PDF Uploads
Use this if your ECG machine sends HTTP POST instead of FTP
"""

import os
from http.server import HTTPServer, BaseHTTPRequestHandler
from datetime import datetime
import base64


class ECGUploadHandler(BaseHTTPRequestHandler):
    """Handle HTTP POST uploads from ECG machine"""
    
    # Basic authentication credentials
    USERNAME = "admin"
    PASSWORD = "admin123"
    
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
            
            # Read the posted data
            post_data = self.rfile.read(content_length)
            
            # Generate filename
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            
            # Try to get filename from headers or path
            filename = None
            if 'Content-Disposition' in self.headers:
                content_disp = self.headers.get('Content-Disposition')
                if 'filename=' in content_disp:
                    filename = content_disp.split('filename=')[1].strip('"')
            
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
                
                print(f"  Also extracted PDF: {pdf_filename} ({len(extracted_pdf):,} bytes)")
            
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
            print(f"\n✓ [FILE RECEIVED via HTTP POST]")
            print(f"  From: {self.client_address[0]}")
            print(f"  File: {filename}")
            print(f"  Type: {file_type}")
            print(f"  Size: {file_size:,} bytes")
            print(f"  Saved to: {filepath}\n")
            
            # Send ECG-compatible success response
            self._send_ecg_success_response()
            
        except Exception as e:
            print(f"\n✗ Error processing POST upload: {e}\n")
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
            print(f"\n✓ [FILE RECEIVED via HTTP PUT]")
            print(f"  From: {self.client_address[0]}")
            print(f"  File: {filename}")
            print(f"  Type: {file_type}")
            print(f"  Size: {file_size:,} bytes")
            print(f"  Saved to: {filepath}\n")
            
            # Send ECG-compatible success response
            self._send_ecg_success_response()
            
        except Exception as e:
            print(f"\n✗ Error processing PUT upload: {e}\n")
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
        self.wfile.write(raw_response)
        self.wfile.flush()
    
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
        self.wfile.write(raw_response)
        self.wfile.flush()
    
    def send_auth_required(self):
        """Send 401 authentication required response"""
        self.send_response(401)
        self.send_header('WWW-Authenticate', 'Basic realm="ECG Upload Server"')
        self.send_header('Content-Type', 'text/plain; charset=utf-8')
        self.send_header('Content-Length', '13')
        self.send_header('Connection', 'close')
        self.send_header('Server', 'ECG-Upload-Server/1.0')
        self.end_headers()
        self.wfile.write(b'Unauthorized')
    
    def do_OPTIONS(self):
        """Handle OPTIONS requests (CORS preflight)"""
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'POST, PUT, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Authorization, Content-Type')
        self.send_header('Connection', 'close')
        self.send_header('Server', 'Apache/2.4.0')
        self.end_headers()
    
    def do_GET(self):
        """Handle GET requests"""
        self.send_response(200)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.send_header('Connection', 'close')
        self.send_header('Server', 'Apache/2.4.0')
        self.end_headers()
        
        html = """
        <html>
        <head><title>ECG Upload Server</title></head>
        <body>
            <h1>ECG Upload Server</h1>
            <p>Server is running and ready to receive PDF uploads via HTTP POST/PUT</p>
            <p>Upload directory: ftp_data/</p>
            <hr>
            <h2>Test Upload</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="file" accept=".pdf">
                <button type="submit">Upload</button>
            </form>
        </body>
        </html>
        """
        self.wfile.write(html.encode())
    
    def log_message(self, format, *args):
        """Custom log format - show ECG machine requests"""
        if "POST" in format % args or "PUT" in format % args:
            print(f"[ECG REQUEST] {format % args}")
        # Suppress other HTTP logging noise
        pass


def main():
    # Server configuration
    HOST = '0.0.0.0'
    PORT = 8080
    
    # Create upload directory
    upload_dir = os.path.join(os.path.dirname(__file__), "ftp_data")
    if not os.path.exists(upload_dir):
        os.makedirs(upload_dir)
    
    # Create server
    server = HTTPServer((HOST, PORT), ECGUploadHandler)
    
    print("="*70)
    print("HTTP Upload Server for ECG Machine")
    print("="*70)
    print(f"Server Address: http://{HOST}:{PORT}")
    print(f"Upload Directory: {upload_dir}")
    print(f"\nAuthentication:")
    print(f"  Username: admin")
    print(f"  Password: admin123")
    print(f"\nSupported Methods:")
    print(f"  - HTTP POST")
    print(f"  - HTTP PUT")
    print(f"\nECG Machine Configuration:")
    print(f"  URL: http://192.168.0.100:{PORT}/")
    print(f"  OR:  http://192.168.0.100:{PORT}/upload")
    print(f"  Method: POST or PUT")
    print(f"  Auth: Basic Authentication")
    print("="*70)
    print("\nWatching for file uploads...")
    print("Press Ctrl+C to stop the server\n")
    
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\n\nShutting down HTTP server...")
        server.shutdown()


if __name__ == "__main__":
    main()

