<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\ChatResult;
use App\Services\AI\ProviderCredentials;

/**
 * A single non-streaming chat completion.
 *
 * Deliberately narrow. The app asks one question — "read this athlete's data and
 * write an analysis" — so a driver needs a system prompt, a user prompt, and
 * somewhere to send them. Anything wider would have to be implemented for every
 * provider and is not used.
 */
interface ChatDriver
{
    /**
     * @throws \App\Services\AI\ProviderException when the call fails or the
     *                                            response cannot be read.
     */
    public function chat(ProviderCredentials $credentials, string $systemPrompt, string $userPrompt): ChatResult;

    /** The provider key this driver serves, matching ProviderRegistry. */
    public function provider(): string;
}
