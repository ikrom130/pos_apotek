<?php

declare(strict_types=1);

// check openssl.
if (!extension_loaded('openssl')) {
    exit("ERROR: Ekstensi OpenSSL belum aktif.\n");
}

// inisaialisasi direktori.
$keyDirectory = 'D:/Project/PHP/secrets/pos_apotek';

$privateKeyPath = $keyDirectory . DIRECTORY_SEPARATOR . 'jwt-private.pem';
$publicKeyPath  = $keyDirectory . DIRECTORY_SEPARATOR . 'jwt-public.pem';

// check existensi key.
if (file_exists($privateKeyPath) || file_exists($publicKeyPath)) {
    exit(
        "ERROR: File kunci sudah ada. " .
        "Periksa dahulu sebelum membuat pasangan baru.\n"
    );
}

// buat direktori
if (
    !is_dir($keyDirectory) &&
    !mkdir($keyDirectory, 0700, true) &&
    !is_dir($keyDirectory)
) {
    exit("ERROR: Direktori kunci gagal dibuat.\n");
}

// inisialisasi RSA 3072-bit.
$key = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 3072,
]);

if ($key === false) {
    exit("ERROR: Gagal membuat kunci RSA.\n");
}

// dekalarasi private key.
$privatePem = '';

if (!openssl_pkey_export($key, $privatePem)) {
    exit("ERROR: Private key gagal diekspor.\n");
}

// get public key.
$details = openssl_pkey_get_details($key);

if ($details === false || !isset($details['key'])) {
    exit("ERROR: Public key gagal diperoleh.\n");
}

$publicPem = $details['key'];

// simpan kedua file, tanpa menampilkan isi kuncinya.
if (file_put_contents($privateKeyPath, $privatePem, LOCK_EX) === false) {
    exit("ERROR: Private key gagal disimpan.\n");
}

if (file_put_contents($publicKeyPath, $publicPem, LOCK_EX) === false) {
    @unlink($privateKeyPath);
    exit("ERROR: Public key gagal disimpan.\n");
}

// Verifikasi bahwa kedua file bisa dibaca.
$privateCheck = openssl_pkey_get_private(
    file_get_contents($privateKeyPath)
);
$publicCheck = openssl_pkey_get_public(
    file_get_contents($publicKeyPath)
);

if ($privateCheck === false || $publicCheck === false) {
    @unlink($privateKeyPath);
    @unlink($publicKeyPath);
    exit("ERROR: Verifikasi file kunci gagal.\n");
}

echo "Pasangan kunci RSA berhasil dibuat.\n";
echo "Private key: {$privateKeyPath}\n";
echo "Public key:  {$publicKeyPath}\n";
echo "Ukuran RSA:  {$details['bits']} bit\n";
