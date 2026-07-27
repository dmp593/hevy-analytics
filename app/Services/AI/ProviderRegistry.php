<?php

namespace App\Services\AI;

/**
 * The providers an athlete can choose, and what each one needs.
 *
 * Kept as data rather than as a set of classes because the differences that
 * matter to a settings form — what the key looks like, whether the base URL is
 * fixed, which models are worth suggesting — are values, not behaviour. The
 * behavioural differences live in the drivers.
 *
 * `models` are suggestions shown in the UI, not a whitelist: providers ship new
 * models constantly and an athlete who wants a newer one should not have to wait
 * for a release here, so the field accepts free text with these as a datalist.
 */
class ProviderRegistry
{
    public const OPENAI = 'openai';

    public const ANTHROPIC = 'anthropic';

    public const DEEPSEEK = 'deepseek';

    public const COMPATIBLE = 'compatible';

    /**
     * @return array<string, array{
     *     label: string, driver: class-string, base_url: string,
     *     base_url_editable: bool, models: array<int, string>,
     *     key_hint: string, docs: string
     * }>
     */
    public static function all(): array
    {
        return [
            self::OPENAI => [
                'label' => 'OpenAI',
                'driver' => Drivers\OpenAiDriver::class,
                'base_url' => 'https://api.openai.com/v1',
                'base_url_editable' => false,
                'models' => ['gpt-4o', 'gpt-4o-mini', 'o4-mini'],
                'key_hint' => 'sk-…',
                'docs' => 'https://platform.openai.com/api-keys',
            ],

            self::ANTHROPIC => [
                'label' => 'Anthropic',
                'driver' => Drivers\AnthropicDriver::class,
                'base_url' => 'https://api.anthropic.com/v1',
                'base_url_editable' => false,
                'models' => ['claude-sonnet-4-5', 'claude-opus-4-1', 'claude-haiku-4-5'],
                'key_hint' => 'sk-ant-…',
                'docs' => 'https://console.anthropic.com/settings/keys',
            ],

            self::DEEPSEEK => [
                'label' => 'DeepSeek',
                'driver' => Drivers\OpenAiDriver::class,
                'base_url' => 'https://api.deepseek.com/v1',
                'base_url_editable' => false,
                'models' => ['deepseek-chat', 'deepseek-reasoner'],
                'key_hint' => 'sk-…',
                'docs' => 'https://platform.deepseek.com/api_keys',
            ],

            // Anything speaking the OpenAI chat-completions schema: Groq,
            // Together, OpenRouter, vLLM, Ollama, LM Studio. The base URL is the
            // athlete's to set, which is exactly why UrlGuard exists.
            self::COMPATIBLE => [
                'label' => 'Other (OpenAI-compatible)',
                'driver' => Drivers\OpenAiDriver::class,
                'base_url' => '',
                'base_url_editable' => true,
                'models' => [],
                'key_hint' => '',
                'docs' => '',
            ],
        ];
    }

    public static function has(string $provider): bool
    {
        return array_key_exists($provider, self::all());
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /** @return array{label: string, driver: class-string, base_url: string, base_url_editable: bool, models: array<int, string>, key_hint: string, docs: string} */
    public static function get(string $provider): array
    {
        return self::all()[$provider]
            ?? throw new ProviderException('app.ai.errors.unknown_provider', ['provider' => $provider]);
    }

    public static function driver(string $provider): Contracts\ChatDriver
    {
        return app(self::get($provider)['driver']);
    }

    /** The default endpoint, or null when the athlete must supply one. */
    public static function defaultBaseUrl(string $provider): ?string
    {
        $url = self::get($provider)['base_url'];

        return $url === '' ? null : $url;
    }
}
