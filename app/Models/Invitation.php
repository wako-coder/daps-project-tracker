<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    // organization_id is deliberately not fillable: an invitation's tenant is
    // set only by creating it through $organization->invitations().
    protected $fillable = ['email', 'role', 'token_hash', 'invited_by', 'expires_at'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** The unexpired invitation this link token belongs to, or null. */
    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', static::hashToken($token))
            ->where('expires_at', '>', now())
            ->first();
    }
}
