<?php
namespace SevenCupsMigratos\Test;

use PHPUnit\Framework\TestCase;
use SevenCupsMigratos\Migration;

final class MigrationTest extends TestCase{
    /** @var Migration */
    private $service; 
    protected function setUp(): void
    {
        $db = new \PDO('sqlite:'.__DIR__.'/data/test.db');
        $this->service = new Migration($db);
    }

    public function testGenerateBase() {
        $base = $this->service->generateBase(); 
        $tablename = $this->service::VERSION_TABLE_NAME; 
                $sql = <<<SQL
CREATE TABLE $tablename (
    id int(11) NOT NULL AUTO_INCREMENT, 
    version varchar(25),
    run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    filename varchar(255),
    PRIMARY KEY (id)
)
SQL;

        $this->assertEquals($sql,$base);

    }
}

?>
