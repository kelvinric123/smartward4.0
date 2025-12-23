<?php

namespace App\Http\Controllers;

use App\Models\LdapConfiguration;
use App\Models\LdapRoleMapping;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LdapConfigurationController extends Controller
{
    /**
     * Show the LDAP login page.
     */
    public function showLdapLogin()
    {
        return view('auth.ldap-login');
    }

    /**
     * Handle LDAP login.
     */
    public function ldapLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)
            ->where('is_ldap_user', true)
            ->first();

        if (!$user) {
            return back()
                ->withInput()
                ->with('error', 'No LDAP user found with this email. Please ensure your account has been synced from Active Directory.');
        }

        \Illuminate\Support\Facades\Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Trigger manual LDAP sync.
     */
    public function manualSync()
    {
        try {
            $response = \Illuminate\Support\Facades\Http::post('http://smartward4-ldap:5000/sync');

            if ($response->successful()) {
                return redirect()->back()->with('success', 'LDAP sync triggered successfully.');
            } else {
                return redirect()->back()->with('error', 'Failed to trigger sync: ' . $response->body());
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Display the LDAP integration page.
     */
    public function index()
    {
        $configurations = LdapConfiguration::with('roleMappings')->latest()->get();
        $availableRoles = LdapRoleMapping::getAvailableRoles();
        $ldapUsers = User::where('is_ldap_user', true)->with('ldapConfiguration')->latest()->get();
        $ldapExtensionLoaded = extension_loaded('ldap');

        return view('integration.ldap.index', compact('configurations', 'availableRoles', 'ldapUsers', 'ldapExtensionLoaded'));
    }

    /**
     * Store a new LDAP configuration.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:ldap_configurations,name',
            'server_url' => 'required|string|max:500',
            'ip_address' => 'nullable|string|max:50',
            'port' => 'required|integer|min:1|max:65535',
            'use_ssl' => 'boolean',
            'bind_dn' => 'required|string',
            'bind_password' => 'required|string',
            'base_dn' => 'required|string',
            'filter_string' => 'nullable|string',
            'username_attribute' => 'required|string|max:100',
            'email_attribute' => 'required|string|max:100',
            'name_attribute' => 'required|string|max:100',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $validated['use_ssl'] = $request->has('use_ssl');
        $validated['is_active'] = $request->has('is_active');
        $validated['is_default'] = $request->has('is_default');

        // If this is set as default, unset other defaults
        if ($validated['is_default']) {
            LdapConfiguration::where('is_default', true)->update(['is_default' => false]);
        }

        LdapConfiguration::create($validated);

        return redirect()->route('ldap.index')->with('success', 'LDAP configuration created successfully.');
    }

    /**
     * Update an existing LDAP configuration.
     */
    public function update(Request $request, LdapConfiguration $ldapConfiguration)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:ldap_configurations,name,' . $ldapConfiguration->id,
            'server_url' => 'required|string|max:500',
            'ip_address' => 'nullable|string|max:50',
            'port' => 'required|integer|min:1|max:65535',
            'use_ssl' => 'boolean',
            'bind_dn' => 'required|string',
            'bind_password' => 'nullable|string',
            'base_dn' => 'required|string',
            'filter_string' => 'nullable|string',
            'username_attribute' => 'required|string|max:100',
            'email_attribute' => 'required|string|max:100',
            'name_attribute' => 'required|string|max:100',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $validated['use_ssl'] = $request->has('use_ssl');
        $validated['is_active'] = $request->has('is_active');
        $validated['is_default'] = $request->has('is_default');

        // Only update password if provided
        if (empty($validated['bind_password'])) {
            unset($validated['bind_password']);
        }

        // If this is set as default, unset other defaults
        if ($validated['is_default']) {
            LdapConfiguration::where('is_default', true)
                ->where('id', '!=', $ldapConfiguration->id)
                ->update(['is_default' => false]);
        }

        $ldapConfiguration->update($validated);

        return redirect()->route('ldap.index')->with('success', 'LDAP configuration updated successfully.');
    }

    /**
     * Delete an LDAP configuration.
     */
    public function destroy(LdapConfiguration $ldapConfiguration)
    {
        $ldapConfiguration->delete();
        return redirect()->route('ldap.index')->with('success', 'LDAP configuration deleted successfully.');
    }

    /**
     * Test LDAP connection.
     */
    public function testConnection(Request $request)
    {
        $configId = $request->input('config_id');

        if ($configId) {
            $config = LdapConfiguration::findOrFail($configId);
        } else {
            // Create temporary config from request data
            $config = new LdapConfiguration($request->all());
            if ($request->filled('bind_password')) {
                $config->bind_password = $request->input('bind_password');
            }
        }

        try {
            $result = $this->connectToLdap($config);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'LDAP connection successful!',
                    'details' => $result['details'] ?? null,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync users from LDAP.
     */
    public function syncUsers(Request $request, LdapConfiguration $ldapConfiguration)
    {
        try {
            $result = $this->connectToLdap($ldapConfiguration);

            if (!$result['success']) {
                return redirect()->route('ldap.index')->with('error', 'Failed to connect to LDAP: ' . $result['message']);
            }

            $ldapConn = $result['connection'];

            // Search for users
            $filter = $ldapConfiguration->filter_string ?: '(objectCategory=Person)';
            $search = @ldap_search($ldapConn, $ldapConfiguration->base_dn, $filter);

            if (!$search) {
                $error = ldap_error($ldapConn);
                ldap_close($ldapConn);
                return redirect()->route('ldap.index')->with('error', 'LDAP search failed: ' . $error);
            }

            $entries = ldap_get_entries($ldapConn, $search);
            $synced = 0;
            $updated = 0;
            $errors = [];

            for ($i = 0; $i < $entries['count']; $i++) {
                $entry = $entries[$i];

                try {
                    $result = $this->syncUserFromEntry($entry, $ldapConfiguration);
                    if ($result === 'created') {
                        $synced++;
                    } elseif ($result === 'updated') {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $errors[] = ($entry[$ldapConfiguration->username_attribute][0] ?? 'Unknown') . ': ' . $e->getMessage();
                }
            }

            ldap_close($ldapConn);

            // Update last sync time
            $ldapConfiguration->update(['last_sync_at' => now()]);

            $message = "Sync completed. Created: {$synced}, Updated: {$updated}";
            if (!empty($errors)) {
                $message .= ". Errors: " . count($errors);
            }

            return redirect()->route('ldap.index')->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->route('ldap.index')->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Store a role mapping.
     */
    public function storeRoleMapping(Request $request, LdapConfiguration $ldapConfiguration)
    {
        $validated = $request->validate([
            'ldap_group_dn' => 'required|string',
            'ldap_group_name' => 'required|string|max:255',
            'local_role' => 'required|string|in:' . implode(',', array_keys(LdapRoleMapping::getAvailableRoles())),
        ]);

        $validated['ldap_configuration_id'] = $ldapConfiguration->id;

        LdapRoleMapping::create($validated);

        return redirect()->route('ldap.index')->with('success', 'Role mapping created successfully.');
    }

    /**
     * Delete a role mapping.
     */
    public function destroyRoleMapping(LdapRoleMapping $roleMapping)
    {
        $roleMapping->delete();
        return redirect()->route('ldap.index')->with('success', 'Role mapping deleted successfully.');
    }

    /**
     * Connect to LDAP server.
     */
    private function connectToLdap(LdapConfiguration $config): array
    {
        // Check if LDAP extension is loaded
        if (!extension_loaded('ldap')) {
            return [
                'success' => false,
                'message' => 'PHP LDAP extension is not installed or enabled.',
            ];
        }

        // Build connection string
        $connectionString = $config->getConnectionString();

        // Connect to LDAP server
        $ldapConn = @ldap_connect($connectionString);

        if (!$ldapConn) {
            return [
                'success' => false,
                'message' => 'Failed to connect to LDAP server.',
            ];
        }

        // Set LDAP options
        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, 10);

        // For LDAPS, we may need to set TLS options
        if ($config->use_ssl) {
            // Skip certificate verification for self-signed certs (common in enterprise)
            // In production, you might want to configure proper CA certificates
            putenv('LDAPTLS_REQCERT=never');
        }

        // Bind to LDAP
        $bind = @ldap_bind($ldapConn, $config->bind_dn, $config->bind_password);

        if (!$bind) {
            $error = ldap_error($ldapConn);
            $errno = ldap_errno($ldapConn);
            ldap_close($ldapConn);
            return [
                'success' => false,
                'message' => "LDAP bind failed: {$error} (Error code: {$errno})",
            ];
        }

        return [
            'success' => true,
            'connection' => $ldapConn,
            'details' => [
                'server' => $connectionString,
                'bind_dn' => $config->bind_dn,
            ],
        ];
    }

    /**
     * Sync a single user from LDAP entry.
     */
    private function syncUserFromEntry(array $entry, LdapConfiguration $config): string
    {
        $usernameAttr = strtolower($config->username_attribute);
        $emailAttr = strtolower($config->email_attribute);
        $nameAttr = strtolower($config->name_attribute);

        $username = $entry[$usernameAttr][0] ?? null;
        $email = $entry[$emailAttr][0] ?? null;
        $name = $entry[$nameAttr][0] ?? $username;
        $dn = $entry['dn'] ?? null;

        // Try to get objectGUID
        $guid = null;
        if (isset($entry['objectguid'][0])) {
            $guid = $this->convertGuidToString($entry['objectguid'][0]);
        }

        if (!$username) {
            throw new \Exception('Username attribute not found');
        }

        // If no email, create one from username
        if (!$email) {
            $email = $username . '@ldap.local';
        }

        // Determine role based on group membership
        $role = $this->determineUserRole($entry, $config);

        // Find existing user by LDAP GUID or email
        $user = null;
        if ($guid) {
            $user = User::where('ldap_guid', $guid)->first();
        }
        if (!$user) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            // Update existing user
            $user->update([
                'name' => $name,
                'ldap_guid' => $guid,
                'ldap_dn' => $dn,
                'ldap_configuration_id' => $config->id,
                'is_ldap_user' => true,
                'role' => $role,
                'ldap_synced_at' => now(),
            ]);
            return 'updated';
        } else {
            // Create new user
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(32)), // Random password for LDAP users
                'ldap_guid' => $guid,
                'ldap_dn' => $dn,
                'ldap_configuration_id' => $config->id,
                'is_ldap_user' => true,
                'role' => $role,
                'ldap_synced_at' => now(),
            ]);
            return 'created';
        }
    }

    /**
     * Determine user role based on LDAP group membership.
     */
    private function determineUserRole(array $entry, LdapConfiguration $config): string
    {
        $memberOf = $entry['memberof'] ?? [];
        $roleMappings = $config->roleMappings;

        // Default role
        $role = 'user';

        // Check each group membership against role mappings
        if (is_array($memberOf)) {
            foreach ($roleMappings as $mapping) {
                for ($i = 0; $i < ($memberOf['count'] ?? 0); $i++) {
                    if (
                        stripos($memberOf[$i], $mapping->ldap_group_dn) !== false ||
                        stripos($mapping->ldap_group_dn, $memberOf[$i]) !== false
                    ) {
                        $role = $mapping->local_role;
                        break 2;
                    }
                }
            }
        }

        return $role;
    }

    /**
     * Convert binary GUID to string format.
     */
    private function convertGuidToString($binaryGuid): string
    {
        $hex = bin2hex($binaryGuid);
        $hex1 = substr($hex, 6, 2) . substr($hex, 4, 2) . substr($hex, 2, 2) . substr($hex, 0, 2);
        $hex2 = substr($hex, 10, 2) . substr($hex, 8, 2);
        $hex3 = substr($hex, 14, 2) . substr($hex, 12, 2);
        $hex4 = substr($hex, 16, 4);
        $hex5 = substr($hex, 20, 12);

        return strtoupper($hex1 . '-' . $hex2 . '-' . $hex3 . '-' . $hex4 . '-' . $hex5);
    }

    /**
     * Fetch LDAP groups for role mapping.
     */
    public function fetchGroups(Request $request, LdapConfiguration $ldapConfiguration)
    {
        try {
            $result = $this->connectToLdap($ldapConfiguration);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ]);
            }

            $ldapConn = $result['connection'];

            // Search for groups
            $filter = '(objectClass=group)';
            $search = @ldap_search($ldapConn, $ldapConfiguration->base_dn, $filter, ['cn', 'distinguishedName']);

            if (!$search) {
                ldap_close($ldapConn);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to search for groups: ' . ldap_error($ldapConn),
                ]);
            }

            $entries = ldap_get_entries($ldapConn, $search);
            $groups = [];

            for ($i = 0; $i < $entries['count']; $i++) {
                $groups[] = [
                    'cn' => $entries[$i]['cn'][0] ?? 'Unknown',
                    'dn' => $entries[$i]['distinguishedname'][0] ?? $entries[$i]['dn'],
                ];
            }

            ldap_close($ldapConn);

            return response()->json([
                'success' => true,
                'groups' => $groups,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}



