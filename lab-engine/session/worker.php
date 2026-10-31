<?php

declare(strict_types=1);

error_reporting(0);

$request = json_decode(stream_get_contents(STDIN), true);

if (! is_array($request)
    || ! isset($request['format'], $request['sample'], $request['storage_path'])
    || ! is_string($request['format'])
    || ! in_array($request['format'], ['php', 'php_serialize'], true)
    || ! is_string($request['sample'])
    || strlen($request['sample']) > 1024
    || ! is_string($request['storage_path'])) {
    fwrite(STDOUT, json_encode(['error' => 'Invalid worker input.']));
    exit(2);
}

$storagePath = realpath($request['storage_path']);
$temporaryRoot = realpath(sys_get_temp_dir());

if ($storagePath === false
    || $temporaryRoot === false
    || dirname($storagePath) !== $temporaryRoot
    || ! str_starts_with(basename($storagePath), 'php2shell-session-')
    || ! is_dir($storagePath)
    || ! is_writable($storagePath)) {
    fwrite(STDOUT, json_encode(['error' => 'Session storage is not an approved temporary directory.']));
    exit(2);
}

ini_set('session.save_handler', 'files');
ini_set('session.save_path', $storagePath);
ini_set('session.serialize_handler', $request['format']);
ini_set('session.name', 'PHP2SHELLSESSID');
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.gc_probability', '0');

if (session_start() !== true) {
    fwrite(STDOUT, json_encode(['error' => 'Could not create the demonstration session.']));
    exit(1);
}

$sessionId = session_id();
$_SESSION = [
    'sample_input' => $request['sample'],
    'metadata' => [
        'source' => 'trusted laboratory fixture',
        'active' => true,
    ],
];
session_write_close();

$sessionFile = $storagePath.DIRECTORY_SEPARATOR.'sess_'.$sessionId;
$rawStorage = file_get_contents($sessionFile);

if ($rawStorage === false) {
    fwrite(STDOUT, json_encode(['error' => 'Could not inspect the temporary session file.']));
    exit(1);
}

session_id($sessionId);

if (session_start() !== true) {
    fwrite(STDOUT, json_encode(['error' => 'Could not retrieve the demonstration session.']));
    exit(1);
}

$retrieved = $_SESSION;
session_write_close();

$response = [
    'php_version' => PHP_VERSION,
    'session' => [
        'save_handler' => ini_get('session.save_handler'),
        'serialize_handler' => ini_get('session.serialize_handler'),
        'save_path' => ini_get('session.save_path'),
        'cookie_name' => ini_get('session.name'),
        'strict_mode' => ini_get('session.use_strict_mode'),
        'cookies_enabled' => ini_get('session.use_cookies'),
    ],
    'session_id' => $sessionId,
    'raw_storage' => $rawStorage,
    'retrieved' => $retrieved,
    'sample_is_plain_data' => is_string($retrieved['sample_input'] ?? null),
];

fwrite(STDOUT, json_encode($response, JSON_THROW_ON_ERROR));
