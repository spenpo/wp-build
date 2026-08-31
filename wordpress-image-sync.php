<?php
/**
 * WordPress Image Sync Script
 *
 * This script registers downloaded images in the WordPress database.
 * Run with: wp eval-file wordpress-image-sync.php [options]
 *
 * Options:
 *   skip-pdfs         Skip processing of PDF files.
 *   max-size=<MB>     Skip files larger than this size (default: 20MB).
 *
 * Example:
 *   wp eval-file wordpress-image-sync.php skip-pdfs max-size=10
 */

// Ensure we're in WordPress context
if (!function_exists('wp_insert_attachment')) {
    echo "❌ This script must be run within WordPress context\n";
    echo "Usage: wp eval-file wordpress-image-sync.php [options]\n";
    exit(1);
}

// --- CONFIGURATION & ARGUMENT PARSING ---
echo "\n🔧 Script Configuration:\n";
$skip_pdfs = false;
$max_size_mb = 20; // Default

if (isset($args) && is_array($args)) {
    echo "   Raw Arguments Received: " . json_encode($args) . "\n";

    foreach ($args as $arg) {
        // Strip dashes and normalize
        $clean_arg = strtolower(trim(ltrim($arg, '-')));

        if ($clean_arg === 'skip-pdfs') {
            $skip_pdfs = true;
        }

        if (strpos($clean_arg, 'max-size=') === 0) {
            $parts = explode('=', $clean_arg);
            if (isset($parts[1])) {
                $val = (int) $parts[1];
                if ($val > 0) {
                    $max_size_mb = $val;
                }
            }
        }
    }
} else {
    echo "   ⚠️  No arguments received (using defaults).\n";
}

echo "   - Skip PDFs: " . ($skip_pdfs ? "YES" : "NO") . "\n";
echo "   - Max File Size: " . $max_size_mb . " MB\n";
echo "----------------------------------------\n\n";

// System adjustments
@ini_set('memory_limit', '512M');
set_time_limit(0);

logMsg("🔄 Registering images in WordPress database...");

$uploadsDir = wp_upload_dir()['basedir'];
$registered = 0;
$skipped = 0;
$skipped_large = 0;
$skipped_pdfs = 0;
$processed_count = 0;

// Scan uploads directory for images
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isLink()) continue;
    if (!$file->isFile()) continue;

    $filePath = $file->getPathname();
    $relativePath = str_replace($uploadsDir . '/', '', $filePath);
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    // Categorize
    $is_pdf = ($extension === 'pdf');
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];

    if (!$is_pdf && !in_array($extension, $imageExtensions)) continue;

    if ($is_pdf && $skip_pdfs) {
        $skipped_pdfs++;
        continue;
    }

    // Skip WordPress thumbnails
    $filename = basename($filePath, '.' . $extension);
    if (preg_match('/-\d+x\d+$/', $filename)) continue;

    // Size check
    $fileSize = $file->getSize();
    $fileSizeMB = $fileSize / 1024 / 1024;

    if ($fileSizeMB > $max_size_mb) {
        logMsg("   ⚠️  Skipped Large File: $relativePath (" . round($fileSizeMB, 2) . " MB > {$max_size_mb} MB)");
        $skipped_large++;
        continue;
    }

    if (isImageRegistered($relativePath)) {
        $skipped++;
        if ($skipped % 100 === 0) {
            logMsg("   ... scanned $skipped existing files ...", false);
            cleanup_memory();
        }
        continue;
    }

    $sizeStr = round($fileSizeMB, 2) . " MB";
    logMsg("   " . ($is_pdf ? "📄" : "⏳") . " Processing: $relativePath ($sizeStr) ... ", false);

    $attachId = registerAttachment($relativePath, $filePath, $is_pdf);
    if ($attachId) {
        $registered++;
        logMsg("DONE ✅ (ID: $attachId)");
    } else {
        logMsg("FAILED ❌");
    }

    cleanup_memory();
}

logMsg("\n🎉 Complete!");
logMsg("   - Registered: $registered");
logMsg("   - Skipped (Existing): $skipped");
if ($skipped_pdfs > 0) logMsg("   - Skipped (PDFs): $skipped_pdfs");
if ($skipped_large > 0) logMsg("   - Skipped (Too Large): $skipped_large");

// --- HELPER FUNCTIONS ---

function logMsg($msg, $newline = true) {
    echo $msg . ($newline ? "\n" : "");
    if (ob_get_level() > 0) ob_flush();
    flush();
}

function cleanup_memory() {
    global $wp_object_cache;
    wp_cache_flush();
    if (is_object($wp_object_cache) && isset($wp_object_cache->cache)) {
        $wp_object_cache->cache = array();
    }
    gc_collect_cycles();
}

function isImageRegistered($relativePath) {
    global $wpdb;
    $attachment_id = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'attachment' AND pm.meta_key = '_wp_attached_file' AND pm.meta_value = %s
        LIMIT 1", $relativePath
    ));
    return $attachment_id !== null;
}

function registerAttachment($relativePath, $filePath, $is_pdf = false) {
    $fileType = wp_check_filetype(basename($filePath), null);
    if (!$fileType['type']) return false;

    $attachment = array(
        'post_mime_type' => $fileType['type'],
        'post_title' => preg_replace('/\.[^.]+$/', '', basename($filePath)),
        'post_content' => '',
        'post_status' => 'inherit',
        'post_author' => get_current_user_id() ?: 1,
    );

    $attachId = wp_insert_attachment($attachment, $filePath);
    if (is_wp_error($attachId)) return false;

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    try {
        // This is the heavy part
        $attachData = wp_generate_attachment_metadata($attachId, $filePath);
        if (!is_wp_error($attachData)) {
            wp_update_attachment_metadata($attachId, $attachData);
        }
    } catch (Throwable $t) {
        logMsg("(Error: " . $t->getMessage() . ") ", false);
        return false;
    }
    return $attachId;
}