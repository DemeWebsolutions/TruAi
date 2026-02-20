#!/usr/bin/env php
<?php
/**
 * DemeWebsolutions Biometric Auth — Native Messaging Host
 *
 * Bridges the Chrome/Firefox browser extension to the OS keychain.
 * Install this script as the native messaging host for:
 *   com.demewebsolutions.biometric
 *
 * macOS:   ~/Library/Application Support/Google/Chrome/NativeMessagingHosts/
 * Linux:   ~/.config/google-chrome/NativeMessagingHosts/
 *
 * @package TruAi
 * @copyright My Deme, LLC © 2026
 */

require_once dirname(__DIR__) . '/backend/ubsas_auth_service.php';

// ─── Message I/O ──────────────────────────────────────────────────────────────

function readMessage(): ?array {
    $stdin = fopen('php://stdin', 'rb');
    $lenBytes = fread($stdin, 4);
    if (strlen($lenBytes) !== 4) {
        fclose($stdin);
        return null;
    }
    $length  = unpack('V', $lenBytes)[1];
    $message = fread($stdin, $length);
    fclose($stdin);
    return json_decode($message, true);
}

function sendMessage(array $message): void {
    $encoded = json_encode($message);
    $stdout  = fopen('php://stdout', 'wb');
    fwrite($stdout, pack('V', strlen($encoded)));
    fwrite($stdout, $encoded);
    fclose($stdout);
}

// ─── Main ─────────────────────────────────────────────────────────────────────

$request = readMessage();

if (!$request || !isset($request['action'])) {
    sendMessage(['success' => false, 'error' => 'Invalid request']);
    exit(1);
}

$ubsas = new UBSASAuthService();

switch ($request['action']) {
    case 'getCredentials':
        $app = trim($request['app'] ?? '');
        if (empty($app)) {
            sendMessage(['success' => false, 'error' => 'app parameter required']);
            exit(1);
        }
        $credentials = $ubsas->autofillCredentials($app);
        if ($credentials) {
            sendMessage([
                'success'     => true,
                'credentials' => [
                    'username' => $credentials['username'],
                    'password' => $credentials['password'],
                ],
            ]);
        } else {
            sendMessage(['success' => false, 'error' => 'Credentials not found for ' . $app]);
        }
        break;

    case 'ping':
        sendMessage(['success' => true, 'message' => 'Native host operational']);
        break;

    default:
        sendMessage(['success' => false, 'error' => 'Unknown action: ' . $request['action']]);
        exit(1);
}
