FROM python:3.12-slim

WORKDIR /app

# Install system dependencies if needed (e.g., for ldap/sasl)
RUN apt-get update && apt-get install -y \
    gcc \
    libldap2-dev \
    libsasl2-dev \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY ldap_conn.py .

CMD ["python", "ldap_conn.py"]
