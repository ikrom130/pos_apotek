<?php

declare(strict_types=1);

if (!defined('API_INTERNAL_INCLUDE')) {
    http_response_code(404);
    exit;
}

function nextSequentialId(
    PDO $db,
    string $sequenceKey,
    string $prefix,
    int $minimumDigits,
    int $maxLength
): string {
    if (!$db->inTransaction()) {
        throw new LogicException('Allocator ID harus dipanggil di dalam transaksi.');
    }
    if (
        preg_match('/\A[a-z][a-z0-9:_-]{0,49}\z/', $sequenceKey) !== 1 ||
        $minimumDigits < 1 || $maxLength < strlen($prefix) + $minimumDigits
    ) {
        throw new InvalidArgumentException('Konfigurasi sequence ID tidak valid.');
    }

    $init = $db->prepare(
        'INSERT IGNORE INTO id_sequences (sequence_key, last_id_recorded) VALUES (?, 0)'
    );
    $init->execute([$sequenceKey]);

    $read = $db->prepare('SELECT last_id_recorded FROM id_sequences WHERE sequence_key = ? FOR UPDATE');
    $read->execute([$sequenceKey]);
    $lastValue = $read->fetchColumn();
    if ($lastValue === false) {
        throw new RuntimeException('Counter ID tidak dapat dibaca.');
    }

    $nextValue = (int) $lastValue + 1;
    $id = $prefix . str_pad((string) $nextValue, $minimumDigits, '0', STR_PAD_LEFT);
    if (strlen($id) > $maxLength) {
        throw new RuntimeException('Rentang ID sudah habis.');
    }

    $update = $db->prepare('UPDATE id_sequences SET last_id_recorded = ? WHERE sequence_key = ?');
    $update->execute([$nextValue, $sequenceKey]);
    return $id;
}