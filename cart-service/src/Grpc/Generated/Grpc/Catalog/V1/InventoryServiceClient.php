<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Grpc\Catalog\V1;

/**
 */
class InventoryServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Grpc\Catalog\V1\CheckStockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Grpc\Catalog\V1\CheckStockResponse>
     */
    public function CheckStock(\Grpc\Catalog\V1\CheckStockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/catalog.v1.InventoryService/CheckStock',
        $argument,
        ['\Grpc\Catalog\V1\CheckStockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Grpc\Catalog\V1\DeductStocksRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Grpc\Catalog\V1\DeductStocksResponse>
     */
    public function DeductStocks(\Grpc\Catalog\V1\DeductStocksRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/catalog.v1.InventoryService/DeductStocks',
        $argument,
        ['\Grpc\Catalog\V1\DeductStocksResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Grpc\Catalog\V1\GetProductPricesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Grpc\Catalog\V1\GetProductPricesResponse>
     */
    public function GetProductPrices(\Grpc\Catalog\V1\GetProductPricesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/catalog.v1.InventoryService/GetProductPrices',
        $argument,
        ['\Grpc\Catalog\V1\GetProductPricesResponse', 'decode'],
        $metadata, $options);
    }

}
