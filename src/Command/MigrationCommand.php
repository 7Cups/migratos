<?php
namespace SevenCupsMigratos\Command;

use SevenCupsMigratos\Migration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationCommand extends Command{
    /** @var Migration */
    private $service;

    public function __construct(\PDO $connection)
    {
        $this->service = new Migration($connection);
        parent::__construct(); 
    }

    protected function configure(): void{
        $this
            ->setName('app:migration')
            ->addOption('up','u',InputOption::VALUE_NONE,'Runs newest migrations')
            ->addOption('rollback','d',InputOption::VALUE_REQUIRED,'It will runs down migrations until reach the given timestamp')
            ->addOption('init','i',InputOption::VALUE_NONE,'First it will run base.sql and then all migrations.')
        ->addOption('base','b',InputOption::VALUE_NONE,'It will produce a base.sql file which contains migration table schema.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int{
        $up = $input->getOption('up');
        $rollback = $input->getOption('rollback');
        $init = $input->getOption('init');
        $base = $input->getOption('base');

        $io = new SymfonyStyle($input, $output);

        $this->service->transactionStart();

        if($rollback) return $this->runRollbackMigration($rollback,$io);

        if($init) return $this->runInitialMigration($io);

        if($base) return $this->generateBase($io);

        if(!$up) {
            $output->writeln('<error>You have to set rollback or init after you disable up migrations</error>');
            return Command::FAILURE;
        }
        return $this->runUpMigrations($io);
    }

    private function runRollbackMigration($rollbackTimestamp,SymfonyStyle $output):int {
        return Command::SUCCESS; 
    }

    private function runInitialMigration(SymfonyStyle $output):int {
        $this->service->init();
        $output->newLine();
        $output->text('Initial migration ran');
        $output->newLine();

        return $this->runUpMigrations($output);
    }

    private function runUpMigrations(SymfonyStyle $output) :int {
        $new_migrations = $this->service->findNewMigrations();
        if(count($new_migrations)==0){
            $output->info('There is no new migrations to run');
            return self::SUCCESS;
        }

        $output->info('<bold>'.count($new_migrations).'</bold> new migrations will be run on database.');

        $continue = $output->confirm("Are you sure to continue?");
        if(!$continue) return self::FAILURE;
        
        foreach($new_migrations as $version) {
            $migration = $this->service->getMigration($version);
            $output->text($migration);
            $approve = $output->confirm('Do you approve to run printed sql on database?',false);
            if(!$approve) continue;

            $this->service->runMigration($migration);
            $output->text("$version migrated!");
            $output->newLine();
        }

        $this->service->transactionCommit();
        return Command::SUCCESS; 
    }

    private function generateBase(SymfonyStyle $output): int {
        $content = $this->service->generateBase();
        $output->title("Base.sql");
        $output->text($content);
        $save = $output->confirm("Do you want to save migrations folder?");
        if(!$save) return self::SUCCESS;

        $this->service->writeBaseFile($content);
        return self::SUCCESS;
    }
}
