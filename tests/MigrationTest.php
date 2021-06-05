<?php

namespace SevenCupsMigratos\Test;

use Exception;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SevenCupsMigratos\Migration;

final class MigrationTest extends TestCase
{
    /** @var Migration */
    private $service;

    /** @var PDO|MockObject */
    private $database;

    protected function setUp(): void
    {
        $this->database = $this->createMock(PDO::class);
        $this->service = new Migration($this->database);
    }

    public function testSetDatabase()
    {
        $this->database
            ->expects($this->once())
            ->method('setAttribute')
            ->with(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->service->setDatabase($this->database);

        $this->assertEquals($this->database, $this->service->getDatabase());
    }

    public function testSetMigrationFolder()
    {
        $directory = __DIR__ . '/data/';

        $this->service->setMigrationFolder($directory);

        $this->assertEquals($directory, $this->service->getMigrationFolder());
    }

    public function testSetMigrationFolderWhenThrowException()
    {
        $directory = 'test';

        $this->expectException(Exception::class);
        $this->expectDeprecationMessage(sprintf(
            'You cannot set nonexisting folder as migration folder. Please create %s',
            $directory
        ));

        $this->service->setMigrationFolder('test');
    }

    public function testSetBaseMigrationVersion()
    {
        $version = 1;

        $this->service->setBaseMigrationVersion($version);

        $this->assertEquals($version, $this->service->getBaseMigrationVersion());
    }

    public function testPurgeDatabase()
    {
        $tables = ['foo', 'bar'];
        $PDOStatement = $this->createMock(PDOStatement::class);
        /** @var Migration|MockObject $migrationMock */
        $migrationMock = $this->getMockBuilder(Migration::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDatabase'])
            ->getMock();

        $migrationMock->expects($this->exactly(2))->method('getDatabase')->willReturn($this->database);
        $this->database->method('query')
            ->withConsecutive(
                ['show tables'],
                ['SET foreign_key_checks = 0;DROP TABLE IF EXISTS f,b CASCADE;SET foreign_key_checks = 1']
            )
            ->willReturn($PDOStatement);
        $PDOStatement->expects($this->once())->method('fetchAll')->willReturn($tables);

        $migrationMock->purgeDatabase();
    }

    public function testInit()
    {
        $version = '001';
        /** @var Migration|MockObject $migrationMock */
        $migrationMock = $this->getMockBuilder(Migration::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMigration', 'getBaseMigrationVersion', 'runMigration'])
            ->getMock();

        $migrationMock->expects($this->exactly(2))->method('getBaseMigrationVersion')->willReturn($version);
        $migrationMock->expects($this->once())->method('getMigration')->with($version)->willReturn($version);
        $migrationMock->expects($this->once())->method('runMigration')->with($version, $version);

        $migrationMock->init();
    }

    public function testRunMigration()
    {
        $migration = 'foo';
        $version = 24;
        $direction = 'o';
        /** @var Migration|MockObject $migrationMock */
        $migrationMock = $this->getMockBuilder(Migration::class)
            ->setConstructorArgs([$this->database])
            ->onlyMethods(['validateSchema', 'getDatabase'])
            ->getMock();

        $migrationMock->expects($this->exactly(2))->method('getDatabase')->willReturn($this->database);
        $this->database
            ->method('query')
            ->withConsecutive(
                [$migration],
                [sprintf(
                    'INSERT INTO migratos_migration_versions VALUES(null,\'%s\',CURRENT_TIMESTAMP(), \'\', \'%s\',%s)',
                    $version,
                    $direction,
                    $version
                )]
            );

        $migrationMock->runMigration($migration, $version, $direction);
    }

    public function testRunMigration2()
    {
        $migration = 'foo';
        $version = 24;
        $direction = 'd';
        $currentVersion = 12;
        /** @var Migration|MockObject $migrationMock */
        $migrationMock = $this->getMockBuilder(Migration::class)
            ->setConstructorArgs([$this->database])
            ->onlyMethods(['validateSchema', 'getDatabase'])
            ->getMock();
        $PDOStatement = $this->createMock(PDOStatement::class);

        $migrationMock->expects($this->exactly(3))->method('getDatabase')->willReturn($this->database);
        $this->database
            ->method('query')
            ->withConsecutive(
                [$migration],
                [sprintf(
                    'SELECT version FROM migratos_migration_versions WHERE direction=\'u\' AND version < %s ORDER BY version desc LIMIT 1',
                    $version
                )],
                [sprintf(
                    'INSERT INTO migratos_migration_versions VALUES(null,\'%s\',CURRENT_TIMESTAMP(), \'\', \'%s\',%s)',
                    $version,
                    $direction,
                    $currentVersion
                )]
            )
            ->willReturn($PDOStatement);
        $PDOStatement->expects($this->once())->method('fetchColumn')->willReturn($currentVersion);

        $migrationMock->runMigration($migration, $version, $direction);
    }

    public function testValidateSchema()
    {
        $PDOStatement = $this->createMock(PDOStatement::class);

        $this->database
            ->expects($this->once())
            ->method('prepare')
            ->with('show tables like ?')
            ->willReturn($PDOStatement);
        $PDOStatement->expects($this->once())->method('execute')->with(['migratos_migration_versions']);
        $PDOStatement->expects($this->once())->method('fetchColumn')->willReturn(true);

        $this->assertTrue($this->service->validateSchema());
    }

    public function testValidateSchemaWhenThrowException()
    {
        $this->expectException(Exception::class);
        $this->expectDeprecationMessage('You have run command with init option in first run.');

        $PDOStatement = $this->createMock(PDOStatement::class);

        $this->database
            ->expects($this->once())
            ->method('prepare')
            ->with('show tables like ?')
            ->willReturn($PDOStatement);
        $PDOStatement->expects($this->once())->method('execute')->with(['migratos_migration_versions']);
        $PDOStatement->expects($this->once())->method('fetchColumn')->willReturn(false);

        $this->service->validateSchema();
    }


    public function testFindRunnedMigrations()
    {
        $currentVersion = 1;
        $PDOStatement = $this->createMock(PDOStatement::class);
        /** @var Migration|MockObject $migrationMock */
        $migrationMock = $this->getMockBuilder(Migration::class)
            ->setConstructorArgs([$this->database])
            ->onlyMethods(['validateSchema', 'getDatabase'])
            ->getMock();

        $migrationMock->expects($this->once())->method('validateSchema');
        $migrationMock->expects($this->once())->method('getDatabase')->willReturn($this->database);
        $this->database
            ->expects($this->once())
            ->method('query')
            ->with('SELECT current_version FROM migratos_migration_versions order by id desc LIMIT 1')
            ->willReturn($PDOStatement);
        $PDOStatement->expects($this->once())->method('fetchColumn')->willReturn($currentVersion);
        $this->database
            ->expects($this->once())
            ->method('prepare')
            ->with(sprintf('SELECT version FROM migratos_migration_versions WHERE version<=%s', $currentVersion))
            ->willReturn($PDOStatement);
        $PDOStatement->expects($this->once())->method('execute');
        $PDOStatement->expects($this->once())->method('fetchAll');

        $migrationMock->findRunnedMigrations();
    }

    public function testFindMigrationsSince()
    {
        $version = 1;
        $PDOStatement = $this->createMock(PDOStatement::class);
        /** @var Migration|MockObject $migrationMock */
        $migrationMock = $this->getMockBuilder(Migration::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDatabase'])
            ->getMock();

        $migrationMock->expects($this->once())->method('getDatabase')->willReturn($this->database);
        $this->database
            ->expects($this->once())
            ->method('query')
            ->with('SELECT DISTINCT version FROM migratos_migration_versions WHERE direction=\'u\' AND version >= 1 ORDER BY version desc ')
            ->willReturn($PDOStatement);
        $PDOStatement->expects($this->once())->method('fetchAll')->with(PDO::FETCH_ASSOC);

        $migrationMock->findMigrationsSince($version);
    }

    public function testGenerateBase()
    {
        $tableName = $this->service::VERSION_TABLE_NAME;
        $sql = <<<SQL
CREATE TABLE $tableName (
    id int(11) NOT NULL AUTO_INCREMENT, 
    version int(11),
    run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    direction ENUM('u','d'),
    current_version varchar(255),
    PRIMARY KEY (id)
)
SQL;

        $this->assertEquals($sql, $this->service->generateBase());

    }

    public function testGenerateUpTemplate()
    {
        $ts = 1621698206;
        $expected = <<<SQL
/*
--Version $ts
--add your up queries to this file
----------------------------------
*/

SQL;

        $this->assertEquals($expected, $this->service->generateUpTemplate($ts));
    }

    public function testGenerateDownTemplate()
    {
        $ts = 1621698206;
        $expected = <<<SQL
/*
--Version $ts
--add your down queries to this file
------------------------------------
*/

SQL;

        $this->assertEquals($expected, $this->service->generateDownTemplate($ts));
    }

    public function testTransactionStart()
    {
        $this->database->expects($this->once())->method('beginTransaction')->willReturn(true);

        $this->assertTrue($this->service->transactionStart());
    }

    public function testTransactionCommit()
    {
        $this->database->expects($this->once())->method('commit')->willReturn(true);

        $this->assertTrue($this->service->transactionCommit());
    }

}
