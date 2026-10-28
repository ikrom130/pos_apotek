<?php

declare(strict_types=1);

$privatePath = 'D:/Project/PHP/secrets/pos_apotek/jwt-private.pem';
$publicPath  = 'D:/Project/PHP/secrets/pos_apotek/jwt-public.pem';

if (!is_readable($privatePath) || !is_readable($publicPath)) {
    exit("ERROR: File kunci tidak ditemukan atau tidak bisa dibaca.\n");
}

$privateKey = openssl_pkey_get_private(
    file_get_contents($privatePath)
);

$publicKey = openssl_pkey_get_public(
    file_get_contents($publicPath)
);

if ($privateKey === false || $publicKey === false) {
    exit("ERROR: Salah satu file bukan kunci PEM yang valid.\n");
}

$privateDetails = openssl_pkey_get_details($privateKey);
$publicDetails  = openssl_pkey_get_details($publicKey);

if (
    $privateDetails === false ||
    $publicDetails === false ||
    $privateDetails['key'] !== $publicDetails['key']
) {
    exit("ERROR: Kunci publik tidak cocok dengan private key.\n");
}

echo "Kedua file valid dan merupakan pasangan kunci yang cocok.\n";
echo "Algoritma: RSA\n";
echo "Ukuran: {$privateDetails['bits']} bit\n";
