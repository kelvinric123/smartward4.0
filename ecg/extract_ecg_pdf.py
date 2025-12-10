#!/usr/bin/env python3
"""
Extract PDF from ECG XML files
Handles the specific format where PDF is base64-encoded in <StudyData> element
"""

import os
import sys
import xml.etree.ElementTree as ET
import base64

def extract_pdf_from_ecg_xml(xml_file):
    """Extract embedded PDF from ECG XML file"""
    print(f"Processing: {os.path.basename(xml_file)}")
    
    # Read XML file
    with open(xml_file, 'rb') as f:
        data = f.read()
    
    # Decode XML content
    try:
        if data[:2] == b'\xff\xfe':
            xml_content = data.decode('utf-16-le')
        elif data[:2] == b'\xfe\xff':
            xml_content = data.decode('utf-16-be')
        else:
            xml_content = data.decode('utf-8')
    except Exception as e:
        print(f"Error decoding XML: {e}")
        return None
    
    # Parse XML
    try:
        root = ET.fromstring(xml_content)
    except Exception as e:
        print(f"Error parsing XML: {e}")
        return None
    
    # Look for StudyData element (ECG specific)
    study_data = root.find('.//StudyData')
    if study_data is not None and study_data.text and study_data.text.strip():
        pdf_data = study_data.text.strip()
        print(f"Found StudyData element with {len(pdf_data)} characters")
        
        # Check if it starts with PDF base64 signature
        if pdf_data.startswith('JVBERi'):  # %PDF in base64
            try:
                pdf_bytes = base64.b64decode(pdf_data)
                
                # Verify it's a PDF
                if pdf_bytes.startswith(b'%PDF'):
                    print(f"Successfully decoded PDF ({len(pdf_bytes):,} bytes)")
                    return pdf_bytes
                else:
                    print("Decoded data is not a valid PDF")
                    return None
                    
            except Exception as e:
                print(f"Error decoding base64 PDF data: {e}")
                return None
        else:
            print("StudyData doesn't contain PDF base64 data")
            return None
    else:
        print("No StudyData element found or element is empty")
        return None

def main():
    print("="*70)
    print("ECG PDF Extractor")
    print("="*70)
    print()
    
    # Get input file or process all XML files in ftp_data
    if len(sys.argv) > 1:
        xml_file = sys.argv[1]
        if not os.path.exists(xml_file):
            print(f"Error: File not found: {xml_file}")
            return
        
        # Single file processing
        pdf_data = extract_pdf_from_ecg_xml(xml_file)
        
        if pdf_data:
            # Save extracted PDF
            pdf_file = os.path.splitext(xml_file)[0] + '_extracted.pdf'
            
            with open(pdf_file, 'wb') as f:
                f.write(pdf_data)
            
            print(f"\n[SUCCESS] PDF extracted successfully!")
            print(f"Saved to: {pdf_file}")
            print(f"Size: {len(pdf_data):,} bytes")
            print(f"\nYou can now open it with WPS Office or any PDF viewer!")
        else:
            print("\n[ERROR] Could not extract PDF from XML file")
    else:
        # Process all XML files in ftp_data
        ftp_data_dir = os.path.join(os.path.dirname(__file__), "ftp_data")
        
        if not os.path.exists(ftp_data_dir):
            print("Error: ftp_data directory not found")
            return
        
        xml_files = [f for f in os.listdir(ftp_data_dir) if f.endswith('.xml')]
        
        if not xml_files:
            print("Error: No XML files found in ftp_data directory")
            return
        
        print(f"Found {len(xml_files)} XML file(s) to process:")
        for f in xml_files:
            print(f"  - {f}")
        print()
        
        # Process all files
        total_processed = 0
        total_extracted = 0
        
        for xml_filename in xml_files:
            xml_file = os.path.join(ftp_data_dir, xml_filename)
            print(f"Processing {xml_filename}...")
            
            pdf_data = extract_pdf_from_ecg_xml(xml_file)
            total_processed += 1
            
            if pdf_data:
                # Save extracted PDF
                pdf_filename = os.path.splitext(xml_filename)[0] + '_extracted.pdf'
                pdf_path = os.path.join(ftp_data_dir, pdf_filename)
                
                with open(pdf_path, 'wb') as f:
                    f.write(pdf_data)
                
                print(f"[OK] PDF extracted to: {pdf_filename}")
                total_extracted += 1
            else:
                print("[NO] No PDF found in this XML file")
            
            print("-" * 50)
        
        print(f"\nSummary:")
        print(f"Files processed: {total_processed}")
        print(f"PDFs extracted: {total_extracted}")
        
        if total_extracted > 0:
            print(f"\n[SUCCESS] You can now open the extracted PDF files with:")
            print("  - WPS Office")
            print("  - Adobe Reader")
            print("  - Chrome/Edge browser")
            print("  - Any PDF viewer")
            print(f"\nLocation: {ftp_data_dir}")

if __name__ == "__main__":
    main()
