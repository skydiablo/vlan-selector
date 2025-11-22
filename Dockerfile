FROM php:8.2-cli AS builder

# Install system dependencies and Composer
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json ./
# Copy composer.lock if it exists (optional for reproducible builds)
COPY composer.lock* ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Production stage
FROM php:8.2-cli

# Set working directory
WORKDIR /app

# Copy installed dependencies from builder
COPY --from=builder /app/vendor ./vendor

# Copy application files
COPY src/ ./src/
#COPY mac-vlan-config.yaml ./

# Create non-root user for security
RUN useradd -m -u 1000 appuser && chown -R appuser:appuser /app
USER appuser

# Expose RADIUS port (UDP)
EXPOSE 1812/udp

# Start the server
CMD ["php", "src/server.php"]

