<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ldap_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Template name like "phkl"
            $table->string('server_url'); // e.g., ldaps://MYW02PPL003:636
            $table->string('ip_address')->nullable(); // IP Address
            $table->integer('port')->default(636); // LDAP port
            $table->boolean('use_ssl')->default(true); // Use LDAPS
            $table->text('bind_dn'); // LDAP Username/DN
            $table->text('bind_password'); // Encrypted password
            $table->text('base_dn'); // Base String Search
            $table->text('filter_string')->nullable(); // LDAP filter string
            $table->string('username_attribute')->default('sAMAccountName'); // Attribute for username
            $table->string('email_attribute')->default('mail'); // Attribute for email
            $table->string('name_attribute')->default('displayName'); // Attribute for display name
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ldap_role_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ldap_configuration_id')->constrained()->onDelete('cascade');
            $table->text('ldap_group_dn'); // LDAP group DN
            $table->string('ldap_group_name'); // Friendly name for the group
            $table->string('local_role'); // Local system role
            $table->timestamps();
        });

        // Add LDAP-related fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('ldap_guid')->nullable()->unique()->after('id');
            $table->string('ldap_dn')->nullable()->after('ldap_guid');
            $table->foreignId('ldap_configuration_id')->nullable()->constrained()->nullOnDelete()->after('ldap_dn');
            $table->boolean('is_ldap_user')->default(false)->after('role');
            $table->timestamp('ldap_synced_at')->nullable()->after('is_ldap_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['ldap_configuration_id']);
            $table->dropColumn(['ldap_guid', 'ldap_dn', 'ldap_configuration_id', 'is_ldap_user', 'ldap_synced_at']);
        });

        Schema::dropIfExists('ldap_role_mappings');
        Schema::dropIfExists('ldap_configurations');
    }
};


































