<?php

namespace App\Models;

use App\Services\AI\ProviderRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An athlete's own API key for one AI provider.
 *
 * The key is cast `encrypted`, so it is never at rest in plaintext and never
 * appears in a query log. It is also hidden from serialisation: this model ends
 * up inside the GDPR export, and an export is a file people email to themselves.
 */
class AiCredential extends Model
{
    protected $fillable = [
        'provider', 'api_key', 'model', 'base_url', 'is_active',
        'last_verified_at', 'last_error',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
            'last_verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function providerLabel(): string
    {
        return ProviderRegistry::has($this->provider)
            ? ProviderRegistry::get($this->provider)['label']
            : $this->provider;
    }

    /**
     * Enough of the key to recognise it, never enough to use it. Shown so an
     * athlete can tell which key is stored without the app ever displaying one.
     */
    public function maskedKey(): string
    {
        $key = (string) $this->api_key;

        return strlen($key) <= 8
            ? str_repeat('•', 8)
            : substr($key, 0, 4).str_repeat('•', 8).substr($key, -4);
    }
}
