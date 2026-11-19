<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
/**
 * @use HasFactory<\Database\Factories\UserFactory>
 * @mixin \Spatie\Permission\Traits\HasRoles
 */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'kelas_id',
        'password',
        'active_session_id',
        'active_device_token',
        'active_device_ip',
        'active_device_agent',
        'last_activity_at',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function guru()
    {
        return $this->hasOne(Guru::class, 'user_id');
    }

    public function siswa()
    {
        return $this->hasOne(Siswa::class, 'user_id');
    }

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
            'last_activity_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user is a student.
     */
    public function isStudent(): bool
    {
        return $this->hasRole('murid') || $this->siswa()->exists();
    }

    /**
     * Check if user has an active session from another device.
     */
    public function isDeviceSessionActive(?string $currentSessionId = null): bool
    {
        if (empty($this->active_session_id) || $this->active_session_id === 'RESET') {
            return false;
        }

        if ($currentSessionId !== null && $this->active_session_id === $currentSessionId) {
            return false;
        }

        $lifetimeMinutes = (int) config('session.lifetime', 120);
        if ($this->last_activity_at && $this->last_activity_at->diffInMinutes(now()) >= $lifetimeMinutes) {
            return false;
        }

        return true;
    }

    /**
     * Reset active device session.
     */
    public function resetDeviceSession(): void
    {
        $this->update([
            'active_session_id' => null,
            'active_device_token' => null,
            'active_device_ip' => null,
            'active_device_agent' => null,
            'last_activity_at' => null,
        ]);
    }
}
