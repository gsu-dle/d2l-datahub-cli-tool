# gsu-dle/d2l-datahub-cli-tool
PHP command-line tool to download, process, and load D2L Data Hub files.


## Install
Via [git](https://git-scm.com/)
```bash
git clone https://github.com/gsu-dle/d2l-datahub-cli-tool.git
```

Or via [direct download](https://github.com/gsu-dle/d2l-datahub-cli-tool/archive/refs/heads/main.zip)
```bash
curl \
  -L https://github.com/gsu-dle/d2l-datahub-cli-tool/archive/refs/heads/main.zip \
  -o d2l-datahub-cli-tool.zip
```

Requires PHP 8.1 or newer.


## Usage
The application comes with a large set of commands. You can run the tool with no arguments to view all available commands.
```bash
$ cd d2l-datahub-cli-tool
$ ./bin/d2l-datahub-cli-tool
D2L DataHub CLI Tool 1.0

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display help for the given command. When no command is given display help for the list command
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  completion            Dump the shell completion script
  help                  Display help for a command
  list                  List commands
 auth
  auth:get              Show the current client ID, client secret, and refresh token
  auth:set              Set the client ID, client secret, and refresh token
 cache
  cache:clear           Clear application cache
  cache:dump            Dump contents of application cache
 datahub
  datahub:download      Download available datasets from D2L
  datahub:load          Load processed datasets
  datahub:process       Process downloaded datasets
 schema
  schema:fetch          Fetch dataset schema
  schema:gen-table-sql  Generate MySQL table SQL
  schema:load           Create MySQL tables
```

You can see the usage documentation on each command by running it with the `--help` option:
```bash
Description:
  Download available datasets from D2L

Usage:
  datahub:download [options] [--] [<type> [<datasets>...]]

Arguments:
  type                  Type of dataset to download. Valid options are 'Full', 'Differential', and 'All' [default: "All"]
  datasets              Specific dataset(s) to download. If this is not specified, all available datasets are downloaded.

Options:
      --force           Clear cache and download datasets as specified by command arguments
  -h, --help            Display help for the given command. When no command is given display help for the list command
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

Here's an example of how this tool might be used. Note: The first command (`auth:set`) only needs to be run once to initialize the application cache.
```bash
$ ./bin/d2l-datahub-cli-tool auth:set \
  "12345678-1234-1234-1234-123456789ABCD" \
  "0-123456789ABCDEF0123456789ABCDEF0123456789" \
  "rt.us-east-0.1234567890ABCDEF01234567890ABCDEF012345-678"

$ ./bin/d2l-datahub-cli-tool datahub:download \
  "All" \
  "Organizational Units" \
  "Organizational Unit Ancestors" \
  "Organizational Unit Descendants" \
  "Organizational Unit Parents" \
  "Role Details" \
  "Users" \
  "User Enrollments"

$ ./bin/d2l-datahub-cli-tool datahub:process \
  "All" \
  "Organizational Units" \
  "Organizational Unit Ancestors" \
  "Organizational Unit Descendants" \
  "Organizational Unit Parents" \
  "Role Details" \
  "Users" \
  "User Enrollments"

$ ./bin/d2l-datahub-cli-tool datahub:load \
  "All" \
  "Organizational Units" \
  "Organizational Unit Ancestors" \
  "Organizational Unit Descendants" \
  "Organizational Unit Parents" \
  "Role Details" \
  "Users" \
  "User Enrollments"
```


## External Documentation
- [Brightspace Data Sets](https://documentation.brightspace.com/EN/insights/data_hub/admin/bds_title.htm) - D2L Brightspace Administrator Documentation
- [Data Hub and Data Export Framework](https://docs.valence.desire2learn.com/res/dataExport.html) - D2L Developer Platform Documentation
- [MySQL 8.0 Reference Manual](https://dev.mysql.com/doc/refman/8.0/en/)


## Third-party Libraries & Frameworks
- [Monolog](https://github.com/Seldaek/monolog) - Sends logs to files, sockets, inboxes, databases and various web services. Implements the [PSR-3](https://www.php-fig.org/psr/psr-3/) interface that can be type-hinted against in your own libraries to keep a maximum of interoperability.
- [PHP-DI](https://php-di.org/doc/) - Dependency injection container for humans
- [Guzzle](https://docs.guzzlephp.org/en/stable/) - PHP HTTP client that makes it easy to send HTTP requests and trivial to integrate with web services.
- [Symfony framework](https://symfony.com/doc/current/index.html) - Set of reusable PHP components and standard foundation on which the best PHP applications are built.
  - [Cache](https://symfony.com/doc/current/components/cache.html) - Provides features covering simple to advanced caching needs. It natively implements [PSR-6](https://www.php-fig.org/psr/psr-6/) and the [Cache Contracts](https://github.com/symfony/contracts/blob/master/Cache/CacheInterface.php) for greatest interoperability. It is designed for performance and resiliency, ships with ready to use adapters for the most common caching backends. It enables tag-based invalidation and cache stampede protection via locking and early expiration.
  - [Console](https://symfony.com/doc/current/components/console.html) - Eases the creation of beautiful and testable command line interfaces. Allows the creation of command-line commands. Console commands can be used for any recurring task, such as cronjobs, imports, or other batch jobs.
  - [Dotenv](https://github.com/symfony/dotenv) - Parses `.env` files to make environment variables stored in them accessible via `$_SERVER` or `$_ENV`.
  - [Lock](https://symfony.com/doc/current/components/lock.html) - creates and manages locks, a mechanism to provide exclusive access to a shared resource


## Credits
- [Melody Forest](https://github.com/mforest-gsu)
- [Jeb Barger](https://github.com/jebba2)


## License
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.