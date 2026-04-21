# Google Cloud Run Deployment Setup

This guide details the procedures for securely deploying the Wiki-Driven AI CRM Architect onto Google Cloud Run, leveraging Cloud Storage (GCS) FUSE for high-performance persisting of our Wiki Markdown payload, and Secret Manager for configuration credentials.

## 1. Prerequisites
Ensure you have the following before running `deploy.sh`:
- Google Cloud SDK (`gcloud`) installed and locally authenticated.
- A functional Google Cloud Project with Billing enabled.

Enable the necessary APIs:
```bash
gcloud services enable run.googleapis.com \
    cloudbuild.googleapis.com \
    secretmanager.googleapis.com \
    compute.googleapis.com
```

## 2. Infrastructure Setup (FUSE & IAM)

### A. Create the Knowledge Bucket
Our LLM-Wiki persists autonomously in Cloud Storage to resist ephemeral Cloud Run teardowns.
```bash
gsutil mb -l us-central1 gs://ai-crm-knowledge-bucket
```
*(Note: Be sure to edit `deploy.sh` to match your uniquely chosen bucket name).*

### B. Configure Secret Manager
Instead of exposing credentials in our source code or Docker configs, securely cache them in Secret Manager:
```bash
# Add Telegram Token
printf "your-telegram-token" | gcloud secrets create TELEGRAM_TOKEN --data-file=-

# Add Gemini API Key
printf "your-gemini-key" | gcloud secrets create GEMINI_API_KEY --data-file=-
```

### C. Grant Service Account Permissions
Cloud Run utilizes a default service account (or one you designate) which must dynamically read secrets and mutate storage.
```bash
PROJECT_ID=$(gcloud config get-value project)
PROJECT_NUMBER=$(gcloud projects describe $PROJECT_ID --format="value(projectNumber)")
SA_EMAIL="${PROJECT_NUMBER}-compute@developer.gserviceaccount.com"

# Grant Secret Manager Access
gcloud projects add-iam-policy-binding $PROJECT_ID \
  --member="serviceAccount:${SA_EMAIL}" \
  --role="roles/secretmanager.secretAccessor"

# Grant Cloud Storage Access (For FUSE reading/writing wiki contents)
gcloud projects add-iam-policy-binding $PROJECT_ID \
  --member="serviceAccount:${SA_EMAIL}" \
  --role="roles/storage.objectAdmin"
```

## 3. Deployment

Run the automated deploy script:
```bash
./deploy.sh
```

**What the script does organically:**
1. Leverages `--source .` to hand over our local repository directly to Google Cloud Build. Cloud Build will execute our `Dockerfile`, layering PHP 8.2 FPM, Supervisord, Composer structure and Nginx tightly.
2. Specifies `--execution-environment gen2` and natively binds the GCS bucket directly onto `/knowledge` mapping our `WikiManager` into the unified storage FUSE.
3. Maps our Secret Manager credentials natively to Docker environment variables passing into our Symfony Autowiring dynamically.

## 4. Teardown / Stopping Services

To stop incurring charges or fully remove the deployment, you can delete the resources separately:

### A. Delete the Cloud Run Service
This stops the application from running and removes the endpoint.
```bash
gcloud run services delete ai-crm-architect --region us-central1
```

### B. Delete the FUSE Knowledge Bucket
**Warning:** This will permanently delete all your AI's learned wiki knowledge!
```bash
gsutil rm -r gs://ai-crm-knowledge-bucket
```

### C. Delete the Secrets
Removes the API keys from Secret Manager.
```bash
gcloud secrets delete TELEGRAM_TOKEN
gcloud secrets delete GEMINI_API_KEY
```
