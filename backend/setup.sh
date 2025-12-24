#!/bin/bash

# Task Management Backend Setup Script
# This script automates PostgreSQL database setup

echo "╔════════════════════════════════════════════════╗"
echo "║   Task Management Backend Setup Script         ║"
echo "╚════════════════════════════════════════════════╝"
echo ""

# Check if PostgreSQL is installed
if ! command -v psql &> /dev/null; then
    echo "❌ PostgreSQL is not installed."
    echo ""
    echo "Install PostgreSQL:"
    echo "  Linux (Ubuntu/Debian): sudo apt install postgresql"
    echo "  macOS: brew install postgresql"
    echo "  Windows: Download from https://www.postgresql.org/download/windows/"
    exit 1
fi

echo "✓ PostgreSQL found"
echo ""

# Database configuration
DB_HOST="localhost"
DB_PORT="5432"
DB_NAME="task_management"
DB_USER="postgres"
DB_PASSWORD="postgres"

# Ask for configuration
read -p "Database host [$DB_HOST]: " input
DB_HOST=${input:-$DB_HOST}

read -p "Database port [$DB_PORT]: " input
DB_PORT=${input:-$DB_PORT}

read -p "Database name [$DB_NAME]: " input
DB_NAME=${input:-$DB_NAME}

read -p "Database user [$DB_USER]: " input
DB_USER=${input:-$DB_USER}

read -sp "Database password [$DB_PASSWORD]: " input
DB_PASSWORD=${input:-$DB_PASSWORD}
echo ""

# Create .env file
echo "Creating .env file..."
cat > ".env" << EOF
# PostgreSQL Database Configuration
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASSWORD

# Application Settings
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:8000
EOF

echo "✓ .env file created"
echo ""

# Create database
echo "Creating PostgreSQL database..."

# Try to create database
PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -U $DB_USER -c "CREATE DATABASE $DB_NAME;" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✓ Database created successfully"
else
    echo "⚠ Database may already exist (this is fine)"
fi

echo ""
echo "╔════════════════════════════════════════════════╗"
echo "║   Setup Complete!                             ║"
echo "╚════════════════════════════════════════════════╝"
echo ""
echo "📝 Configuration saved in: .env"
echo ""
echo "Next steps:"
echo "1. Start PHP development server:"
echo "   php -S localhost:8000"
echo ""
echo "2. Visit the backend dashboard:"
echo "   http://localhost:8000/backend/"
echo ""
echo "3. Update backend/config/database.php with your credentials"
echo ""
echo "4. Test the API:"
echo "   curl -X POST http://localhost:8000/backend/api/auth.php?action=register \\\"
echo "     -H 'Content-Type: application/json' \\\"
echo "     -d '{\"email\":\"test@example.com\",\"password\":\"test123\"}"
echo ""
