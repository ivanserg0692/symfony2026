<?php

namespace App\Search\Product\Infrastructure\Elasticsearch\Bulk;

final readonly class BulkItemResponse
{
    private function __construct(
        public int $status,
        public bool $successful,
        public ?string $error,
    ) {
    }

    public static function fromArray(mixed $item): self
    {
        $operation = is_array($item) ? reset($item) : null;

        if (!is_array($operation)) {
            return new self(
                status: 0,
                successful: false,
                error: "Missing or malformed Bulk API item response.",
            );
        }

        $status = (int) ($operation["status"] ?? 0);
        if ($status >= 200 && $status < 300 && !isset($operation["error"])) {
            return new self(
                status: $status,
                successful: true,
                error: null,
            );
        }

        $error = $operation["error"] ?? ["reason" => "Bulk operation returned HTTP status {$status}."];
        $encodedError = json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new self(
            status: $status,
            successful: false,
            error: $encodedError === false ? "Unable to encode Elasticsearch error." : $encodedError,
        );
    }
}
