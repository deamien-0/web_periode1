<?php
require_once '../vendor/autoload.php';
require_once 'config.php';
require_once 'session.php';

use Spatie\DbDumper\Databases\MySql;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Controleer of gebruiker is ingelogd en admin is
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Setup logging
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

$log = new Logger('database');
$log->pushHandler(new StreamHandler($logDir . '/database.log', Logger::INFO));

try {
    // Maak backup directory als die niet bestaat
    $backupDir = __DIR__ . '/backups';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0777, true);
    }

    // Genereer bestandsnamen voor verschillende exports
    $timestamp = date('Y-m-d_H-i-s');
    $structureFile = $backupDir . '/structure_' . $timestamp . '.sql';
    $dataFile = $backupDir . '/data_' . $timestamp . '.sql';
    $fullFile = $backupDir . '/full_' . $timestamp . '.sql';

    // Exporteer alleen de database structuur
    MySql::create()
        ->setHost(DB_HOST)
        ->setDbName(DB_NAME)
        ->setUserName(DB_USER)
        ->setPassword(DB_PASS)
        ->setDumpBinaryPath('C:\MAMP\bin\mysql\bin')
        ->addExtraOption('--no-data')
        ->dumpToFile($structureFile);

    $log->info('Database structuur geëxporteerd', ['file' => $structureFile]);

    // Exporteer alleen de data
    MySql::create()
        ->setHost(DB_HOST)
        ->setDbName(DB_NAME)
        ->setUserName(DB_USER)
        ->setPassword(DB_PASS)
        ->setDumpBinaryPath('C:\MAMP\bin\mysql\bin')
        ->addExtraOption('--no-create-info')
        ->dumpToFile($dataFile);

    $log->info('Database gegevens geëxporteerd', ['file' => $dataFile]);

    // Exporteer volledige database (structuur + data)
    MySql::create()
        ->setHost(DB_HOST)
        ->setDbName(DB_NAME)
        ->setUserName(DB_USER)
        ->setPassword(DB_PASS)
        ->setDumpBinaryPath('C:\MAMP\bin\mysql\bin')
        ->dumpToFile($fullFile);

    $log->info('Complete database geëxporteerd', ['file' => $fullFile]);

    // Verwijder oude backups (bewaar laatste 5)
    $files = glob($backupDir . '/*.sql');
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    if (count($files) > 15) { // Behoud 5 van elk type (15 totaal)
        for ($i = 15; $i < count($files); $i++) {
            unlink($files[$i]);
            $log->info('Oude backup verwijderd', ['file' => $files[$i]]);
        }
    }

    // Redirect met succes bericht
    $_SESSION['backup_message'] = 'Database exports succesvol aangemaakt!';
    header('Location: account.php');
    exit();

} catch (Exception $e) {
    $log->error('Export fout', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    $_SESSION['backup_error'] = 'Fout bij maken van database exports: ' . $e->getMessage();
    header('Location: account.php');
    exit();
}