// Generate a simple icon for the application
// This creates a basic icon.ico file using Node.js

const fs = require('fs');
const path = require('path');

// Simple ICO file generator
// Creates a basic 32x32 icon with a gradient

function createSimpleIco() {
  // ICO header
  const header = Buffer.alloc(6);
  header.writeUInt16LE(0, 0);      // Reserved
  header.writeUInt16LE(1, 2);      // ICO type
  header.writeUInt16LE(1, 4);      // Number of images

  // Image directory entry (for 32x32)
  const dirEntry = Buffer.alloc(16);
  dirEntry.writeUInt8(32, 0);      // Width
  dirEntry.writeUInt8(32, 1);      // Height
  dirEntry.writeUInt8(0, 2);       // Color palette
  dirEntry.writeUInt8(0, 3);       // Reserved
  dirEntry.writeUInt16LE(1, 4);    // Color planes
  dirEntry.writeUInt16LE(32, 6);   // Bits per pixel
  
  // Create a simple 32x32 BMP image data
  const width = 32;
  const height = 32;
  const bitsPerPixel = 32;
  const rowSize = Math.ceil((width * bitsPerPixel) / 32) * 4;
  const pixelDataSize = rowSize * height;
  
  // BMP info header (BITMAPINFOHEADER)
  const infoHeader = Buffer.alloc(40);
  infoHeader.writeUInt32LE(40, 0);         // Header size
  infoHeader.writeInt32LE(width, 4);       // Width
  infoHeader.writeInt32LE(height * 2, 8);  // Height (doubled for ICO format)
  infoHeader.writeUInt16LE(1, 12);         // Planes
  infoHeader.writeUInt16LE(32, 14);        // Bits per pixel
  infoHeader.writeUInt32LE(0, 16);         // Compression
  infoHeader.writeUInt32LE(pixelDataSize + height * width / 8, 20); // Image size
  infoHeader.writeInt32LE(0, 24);          // X pixels per meter
  infoHeader.writeInt32LE(0, 28);          // Y pixels per meter
  infoHeader.writeUInt32LE(0, 32);         // Colors used
  infoHeader.writeUInt32LE(0, 36);         // Important colors

  // Create pixel data (BGRA format, bottom-up)
  const pixelData = Buffer.alloc(pixelDataSize);
  
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const offset = y * rowSize + x * 4;
      
      // Create a rounded square with gradient
      const centerX = width / 2;
      const centerY = height / 2;
      const dx = x - centerX;
      const dy = y - centerY;
      const distance = Math.sqrt(dx * dx + dy * dy);
      const maxDist = Math.sqrt(centerX * centerX + centerY * centerY);
      
      // Check if inside rounded rectangle
      const margin = 4;
      const cornerRadius = 6;
      
      let inside = true;
      if (x < margin || x >= width - margin || y < margin || y >= height - margin) {
        inside = false;
      } else {
        // Check corners
        const corners = [
          { cx: margin + cornerRadius, cy: margin + cornerRadius },
          { cx: width - margin - cornerRadius - 1, cy: margin + cornerRadius },
          { cx: margin + cornerRadius, cy: height - margin - cornerRadius - 1 },
          { cx: width - margin - cornerRadius - 1, cy: height - margin - cornerRadius - 1 }
        ];
        
        for (const corner of corners) {
          const cdx = Math.abs(x - corner.cx);
          const cdy = Math.abs(y - corner.cy);
          if (cdx <= cornerRadius && cdy <= cornerRadius) {
            if (Math.sqrt(cdx * cdx + cdy * cdy) > cornerRadius) {
              // Check which quadrant
              if ((x < centerX && y < centerY && x < corner.cx && y < corner.cy) ||
                  (x >= centerX && y < centerY && x > corner.cx && y < corner.cy) ||
                  (x < centerX && y >= centerY && x < corner.cx && y > corner.cy) ||
                  (x >= centerX && y >= centerY && x > corner.cx && y > corner.cy)) {
                inside = false;
              }
            }
          }
        }
      }
      
      if (inside) {
        // Gradient from teal to darker teal
        const gradientFactor = (height - y) / height;
        const r = Math.floor(0 + gradientFactor * 0);
        const g = Math.floor(168 + gradientFactor * 44);
        const b = Math.floor(138 + gradientFactor * 32);
        const a = 255;
        
        pixelData.writeUInt8(b, offset);     // Blue
        pixelData.writeUInt8(g, offset + 1); // Green
        pixelData.writeUInt8(r, offset + 2); // Red
        pixelData.writeUInt8(a, offset + 3); // Alpha
      } else {
        // Transparent
        pixelData.writeUInt8(0, offset);
        pixelData.writeUInt8(0, offset + 1);
        pixelData.writeUInt8(0, offset + 2);
        pixelData.writeUInt8(0, offset + 3);
      }
    }
  }

  // AND mask (all zeros for 32-bit icons)
  const andMaskSize = Math.ceil(width / 8) * height;
  const andMask = Buffer.alloc(andMaskSize);

  // Calculate image data size
  const imageDataSize = infoHeader.length + pixelData.length + andMask.length;
  
  // Update directory entry with size and offset
  dirEntry.writeUInt32LE(imageDataSize, 8);  // Image size
  dirEntry.writeUInt32LE(header.length + dirEntry.length, 12);  // Offset

  // Combine all parts
  const ico = Buffer.concat([header, dirEntry, infoHeader, pixelData, andMask]);
  
  return ico;
}

// Generate and save the icon
const iconPath = path.join(__dirname, 'assets', 'icon.ico');
const iconData = createSimpleIco();

fs.writeFileSync(iconPath, iconData);
console.log('Icon generated successfully:', iconPath);

