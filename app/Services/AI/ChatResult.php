<?php

namespace App\Services\AI;

/**
 * One completed chat call, in the shape the app cares about.
 *
 * Providers disagree about almost everything in their response envelopes —
 * OpenAI returns choices[0].message.content with usage.prompt_tokens, Anthropic
 * returns content[0].text with usage.input_tokens — so the differences are
 * absorbed by each driver and never reach the rest of the app.
 */
class ChatResult
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
    ) {}
}
