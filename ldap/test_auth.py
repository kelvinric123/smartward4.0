import requests
import json

# Test LDAP authentication endpoint
url = 'http://localhost:5000/authenticate'
data = {
    'username': 'drtai',
    'password': '88888888'
}

try:
    response = requests.post(url, json=data)
    print(f"Status Code: {response.status_code}")
    print(f"Response: {json.dumps(response.json(), indent=2)}")
except Exception as e:
    print(f"Error: {e}")
