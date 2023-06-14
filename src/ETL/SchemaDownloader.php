<?php

declare(strict_types=1);

namespace D2L\DataHub\ETL;

use D2L\DataHub\Model\Dataset;
use D2L\DataHub\Model\DatasetField;
use D2L\DataHub\Repository\ValenceAPIRepositoryInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;

class SchemaDownloader implements SchemaDownloaderInterface
{
    /** @var array<string,string> $pageList */
    private array $pageList;


    /**
     * @param ValenceAPIRepositoryInterface $apiRepo
     * @param ClientInterface $httpClient
     * @param ServerRequestFactoryInterface $requestFactory
     * @param string $schemaURL
     * @param string|array<string,string> $pageList
     */
    public function __construct(
        private ValenceAPIRepositoryInterface $apiRepo,
        private ClientInterface $httpClient,
        private ServerRequestFactoryInterface $requestFactory,
        private string $schemaURL,
        string|array $pageList
    ) {
        if (is_string($pageList)) {
            /** @var array<string,string> $_pageList */
            $_pageList = require $pageList;
            $this->pageList = $_pageList;
        } else {
            $this->pageList = $pageList;
        }
    }


    /**
     * @return Dataset[]
     */
    public function downloadDatasets(): array
    {
        $availableDatasets = $this->apiRepo->listDatasets();

        $allDatasets = [];

        $pageList = $this->getPageList();
        foreach ($pageList as $url) {
            $pageContents = $this->getPageContents($url);
            $datasets = $this->parsePage($pageContents);

            foreach ($datasets as $dataset) {
                $dataset->SchemaId  = $availableDatasets[$dataset->SearchName] ?? '';
                $dataset->SchemaURL = $url
                    . "#Brightspace_Data_Set:_"
                    . str_replace(" ", "_", $dataset->Name);
                $allDatasets[$dataset->Name] = $dataset;
            }
        }

        ksort($allDatasets);

        return $allDatasets;
    }


    /**
     * @return array<string,string>
     */
    private function getPageList(): array
    {
        $pageList = [];
        foreach ($this->pageList as $name => $url) {
            $pageList[$name] = $this->schemaURL . '/' . $url;
        }
        return $pageList;
    }


    /**
     * @param string $url
     * @return string
     */
    private function getPageContents(string $url): string
    {
        $request = $this->requestFactory->createServerRequest('GET', $url);
        $response = $this->httpClient->sendRequest($request);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
            throw new \RuntimeException('Error fetching page contents: ' . $response->getStatusCode());
        }
        return $response->getBody()->getContents();
    }


    /**
     * @param string $pageContents
     * @return Dataset[]
     */
    private function parsePage(string $pageContents): array
    {
        $document = new \DOMDocument();
        @$document->loadHTML($pageContents);

        $mcMainContent = $document->getElementById('mc-main-content');
        if ($mcMainContent === null) {
            throw new \RuntimeException('Unable to find content element in document');
        }

        $datasets = [];
        for ($idx = 0; $idx < $mcMainContent->childNodes->length; $idx++) {
            $item = $mcMainContent->childNodes->item($idx);
            if ($item instanceof \DOMNode && $item->nodeType === XML_ELEMENT_NODE) {
                switch ($item->nodeName) {
                    case 'h2':
                        array_push($datasets, [
                            'name'   => $this->parseName($item),
                            'about'  => '',
                            'fields' => []
                        ]);
                        break;
                    case 'p':
                        $dataset = array_pop($datasets);
                        if (is_array($dataset)) {
                            $dataset['about'] = $this->parseAbout($item, $dataset['about']);
                            array_push($datasets, $dataset);
                        }
                        break;
                    case 'table':
                        $dataset = array_pop($datasets);
                        if (is_array($dataset)) {
                            $dataset['fields'] = $this->parseTable($item);
                            array_push($datasets, $dataset);
                        }
                        break;
                }
            }
        }

        foreach ($datasets as $idx => $values) {
            $datasets[$idx] = new Dataset(
                name: $values['name'] ?? '',
                about: $values['about'] ?? '',
                fields: $values['fields'] ?? []
            );
        }

        /** @var Dataset[] $datasets */
        return $datasets;
    }


    /**
     * @param \DOMNode $item
     * @return string
     */
    private function parseName(\DOMNode $item): string
    {
        $name = strval($item->nodeValue);
        $name = str_replace("Brightspace Data Set:", "", $name);
        $name = trim($name, " \t\n\r\0\x0B\xc2\xa0");
        /** @var string $name */
        $name = preg_replace('/[ ]+/', ' ', $name);
        return $name;
    }


    /**
     * @param \DOMNode $item
     * @param string $about
     * @return string
     */
    private function parseAbout(\DOMNode $item, string $about): string
    {
        $attr = $item->attributes?->item(0);
        if ($attr instanceof \DOMNode && $attr->nodeName === 'class' && $attr->nodeValue === 'bodytext') {
            if ($about !== '') {
                $about .= ' ' . trim(strval($item->nodeValue), " \t\n\r\0\x0B\xc2\xa0");
            } else {
                $about = trim(strval($item->nodeValue), " \t\n\r\0\x0B\xc2\xa0");
            }
        }
        return $about;
    }


    /**
     * @param \DOMNode $item
     * @return DatasetField[]
     */
    private function parseTable(\DOMNode $item): array
    {
        $body = $this->findChildrenByName($item, 'tbody')[0] ?? null;
        $rows = $this->findChildrenByName($body, 'tr');
        $fields = [];
        foreach ($rows as $row) {
            $cols = $this->findChildrenByName($row, 'td');
            $fields[] = new DatasetField(
                name: ($cols[1] ?? null)?->nodeValue ?? '',
                description: ($cols[2] ?? '')?->nodeValue ?? '',
                dataType: ($cols[3] ?? null)?->nodeValue ?? '',
                columnSize: ($cols[4] ?? null)?->nodeValue ?? '',
                key: ($cols[5] ?? null)?->nodeValue ?? '',
                versionHistory: ($cols[0] ?? null)?->nodeValue ?? '',
            );
        }
        return $fields;
    }


    /**
     * @param ?\DOMNode $node
     * @param string $name
     * @return \DOMNode[]
     */
    private function findChildrenByName(?\DOMNode $node, string $name): array
    {
        $children = [];

        for ($idx = 0; $idx < $node?->childNodes->length; $idx++) {
            $item = $node->childNodes->item($idx);
            if ($item instanceof \DOMNode && $item->nodeType === XML_ELEMENT_NODE && $item->nodeName === $name) {
                $children[] = $item;
            }
        }

        return $children;
    }
}
