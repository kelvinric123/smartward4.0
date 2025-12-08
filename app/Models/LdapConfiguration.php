<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class LdapConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'server_url',
        'ip_address',
        'port',
        'use_ssl',
        'bind_dn',
        'bind_password',
        'base_dn',
        'filter_string',
        'username_attribute',
        'email_attribute',
        'name_attribute',
        'is_active',
        'is_default',
        'last_sync_at',
    ];

    protected $casts = [
        'use_ssl' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'last_sync_at' => 'datetime',
        'port' => 'integer',
    ];

    protected $hidden = [
        'bind_password',
    ];

    /**
     * Encrypt the bind password when setting it.
     */
    public function setBindPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['bind_password'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt the bind password when getting it.
     */
    public function getBindPasswordAttribute($value)
    {
        if (!empty($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return $value;
            }
        }
        return $value;
    }

    /**
     * Get the role mappings for this LDAP configuration.
     */
    public function roleMappings(): HasMany
    {
        return $this->hasMany(LdapRoleMapping::class);
    }

    /**
     * Get the users associated with this LDAP configuration.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the connection string for LDAP.
     */
    public function getConnectionString(): string
    {
        if ($this->ip_address) {
            $protocol = $this->use_ssl ? 'ldaps://' : 'ldap://';
            return $protocol . $this->ip_address . ':' . $this->port;
        }
        return $this->server_url;
    }

    /**
     * Scope for active configurations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default configuration.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}













