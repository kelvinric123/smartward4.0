<?php

namespace Database\Seeders;

use App\Models\LdapConfiguration;
use App\Models\LdapRoleMapping;
use Illuminate\Database\Seeder;

class LdapConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the PHKL LDAP configuration template
        $phklConfig = LdapConfiguration::create([
            'name' => 'phkl',
            'server_url' => 'ldaps://MYW02PPL003:636',
            'ip_address' => '192.168.17.11',
            'port' => 636,
            'use_ssl' => true,
            'bind_dn' => 'CN=MYW02_SMARTWARDSVC,OU=Service Accounts,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM',
            'bind_password' => 'Nickelbook44^145',
            'base_dn' => 'OU=Users,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM',
            'filter_string' => '(&(objectCategory=Person)(sAMAccountName=*)(memberOf=CN=MY-PHKL-02-smart_ward,OU=Security Groups,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM))',
            'username_attribute' => 'sAMAccountName',
            'email_attribute' => 'mail',
            'name_attribute' => 'displayName',
            'is_active' => true,
            'is_default' => true,
        ]);

        // Create a default role mapping for the smart_ward group
        LdapRoleMapping::create([
            'ldap_configuration_id' => $phklConfig->id,
            'ldap_group_dn' => 'CN=MY-PHKL-02-smart_ward,OU=Security Groups,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM',
            'ldap_group_name' => 'Smart Ward Users',
            'local_role' => 'user',
        ]);
    }
}
























