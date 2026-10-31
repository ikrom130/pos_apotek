<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$password = getenv('BOOTSTRAP_ADMIN_PASSWORD');
if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "BOOTSTRAP_ADMIN_PASSWORD wajib 12–72 byte dan hanya dipakai sekali.\n");
    exit(1);
}

$prompt = static function (string $label, string $default): string {
    fwrite(STDOUT, $label . ' [' . $default . ']: ');
    $value = fgets(STDIN);
    return trim($value === false ? '' : $value) ?: $default;
};

$username = trim((string) (getenv('BOOTSTRAP_ADMIN_USERNAME') ?: $prompt('Username admin', 'admin.local')));
$employeeName = trim((string) (getenv('BOOTSTRAP_EMPLOYEE_NAME') ?: $prompt('Nama admin', 'Admin Apotek')));
if (preg_match('/\A[a-zA-Z0-9._-]{3,50}\z/D', $username) !== 1 || $employeeName === '' || strlen($employeeName) > 100) {
    fwrite(STDERR, "Username/nama admin tidak memenuhi format kolom employees.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->beginTransaction();

    $id = 'EMP-00001';
    $existing = $db->prepare(
        'SELECT employee_id, employee_role, employee_password
         FROM employees WHERE employee_id = ? FOR UPDATE'
    );
    $existing->execute([$id]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);

    $duplicate = $db->prepare(
        'SELECT employee_id FROM employees WHERE employee_username = ? AND employee_id <> ? LIMIT 1'
    );
    $duplicate->execute([$username, $id]);
    if ($duplicate->fetchColumn() !== false) {
        throw new RuntimeException('Username sudah dipakai pegawai lain.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash)) {
        throw new RuntimeException('Hash sandi gagal dibuat.');
    }

    if ($row) {
        if (
            $row['employee_role'] !== 'admin' ||
            $row['employee_password'] !== 'DUMMY_HASH_REPLACE_BEFORE_AUTH'
        ) {
            throw new RuntimeException(
                'EMP-00001 bukan akun admin placeholder; script berhenti tanpa mengubah data.'
            );
        }
        $update = $db->prepare(
            'UPDATE employees
             SET employee_name = ?, employee_username = ?, employee_password = ?, is_active = 1
             WHERE employee_id = ?'
        );
        $update->execute([$employeeName, $username, $hash, $id]);
    } else {
        $count = (int) $db->query('SELECT COUNT(*) FROM employees')->fetchColumn();
        if ($count !== 0) {
            throw new RuntimeException(
                'Pegawai sudah ada tetapi EMP-00001 tidak ditemukan; jangan menebak akun administrator.'
            );
        }
        $insert = $db->prepare(
            'INSERT INTO employees
                (employee_id, employee_name, employee_role, employee_username, employee_password, is_active)
             VALUES (?, ?, \'admin\', ?, ?, 1)'
        );
        $insert->execute([$id, $employeeName, $username, $hash]);
    }

    $db->commit();
    fwrite(STDOUT, "Admin awal siap: {$id} ({$username}). Sandi tidak ditampilkan.\n");
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    unset($password);
    putenv('BOOTSTRAP_ADMIN_PASSWORD');
}
