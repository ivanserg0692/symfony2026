<?php

namespace App\Search\Product\Infrastructure\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Transport\Client\Curl;

final readonly class ElasticsearchClientFactory
{
    public function __construct(
        private string $elasticsearchUrl,
        private string $elasticsearchUsername,
        private string $elasticsearchPassword,
    ) {
    }

    public function create(): Client
    {
        $share = curl_share_init_persistent([
            CURL_LOCK_DATA_CONNECT,
            CURL_LOCK_DATA_DNS,
        ]);

        if ($share === false) {
            throw new \RuntimeException('Unable to initialize the persistent Elasticsearch cURL share handle.');
        }

        $builder = ClientBuilder::create()
            ->setHosts([$this->elasticsearchUrl])
            ->setHttpClient(new Curl())
            ->setHttpClientOptions([CURLOPT_SHARE => $share]);

        if ($this->elasticsearchUsername !== "") {
            $builder->setBasicAuthentication($this->elasticsearchUsername, $this->elasticsearchPassword);
        }

        return $builder->build();
    }
}
