<?php

namespace App\Search\Product\Infrastructure\Elasticsearch;

use Elastic\Transport\Client\Curl;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ElasticsearchCurlClient extends Curl
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        // The upstream cURL transport parses an interim 100 Continue as the final response.
        // An empty Expect header prevents libcurl from adding Expect: 100-continue for large bodies.
        return parent::sendRequest($request->withHeader('Expect', ''));
    }
}
