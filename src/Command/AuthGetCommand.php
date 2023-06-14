<?php

declare(strict_types=1);

namespace D2L\DataHub\Command;

use D2L\DataHub\Repository\ValenceAPIRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'auth:get',
    description: 'Show the current client ID, client secret, and refresh token'
)]
class AuthGetCommand extends Command
{
    /**
     * @param ValenceAPIRepositoryInterface $apiRepo
     */
    public function __construct(private ValenceAPIRepositoryInterface $apiRepo)
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
        $clientId     = $this->apiRepo->getClientId();
        $clientSecret = $this->apiRepo->getClientSecret();
        $refreshToken = $this->apiRepo->getRefreshToken();
        $accessToken  = $this->apiRepo->getAccessToken();

        $output->writeln("client_id     => \"{$clientId}\"");
        $output->writeln("client_secret => \"{$clientSecret}\"");
        $output->writeln("refresh_token => \"{$refreshToken}\"");
        $output->writeln("access_token  => \"{$accessToken}\"");
        $output->writeln("\nThe following command can be used to (re)set auth values:");
        $output->writeln("./bin/d2l-datahub-cli-tool auth:set \"{$clientId}\" \"{$clientSecret}\" \"{$refreshToken}\"");

        return Command::SUCCESS;
    }
}
