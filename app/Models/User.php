<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** The organizations this user is a member of. */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)->withPivot('role')->withTimestamps();
    }

    /**
     * This user's role in the organization, or null when they are not a member.
     * Always read from the database: a removed member loses access on their next request.
     */
    public function roleIn(Organization $organization): ?Role
    {
        $role = $this->organizations()
            ->whereKey($organization->getKey())
            ->value('organization_user.role');

        return $role === null ? null : Role::from($role);
    }
}
