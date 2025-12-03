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
}
