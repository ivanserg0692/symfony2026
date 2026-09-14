<?php

namespace App\Search\Product\Infrastructure\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

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
        $builder = ClientBuilder::create()->setHosts([$this->elasticsearchUrl]);

        if ($this->elasticsearchUsername !== "") {
            $builder->setBasicAuthentication($this->elasticsearchUsername, $this->elasticsearchPassword);
        }

        return $builder->build();
    }
}
