# Migratos

Migratos is a migration library for legacy projects. 


## Installation

You can install migratos via composer and then you have to register to your console application or you can 
write your own run script. Migratos will waits a PDO connection instance to start to work. There is symfony/console based 
application script included in `src/Command/MigrationCommand.php` file. You can review that file to write your own cli script
until Migratos API document ready.

```bash
composer require 7cups/migratos
```

## Usage

You have register console command. 

```php
<?php

require_once(__DIR__.'/vendor/autoload.php');

use SevenCupsMigratos\Command\MigrationCommand;
use Symfony\Component\Console\Application;

$db = new \PDO('sqlite:./test.db');

$application = new Application('7Cups Console','1.0');

$application->add(new MigrationCommand($db));
$application->run();
```

### Console Commands

There is a predefined symfony console app exists for that library. This is the basic descriptions of commands. Also 
you will see same output when you run `app:migration -h` command.

```
Usage:
  app:migration [options]

Options:
  -u, --up                 Runs newest migrations
  -d, --rollback=ROLLBACK  It will runs down migrations until reach the given timestamp
  -i, --init               First it will run base.sql and then all migrations.
  -b, --base               It will produce a base.sql file which contains migration table schema.
  -c, --new                It will produce draft migration files.
  -h, --help               Display help for the given command. When no command is given display help for the list command
  -q, --quiet              Do not output any message
  -V, --version            Display this application version
      --ansi               Force ANSI output
      --no-ansi            Disable ANSI output
  -n, --no-interaction     Do not ask any interactive question
  -v|vv|vvv, --verbose     Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate.

## License
[MIT](https://choosealicense.com/licenses/mit/)
