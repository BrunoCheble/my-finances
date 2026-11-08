<?php
/**
 * Opens a backup made by App\Services\SystemBackupService with the private key and writes the JSON.
 * The scheduled backup runs in the private repository BrunoCheble/escudo-backups (the backups stay there).
 *
 *   php scripts/decrypt-backup.php escudo-backup-2026-11-08.bin ~/Escudo-backup-private.pem > backup.json
 *
 * Without PHP on the machine (Docker):
 *   docker run --rm -v "$PWD":/w -v ~/Escudo-backup-private.pem:/key.pem:ro -w /w php:8.3-cli \
 *     php scripts/decrypt-backup.php escudo-backup-2026-11-08.bin /key.pem > backup.json
 */
[$script, $file, $keyFile] = $argv + [null, null, null];
if (!$file || !$keyFile) {
    fwrite(STDERR, "Usage: php $script <backup file> <private key>\n");
    exit(1);
}

$data = file_get_contents($file);
if (substr($data, 0, 9) !== 'ESCUDOBK1') {
    fwrite(STDERR, "Not an Escudo backup.\n");
    exit(1);
}
$length = unpack('n', substr($data, 9, 2))[1];
$encryptedKey = substr($data, 11, $length);
$iv = substr($data, 11 + $length, 12);
$tag = substr($data, 23 + $length, 16);
$cipher = substr($data, 39 + $length);

$privateKey = openssl_pkey_get_private(file_get_contents($keyFile));
if (!$privateKey || !openssl_private_decrypt($encryptedKey, $key, $privateKey, OPENSSL_PKCS1_OAEP_PADDING)) {
    fwrite(STDERR, "Wrong private key.\n");
    exit(1);
}
$plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
if ($plain === false) {
    fwrite(STDERR, "The file is damaged.\n");
    exit(1);
}
echo gzdecode($plain);
