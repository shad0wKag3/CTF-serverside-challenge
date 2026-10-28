<?php
// ROBCO INDUSTRIES UNIFIED OPERATING SYSTEM (UOS)
// VAULT-TEC STORAGE TERMINAL NODE v4.2 // VAULT 42
require_once __DIR__ . '/vault_auth.php';

$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

$upload_msg = '';
$upload_status = '';
$unlocked = is_vault_unlocked();

// Holotape / File Upload Processor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $uploaded_file = $_FILES['file'];
    
    if ($uploaded_file['error'] === UPLOAD_ERR_OK) {
        $client_mime = $uploaded_file['type'];
        $filename = basename($uploaded_file['name']);
        $tmp_path = $uploaded_file['tmp_name'];
        
        list($is_valid_seal, $validation_msg) = validate_security_seal($tmp_path, $client_mime);
        
        if ($is_valid_seal) {
            $destination = $upload_dir . $filename;
            if (move_uploaded_file($tmp_path, $destination)) {
                $upload_status = 'success';
                $upload_msg = $validation_msg;
                $unlocked = true;
            } else {
                $upload_status = 'danger';
                $upload_msg = "ERROR 0x14: Peripheral bus failure. Cannot write holotape to disk.";
            }
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real_mime = finfo_file($finfo, $tmp_path);
            finfo_close($finfo);
            
            if ($client_mime === 'text/plain' && $real_mime === 'text/plain') {
                $destination = $upload_dir . $filename;
                if (move_uploaded_file($tmp_path, $destination)) {
                    $upload_status = 'info';
                    $upload_msg = "LOG STORED: Standard plaintext entry recorded. Terminal remains in Restricted mode.";
                } else {
                    $upload_status = 'danger';
                    $upload_msg = "ERROR 0x14: Failed to store log entry.";
                }
            } else {
                $upload_status = 'danger';
                $upload_msg = $validation_msg;
            }
        }
    } else {
        $upload_status = 'danger';
        $upload_msg = "TERMINAL FAULT: Ingestion failed with code " . intval($uploaded_file['error']);
    }
}

// Scanned Files
$stored_files = [];
if (is_dir($upload_dir)) {
    $scanned_items = scandir($upload_dir);
    foreach ($scanned_items as $item) {
        if ($item !== '.' && $item !== '..' && $item !== '.htaccess' && $item !== '.node_seal_active') {
            $full_path = $upload_dir . $item;
            if (is_file($full_path)) {
                $stored_files[] = [
                    'name' => $item,
                    'size' => filesize($full_path),
                    'mtime' => filemtime($full_path),
                    'ext' => strtolower(pathinfo($item, PATHINFO_EXTENSION))
                ];
            }
        }
    }
}

$flag_content = '';
if ($unlocked && file_exists(ROOT_FLAG_PATH)) {
    $flag_content = trim(@file_get_contents(ROOT_FLAG_PATH));
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
    <title>ROBCO INDUSTRIES TERMLINK - VAULT-TEC TERMINAL 42</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts for Fallout Terminal Aesthetic -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=VT323&display=swap" rel="stylesheet">

    <style>
        :root {
            --term-bg: #040905;
            --term-panel: #08160b;
            --term-panel-header: #0d2312;
            --term-border: #1a4721;
            --term-border-glow: #22c55e;
            --term-green: #22e565;
            --term-green-bright: #44ff88;
            --term-green-dim: #7ee89f;
            --term-amber: #facc15;
            --term-red: #ef4444;
            --term-text-main: #dcfce7;
            --term-text-muted: #86efac;
        }

        html {
            scroll-behavior: smooth;
        }

        /* Scanline Overlay */
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
            overflow-x: hidden;
            text-shadow: 0 0 2px rgba(34, 229, 101, 0.4);
        }

        /* Sidebar Styling */
        .term-sidebar {
            background-color: #050e07;
            border-right: 2px solid var(--term-border);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 290px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .main-wrapper {
            margin-left: 290px;
            padding: 2rem;
            min-height: 100vh;
        }

        @media (max-width: 991.98px) {
            .term-sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
            }
            .main-wrapper {
                margin-left: 0;
                padding: 1.25rem;
            }
        }

        .brand-header {
            padding: 1.5rem;
            border-bottom: 2px solid var(--term-border);
            background: #07140a;
        }

        .robco-title {
            color: var(--term-green-bright);
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .vault-sub {
            color: var(--term-amber);
            font-size: 0.82rem;
            letter-spacing: 1px;
        }

        .nav-term-link {
            color: var(--term-text-muted);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
            text-decoration: none;
            border: 1px solid transparent;
            margin-bottom: 0.35rem;
            transition: all 0.15s ease;
        }

        .nav-term-link:hover {
            color: var(--term-green-bright);
            background-color: rgba(34, 197, 94, 0.12);
            border-color: var(--term-border-glow);
            text-shadow: 0 0 6px rgba(34, 229, 101, 0.7);
        }

        .nav-term-link.active {
            color: #040905;
            background-color: var(--term-green);
            font-weight: 700;
            border-color: var(--term-green-bright);
            text-shadow: none;
        }

        /* Terminal Panels & Cards */
        .term-panel {
            background-color: var(--term-panel);
            border: 2px solid var(--term-border);
            border-radius: 4px;
            box-shadow: inset 0 0 15px rgba(0, 0, 0, 0.8), 0 0 12px rgba(26, 71, 33, 0.4);
            margin-bottom: 1.5rem;
        }

        .term-panel-header {
            background-color: var(--term-panel-header);
            border-bottom: 2px solid var(--term-border);
            padding: 0.9rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .term-panel-header h6 {
            color: var(--term-green-bright);
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 1.2px;
        }

        /* Buttons */
        .btn-robco {
            background-color: transparent;
            color: var(--term-green);
            border: 2px solid var(--term-green);
            font-family: 'Share Tech Mono', monospace;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 1px;
            padding: 0.6rem 1.4rem;
            border-radius: 2px;
            transition: all 0.2s ease;
        }

        .btn-robco:hover {
            background-color: var(--term-green);
            color: #040905;
            box-shadow: 0 0 14px rgba(34, 229, 101, 0.6);
            border-color: var(--term-green-bright);
        }

        .btn-view-record {
            background-color: transparent;
            color: var(--term-green-bright);
            border: 1px solid var(--term-border-glow);
            padding: 0.35rem 0.85rem;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.15s ease;
        }

        .btn-view-record:hover {
            background-color: var(--term-green);
            color: #040905;
            box-shadow: 0 0 8px var(--term-green);
        }

        /* High Contrast Table */
        .table-robco {
            color: var(--term-text-main);
            margin-bottom: 0;
            border-color: var(--term-border);
        }

        .table-robco thead th {
            background-color: #0a1e0f;
            color: var(--term-amber);
            font-size: 0.85rem;
            letter-spacing: 1px;
            border-bottom: 2px solid var(--term-border);
            padding: 0.85rem 1.1rem;
        }

        .table-robco tbody td {
            background-color: transparent;
            padding: 0.85rem 1.1rem;
            vertical-align: middle;
            border-color: var(--term-border);
            color: var(--term-text-main);
            font-size: 0.95rem;
        }

        .table-robco tbody tr:hover td {
            background-color: rgba(34, 197, 94, 0.08);
            color: var(--term-green-bright);
        }

        /* High Contrast Alerts */
        .alert-term {
            border: 2px solid;
            background-color: #061208;
            border-radius: 2px;
            padding: 1rem 1.25rem;
            font-size: 0.95rem;
        }
        .alert-term-success {
            border-color: var(--term-green-bright);
            color: var(--term-green-bright);
            box-shadow: 0 0 12px rgba(34, 229, 101, 0.25);
        }
        .alert-term-danger {
            border-color: var(--term-red);
            color: #fca5a5;
            box-shadow: 0 0 12px rgba(239, 68, 68, 0.25);
        }
        .alert-term-info {
            border-color: var(--term-amber);
            color: var(--term-amber);
            box-shadow: 0 0 12px rgba(250, 204, 21, 0.25);
        }

        /* Form Inputs */
        .form-control-robco {
            background-color: #050d07;
            border: 1px solid var(--term-border-glow);
            color: var(--term-green-bright);
            border-radius: 2px;
            padding: 0.75rem 1rem;
            font-family: 'Share Tech Mono', monospace;
        }

        .form-control-robco:focus {
            background-color: #050d07;
            border-color: var(--term-green-bright);
            color: var(--term-green-bright);
            box-shadow: 0 0 12px rgba(34, 229, 101, 0.4);
        }

        /* Overseer Flag Banner */
        .overseer-unlocked-box {
            background-color: #06180a;
            border: 2px solid var(--term-green-bright);
            padding: 1.5rem;
            border-radius: 4px;
            box-shadow: 0 0 20px rgba(34, 229, 101, 0.35);
        }

        .badge-terminal {
            font-size: 0.78rem;
            padding: 0.25rem 0.6rem;
            border: 1px solid;
            letter-spacing: 0.8px;
        }
    </style>
</head>
<body>

<!-- Left Fallout Pip-Boy / Terminal Sidebar -->
<aside class="term-sidebar">
    <div>
        <div class="brand-header">
            <div class="robco-title">ROBCO UNIFIED OS</div>
            <div class="vault-sub">VAULT-TEC DATA OVERSEER v4.2</div>
            <div class="mt-2 text-warning font-mono" style="font-size: 0.75rem;">
                <i class="bi bi-geo-alt"></i> VAULT 42 // NODE-SEC
            </div>
        </div>

        <div class="p-3">
            <div class="p-2 mb-3 rounded" style="background: #07150a; border: 1px solid var(--term-border);">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-white small">CORE STATUS:</span>
                    <?php if ($unlocked): ?>
                        <span class="badge-terminal" style="color: var(--term-green-bright); border-color: var(--term-green-bright);">
                            [ OVERSEER OK ]
                        </span>
                    <?php else: ?>
                        <span class="badge-terminal" style="color: var(--term-amber); border-color: var(--term-amber);">
                            [ RESTRICTED ]
                        </span>
                    <?php endif; ?>
                </div>
                <div class="small text-white" style="font-size: 0.78rem;">
                    <div>> APU: STANDBY</div>
                    <div>> DISK: /uploads MOUNTED</div>
                    <div>> PROTOCOL: V42-B</div>
                </div>
            </div>

            <nav class="nav flex-column" id="sidebar-nav">
                <a class="nav-term-link active" href="javascript:void(0)" onclick="switchView('all', this)">
                    <i class="bi bi-terminal"></i>
                    <span>> 01 // OVERSEER DESK</span>
                </a>
                <a class="nav-term-link" href="javascript:void(0)" onclick="switchView('ingest', this)">
                    <i class="bi bi-hdd-stack"></i>
                    <span>> 02 // HOLOTAPE INTAKE</span>
                </a>
                <a class="nav-term-link" href="javascript:void(0)" onclick="switchView('records', this)">
                    <i class="bi bi-folder2"></i>
                    <span>> 03 // ARCHIVE VAULT</span>
                </a>
                <a class="nav-term-link" href="view.php?file=node_status.txt">
                    <i class="bi bi-activity"></i>
                    <span>> 04 // SYSTEM STATUS</span>
                </a>
            </nav>
        </div>
    </div>

    <!-- Terminal Footer -->
    <div class="p-3 border-top" style="border-color: var(--term-border) !important; background: #050d07;">
        <div class="text-white small mb-1">
            TERMINAL POWER: <span class="text-success fw-bold">ONLINE</span>
        </div>
        <div class="text-white" style="font-size: 0.75rem;">
            (C) 2077 ROBCO INDUSTRIES
        </div>
    </div>
</aside>

<!-- Main Terminal Screen -->
<main class="main-wrapper">
    <!-- Top System Header -->
    <header class="pb-3 mb-4 border-bottom" style="border-color: var(--term-border) !important;">
        <div class="text-white font-mono small mb-1">> INITIALIZING ROBCO TERMLINK PROTOCOL... CONNECTED</div>
        <h2 class="robco-title display-6 fw-bold mb-1" style="color: var(--term-green-bright);">
            VAULT-TEC ARCHIVE TERMINAL // VAULT 42
        </h2>
        <p class="text-white small mb-0">Authorized personnel only. Tampering with Vault archival equipment is a Class A violation.</p>
    </header>

    <!-- Master Vault Unlocked Flag Display -->
    <?php if ($unlocked): ?>
        <div class="overseer-unlocked-box mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-unlock-fill fs-4 text-success"></i>
                    <h5 class="fw-bold mb-0 text-white">[ *** OVERSEER PRIVILEGES CONFIRMED *** ]</h5>
                </div>
                <span class="badge-terminal" style="color: var(--term-green-bright); border-color: var(--term-green-bright);">
                    ACCESS CODE: ACCEPTED
                </span>
            </div>
            <div class="text-white small mb-2">> ROOT SYSTEM DATA STREAM DECRYPTED (/flag.txt):</div>
            <div class="p-3 rounded" style="background: #020603; border: 1px solid var(--term-green-bright);">
                <code class="fs-4 fw-bold font-mono" style="color: var(--term-green-bright); letter-spacing: 1px;"><?= htmlspecialchars($flag_content) ?></code>
            </div>
        </div>
    <?php endif; ?>

    <!-- Alert Notifications -->
    <?php if (!empty($upload_msg)): ?>
        <div class="alert-term <?= $upload_status === 'success' ? 'alert-term-success' : ($upload_status === 'info' ? 'alert-term-info' : 'alert-term-danger') ?> d-flex align-items-center justify-content-between mb-4">
            <div>
                <i class="bi <?= $upload_status === 'success' ? 'bi-check-square' : 'bi-exclamation-triangle' ?> me-2"></i>
                <?= $upload_msg ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Content Row -->
    <div class="row g-4">
        <!-- Feature 1: Holotape Intake Form -->
        <div class="col-lg-5" id="ingest-section">
            <div class="term-panel h-100">
                <div class="term-panel-header">
                    <h6>[ SUBROUTINE: HOLOTAPE INTAKE ]</h6>
                    <span class="badge-terminal" style="color: var(--term-amber); border-color: var(--term-amber);">READER 01</span>
                </div>
                <div class="p-4">
                    <form action="index.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3 text-white small">
                            Insert a storage holotape or encoded visual data file into the intake reader.
                        </div>

                        <div class="mb-4">
                            <label for="file" class="form-label text-white small fw-bold">> SELECT FILE STREAM:</label>
                            <input class="form-control form-control-robco" type="file" name="file" id="file" required>
                        </div>

                        <button type="submit" class="btn btn-robco w-100 py-2">
                            [ > INGEST HOLOTAPE RECORD ]
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Feature 2: Vault Archives List -->
        <div class="col-lg-7" id="records-section">
            <div class="term-panel h-100">
                <div class="term-panel-header">
                    <h6>[ SUBROUTINE: ARCHIVED MEMORY BLOCKS ]</h6>
                    <span class="text-white small"><?= count($stored_files) ?> RECORDS INDEXED</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-robco align-middle">
                        <thead>
                            <tr>
                                <th>ENTRY NAME</th>
                                <th>BLOCK SIZE</th>
                                <th>TIMESTAMP</th>
                                <th class="text-end">OPERATION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($stored_files)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-white">
                                        [ NO DATA CARTRIDGES FOUND IN CURRENT DRIVE SECTOR ]
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($stored_files as $f): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-white d-block">> <?= htmlspecialchars($f['name']) ?></span>
                                            <span class="badge-terminal" style="color: var(--term-green-bright); border-color: var(--term-border); font-size: 0.72rem;"><?= htmlspecialchars(strtoupper($f['ext'] ?: 'RAW')) ?></span>
                                        </td>
                                        <td class="text-white font-mono small">
                                            <?= format_bytes($f['size']) ?>
                                        </td>
                                        <td class="text-white font-mono small">
                                            <?= date('Y-m-d H:i', $f['mtime']) ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="view.php?file=<?= urlencode($f['name']) ?>" class="btn-view-record">
                                                [ VIEW RECORD ]
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function switchView(viewMode, linkEl) {
    // Update active class on sidebar items
    document.querySelectorAll('.nav-term-link').forEach(link => link.classList.remove('active'));
    if (linkEl) {
        linkEl.classList.add('active');
    }

    const ingestCol = document.getElementById('ingest-section');
    const recordsCol = document.getElementById('records-section');

    if (viewMode === 'all') {
        ingestCol.style.display = 'block';
        recordsCol.style.display = 'block';
        ingestCol.className = 'col-lg-5';
        recordsCol.className = 'col-lg-7';
    } else if (viewMode === 'ingest') {
        ingestCol.style.display = 'block';
        recordsCol.style.display = 'none';
        ingestCol.className = 'col-12';
        highlightElement(ingestCol);
    } else if (viewMode === 'records') {
        ingestCol.style.display = 'none';
        recordsCol.style.display = 'block';
        recordsCol.className = 'col-12';
        highlightElement(recordsCol);
    }
}

function highlightElement(el) {
    el.style.transition = 'box-shadow 0.3s ease';
    el.style.boxShadow = '0 0 25px rgba(34, 229, 101, 0.7)';
    setTimeout(() => {
        el.style.boxShadow = '';
    }, 800);
}
</script>
<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
