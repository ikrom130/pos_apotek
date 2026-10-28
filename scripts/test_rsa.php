
<?php

$key = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 3072,
]);

if ($key === false) {
    echo "Gagal membuat kunci RSA:\n";

    while ($error = openssl_error_string()) {
        echo $error . PHP_EOL;
    }

    exit(1);
}

echo "RSA key generation OK" . PHP_EOL;
