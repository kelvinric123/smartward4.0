from ldap3 import Server, Connection, ALL, SUBTREE, Tls
import ssl
import json

# --- CONFIGURATION ---
# Use the hostname for SSL to match the certificate usually
# LDAP_SERVER_HOST = 'MYW02PPL003'
LDAP_SERVER_HOST = '192.168.17.11' 
LDAP_PORT = 636

# The full Distinguished Name (DN) acts as the username for binding
LDAP_USER_DN = "CN=MYW02_SMARTWARDSVC,OU=Service Accounts,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM"
LDAP_PASSWORD = "P@55wordP@55word" 

# Where to start looking (The "Table")
SEARCH_BASE = "OU=Users,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM"

# The criteria (The "WHERE" clause)
SEARCH_FILTER = "(&(objectCategory=Person)(sAMAccountName=*)(memberOf=CN=MY-PHKL-02-smart_ward,OU=Security Groups,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM))"

def fetch_ldap_data():
    try:
        # 1. Define the Server
        # use_ssl=True is mandatory because port is 636
        # Define TLS configuration to ignore certificate errors
        print("Configuring TLS...")
        tls_configuration = Tls(validate=ssl.CERT_NONE, version=ssl.PROTOCOL_TLSv1_2)
        
        print(f"Creating Server object for {LDAP_SERVER_HOST}...")
        # Removed get_info=ALL to prevent hanging on schema fetch
        server = Server(LDAP_SERVER_HOST, port=LDAP_PORT, use_ssl=True, tls=tls_configuration)
        
        # 2. Define the Connection
        # auto_bind=True attempts to connect immediately
        print("Attempting to connect and bind...")
        conn = Connection(server, user=LDAP_USER_DN, password=LDAP_PASSWORD, auto_bind=True)
        
        print(f"Successfully connected to {LDAP_SERVER_HOST}")

        # 3. Execute the Search (The "SELECT" query)
        # attributes=['*'] fetches all standard user attributes
        print("Executing search...")
        conn.search(
            search_base=SEARCH_BASE,
            search_filter=SEARCH_FILTER,
            search_scope=SUBTREE, 
            attributes=['*'] 
        )

        # 4. Process Results
        print(f"Found {len(conn.entries)} entries.")
        
        for entry in conn.entries:
            # entry.entry_to_json() converts the LDAP object to a JSON string
            # entry.entry_attributes_as_dict returns a Python dictionary
            user_data = entry.entry_attributes_as_dict
            
            # Example: Print specific fields (handle if they are missing)
            name = user_data.get('cn', ['Unknown'])[0]
            account = user_data.get('sAMAccountName', ['Unknown'])[0]
            print(f"User: {name} | Account: {account}")

    except Exception as e:
        print(f"An error occurred: {e}")

    finally:
        # Always close the connection
        if 'conn' in locals() and conn.bound:
            conn.unbind()

if __name__ == "__main__":
    import time
    import sys

    print("Starting LDAP Sync Service...", flush=True)
    
    while True:
        print("\n--- Starting Sync Cycle ---", flush=True)
        fetch_ldap_data()
        print("--- Cycle Finished ---\n", flush=True)
        
        # Sleep for 60 seconds (or configurable interval)
        time.sleep(60)
