<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class IdSequenceTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $dbCfg = $GLOBALS['testDbConfig'];
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $dbCfg['host'],
            $dbCfg['dbname'],
            $dbCfg['charset']
        );
        $this->db = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->db->exec('TRUNCATE TABLE id_sequences');
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function test_melempar_logic_exception_jika_dipanggil_di_luar_transaksi(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Allocator ID harus dipanggil di dalam transaksi.');

        nextSequentialId($this->db, 'employee', 'EMP-', 5, 15);
    }

    public function test_validasi_parameter_sequence_tidak_valid(): void
    {
        $this->db->beginTransaction();

        // Sequence key mengandung huruf kapital / karakter ilegal
        try {
            nextSequentialId($this->db, 'INVALID_KEY', 'EMP-', 5, 15);
            $this->fail('Seharusnya melempar InvalidArgumentException untuk sequenceKey.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Konfigurasi sequence ID tidak valid.', $e->getMessage());
        }

        // maxLength lebih kecil dari panjang prefix + minimumDigits
        $this->expectException(InvalidArgumentException::class);
        nextSequentialId($this->db, 'employee', 'EMP-', 5, 8); // strlen('EMP-') + 5 = 9 > 8
    }

    public function test_membuat_sequence_baru_dan_increment_berurutan(): void
    {
        $this->db->beginTransaction();
        $first = nextSequentialId($this->db, 'employee', 'EMP-', 5, 15);
        $second = nextSequentialId($this->db, 'employee', 'EMP-', 5, 15);
        $otherKey = nextSequentialId($this->db, 'transaction', 'TRX-', 6, 20);
        $this->db->commit();

        $this->assertSame('EMP-00001', $first);
        $this->assertSame('EMP-00002', $second);
        $this->assertSame('TRX-000001', $otherKey);

        // Pastikan tersimpan di tabel id_sequences
        $stmt = $this->db->prepare('SELECT last_id_recorded FROM id_sequences WHERE sequence_key = ?');
        $stmt->execute(['employee']);
        $this->assertSame(2, (int) $stmt->fetchColumn());
    }

    public function test_melempar_runtime_exception_jika_rentang_id_habis(): void
    {
        // Set counter mendekati batas maksimal 2 digit (99) dengan maxLength = 6 ('ID-' + 2 digit = 5, max 6 -> max 999)
        $this->db->exec("INSERT INTO id_sequences (sequence_key, last_id_recorded) VALUES ('short_seq', 999)");

        $this->db->beginTransaction();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rentang ID sudah habis.');

        // Berikutnya 1000 -> 'ID-1000' (panjang 7 > maxLength 6)
        nextSequentialId($this->db, 'short_seq', 'ID-', 2, 6);
    }

    public function test_rollback_membatalkan_kenaikan_sequence(): void
    {
        $this->db->beginTransaction();
        $id1 = nextSequentialId($this->db, 'employee', 'EMP-', 5, 15);
        $this->assertSame('EMP-00001', $id1);
        $this->db->rollBack();

        // Transaksi baru harus kembali mulai dari EMP-00001
        $this->db->beginTransaction();
        $idRetry = nextSequentialId($this->db, 'employee', 'EMP-', 5, 15);
        $this->db->commit();

        $this->assertSame('EMP-00001', $idRetry);
    }
}