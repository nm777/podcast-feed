#!/bin/bash

# Podcast Feed Docker Build Script
# This script helps build and manage local Docker containers

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to show usage
show_usage() {
    echo "Podcast Feed Docker Build Script"
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  build           Build the Docker image"
    echo "  dev             Build and start development environment"
    echo "  prod            Build production image"
    echo "  stop            Stop all containers"
    echo "  clean           Remove containers, images, and volumes"
    echo "  logs            Show container logs"
    echo "  shell           Access app container shell"
    echo "  migrate         Run database migrations"
    echo "  test            Run tests"
    echo "  help            Show this help message"
    echo ""
    echo "Options:"
    echo "  --no-cache      Build without using cache"
    echo "  --pull          Pull latest base images"
    echo "  --verbose       Show detailed output"
    echo ""
    echo "Examples:"
    echo "  $0 dev                    # Build and start development"
    echo "  $0 build --no-cache       # Build without cache"
    echo "  $0 prod                   # Build production image"
    echo "  $0 logs -f app            # Follow app container logs"
}

# Function to check if Docker is running
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        print_error "Docker is not running. Please start Docker first."
        exit 1
    fi
}

# Function to build Docker image
build_image() {
    local cache_flag="--pull"
    local verbose_flag=""
    
    if [[ "$*" == *"--no-cache"* ]]; then
        cache_flag="--no-cache"
        print_warning "Building without cache..."
    fi
    
    if [[ "$*" == *"--verbose"* ]]; then
        verbose_flag="--progress=plain"
    fi
    
    print_status "Building Docker image..."
    docker build $cache_flag $verbose_flag -t podcast-feed:latest .
    
    if [ $? -eq 0 ]; then
        print_success "Docker image built successfully!"
    else
        print_error "Failed to build Docker image"
        exit 1
    fi
}

# Function to start development environment
start_dev() {
    print_status "Starting development environment..."
    
    # Copy environment file if it doesn't exist
    if [ ! -f "src/.env" ]; then
        print_status "Creating environment file..."
        cp src/.env.example src/.env
        print_warning "Please update src/.env with your configuration"
    fi
    
    # Build and start containers
    docker-compose up -d --build
    
    if [ $? -eq 0 ]; then
        print_success "Development environment started!"
        print_status "Application is available at: http://localhost:8000"
        print_status "Database is available at: localhost:3306"
        print_status "Redis is available at: localhost:6379"
        echo ""
        print_status "Next steps:"
        echo "  1. Run migrations: $0 migrate"
        echo "  2. Access shell: $0 shell"
        echo "  3. View logs: $0 logs"
    else
        print_error "Failed to start development environment"
        exit 1
    fi
}

# Function to build production image
build_prod() {
    print_status "Building production image..."
    
    # Create production Dockerfile if it doesn't exist
    if [ ! -f "Dockerfile.prod" ]; then
        print_status "Creating production Dockerfile..."
        cat > Dockerfile.prod << 'EOF'
# Multi-stage production build
FROM node:18-alpine AS frontend

WORKDIR /app

# Copy package files
COPY src/package*.json ./
RUN npm ci --only=production

# Copy source and build
COPY src/ .
RUN npm run build

# Production PHP image
FROM php:8.2-fpm-alpine AS production

# Install system dependencies
RUN apk add --no-cache \
    libzip-dev \
    zip \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libxml2-dev \
    oniguruma-dev \
    sqlite-dev \
    icu-dev \
    icu-data-full \
    g++ \
    make \
    autoconf

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl --enable-intl \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_mysql \
        pdo_sqlite \
        zip \
        bcmath \
        xml \
        ctype \
        intl \
        mbstring \
        opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY src/ .

# Copy built frontend assets
COPY --from=frontend /app/public/build /var/www/html/public/build

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy environment file
RUN cp .env.example .env

# Generate application key
RUN php artisan key:generate

# Optimize for production
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
EOF
    fi
    
    docker build -f Dockerfile.prod -t podcast-feed:prod .
    
    if [ $? -eq 0 ]; then
        print_success "Production image built successfully!"
        print_status "Tag: podcast-feed:prod"
    else
        print_error "Failed to build production image"
        exit 1
    fi
}

# Function to stop containers
stop_containers() {
    print_status "Stopping all containers..."
    docker-compose down
    print_success "All containers stopped"
}

# Function to clean up
clean_up() {
    print_warning "This will remove all containers, images, and volumes. Are you sure? (y/N)"
    read -r response
    if [[ "$response" =~ ^([yY][eE][sS]|[yY])$ ]]; then
        print_status "Removing containers, images, and volumes..."
        docker-compose down -v --rmi all
        docker system prune -f
        print_success "Cleanup completed"
    else
        print_status "Cleanup cancelled"
    fi
}

# Function to show logs
show_logs() {
    docker-compose logs "$@"
}

# Function to access shell
access_shell() {
    docker-compose exec app sh
}

# Function to run migrations
run_migrations() {
    print_status "Running database migrations..."
    docker-compose exec app php artisan migrate
    print_success "Migrations completed"
}

# Function to run tests
run_tests() {
    print_status "Running tests..."
    docker-compose exec app php artisan test
}

# Main script logic
main() {
    check_docker
    
    case "${1:-help}" in
        "build")
            build_image "$@"
            ;;
        "dev")
            start_dev
            ;;
        "prod")
            build_prod
            ;;
        "stop")
            stop_containers
            ;;
        "clean")
            clean_up
            ;;
        "logs")
            show_logs "${@:2}"
            ;;
        "shell")
            access_shell
            ;;
        "migrate")
            run_migrations
            ;;
        "test")
            run_tests
            ;;
        "help"|*)
            show_usage
            ;;
    esac
}

# Run main function with all arguments
main "$@"