<?php

require_once __DIR__ . '../vendor/autoload.php';

use Spatie\DbDumper\Databases\MySql;

// Load database configuration from existing config.php
require_once __DIR__ . '/config.php';

$backupPath = __DIR__ . '/backups';
if (!file_exists($backupPath)) {
    mkdir($backupPath, 0755, true);
}

// Simple CSRF token
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// Flash helper
function set_flash($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// Actions: create, download, delete, list
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        set_flash('Ongeldige CSRF token', 'danger');
        header('Location: databasebackup.php');
        exit;
    }

    if (!empty($_POST['create_backup'])) {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "$backupPath/" . DB_NAME . "_" . $timestamp . ".sql";

        try {
            MySql::create()
                ->setDbName(DB_NAME)
                ->setUserName(DB_USER)
                ->setPassword(DB_PASS)
                ->setHost(DB_HOST)
                ->setPort(3306)
                ->addExtraOption('--single-transaction')
                ->addExtraOption('--quick')
                ->dumpToFile($filename);

            // Optional compression
            if (extension_loaded('zlib')) {
                $gz = $filename . '.gz';
                $fp = fopen($filename, 'rb');
                $gzfp = gzopen($gz, 'wb9');
                while (!feof($fp)) {
                    gzwrite($gzfp, fread($fp, 1024 * 512));
                }
                fclose($fp);
                gzclose($gzfp);
                unlink($filename);
                $filename = $gz;
            }

            set_flash('Backup succesvol aangemaakt: ' . basename($filename));
        } catch (Exception $e) {
            set_flash('Backup mislukt: ' . $e->getMessage(), 'danger');
        }

        header('Location: databasebackup.php');
        exit;
    }

    if (!empty($_POST['delete']) && !empty($_POST['file'])) {
        $file = basename($_POST['file']);
        $full = $backupPath . '/' . $file;
        if (file_exists($full)) {
            unlink($full);
            set_flash('Backup verwijderd: ' . $file);
        } else {
            set_flash('Bestand niet gevonden', 'danger');
        }
        header('Location: databasebackup.php');
        exit;
    }
}

// Download action (GET)
if ($action === 'download' && !empty($_GET['file'])) {
    $file = basename($_GET['file']);
    $full = $backupPath . '/' . $file;
    if (file_exists($full)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($full));
        readfile($full);
        exit;
    } else {
        set_flash('Bestand niet gevonden', 'danger');
        header('Location: databasebackup.php');
        exit;
    }
}

// List backups
function list_backups($dir) {
    $files = glob($dir . '/*.{sql,gz}', GLOB_BRACE);
    $out = [];
    foreach ($files as $f) {
        $out[] = [
            'name' => basename($f),
            'path' => $f,
            'size' => filesize($f),
            'mtime' => filemtime($f)
        ];
    }
    usort($out, function($a, $b) { return $b['mtime'] - $a['mtime']; });
    return $out;
}

// HTML UI
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Database Backups</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <h1 class="mb-4">Database Backups</h1>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'danger' ? 'danger' : 'success'; ?>">
            <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="mb-3">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <button name="create_backup" class="btn btn-primary">Maak backup</button>
    </form>

    <h3>Beschikbare backups</h3>
    <table class="table table-striped">
        <thead>
            <tr><th>Bestand</th><th>Grootte</th><th>Datum</th><th>Acties</th></tr>
        </thead>
        <tbody>
        <?php foreach (list_backups($backupPath) as $file): ?>
            <tr>
                <td><?php echo htmlspecialchars($file['name']); ?></td>
                <td><?php echo round($file['size']/1024, 2) . ' KB'; ?></td>
                <td><?php echo date('Y-m-d H:i:s', $file['mtime']); ?></td>
                <td>
                    <a href="?action=download&file=<?php echo urlencode($file['name']); ?>" class="btn btn-sm btn-success">Download</a>
                    <form method="POST" style="display:inline-block;margin-left:6px;" onsubmit="return confirm('Backup verwijderen?');">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="file" value="<?php echo htmlspecialchars($file['name']); ?>">
                        <button name="delete" class="btn btn-sm btn-danger">Verwijder</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

</div>
</body>
</html>