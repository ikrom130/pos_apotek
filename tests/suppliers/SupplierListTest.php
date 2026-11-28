<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SupplierListTest extends TestCase
{
    private PDO $db;
    private array $config;
    private array $adminActor;

    protected function setUp(): void
    {
        $dbCfg = $GLOBALS['testDbConfig'];
        $this->config = $GLOBALS['authConfig'];
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
        $this->db->exec('TRUNCATE TABLE suppliers');
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->adminActor = [
            'employee_id' => 'EMP-00000',
            'employee_name' => 'Super Admin',
            'employee_role' => 'admin',
            'employee_username' => 'admin',
        ];

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => $this->config['app_origin'],
            'REMOTE_ADDR' => '192.168.1.10',
            'HTTP_USER_AGENT' => 'PHPUnit Test Agent',
        ];
        $_GET = [];
        $_COOKIE = [];
    }

    private function seedSupplier(
        string $id,
        string $name,
        ?string $phone = '0812-0000-0001',
        ?string $email = 'info@supplier.test',
        ?string $address = 'Jl. Sudirman No. 1',
        int $isActive = 1
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO suppliers (supplier_id, supplier_name, supplier_phone, supplier_email, supplier_address, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $name, $phone, $email, $address, $isActive]);
    }

    private function executeList(array $query = [], ?array $actor = null): AuthResponseException
    {
        $_GET = $query;
        $activeActor = $actor ?? $this->adminActor;

        try {
            handleListSuppliers($this->db, $activeActor);
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

    public function test_menolak_jika_role_tidak_diizinkan(): void
    {
        $kasirActor = [
            'employee_id' => 'EMP-00002',
            'employee_role' => 'kasir',
        ];

        $response = $this->executeList([], $kasirActor);
        $this->assertSame(403, $response->statusCode);
        $this->assertSame('Role tidak memiliki akses ke fitur ini.', $response->payload['message']);
    }

    public function test_mengizinkan_role_apoteker(): void
    {
        $this->seedSupplier('SUP-00001', 'PT Kimia Farma Trading');

        $apotekerActor = [
            'employee_id' => 'EMP-00003',
            'employee_role' => 'apoteker',
        ];

        $response = $this->executeList([], $apotekerActor);
        $this->assertSame(200, $response->statusCode);
        $this->assertCount(1, $response->payload['data']);
    }

    public function test_validasi_parameter_query_tidak_valid(): void
    {
        // page < 1
        $resPageMin = $this->executeList(['page' => '0']);
        $this->assertSame(422, $resPageMin->statusCode);

        // page > 1000000
        $resPageMax = $this->executeList(['page' => '1000001']);
        $this->assertSame(422, $resPageMax->statusCode);

        // page non-integer
        $resPageStr = $this->executeList(['page' => 'abc']);
        $this->assertSame(422, $resPageStr->statusCode);

        // limit < 1
        $resLimitMin = $this->executeList(['limit' => '0']);
        $this->assertSame(422, $resLimitMin->statusCode);

        // limit > 100
        $resLimitMax = $this->executeList(['limit' => '101']);
        $this->assertSame(422, $resLimitMax->statusCode);

        // search > 100 karakter
        $resSearchLong = $this->executeList(['search' => str_repeat('a', 101)]);
        $this->assertSame(422, $resSearchLong->statusCode);

        // active bukan 0 atau 1
        $resActiveInvalid = $this->executeList(['active' => '2']);
        $this->assertSame(422, $resActiveInvalid->statusCode);

        // parameter bukan string (array)
        $resArrayParam = $this->executeList(['search' => ['test']]);
        $this->assertSame(422, $resArrayParam->statusCode);
    }

    public function test_berhasil_memuat_daftar_supplier_dengan_pagination_dan_urutan_nama(): void
    {
        $this->seedSupplier('SUP-00001', 'PT Tempo Scan Pacific', '021-1111111', 'tempo@supplier.test', 'Jakarta', 1);
        $this->seedSupplier('SUP-00002', 'PT Anugrah Argon Medica', '021-2222222', 'aam@supplier.test', 'Tangerang', 1);
        $this->seedSupplier('SUP-00003', 'PT Kimia Farma Trading', '021-3333333', 'kftd@supplier.test', 'Jakarta', 0);

        $page1 = $this->executeList(['page' => '1', 'limit' => '2']);
        $this->assertSame(200, $page1->statusCode);
        $this->assertSame('success', $page1->payload['status']);
        $this->assertSame(['page' => 1, 'limit' => 2, 'total' => 3], $page1->payload['pagination']);
        $this->assertCount(2, $page1->payload['data']);
        // Urutan ASC berdasarkan supplier_name
        $this->assertSame('SUP-00002', $page1->payload['data'][0]['supplier_id']);
        $this->assertSame('SUP-00003', $page1->payload['data'][1]['supplier_id']);

        $page2 = $this->executeList(['page' => '2', 'limit' => '2']);
        $this->assertSame(200, $page2->statusCode);
        $this->assertCount(1, $page2->payload['data']);
        $this->assertSame('SUP-00001', $page2->payload['data'][0]['supplier_id']);
    }

    public function test_filter_berdasarkan_active_dan_search(): void
    {
        $this->seedSupplier('SUP-00001', 'PT Kimia Farma Trading', '0812-1111-2222', 'sales@kftd.co.id', 'Jakarta', 1);
        $this->seedSupplier('SUP-00002', 'PT Bina San Prima', '0813-9999-8888', 'admin@bsp.co.id', 'Bandung', 0);

        // Filter active = 1
        $activeOnly = $this->executeList(['active' => '1']);
        $this->assertSame(200, $activeOnly->statusCode);
        $this->assertSame(1, $activeOnly->payload['pagination']['total']);
        $this->assertSame('SUP-00001', $activeOnly->payload['data'][0]['supplier_id']);

        // Filter active = 0
        $inactiveOnly = $this->executeList(['active' => '0']);
        $this->assertSame(200, $inactiveOnly->statusCode);
        $this->assertSame(1, $inactiveOnly->payload['pagination']['total']);
        $this->assertSame('SUP-00002', $inactiveOnly->payload['data'][0]['supplier_id']);

        // Search berdasarkan email / nama / id / phone
        $searchRes = $this->executeList(['search' => 'bsp.co.id']);
        $this->assertSame(200, $searchRes->statusCode);
        $this->assertSame(1, $searchRes->payload['pagination']['total']);
        $this->assertSame('SUP-00002', $searchRes->payload['data'][0]['supplier_id']);
    }
}