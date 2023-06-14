<?php

declare(strict_types=1);

namespace D2L\DataHub\Repository;

use D2L\DataHub\Model\Dataset                      as Dataset;
use D2L\DataHub\Model\DatasetExtract               as DatasetExtract;
use Psr\Cache\CacheItemPoolInterface               as CacheItemPool;
use Psr\Http\Client\ClientInterface                as HttpClient;
use Psr\Http\Message\ServerRequestFactoryInterface as ServerRequestFactory;
use RuntimeException                               as RuntimeException;

class ValenceAPIRepository implements ValenceAPIRepositoryInterface
{
    /**
     * @param CacheItemPool $cache
     * @param HttpClient $httpClient
     * @param ServerRequestFactory $requestFactory
     * @param string $apiURL
     * @param string $authURL
     */
    public function __construct(
        private CacheItemPool $cache,
        private HttpClient $httpClient,
        private ServerRequestFactory $requestFactory,
        private string $apiURL,
        private string $authURL
    ) {
    }


    /**
     * @return ?string
     */
    public function getClientId(): ?string
    {
        $clientId = $this->cache->getItem('client_id')->get();
        return is_string($clientId) ? $clientId : '';
    }


    /**
     * @return ?string
     */
    public function getClientSecret(): ?string
    {
        $clientSecret = $this->cache->getItem('client_secret')->get();
        return is_string($clientSecret) ? $clientSecret : '';
    }


    /**
     * @return ?string
     */
    public function getRefreshToken(): ?string
    {
        $refreshToken = $this->cache->getItem('refresh_token')->get();
        return is_string($refreshToken) ? $refreshToken : '';
    }


    /**
     * @return ?string
     */
    public function getAccessToken(): ?string
    {
        $accessToken = $this->cache->getItem('access_token')->get();
        if (is_string($accessToken)) {
            return $accessToken;
        }

        $clientId     = $this->getClientId();
        $clientSecret = $this->getClientSecret();
        $refreshToken = $this->getRefreshToken();

        $request = $this->requestFactory
            ->createServerRequest('POST', $this->authURL)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', '*/*');

        $request->getBody()->write(
            http_build_query([
                'grant_type'    => 'refresh_token',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken
            ], "", null, PHP_QUERY_RFC3986)
        );

        $response = $this->httpClient->sendRequest($request);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
            throw new RuntimeException(
                "Code: " . $response->getStatusCode() . "\n" .
                "Response: " . $response->getBody()->getContents()
            );
        }

        $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException(); // TODO: add message
        }

        $accessToken  = $data['access_token'] ?? null;
        $expiresIn    = intval($data['expires_in'] ?? null);
        $refreshToken = $data['refresh_token'] ?? null;

        $this->setRefreshToken($refreshToken);
        $this->setAccessToken($accessToken, $expiresIn);

        return $accessToken;
    }


    /**
     * @return array<string,string>
     */
    public function listDatasets(): array
    {
        $schemaIDList = [];

        $data = $this->sendGetRequest('/datasets/bds');

        /** @var array<string,string|array<string,string>>[] $objects */
        $objects = $data['Objects'];
        foreach ($objects as $object) {
            $schemaId = $object['SchemaId'] ?? null;
            if (is_string($schemaId)) {
                $name = $object['Full']['Name'] ?? '';
                if ($name === 'Outcome Registry Owners') {
                    $name = 'Outcomes Registry Owners';
                }
                $schemaIDList[str_replace(" ", "", strtoupper($name))] = $schemaId;
            }
        }
        ksort($schemaIDList);

        return $schemaIDList;
    }


    /**
     * @param Dataset $dataset
     * @return DatasetExtract[]
     */
    public function listDatasetExtracts(Dataset $dataset): array
    {
        /** @var DatasetExtract[] $extracts */
        $extracts = [];

        $data = $this->sendGetRequest("/datasets/bds/{$dataset->SchemaId}/extracts");

        /** @var array<string,int|string>[] $objects */
        $objects = $data['Objects'];
        foreach ($objects as $values) {
            $createdDate = strval($values['CreatedDate'] ?? '');
            $extracts[$createdDate] = new DatasetExtract(
                dataset: $dataset,
                schemaId: strval($values['SchemaId'] ?? ''),
                pluginId: strval($values['PluginId'] ?? ''),
                bdsType: strval($values['BdsType'] ?? ''),
                createdDate: $createdDate,
                downloadLink: strval($values['DownloadLink'] ?? ''),
                downloadSize: intval($values['DownloadSize'] ?? ''),
                queuedForProcessingDate: strval($values['QueuedForProcessingDate'] ?? ''),
                version: strval($values['Version'] ?? ''),
            );
        }

        return $extracts;
    }


    /**
     * @param DatasetExtract $datasetExtract
     * @param string $outputLocation
     * @return void
     */
    public function downloadDatasetExtract(
        DatasetExtract $datasetExtract,
        string $outputLocation,
    ): void {
        $this->sendGetRequest(
            $datasetExtract->DownloadLink,
            $outputLocation
        );
    }


    /**
     * @param string|null $clientId
     * @return bool
     */
    public function setClientId(?string $clientId): bool
    {
        if ($clientId === null) {
            return $this->cache->deleteItem('client_id');
        } else {
            return $this->cache->save(
                $this->cache
                    ->getItem('client_id')
                    ->set($clientId)
            );
        }
    }


    /**
     * @param string|null $clientSecret
     * @return bool
     */
    public function setClientSecret(?string $clientSecret): bool
    {
        if ($clientSecret === null) {
            return $this->cache->deleteItem('client_secret');
        } else {
            return $this->cache->save(
                $this->cache
                    ->getItem('client_secret')
                    ->set($clientSecret)
            );
        }
    }


    /**
     * @param string|null $refreshToken
     * @return bool
     */
    public function setRefreshToken(?string $refreshToken): bool
    {
        if ($refreshToken === null) {
            return $this->cache->deleteItem('refresh_token');
        } else {
            return $this->cache->save(
                $this->cache
                    ->getItem('refresh_token')
                    ->set($refreshToken)
            );
        }
    }


    /**
     * @param string|null $accessToken
     * @param int $expiresAfter
     * @return bool
     */
    public function setAccessToken(
        ?string $accessToken,
        int $expiresAfter = 0
    ): bool {
        if ($accessToken === null) {
            return $this->cache->deleteItem('access_token');
        } else {
            return $this->cache->save(
                $this->cache
                    ->getItem('access_token')
                    ->set($accessToken)
                    ->expiresAfter($expiresAfter)
            );
        }
    }


    /**
     * @param string $uri
     * @param ?string $outputLocation
     * @return array<int|string,mixed>
     */
    private function sendGetRequest(
        string $uri,
        ?string $outputLocation = null
    ): array {
        if (!str_starts_with($uri, 'http')) {
            $uri = $this->apiURL . $uri;
        }

        $request = $this->requestFactory
            ->createServerRequest('GET', $uri)
            ->withHeader('Authorization', 'Bearer ' . $this->getAccessToken());

        $response = $this->httpClient->sendRequest($request);
        for ($i = 0; $i < 5; $i++) {
            if ($response->getStatusCode() !== 301 && $response->getStatusCode() !== 302) {
                break;
            }

            $location = $response->getHeader('Location')[0] ?? null;
            if ($location === null) {
                throw new RuntimeException();
            }

            $request = $this->requestFactory->createServerRequest('GET', $location);
            $response = $this->httpClient->sendRequest($request);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
            throw new RuntimeException(
                "Code: " . $response->getStatusCode() . "\n" .
                "Response: " . $response->getBody()->getContents()
            );
        }

        if ($outputLocation !== null) {
            $blockSize = 10485760;

            try {
                $fp = fopen($outputLocation, 'w');
                if (!is_resource($fp)) {
                    throw new RuntimeException();
                }

                $body = $response->getBody();
                while (!$body->eof()) {
                    $bytesWritten = fwrite($fp, $body->read($blockSize));
                    if ($bytesWritten === false) {
                        throw new RuntimeException();
                    }
                }

                $data = ['output' => $outputLocation];
            } finally {
                if (is_resource($fp ?? null)) {
                    fclose($fp);
                }
            }
        } else {
            $contents = '';
            $body = $response->getBody();
            while (!$body->eof()) {
                $contents .= $body->read(10485760);
            }

            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new RuntimeException();
            }
        }

        return $data;
    }
}
