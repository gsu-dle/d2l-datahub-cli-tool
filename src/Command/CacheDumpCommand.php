<?php

declare(strict_types=1);

namespace D2L\DataHub\Command;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cache:dump',
    description: 'Dump contents of application cache'
)]
class CacheDumpCommand extends Command
{
    /**
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(private CacheItemPoolInterface $cache)
    {
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument('items', InputArgument::IS_ARRAY, 'Specific cache items to dump.');
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
        $items = $input->getArgument('items');
        if (!is_array($items)) {
            $items = [];
        }

        /** @var iterable<CacheItemInterface> $itemList */
        $itemList = $this->cache->getItems($items);
        foreach ($itemList as $item) {
            var_dump($item->get());
        }

        return Command::SUCCESS;
    }
}
