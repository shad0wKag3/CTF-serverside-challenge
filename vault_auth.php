<?php
// VaultDrive / Vault-Tec Storage Node v4.2 - Core Security Subsystem
// ROBCO INDUSTRIES UNIFIED OPERATING SYSTEM - RESTRICTED TERMINAL

define('NODE_MASTER_SECRET', 'VD-SECRET-KEY-9821a7c4f3b0');
define('VAULT_STATUS_FILE', __DIR__ . '/uploads/.node_seal_active');
define('ROOT_FLAG_PATH', file_exists('/flag.txt') ? '/flag.txt' : __DIR__ . '/flag.txt');

/**
 * Checks if the terminal/node has verified Overseer credentials.
 */
function is_vault_unlocked() {
    if (file_exists(VAULT_STATUS_FILE)) {
        return true;
    }
    
    $upload_dir = __DIR__ . '/uploads/';
    if (is_dir($upload_dir)) {
        $files = scandir($upload_dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && $file !== '.htaccess') {
                $path = $upload_dir . $file;
                if (is_file($path)) {
                    $content = @file_get_contents($path);
                    if ($content && strpos($content, NODE_MASTER_SECRET) !== false) {
                        @file_put_contents(VAULT_STATUS_FILE, time());
                        return true;
                    }
                }
            }
        }
    }
    return false;
}

/**
 * Validates incoming security seal archive.
 */
function validate_security_seal($tmp_path, $client_mime) {
    $allowed_mimes = ['image/gif', 'image/png', 'image/jpeg'];
    
    // Check Client Header
    if (!in_array(strtolower(trim($client_mime)), $allowed_mimes)) {
        return [false, "REJECTED: Unauthorized data envelope format [ $client_mime ]. Approved visual formats: GIF, PNG, JPG."];
    }
    
    // Check Real Binary Magic-Bytes
    if (!function_exists('finfo_open')) {
        return [false, "SYSTEM ERROR: Fileinfo subsystem offline."];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $real_mime = finfo_file($finfo, $tmp_path);
    finfo_close($finfo);
    
    if (!in_array($real_mime, $allowed_mimes)) {
        return [false, "CORRUPTED RECORD: Binary header mismatch. Actual payload signature detected as [ $real_mime ]."];
    }
    
    // Check Embedded Overseer Signature Token
    $content = @file_get_contents($tmp_path);
    if ($content === false || strpos($content, NODE_MASTER_SECRET) === false) {
        return [false, "ACCESS DENIED: Visual header verified, but record lacks required cryptographic Overseer signature."];
    }
    
    return [true, "AUTHENTICATION VERIFIED: Overseer security seal accepted. Master Vault unlocked."];
}
?>
