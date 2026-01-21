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

def get_first_attr(attributes, key, default=None):
    """Safely get valid first value from LDAP attributes dict which might contain lists or strings."""
    val = attributes.get(key)
    if val is None:
        return default
        
    # If it's a list, take first item (if exists)
    if isinstance(val, list):
        if len(val) > 0:
            return val[0]
        return default
        
    # If bytes, decode
    if isinstance(val, bytes):
        return val.decode('utf-8', errors='ignore')
        
    # If string or other, return as is
    return val

def fetch_ldap_data():
    conn_ldap = None
    db = None
    cursor = None
    
    try:
        # 1. Define the Server
        print("Configuring TLS...")
        tls_configuration = Tls(validate=ssl.CERT_NONE, version=ssl.PROTOCOL_TLSv1_2)
        
        print(f"Creating Server object for {LDAP_SERVER_HOST}...")
        server = Server(LDAP_SERVER_HOST, port=LDAP_PORT, use_ssl=True, tls=tls_configuration)
        
        # 2. Define the Connection
        print("Attempting to connect and bind...")
        conn_ldap = Connection(server, user=LDAP_USER_DN, password=LDAP_PASSWORD, auto_bind=True)
        print(f"Successfully connected to {LDAP_SERVER_HOST}")

        # 3. Connect to Database (Once)
        import mysql.connector
        import os
        
        print("Connecting to database...")
        db = mysql.connector.connect(
            host=os.environ.get('DB_HOST', 'smartward-db'),
            port=int(os.environ.get('DB_PORT', 3306)),
            user="root",
            password=os.environ.get('DB_PASSWORD', 'smartward_secret'), 
            database=os.environ.get('DB_DATABASE', 'smartward')
        )
        # Use buffered cursor to avoid "Unread result found"
        cursor = db.cursor(buffered=True)

        # 4. Execute the Search with PAGINATION
        print("Executing paged search...")
        entry_generator = conn_ldap.extend.standard.paged_search(
            search_base=SEARCH_BASE,
            search_filter=SEARCH_FILTER,
            search_scope=SUBTREE, 
            attributes=['cn', 'sAMAccountName', 'mail'], # Fetch only needed attributes
            paged_size=1000,
            generator=True
        )

        entry_count = 0
        sync_count = 0
        
        for entry in entry_generator:
            if 'attributes' not in entry:
                continue
                
            entry_count += 1
            user_data = entry['attributes']
            
            # Extract data safely
            name = get_first_attr(user_data, 'cn', 'Unknown')
            account = get_first_attr(user_data, 'sAMAccountName')
            
            # Skip if no account name (crucial)
            if not account:
                continue
                
            # Default email if missing
            email = get_first_attr(user_data, 'mail', f'{account}@ldap.local')
            
            # Debug log every 50 users or for specific ones
            if entry_count % 50 == 0:
                print(f"Processing {entry_count}: {name} ({account})")

            try:
                # Check if user exists - Use LIMIT 1 to prevent multiple results issue
                cursor.execute("SELECT id FROM users WHERE email = %s OR name = %s LIMIT 1", (email, account))
                result = cursor.fetchone()
                # consume any remaining results just in case (though limit 1 prevents it usually)
                try: 
                    cursor.fetchall() 
                except: 
                    pass
                
                if result:
                    # Update
                    sql = """
                        UPDATE users 
                        SET name = %s, is_ldap_user = 1, ldap_synced_at = NOW()
                        WHERE id = %s
                    """
                    cursor.execute(sql, (name, result[0]))
                else:
                    # Insert
                    # print(f"Creating user: {account}")
                    sql = """
                        INSERT INTO users (name, email, password, role, is_ldap_user, ldap_synced_at, created_at, updated_at)
                        VALUES (%s, %s, %s, %s, 1, NOW(), NOW(), NOW())
                    """
                    dummy_pass = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
                    cursor.execute(sql, (name, email, dummy_pass, 'user'))
                
                sync_count += 1
                
            except Exception as db_err:
                print(f"Database error for {account}: {db_err}")

        # Commit all changes at the end
        db.commit()
        print(f"Sync complete. Processed {entry_count} entries. Synced {sync_count} users.")

    except Exception as e:
        print(f"An error occurred during sync: {e}")

    finally:    
        if cursor:
            try: cursor.close()
            except: pass
        if db:
            try: db.close()
            except: pass
        if conn_ldap and conn_ldap.bound:
            try: conn_ldap.unbind()
            except: pass


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
            'message': 'Authentication successful (v2)',
            'user_data': {
                'username': get_first_attr(user_data, 'sAMAccountName', username),
                'email': get_first_attr(user_data, 'mail', f'{username}@ldap.local'),
                'name': get_first_attr(user_data, 'cn', 'Unknown'),
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
                'message': 'Invalid username or password (v2)'
            }
        else:
            return {
                'success': False,
                'message': f'Authentication error (v2): {error_msg}'
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
