// Generate a multi-resolution icon for the application
// Creates an ICO file with multiple sizes including 256x256

const fs = require('fs');
const path = require('path');

// Create a single icon image at specified size
function createIconImage(size) {
  const width = size;
  const height = size;
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
  
  // Scale factors for different sizes
  const margin = Math.max(2, Math.floor(size * 0.125));
  const cornerRadius = Math.max(2, Math.floor(size * 0.1875));
  
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const offset = y * rowSize + x * 4;
      
      // Create a rounded square with gradient
      const centerX = width / 2;
      const centerY = height / 2;
      
      // Check if inside rounded rectangle
      let inside = true;
      if (x < margin || x >= width - margin || y < margin || y >= height - margin) {
        inside = false;
      } else {
        // Check corners for rounded effect
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
            const dist = Math.sqrt(cdx * cdx + cdy * cdy);
            if (dist > cornerRadius) {
              // Check which quadrant
              const inTopLeft = x < centerX && y < centerY && x < corner.cx && y < corner.cy;
              const inTopRight = x >= centerX && y < centerY && x > corner.cx && y < corner.cy;
              const inBottomLeft = x < centerX && y >= centerY && x < corner.cx && y > corner.cy;
              const inBottomRight = x >= centerX && y >= centerY && x > corner.cx && y > corner.cy;
              
              if (inTopLeft || inTopRight || inBottomLeft || inBottomRight) {
                inside = false;
                break;
              }
            }
          }
        }
      }
      
      if (inside) {
        // Gradient from teal (#00d4aa) to darker teal (#00a88a)
        const gradientFactor = (height - y) / height;
        const r = 0;
        const g = Math.floor(168 + gradientFactor * 44);
        const b = Math.floor(138 + gradientFactor * 32);
        const a = 255;
        
        // Add "SW" text for larger sizes
        if (size >= 64) {
          const textX = width / 2;
          const textY = height / 2;
          const textSize = Math.floor(size * 0.4);
          const textMargin = Math.floor(size * 0.3);
          
          // Simple "SW" text rendering
          const inTextArea = Math.abs(x - textX) < textSize && Math.abs(y - textY) < textSize;
          if (inTextArea) {
            // Make text area darker for contrast
            pixelData.writeUInt8(Math.floor(b * 0.3), offset);     // Blue
            pixelData.writeUInt8(Math.floor(g * 0.3), offset + 1); // Green
            pixelData.writeUInt8(0, offset + 2); // Red
            pixelData.writeUInt8(255, offset + 3); // Alpha
            continue;
          }
        }
        
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

  // Combine header, pixel data, and mask
  const imageData = Buffer.concat([infoHeader, pixelData, andMask]);
  const imageDataSize = imageData.length;
  
  return {
    width,
    height,
    data: imageData,
    size: imageDataSize
  };
}

// Create multi-resolution ICO file
function createMultiResolutionIco() {
  // Icon sizes to include (must include 256x256 for electron-builder)
  const sizes = [16, 32, 48, 64, 128, 256];
  const images = sizes.map(size => createIconImage(size));
  
  // ICO header
  const header = Buffer.alloc(6);
  header.writeUInt16LE(0, 0);      // Reserved
  header.writeUInt16LE(1, 2);      // ICO type
  header.writeUInt16LE(images.length, 4);  // Number of images
  
  // Create directory entries
  const dirEntries = [];
  let currentOffset = header.length + (images.length * 16); // Header + all directory entries
  
  for (const img of images) {
    const dirEntry = Buffer.alloc(16);
    
    // Width and height (0 means 256)
    dirEntry.writeUInt8(img.width === 256 ? 0 : img.width, 0);
    dirEntry.writeUInt8(img.height === 256 ? 0 : img.height, 1);
    dirEntry.writeUInt8(0, 2);       // Color palette
    dirEntry.writeUInt8(0, 3);       // Reserved
    dirEntry.writeUInt16LE(1, 4);    // Color planes
    dirEntry.writeUInt16LE(32, 6);   // Bits per pixel
    dirEntry.writeUInt32LE(img.size, 8);  // Image size
    dirEntry.writeUInt32LE(currentOffset, 12);  // Offset
    
    dirEntries.push(dirEntry);
    currentOffset += img.size;
  }
  
  // Combine all parts
  const parts = [header, ...dirEntries, ...images.map(img => img.data)];
  const ico = Buffer.concat(parts);
  
  return ico;
}

// Generate and save the icon
const iconPath = path.join(__dirname, 'assets', 'icon.ico');
const iconData = createMultiResolutionIco();

fs.writeFileSync(iconPath, iconData);
console.log('Multi-resolution icon generated successfully:', iconPath);
console.log('Sizes included: 16x16, 32x32, 48x48, 64x64, 128x128, 256x256');
