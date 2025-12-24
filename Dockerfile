# Use PHP 8.1 with built-in web server
FROM php:8.1-cli

# Install system dependencies for PostgreSQL
RUN apt-get update && apt-get install -y \
    postgresql-client \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Expose port (Render uses PORT environment variable, default 10000)
EXPOSE 10000

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:10000", "-t", "."]
