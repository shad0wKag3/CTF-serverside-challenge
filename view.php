<?php
// ROBCO INDUSTRIES UNIFIED OPERATING SYSTEM
// VAULT-TEC ARCHIVE INSPECTION SUBROUTINE // VAULT 42
require_once __DIR__ . '/vault_auth.php';

$file = $_GET['file'] ?? '';
$sanitized_file = str_replace('../', '', $file);
$filepath = "uploads/" . $sanitized_file;

$file_content = false;
$error_msg = null;
$file_size = 0;
$line_count = 0;

if ($file === '') {
    $error_msg = "TERMINAL PROMPT: No file record designated for readout. Specify parameter.";
} else {
    $is_flag_request = (strpos($filepath, 'flag.txt') !== false || realpath($filepath) === '/flag.txt');

    if ($is_flag_request) {
        if (!is_vault_unlocked()) {
            $error_msg = "ERROR 0x7E: SYSTEM RESTRICTION. Root sector access requires valid Overseer authentication credentials.";
        } else {
            if (file_exists(ROOT_FLAG_PATH)) {
                $file_content = @file_get_contents(ROOT_FLAG_PATH);
                $file_size = filesize(ROOT_FLAG_PATH);
                $line_count = substr_count($file_content, "\n") + 1;
            } else {
                $error_msg = "FILE ERROR: Root record not found in system table.";
            }
        }
    } else {
        if (file_exists($filepath)) {
            if (is_file($filepath)) {
                $file_content = @file_get_contents($filepath);
                if ($file_content === false) {
                    $error_msg = "I/O EXCEPTION: File stream locked or unreadable.";
                } else {
                    $file_size = filesize($filepath);
                    $line_count = substr_count($file_content, "\n") + 1;
                }
            } elseif (is_dir($filepath)) {
                $error_msg = "INVALID TARGET: Record corresponds to a directory sector.";
            }
        } else {
            $error_msg = "RECORD NOT FOUND: Unable to locate designated data block [ " . htmlspecialchars($filepath) . " ].";
        }
    }
}

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ROBCO TERMLINK - ARCHIVE INSPECTOR</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts for Fallout Terminal -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">

    <style>
        :root {
            --term-bg: #040905;
            --term-panel: #08160b;
            --term-panel-header: #0d2312;
            --term-border: #1a4721;
            --term-border-glow: #22c55e;
            --term-green: #22e565;
            --term-green-bright: #44ff88;
            --term-amber: #facc15;
            --term-red: #ef4444;
            --term-text-main: #dcfce7;
            --term-text-muted: #86efac;
        }

        /* Scanlines Overlay */
        body::before {
            content: " ";
            display: block;
            position: fixed;
            top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.28) 50%), linear-gradient(90deg, rgba(34, 197, 94, 0.02), rgba(0, 255, 0, 0.01), rgba(34, 197, 94, 0.02));
            z-index: 9999;
            background-size: 100% 3px, 3px 100%;
            pointer-events: none;
            opacity: 0.75;
        }

        body {
            background-color: var(--term-bg);
            color: var(--term-text-main);
            font-family: 'Share Tech Mono', monospace;
            min-height: 100vh;
            text-shadow: 0 0 2px rgba(34, 229, 101, 0.4);
        }

        .term-navbar {
            background-color: #061108;
            border-bottom: 2px solid var(--term-border);
            padding: 1rem 2rem;
        }

        .btn-term-back {
            background-color: transparent;
            color: var(--term-green-bright);
            border: 2px solid var(--term-green);
            padding: 0.5rem 1.2rem;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-term-back:hover {
            background-color: var(--term-green);
            color: #040905;
            box-shadow: 0 0 12px var(--term-green);
        }

        .term-panel {
            background-color: var(--term-panel);
            border: 2px solid var(--term-border);
            border-radius: 4px;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.8), 0 0 15px rgba(26, 71, 33, 0.4);
        }

        .term-panel-header {
            background-color: var(--term-panel-header);
            border-bottom: 2px solid var(--term-border);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .code-container {
            background-color: #020603;
            border: 1px solid var(--term-border-glow);
            padding: 1.5rem;
            position: relative;
            border-radius: 2px;
        }

        pre.code-dump {
            margin: 0;
            color: var(--term-green-bright);
            font-family: 'Share Tech Mono', monospace;
            font-size: 0.95rem;
            line-height: 1.6;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            text-shadow: 0 0 3px rgba(34, 229, 101, 0.5);
        }

        /* Terminal scrollbar */
        pre.code-dump::-webkit-scrollbar {
            width: 8px;
        }
        pre.code-dump::-webkit-scrollbar-track {
            background: #040905;
        }
        pre.code-dump::-webkit-scrollbar-thumb {
            background: var(--term-border);
        }
        pre.code-dump::-webkit-scrollbar-thumb:hover {
            background: var(--term-green);
        }

        .btn-copy {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: transparent;
            color: var(--term-green-bright);
            border: 1px solid var(--term-border-glow);
            padding: 0.35rem 0.75rem;
            font-size: 0.8rem;
            font-family: 'Share Tech Mono', monospace;
            transition: all 0.2s ease;
        }

        .btn-copy:hover {
            background: var(--term-green);
            color: #040905;
        }

        .badge-terminal {
            font-size: 0.82rem;
            padding: 0.3rem 0.75rem;
            border: 1px solid;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="term-navbar d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn-term-back">
            <i class="bi bi-arrow-left"></i> [ < RETURN TO OVERSEER DESK ]
        </a>
    </div>
    <div class="text-white small">
        ROBCO UOS v4.2 // STREAM INSPECTION
    </div>
</nav>

<div class="container py-4">
    <!-- Panel -->
    <div class="term-panel">
        <div class="term-panel-header flex-wrap gap-2">
            <div>
                <span class="text-white small d-block">> DESIGNATED FILE ENTRY:</span>
                <h5 class="fw-bold mb-0" style="color: var(--term-green-bright); letter-spacing: 1.5px;">
                    [ <?= htmlspecialchars($file !== '' ? $file : 'NULL') ?> ]
                </h5>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <?php if ($file_content !== false): ?>
                    <span class="badge-terminal" style="color: var(--term-amber); border-color: var(--term-amber);">
                        SIZE: <?= format_bytes($file_size) ?>
                    </span>
                    <span class="badge-terminal" style="color: var(--term-green-bright); border-color: var(--term-green-bright);">
                        LINES: <?= $line_count ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-4">
            <!-- Error Notice -->
            <?php if ($error_msg): ?>
                <div class="p-3 mb-4 rounded" style="background: #130606; border: 2px solid var(--term-red); color: #fca5a5;">
                    <div class="fw-bold fs-6 mb-1">
                        <i class="bi bi-exclamation-triangle me-2"></i>[ READOUT EXCEPTION ]
                    </div>
                    <div class="small"><?= $error_msg ?></div>
                </div>
            <?php endif; ?>

            <!-- Code Dump -->
            <?php if ($file_content !== false): ?>
                <div class="text-white small mb-2">> DATA STREAM DECODED SUCCESSFULLY:</div>
                <div class="code-container">
                    <button class="btn-copy" onclick="copyStreamContent()">
                        <span id="copy-text">[ COPY STREAM ]</span>
                    </button>
                    <pre class="code-dump" id="stream-output"><?= htmlspecialchars($file_content) ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyStreamContent() {
    const text = document.getElementById('stream-output').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const copyText = document.getElementById('copy-text');
        copyText.innerText = '[ COPIED! ]';
        setTimeout(() => {
            copyText.innerText = '[ COPY STREAM ]';
        }, 2000);
    });
}
</script>
<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
