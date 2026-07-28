<?php

namespace App\Services\Import;

/**
 * A real CSV whose columns match no known source. Not a failure: it carries
 * what the column-matching screen needs to let the person finish the import
 * themselves.
 */
class UnknownCsvFormat extends ImportException
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $preview
     */
    public function __construct(
        public readonly array $headers,
        public readonly array $preview,
        public readonly string $delimiter,
    ) {
        parent::__construct(__('app.import.errors.unknown_format'));
    }
}
