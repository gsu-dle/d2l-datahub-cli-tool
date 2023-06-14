<?php

declare(strict_types=1);

namespace D2L\DataHub\Command;

use D2L\DataHub\Repository\ValenceAPIRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'auth:set',
    description: 'Set the client ID, client secret, and refresh token'
)]
class AuthSetCommand extends Command
{
    /**
     * @param ValenceAPIRepositoryInterface $apiRepo
     */
    public function __construct(private ValenceAPIRepositoryInterface $apiRepo)
    {
        parent::__construct();
    }


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'client_id',
                mode: InputArgument::REQUIRED,
                description: 'OAuth 2 Client ID'
            )
            ->addArgument(
                name: 'client_secret',
                mode: InputArgument::REQUIRED,
                description: 'OAuth 2 Client Secret'
            )
            ->addArgument(
                name: 'refresh_token',
                mode: InputArgument::REQUIRED,
                description: 'OAuth 2 Refresh Token'
            );
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
        $clientId = $input->getArgument('client_id');
        $clientSecret = $input->getArgument('client_secret');
        $refreshToken = $input->getArgument('refresh_token');
        if (!is_string($clientId) || !is_string($clientSecret) || !is_string($refreshToken)) {
            throw new \InvalidArgumentException();
        }

        $this->apiRepo->setClientId($clientId);
        $this->apiRepo->setClientSecret($clientSecret);
        $this->apiRepo->setRefreshToken($refreshToken);
        $this->apiRepo->setAccessToken(null);

        $command = $this->getApplication()?->find('auth:get');

        return $command?->run(new ArrayInput([]), $output) ?? Command::SUCCESS;
    }
}
