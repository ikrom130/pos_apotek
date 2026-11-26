<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EmployeeListTest extends TestCase
{
    private PDO $db;
    private array $adminActor;

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

        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->exec('TRUNCATE TABLE auth_refresh_tokens');
        $this->db->exec('TRUNCATE TABLE auth_sessions');
        $this->db->exec('TRUNCATE TABLE employees');
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->adminActor = [
            'employee_id' => 'EMP-00000',
            'employee_name' => 'Super Admin',
            'employee_role' => 'admin',
            'employee_username' => 'admin',
        ];

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '192.168.1.10',
            'HTTP_USER_AGENT' => 'PHPUnit Test Agent',
        ];
        $_GET = [];
    }

    private function seedEmployees(): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO employees (employee_id, employee_name, employee_role, employee_phone, employee_username, employee_password, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $hash = password_hash('SandiKuat12345!', PASSWORD_DEFAULT);

        $rows = [
            ['EMP-00001', 'Budi Santoso', 'admin', '0811111111', 'budi.admin', $hash, 1],
            ['EMP-00002', 'Andi Pratama', 'apoteker', '0822222222', 'andi.apt', $hash, 1],
            ['EMP-00003', 'Citra Lestari', 'kasir', null, 'citra.kasir', $hash, 1],
            ['EMP-00004', 'Dedi Kurniawan', 'kasir', '0844444444', 'dedi.nonaktif', $hash, 0],
        ];

        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }

    private function executeList(array $query = [], ?array $actor = null): AuthResponseException
    {
        $_GET = $query;
        $activeActor = $actor ?? $this->adminActor;

        try {
            handleListEmployees($this->db, $activeActor);
            $this->fail('Expected AuthResponseException was not thrown.');
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    public function test_menolak_metode_selain_get(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $response = $this->executeList();
        $this->assertSame(405, $response->statusCode);
    }

    public function test_menolak_jika_role_bukan_admin(): void
    {
        $apotekerActor = [
            'employee_id' => 'EMP-00002',
            'employee_role' => 'apoteker',
        ];

        $response = $this->executeList([], $apotekerActor);
        $this->assertSame(403, $response->statusCode);
        $this->assertSame('Role tidak memiliki akses ke fitur ini.', $response->payload['message']);
    }

    public function test_validasi_parameter_query_tidak_valid(): void
    {
        // page < 1
        $resPage = $this->executeList(['page' => '0']);
        $this->assertSame(422, $resPage->statusCode);

        // limit > 100
        $resLimit = $this->executeList(['limit' => '101']);
        $this->assertSame(422, $resLimit->statusCode);

        // search > 100 karakter
        $resSearch = $this->executeList(['search' => str_repeat('a', 101)]);
        $this->assertSame(422, $resSearch->statusCode);

        // role tidak valid
        $resRole = $this->executeList(['role' => 'supervisor']);
        $this->assertSame(422, $resRole->statusCode);

        // active bukan 0 atau 1
        $resActive = $this->executeList(['active' => '2']);
        $this->assertSame(422, $resActive->statusCode);

        // parameter berupa array (bukan string)
        $resArray = $this->executeList(['search' => ['hack']]);
        $this->assertSame(422, $resArray->statusCode);
    }

    public function test_mengembalikan_daftar_pegawai_terurut_dan_tanpa_password(): void
    {
        $this->seedEmployees();

        $response = $this->executeList();

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('success', $response->payload['status']);
        $this->assertSame(['page' => 1, 'limit' => 20, 'total' => 4], $response->payload['pagination']);

        $data = $response->payload['data'];
        $this->assertCount(4, $data);

        // Urutan default: employee_name ASC (Andi, Budi, Citra, Dedi)
        $this->assertSame('Andi Pratama', $data[0]['employee_name']);
        $this->assertSame('Budi Santoso', $data[1]['employee_name']);
        $this->assertSame('Citra Lestari', $data[2]['employee_name']);
        $this->assertSame('Dedi Kurniawan', $data[3]['employee_name']);

        // Pastikan hash password tidak ikut terekspos di response
        $this->assertArrayNotHasKey('employee_password', $data[0]);
    }

    public function test_pagination_page_dan_limit(): void
    {
        $this->seedEmployees();

        // Ambil halaman ke-2 dengan limit 2 (harus mengembalikan Citra & Dedi)
        $response = $this->executeList(['page' => '2', 'limit' => '2']);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['page' => 2, 'limit' => 2, 'total' => 4], $response->payload['pagination']);
        $this->assertCount(2, $response->payload['data']);
        $this->assertSame('Citra Lestari', $response->payload['data'][0]['employee_name']);
        $this->assertSame('Dedi Kurniawan', $response->payload['data'][1]['employee_name']);
    }

    public function test_filter_search_role_dan_active(): void
    {
        $this->seedEmployees();

        // Filter search berdasarkan username / ID / nama
        $resSearch = $this->executeList(['search' => 'EMP-00002']);
        $this->assertSame(1, $resSearch->payload['pagination']['total']);
        $this->assertSame('Andi Pratama', $resSearch->payload['data'][0]['employee_name']);

        // Filter role = kasir & active = 1 (hanya Citra, Dedi nonaktif tidak masuk)
        $resCombined = $this->executeList(['role' => 'kasir', 'active' => '1']);
        $this->assertSame(1, $resCombined->payload['pagination']['total']);
        $this->assertSame('Citra Lestari', $resCombined->payload['data'][0]['employee_name']);

        // Filter active = 0 (hanya Dedi)
        $resInactive = $this->executeList(['active' => '0']);
        $this->assertSame(1, $resInactive->payload['pagination']['total']);
        $this->assertSame('Dedi Kurniawan', $resInactive->payload['data'][0]['employee_name']);
    }
}