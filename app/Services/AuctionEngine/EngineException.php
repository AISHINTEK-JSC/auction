<?php

namespace App\Services\AuctionEngine;

use RuntimeException;
use Throwable;

/**
 * The engine answered with success=false, or could not be reached.
 */
class EngineException extends RuntimeException
{
    public const NO_BIDS = 'E_102';

    /**
     * @param  array<string, list<string>>  $fieldErrors  the engine's "required" validation map
     */
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly array $fieldErrors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public function is(string $errorCode): bool
    {
        return $this->errorCode === $errorCode;
    }
}
