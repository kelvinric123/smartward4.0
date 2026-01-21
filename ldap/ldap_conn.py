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
        # 3. Execute the Search (The "SELECT" query) with PAGINATION
        # attributes=['*'] fetches all standard user attributes
        print("Executing paged search...")
        
        # paged_size=1000 is standard, but you can adjust. 
        # generator=True yields entries one by one as they are fetched.
        entry_generator = conn.extend.standard.paged_search(
            search_base=SEARCH_BASE,
            search_filter=SEARCH_FILTER,
            search_scope=SUBTREE, 
            attributes=['*'],
            paged_size=1000,
            generator=True
        )

        # 4. Process Results
        entry_count = 0
        
        for entry in entry_generator:
            # When using generator=True, entry is a dictionary with keys like 'dn', 'attributes', 'type'
            if 'attributes' not in entry:
                continue
                
            entry_count += 1
            user_data = entry['attributes']
            
            # Example: Print specific fields (handle if they are missing)
            name = user_data.get('cn', ['Unknown'])[0]
            account = user_data.get('sAMAccountName', ['Unknown'])[0]
            email = user_data.get('mail', [f'{account}@ldap.local'])[0]
            print(f"User: {name} | Account: {account}")

            # Database Update Logic
            try:
                # Basic connection - in production use env vars
                import mysql.connector
                import os
                
                db = mysql.connector.connect(
                    host=os.environ.get('DB_HOST', 'smartward-db'), # Using service name if in same network
                    port=int(os.environ.get('DB_PORT', 3306)),
                    user="root", # Ideally use a specific user
                    password=os.environ.get('DB_PASSWORD', 'smartward_secret'), 
                    database=os.environ.get('DB_DATABASE', 'smartward')
                )
                cursor = db.cursor()
                
                # Check if user exists
                cursor.execute("SELECT id FROM users WHERE email = %s OR name = %s", (email, account))
                result = cursor.fetchone()
                
                if result:
                    # Update
                    print(f"Updating user: {account}")
                    sql = """
                        UPDATE users 
                        SET name = %s, is_ldap_user = 1, ldap_synced_at = NOW()
                        WHERE id = %s
                    """
                    cursor.execute(sql, (name, result[0]))
                else:
                    # Insert
                    print(f"Creating user: {account}")
                    # Default role: 'user'
                    sql = """
                        INSERT INTO users (name, email, password, role, is_ldap_user, ldap_synced_at, created_at, updated_at)
                        VALUES (%s, %s, %s, %s, 1, NOW(), NOW(), NOW())
                    """
                    # Use a dummy password for LDAP users as they auth via LDAP
                    dummy_pass = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' # "password"
                    cursor.execute(sql, (name, email, dummy_pass, 'user'))
                
                db.commit()
                cursor.close()
                db.close()
                
            except Exception as db_err:
                print(f"Database error for {account}: {db_err}")

    except Exception as e:
        print(f"An error occurred: {e}")

    finally:    
        # Always close the connection
        if 'conn' in locals() and conn.bound:
            conn.unbind()


def authenticate_ldap_user(username, password):
    """
    Authenticate a user against LDAP by attempting to bind with their credentials.
    
    Args:
        username: The username (sAMAccountName) to authenticate
        password: The user's password
        
    Returns:
        dict: {'success': bool, 'message': str, 'user_data': dict (optional)}
    """
    try:
        # 1. Configure TLS
        print(f"Attempting to authenticate user: {username}")
        tls_configuration = Tls(validate=ssl.CERT_NONE, version=ssl.PROTOCOL_TLSv1_2)
        
        # 2. Create server connection
        server = Server(LDAP_SERVER_HOST, port=LDAP_PORT, use_ssl=True, tls=tls_configuration)
        
        # 3. First, bind with service account to search for the user's DN
        print("Binding with service account to search for user...")
        conn = Connection(server, user=LDAP_USER_DN, password=LDAP_PASSWORD, auto_bind=True)
        
        # 4. Search for the user to get their full DN
        search_filter = f"(&(objectCategory=Person)(sAMAccountName={username}))"
        conn.search(
            search_base=SEARCH_BASE,
            search_filter=search_filter,
            search_scope=SUBTREE,
            attributes=['cn', 'mail', 'sAMAccountName', 'distinguishedName']
        )
        
        if not conn.entries:
            print(f"User {username} not found in LDAP")
            conn.unbind()
            return {
                'success': False,
                'message': 'User not found in Active Directory'
            }
        
        # Get the user's DN and other attributes
        user_entry = conn.entries[0]
        user_dn = user_entry.distinguishedName.value if hasattr(user_entry.distinguishedName, 'value') else str(user_entry.distinguishedName)
        user_data = user_entry.entry_attributes_as_dict
        
        print(f"Found user DN: {user_dn}")
        conn.unbind()
        
        # 5. Now attempt to bind with the user's credentials
        print(f"Attempting to bind as user: {user_dn}")
        user_conn = Connection(server, user=user_dn, password=password, auto_bind=True)
        
        # If we get here, the bind was successful
        print(f"Authentication successful for user: {username}")
        user_conn.unbind()
        
        return {
            'success': True,
            'message': 'Authentication successful',
            'user_data': {
                'username': user_data.get('sAMAccountName', [username])[0],
                'email': user_data.get('mail', [f'{username}@ldap.local'])[0],
                'name': user_data.get('cn', ['Unknown'])[0],
                'dn': user_dn
            }
        }
        
    except Exception as e:
        error_msg = str(e)
        print(f"Authentication failed for {username}: {error_msg}")
        
        # Check if it's an invalid credentials error
        if 'invalidCredentials' in error_msg or '49' in error_msg:
            return {
                'success': False,
                'message': 'Invalid username or password'
            }
        else:
            return {
                'success': False,
                'message': f'Authentication error: {error_msg}'
            }


from flask import Flask, jsonify, request
import schedule
import time
import threading

app = Flask(__name__)

# ... existing code ...

@app.route('/authenticate', methods=['POST'])
def authenticate():
    """Endpoint to authenticate a user against LDAP."""
    try:
        data = request.get_json()
        username = data.get('username')
        password = data.get('password')
        
        if not username or not password:
            return jsonify({
                'success': False,
                'message': 'Username and password are required'
            }), 400
        
        # Call authentication function
        result = authenticate_ldap_user(username, password)
        
        status_code = 200 if result['success'] else 401
        return jsonify(result), status_code
        
    except Exception as e:
        return jsonify({
            'success': False,
            'message': f'Server error: {str(e)}'
        }), 500


@app.route('/sync', methods=['POST'])
def manual_sync():
    """Endpoint to trigger manual synchronization."""
    print("Manual sync triggered via HTTP request.")
    try:
        fetch_ldap_data()
        return jsonify({"status": "success", "message": "LDAP sync completed successfully."}), 200
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

def run_scheduler():
    """Runs the scheduler in a separate thread."""
    print("Scheduler started. Waiting for 06:00...")
    schedule.every().day.at("06:00").do(fetch_ldap_data)
    
    while True:
        schedule.run_pending()
        time.sleep(1)

def initialize_app():
    """Initialize the application - run scheduler and initial sync."""
    # Start scheduler in a background thread
    scheduler_thread = threading.Thread(target=run_scheduler)
    scheduler_thread.daemon = True
    scheduler_thread.start()
    
    # Run initial sync on startup
    print("Running initial sync on startup...")
    fetch_ldap_data()
    print("LDAP service initialized successfully.", flush=True)

# Initialize when module is imported (for Gunicorn)
# Use a flag to ensure it only runs once even with multiple workers
import os
if os.environ.get('WERKZEUG_RUN_MAIN') != 'true':
    initialize_app()

if __name__ == "__main__":
    # For development/testing - run with Flask dev server
    print("Starting Flask development server on port 5000...", flush=True)
    app.run(host='0.0.0.0', port=5000)
