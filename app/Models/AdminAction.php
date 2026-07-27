<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing an admin did to someone else's account.
 *
 * Append-only by convention: there is no update or delete path in the app. An
 * audit log an admin can edit is not an audit log.
 */
class AdminAction extends Model
{
    protected $fillable = ['admin_id', 'target_user_id', 'action', 'detail', 'ip'];

    public const GRANTED_ACCESS = 'granted_access';

    public const REVOKED_ACCESS = 'revoked_access';

    public const CANCELLED_SUBSCRIPTION = 'cancelled_subscription';

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public static function record(User $admin, User $target, string $action, ?string $detail = null): self
    {
        return self::create([
            'admin_id' => $admin->id,
            'target_user_id' => $target->id,
            'action' => $action,
            'detail' => $detail,
            'ip' => request()->ip(),
        ]);
    }

    public function describe(): string
    {
        return __('app.admin.actions.'.$this->action);
    }
}
