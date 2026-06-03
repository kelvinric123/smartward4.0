<?php
/**
 * Script to replace all onsubmit="return confirm(...)" delete forms
 * with passphrase modal confirmDelete() calls.
 * 
 * Run: php replace_delete_confirms.php
 */

$baseDir = __DIR__ . '/resources/views';

// Recursively find all blade.php files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir)
);

$totalReplacements = 0;

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    if (strpos($file->getFilename(), '.blade.php') === false) continue;
    
    $content = file_get_contents($file->getPathname());
    $original = $content;
    
    // Pattern 1: onsubmit="return confirm('...');" on same line as <form
    // Replace: remove onsubmit from form tag
    $content = preg_replace(
        '/(<form\b[^>]*)\s*onsubmit="return confirm\(\'([^\']*)\'\);?"([^>]*>)/',
        '$1$3',
        $content
    );
    
    // Now find all forms that have @method('DELETE') and change their submit buttons
    // to use confirmDelete() instead
    // This is trickier - we need to find forms with DELETE method and change button type
    
    if ($content !== $original) {
        file_put_contents($file->getPathname(), $content);
        $relativePath = str_replace($baseDir, '', $file->getPathname());
        $count = substr_count($original, 'onsubmit="return confirm') - substr_count($content, 'onsubmit="return confirm');
        echo "Updated: {$relativePath} ({$count} replacements)\n";
        $totalReplacements += $count;
    }
}

echo "\n--- Phase 1: Removed {$totalReplacements} onsubmit attributes ---\n\n";

// Phase 2: Find all buttons with type="submit" inside forms that have @method('DELETE')
// and change them to type="button" onclick="confirmDelete(event, '...')"
$phase2Replacements = 0;

$iterator2 = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir)
);

foreach ($iterator2 as $file) {
    if ($file->getExtension() !== 'php') continue;
    if (strpos($file->getFilename(), '.blade.php') === false) continue;
    
    $content = file_get_contents($file->getPathname());
    $original = $content;
    
    // Find DELETE forms and change their submit buttons
    // Pattern: @method('DELETE') followed by <button type="submit"
    // We need to add onclick and change type
    
    // Strategy: find all @method('DELETE') ... <button type="submit" blocks
    // and replace type="submit" with type="button" onclick="confirmDelete(event, 'Are you sure?')"
    
    // Use a regex to match the pattern within forms
    $content = preg_replace_callback(
        '/(@method\([\'"]DELETE[\'"]\)\s*\n\s*<button\s+)type="submit"/m',
        function($matches) {
            return $matches[1] . 'type="button" onclick="confirmDelete(event, \'Are you sure you want to delete this item?\')"';
        },
        $content
    );
    
    if ($content !== $original) {
        file_put_contents($file->getPathname(), $content);
        $relativePath = str_replace($baseDir, '', $file->getPathname());
        $count = substr_count($original, 'type="submit"') - substr_count($content, 'type="submit"');
        echo "Updated buttons: {$relativePath} ({$count} button changes)\n";
        $phase2Replacements += $count;
    }
}

echo "\n--- Phase 2: Changed {$phase2Replacements} submit buttons to confirmDelete ---\n";
echo "\nTotal changes: " . ($totalReplacements + $phase2Replacements) . "\n";
echo "\nDone! Please verify the changes.\n";
