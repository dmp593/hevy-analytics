<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * A provider call that did not produce usable content.
 *
 * Carries a translation key rather than a provider message, because provider
 * errors are written for developers and frequently quote the request back —
 * which for us would mean putting an athlete's body data into a flash message.
 */
class ProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $translationKey,
        public readonly array $context = [],
        string $logMessage = '',
    ) {
        parent::__construct($logMessage !== '' ? $logMessage : $translationKey);
    }

    public function userMessage(): string
    {
        return __($this->translationKey, $this->context);
    }
}
