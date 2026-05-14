<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isApproved(): bool
    {
        return (bool) $this->is_approved;
    }

    /**
     * Projects owned by this user.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_user_id');
    }

    /**
     * Return an allow-listed audit payload for the given event_type.
     *
     * REQ-AUTH-041: Audit payloads MUST be built from a positive allow-list, not
     * a deny-list. Any field not explicitly enumerated here is excluded by
     * construction — making the redaction policy fail-closed. Future secret fields
     * added to the User model will be silently excluded.
     *
     * Common fields (every event_type): id, name, email, role, is_approved, is_active.
     * Event-specific additions:
     *   user.approved  → + approved_at, approved_by
     *   user.rejected  → common only (user was never approved)
     *   user.deactivated → common only
     *   user.activated   → common only
     *   user.deleted   → + approved_at, approved_by
     *
     * @return array<string, mixed>
     */
    public function auditableAttributes(?string $eventType = null): array
    {
        $common = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_approved' => $this->is_approved,
            'is_active' => $this->is_active,
        ];

        return match ($eventType) {
            'user.approved', 'user.deleted' => array_merge($common, [
                'approved_at' => $this->approved_at?->toISOString(),
                'approved_by' => $this->approved_by,
            ]),
            default => $common,
        };
    }
}
