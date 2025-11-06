# Podcast Feed

A Laravel-based podcast feed management application with React frontend.

## Features

- Podcast feed management
- Media file processing
- Library organization
- User authentication
- Modern React frontend with Inertia.js

## Docker Setup

This application includes Docker support for easy development and deployment.

### Prerequisites

- Docker and Docker Compose installed on your system

### Quick Start

The easiest way to get started is using the build script:

```bash
./build.sh dev
```

This will:
- Build and start all containers
- Create the environment file if needed
- Set up the development environment

Then run migrations:
```bash
./build.sh migrate
```

Access the application at `http://localhost:8000`

### Manual Setup

If you prefer to set up manually:

1. Clone the repository:
```bash
git clone <repository-url>
cd podcast-feed
```

2. Copy the environment file:
```bash
cp src/.env.example src/.env
```

3. Update the database configuration in `src/.env`:
```env
DB_CONNECTION=mysql
DB_HOST=database
DB_PORT=3306
DB_DATABASE=podcast_feed
DB_USERNAME=podcast_user
DB_PASSWORD=secret
```

4. Build and start the containers:
```bash
docker-compose up -d --build
```

5. Run the database migrations:
```bash
docker-compose exec app php artisan migrate
```

6. Install and build frontend assets:
```bash
docker-compose exec app npm install
docker-compose exec app npm run build
```

7. Access the application at `http://localhost:8000`

### Development Commands

Using the build script (recommended):
- Start development: `./build.sh dev`
- Stop services: `./build.sh stop`
- View logs: `./build.sh logs -f`
- Run migrations: `./build.sh migrate`
- Run tests: `./build.sh test`
- Access shell: `./build.sh shell`
- Build production image: `./build.sh prod`
- Clean everything: `./build.sh clean`

Manual Docker commands:
- Start all services: `docker-compose up -d`
- Stop all services: `docker-compose down`
- View logs: `docker-compose logs -f`
- Run artisan commands: `docker-compose exec app php artisan <command>`
- Access container shell: `docker-compose exec app sh`

### Services

The Docker setup includes:

- **app**: PHP-FPM service with Laravel application
- **webserver**: Nginx web server (port 8000)
- **database**: MySQL 8.0 database (port 3306)
- **redis**: Redis cache service (port 6379)

### Production Deployment

For production deployment, use the build script to create an optimized production image:

```bash
./build.sh prod
```

This creates a multi-stage build with:
- Optimized frontend assets
- Production PHP configuration
- Cached Laravel configurations
- Minimal image size

Then run with your preferred orchestration method, ensuring you set the appropriate environment variables.

Example production run:
```bash
docker run -d \
  --name podcast-feed \
  -p 9000:9000 \
  -e APP_ENV=production \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=your-db-host \
  -e DB_DATABASE=your-db \
  -e DB_USERNAME=your-user \
  -e DB_PASSWORD=your-password \
  podcast-feed:prod
```

## Local Development (Without Docker)

If you prefer to develop locally:

1. Install PHP 8.2+, Node.js, and Composer
2. Clone the repository and navigate to the `src/` directory
3. Install dependencies:
   ```bash
   composer install
   npm install
   ```
4. Set up environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
5. Run migrations:
   ```bash
   php artisan migrate
   ```
6. Start development servers:
   ```bash
   composer run dev
   ```

## Testing

Run tests using Docker:

```bash
docker-compose exec app php artisan test
```

Or locally:

```bash
cd src
php artisan test
```

## License

This project is open-sourced software licensed under the MIT license.