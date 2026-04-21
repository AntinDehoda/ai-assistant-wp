# Production Docker Deployment Fix

## Context
After successfully configuring the application locally, the Google Cloud Run deployment resulted in persistent 404 errors for the `/webhook` route, despite Nginx being correctly configured to route to `index.php`.

## The Issue
The `Dockerfile` used for production builds lacked a `COPY . /app` instruction.
Because of this:
1. Google Cloud Build created an image with Nginx, PHP-FPM, and Supervisor, but **without the application source code**.
2. When the container booted on Cloud Run, the `entrypoint.sh` script detected a missing `composer.json` and erroneously executed `composer create-project symfony/skeleton tmp_dir`.
3. The production server was essentially serving a brand-new, empty Symfony skeleton instead of our AI CRM app.

## The Solution
Added the `COPY . /app` instruction to the `Dockerfile` immediately after setting the `WORKDIR /app`. 

```dockerfile
# Set working directory
WORKDIR /app

# Copy application source code
COPY . /app
```

This ensures that Cloud Build securely packages our actual `src/Controller`, `src/Service`, and configurations into the container before deploying it to Cloud Run.
