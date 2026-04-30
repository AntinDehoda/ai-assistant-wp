#!/bin/bash
set -e

# Configuration
SERVICE_NAME="ai-crm-architect"
REGION="us-central1"
BUCKET_NAME="ai-crm-knowledge-bucket" # Replace with your GCS Bucket name

echo "Starting Deployment for $SERVICE_NAME on Google Cloud Run..."

# Deploy from source (Builds Dockerfile and pushes to Artifact Registry automatically)
gcloud run deploy $SERVICE_NAME \
  --source . \
  --region $REGION \
  --allow-unauthenticated \
  --execution-environment gen2 \
  --add-volume=name=knowledge_vol,type=cloud-storage,bucket=$BUCKET_NAME \
  --add-volume-mount=volume=knowledge_vol,mount-path=/app/knowledge \
  --set-secrets="TELEGRAM_TOKEN=TELEGRAM_TOKEN:latest,GEMINI_API_KEY=GEMINI_API_KEY:latest,TELEGRAM_WEBHOOK_SECRET=TELEGRAM_WEBHOOK_SECRET:latest"

echo "Deployment complete."
