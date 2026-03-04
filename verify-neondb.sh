#!/bin/bash

# NeonDB Connection Verification Script

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║         NeonDB Connection Verification                         ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Check if .env file exists
if [ -f "/home/king/Desktop/Projects/Task-Management/backend/.env" ]; then
    echo "[check] .env file found"
else
    echo "❌ .env file not found"
    exit 1
fi

# Extract values from .env
source "/home/king/Desktop/Projects/Task-Management/backend/.env"

echo ""
echo "[chart] NeonDB Configuration:"
echo "────────────────────────────────────────────────────────────────"
echo "Host:        $DB_HOST"
echo "Port:        $DB_PORT"
echo "Database:    $DB_NAME"
echo "User:        $DB_USER"
echo "SSL Mode:    $DB_SSL_MODE"
echo "Frontend:    $FRONTEND_URL"
echo ""

# Test if psql is available
if command -v psql &> /dev/null; then
    echo "[beaker] Testing NeonDB Connection..."
    echo "────────────────────────────────────────────────────────────────"
    
    # Test connection
    PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT version();" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        echo "[check] NeonDB Connection: SUCCESS"
    else
        echo "❌ NeonDB Connection: FAILED"
        echo ""
        echo "Troubleshooting:"
        echo "1. Check credentials in .env file"
        echo "2. Verify NeonDB database is active"
        echo "3. Check firewall allows outbound port 5432"
    fi
else
    echo "[warning] psql not installed (install postgresql client to test)"
    echo ""
    echo "To test manually, try:"
    echo "psql 'postgresql://$DB_USER:****@$DB_HOST:$DB_PORT/$DB_NAME?sslmode=$DB_SSL_MODE'"
fi

echo ""
echo "[rocket] To start the backend:"
echo "────────────────────────────────────────────────────────────────"
echo "cd /home/king/Desktop/Projects/Task-Management"
echo "php -S localhost:8000"
echo ""
echo "Then visit: http://localhost:8000/backend/"
echo ""
