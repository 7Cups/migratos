<?php
namespace SevenCupsMigratos\Command;

use SevenCupsMigratos\Migration;
use Symfony\Component\Console\Command\Command; 
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationCommand extends Command {
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
            ->addOption('new','c',InputOption::VALUE_NONE,'It will produce draft migration files.')
        ; 
    }

    protected function execute(InputInterface $input, OutputInterface $output): int{
        $up = $input->getOption('up');
        $rollback = $input->getOption('rollback');
        $init = $input->getOption('init');
        $base = $input->getOption('base');
        $new = $input->getOption('new');

        $interactive = $input->isInteractive();

        $io = new SymfonyStyle($input, $output);

        $this->service->transactionStart();

        if($rollback) return $this->runRollbackMigration($rollback,$io,$interactive);

        if($init) return $this->runInitialMigration($io,$interactive);

        if($base) return $this->generateBase($io,$interactive);

        if($new) return $this->createNewMigration($io,$interactive);

        if(!$up) {
            $output->writeln('<error>You have to set rollback or init after you disable up migrations</error>');
            return Command::FAILURE;
        }
        return $this->runUpMigrations($io);
    }

    private function runRollbackMigration($rollbackTimestamp,SymfonyStyle $output,bool $interactive = true):int {
        $migration = $this->service->getMigration($rollbackTimestamp,Migration::DIRECTION_DOWN);
        $output->text($migration);

        if($interactive) {
                $approve = $output->confirm('Do you approve to run printed sql on database?',true);
                if(!$approve) return self::FAILURE;
        }
        $this->service->runMigration($migration,$rollbackTimestamp);
        $output->text("$rollbackTimestamp migrated!");
        $output->newLine();
        return Command::SUCCESS; 
    }

    private function runInitialMigration(SymfonyStyle $output,bool $interactive = true):int {
        $output->warning('THERE IS NO WAY TO RUN INIT COMMAND IN QUITE MODE!');
        $output->warning('BE CAREFUL! IT WILL PURGE DATABASE!');
        $confirm = $output->confirm('This process will purge all database and will run base script then will run all up scripts. Are you sure to continue? It cannot be reverse and will cause data lose!',false);
        if(!$confirm)return self::FAILURE;
        $this->service->purgeDatabase();

        $this->service->init();
        $output->newLine();
        $output->text('Initial migration ran');
        $output->newLine();

        return $this->runUpMigrations($output);
    }

    private function runUpMigrations(SymfonyStyle $output,bool $interactive = true) :int {
        $new_migrations = $this->service->findNewMigrations();
        $up_migrations = $new_migrations['u'];

        if(count($up_migrations)==0){
            $output->info('There is no new migrations to run');
            return self::SUCCESS;
        }
        
        $output->info(count($up_migrations).' new migrations will be run on database.');

        if($interactive) {
            $continue = $output->confirm("Are you sure to continue?");
            if(!$continue) return self::FAILURE;
        }

        foreach($up_migrations as $version) {
            $migration = $this->service->getMigration($version,Migration::DIRECTION_UP);
            $output->text($migration);

            if($interactive){
                $approve = $output->confirm('Do you approve to run printed sql on database?',true);
                if(!$approve) continue;
            }

            $this->service->runMigration($migration,$version);
            $output->text("$version migrated!");
            $output->newLine();
        }

        $this->service->transactionCommit();
        return Command::SUCCESS; 
    }

    private function createNewMigration(SymfonyStyle $output, bool $interactive = true): int{
        $date = new \DateTime();
        $ts = $date->getTimestamp();
        $up_migration_template = $this->service->generateUpTemplate($ts);
        $down_migration_template = $this->service->generateDownTemplate($ts);

        $this->service->writeMigrationFile($ts,$up_migration_template,Migration::DIRECTION_UP);
        $this->service->writeMigrationFile($ts,$down_migration_template,Migration::DIRECTION_DOWN);

        $output->success('Up and Down scripts added to '.$this->service->getMigrationFolder().' folder. You can edit.');
        return self::SUCCESS;
    }

    private function generateBase(SymfonyStyle $output,bool $interactive = true): int {
        $content = $this->service->generateBase();
        $output->title("Base.sql");
        $output->text($content);

        if($interactive){
            $save = $output->confirm("Do you want to save migrations folder?");
            if(!$save) return self::SUCCESS;
        }

        $this->service->writeMigrationFile($this->service->getBaseMigrationVersion(), $content);
        return self::SUCCESS;
    }
}
