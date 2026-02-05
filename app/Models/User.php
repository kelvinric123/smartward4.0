<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    // Roles
    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_HOSPITAL_ADMIN = 'hospital_admin';
    public const ROLE_NURSE_HEAD = 'nurse_head';
    public const ROLE_WARD_DASHBOARD = 'ward_dashboard';
    public const ROLE_IT_ADMIN = 'it_admin';
    public const ROLE_NURSE = 'nurse';
    public const ROLE_SIEM_AUDITOR = 'siem_auditor';

    public static function getRoles(): array
    {
        return [
            self::ROLE_SUPERADMIN => 'Superadmin',
            self::ROLE_HOSPITAL_ADMIN => 'Hospital Admin',
            self::ROLE_NURSE_HEAD => 'Nurse Head',
            self::ROLE_WARD_DASHBOARD => 'Ward Dashboard Login',
            self::ROLE_IT_ADMIN => 'IT Admin',
            self::ROLE_NURSE => 'Nurse',
        ];
    }


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
        'ldap_guid',
        'ldap_dn',
        'ldap_configuration_id',
        'is_ldap_user',
        'ldap_synced_at',
        'deactivated_at',
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
            'password' => 'hashed',
            'is_ldap_user' => 'boolean',
            'ldap_synced_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Get the LDAP configuration that this user belongs to.
     */
    public function ldapConfiguration(): BelongsTo
    {
        return $this->belongsTo(LdapConfiguration::class);
    }

    /**
     * Check if user is an LDAP user.
     */
    public function isLdapUser(): bool
    {
        return $this->is_ldap_user ?? false;
    }

    /**
     * Check if user has specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Check if user is Superadmin.
     */
    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function nurse()
    {
        return $this->hasOne(Nurse::class);
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return is_null($this->deactivated_at);
    }

    /**
     * Check if user is deactivated.
     */
    public function isDeactivated(): bool
    {
        return !is_null($this->deactivated_at);
    }
}
