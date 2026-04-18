## Stage 1: Foundation (DevOps Manager)

Switch to DevOps & Git Manager role.
Initialize Symfony 7 project.

Configure Dockerfile and docker-compose.yml with volume mounts for /knowledge.

Setup .env for TELEGRAM_TOKEN and GEMINI_API_KEY.

Commit: "Stage 1: Symfony scaffolding and Docker setup".

## Stage 2: Wiki Persistence Service (Symfony Engineer)

Create a WikiManagerService to handle read, write, list, and appendLog.

Ensure it uses Symfony's Filesystem component.

Commit: "Stage 2: Implement WikiManagerService".

## Stage 3: Gemini Client & Tool Definition (Symfony Engineer & WP Specialist)

Integrate Gemini API.

Implement Function Calling (Tools) that map Gemini's requests to WikiManagerService methods.

Define the "Knowledge Custodian" System Instruction for the AI.

Commit: "Stage 3: Integrate Gemini with Function Calling".

## Stage 4: Telegram Webhook Controller (Symfony Engineer)

Create a WebhookController to receive Telegram updates.

Implement a TelegramService to send responses back (MarkdownV2 support).

Commit: "Stage 4: Telegram Webhook infrastructure".

## Stage 5: Integration & AI Loop (All Agents)

Connect the pieces: Telegram Message -> AI Engine -> Wiki Tool Call -> Response.

Implement a "Knowledge Update" logic: every major insight triggers a write_page call.

Commit: "Stage 5: Full AI-Wiki integration loop".

## Stage 6: GCP Deployment (DevOps Manager)

Finalize Cloud Run config with GCS FUSE mounting.

Setup Secret Manager integration for Symfony secrets.

Commit: "Stage 6: Production-ready GCP configuration".