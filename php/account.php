<?php
require_once '../vendor/autoload.php';
require_once 'config.php';
require_once 'session.php';

use Spatie\DbDumper\Databases\MySql;
use Dompdf\Dompdf;
use Dompdf\Options;

if (!isLoggedIn()) {
    header('Location: inlogen.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get user
$stmt = $mysqli->prepare("SELECT naam, achternaam, email FROM gebruikers WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: inlogen.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update name
    if (isset($_POST['update_name'])) {
        $naam = trim($_POST['naam']);
        $achternaam = trim($_POST['achternaam']);
        
        if (empty($naam) || empty($achternaam)) {
            $error = "Naam en achternaam zijn verplicht.";
        } else {
            $stmt = $mysqli->prepare("UPDATE gebruikers SET naam = ?, achternaam = ? WHERE id = ?");
            $stmt->bind_param("ssi", $naam, $achternaam, $user_id);
            if ($stmt->execute()) {
                $success = "Naam bijgewerkt!";
                $user['naam'] = $naam;
                $user['achternaam'] = $achternaam;
            } else {
                $error = "Fout bij bijwerken naam.";
            }
            $stmt->close();
        }
    }
    
    // Update password
    if (isset($_POST['update_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if (empty($current) || empty($new) || empty($confirm)) {
            $error = "Alle velden zijn verplicht.";
        } elseif ($new !== $confirm) {
            $error = "Wachtwoorden komen niet overeen.";
        } elseif (strlen($new) < 6) {
            $error = "Wachtwoord moet minimaal 6 karakters bevatten.";
        } else {
            // Check current password
            $stmt = $mysqli->prepare("SELECT wachtwoord FROM gebruikers WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stored = $stmt->get_result()->fetch_assoc()['wachtwoord'];
            $stmt->close();
            
            if (password_verify($current, $stored)) {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare("UPDATE gebruikers SET wachtwoord = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed, $user_id);
                if ($stmt->execute()) {
                    $success = "Wachtwoord bijgewerkt!";
                } else {
                    $error = "Fout bij bijwerken wachtwoord.";
                }
                $stmt->close();
            } else {
                $error = "Huidig wachtwoord incorrect.";
            }
        }
    }
    
    // Export to PDF
    if (isset($_POST['export_pdf'])) {
        // Initialize dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Prepare PDF content
        $html = '
        <html>
        <head>
            <style>
                body { 
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                }
                .header { 
                    text-align: center;
                    margin-bottom: 40px;
                    border-bottom: 2px solid #333;
                    padding-bottom: 20px;
                }
                .section { 
                    margin-bottom: 30px;
                    padding: 20px;
                    background-color: #f9f9f9;
                    border-radius: 5px;
                }
                table { 
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
                }
                th, td { 
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }
                th { 
                    background-color: #f5f5f5;
                    font-weight: bold;
                }
                .footer {
                    margin-top: 40px;
                    text-align: center;
                    font-size: 0.9em;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Funko Pop Shop</h1>
                <h2>Account Gegevens</h2>
                <p>Geëxporteerd op ' . date('d-m-Y H:i:s') . '</p>
            </div>
            
            <div class="section">
                <h3>Persoonlijke Informatie</h3>
                <table>
                    <tr>
                        <td><strong>Volledige Naam:</strong></td>
                        <td>' . htmlspecialchars($user['naam'] . ' ' . $user['achternaam']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>E-mailadres:</strong></td>
                        <td>' . htmlspecialchars($user['email']) . '</td>
                    </tr>
                </table>
            </div>

            <div class="footer">
                <p>Dit document is automatisch gegenereerd door het Funko Pop Shop systeem.</p>
                <p>© ' . date('Y') . ' Funko Pop Shop. Alle rechten voorbehouden.</p>
            </div>
        </body>
        </html>';
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();
        
        // Output PDF to browser
        $dompdf->stream("account_gegevens_" . date('Y-m-d') . ".pdf", array("Attachment" => false));
        exit();
    }

    // Create and download backup
    if (isset($_POST['create_backup'])) {
        try {
            // Use php's temp directory for temporary storage
            $tempFile = tempnam(sys_get_temp_dir(), 'db_backup_');
            $filename = 'funko_webshop_backup_' . date('Y-m-d_H-i-s') . '.sql';
            
            MySql::create()
                ->setHost('localhost')
                ->setDbName($DB_NAME)
                ->setUserName($DB_USER)
                ->setPassword($DB_PASS)
                ->setPort(3306)
                ->setDumpBinaryPath('C:\MAMP\bin\mysql\bin')
                ->dumpToFile($tempFile);
            
            // Send the backup as download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($tempFile));
            
            readfile($tempFile);
            unlink($tempFile); // Remove the temporary file
            exit();
        } catch (Exception $e) {
            $error = "Error creating backup: " . $e->getMessage();
        }
    }
    
    // Delete account
    if (isset($_POST['delete_account'])) {
        $password = $_POST['delete_password'];
        
        if (empty($password)) {
            $error = "Wachtwoord verplicht om account te verwijderen.";
        } else {
            $stmt = $mysqli->prepare("SELECT wachtwoord FROM gebruikers WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stored = $stmt->get_result()->fetch_assoc()['wachtwoord'];
            $stmt->close();
            
            if (password_verify($password, $stored)) {
                $stmt = $mysqli->prepare("DELETE FROM gebruikers WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    session_destroy();
                    header('Location: index.php?message=account_deleted');
                    exit();
                }
                $stmt->close();
            } else {
                $error = "Wachtwoord incorrect.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mijn Account - Funko Shop</title>
    <link rel="stylesheet" href="../css/css.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">Funko Shop</a>
            <div class="user-info">
                <a href="winkelwagen.php">Winkelwagen (<?= getCartItemCount() ?>)</a>
                <a href="logout.php">Uitloggen</a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>Mijn Account</h1>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="user-info-display">
                    <h3>Accountgegevens</h3>
                    <p><strong>Naam:</strong> <?= htmlspecialchars($user['naam'] . ' ' . $user['achternaam']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                    
                    <div class="user-actions">
                        <form method="POST">
                            <button type="submit" name="create_backup" class="btn btn-primary">Download Database Backup</button>
                        </form>
                        <form method="POST">
                            <button type="submit" name="export_pdf" class="btn btn-secondary"><i class="fas fa-file-pdf"></i> Export naar PDF</button>
                        </form>
                    </div>
                </div>

                <!-- Update naam -->
                <div class="section">
                    <h3>Naam bijwerken</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="naam">Voornaam:</label>
                            <input type="text" class="form-control" id="naam" name="naam" value="<?= htmlspecialchars($user['naam']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="achternaam">Achternaam:</label>
                            <input type="text" class="form-control" id="achternaam" name="achternaam" value="<?= htmlspecialchars($user['achternaam']) ?>" required>
                        </div>
                        <button type="submit" name="update_name" class="btn btn-primary">Bijwerken</button>
                    </form>
                </div>

                <!-- Update wachtwoord -->
                <div class="section">
                    <h3>Wachtwoord wijzigen</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="current_password">Huidig wachtwoord:</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="new_password">Nieuw wachtwoord:</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Bevestig wachtwoord:</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                        </div>
                        <button type="submit" name="update_password" class="btn btn-primary">Wijzigen</button>
                    </form>
                </div>

                <!-- Delete account -->
                <div class="section delete-section">
                    <h3>Account verwijderen</h3>
                    <div class="warning-text">Let op: Deze actie kan niet ongedaan gemaakt worden!</div>
                    <form method="POST" onsubmit="return confirm('Weet je zeker dat je je account wilt verwijderen?');">
                        <div class="form-group">
                            <label class="form-label" for="delete_password">Bevestig met wachtwoord:</label>
                            <input type="password" class="form-control" id="delete_password" name="delete_password" required>
                        </div>
                        <button type="submit" name="delete_account" class="btn btn-danger">Verwijderen</button>
                    </form>
                </div>

                <a href="index.php" class="btn btn-secondary">Terug naar shop</a>
            </div>
        </div>
    </div>
</body>
</html>