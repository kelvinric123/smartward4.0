# PowerShell script to create icon from existing logo
# Run this if you want to use your own logo as the app icon

# Check if ImageMagick is available
$imageMagick = Get-Command convert -ErrorAction SilentlyContinue

if ($imageMagick) {
    Write-Host "Using ImageMagick to create icon..."
    
    # Check for existing logos
    $logoPath = $null
    if (Test-Path "..\..\logo qmed.png") {
        $logoPath = "..\..\logo qmed.png"
    } elseif (Test-Path "..\..\logo_phkl.webp") {
        $logoPath = "..\..\logo_phkl.webp"
    }
    
    if ($logoPath) {
        # Create multiple sizes and combine into ICO
        convert $logoPath -resize 256x256 -background transparent -gravity center -extent 256x256 icon-256.png
        convert $logoPath -resize 128x128 -background transparent -gravity center -extent 128x128 icon-128.png
        convert $logoPath -resize 64x64 -background transparent -gravity center -extent 64x64 icon-64.png
        convert $logoPath -resize 48x48 -background transparent -gravity center -extent 48x48 icon-48.png
        convert $logoPath -resize 32x32 -background transparent -gravity center -extent 32x32 icon-32.png
        convert $logoPath -resize 16x16 -background transparent -gravity center -extent 16x16 icon-16.png
        
        convert icon-256.png icon-128.png icon-64.png icon-48.png icon-32.png icon-16.png icon.ico
        
        # Cleanup temp files
        Remove-Item icon-*.png
        
        Write-Host "Icon created successfully: icon.ico"
    } else {
        Write-Host "No logo found. Please place a logo image in the project root."
    }
} else {
    Write-Host "ImageMagick not found. Please install it or manually create icon.ico"
    Write-Host "You can use online tools like https://icoconvert.com/ to create an icon"
}


















