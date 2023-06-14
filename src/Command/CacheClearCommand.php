<?php

declare(strict_types=1);

namespace D2L\DataHub\Command;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cache:clear',
    description: 'Clear application cache'
)]
class CacheClearCommand extends Command
{
    /**
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(private CacheItemPoolInterface $cache)
    {
        parent::__construct();
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $this->cache->clear();

        return Command::SUCCESS;
    }
}
