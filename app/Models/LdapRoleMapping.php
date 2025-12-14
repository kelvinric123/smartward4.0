<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LdapRoleMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'ldap_configuration_id',
        'ldap_group_dn',
        'ldap_group_name',
        'local_role',
    ];

    /**
     * Get the LDAP configuration that owns the role mapping.
     */
    public function ldapConfiguration(): BelongsTo
    {
        return $this->belongsTo(LdapConfiguration::class);
    }

    /**
     * Get all available local roles for mapping.
     */
    public static function getAvailableRoles(): array
    {
        return [
            'admin' => 'Administrator',
            'doctor' => 'Doctor',
            'nurse' => 'Nurse',
            'staff' => 'Staff',
            'user' => 'User',
        ];
    }
}



























