## Stage 1: Foundation (DevOps Manager)

Switch to DevOps & Git Manager role.
Initialize Symfony 7 project.

Configure Dockerfile and docker-compose.yml with volume mounts for /knowledge.

Setup .env for TELEGRAM_TOKEN and GEMINI_API_KEY.

Commit: "Stage 1: Symfony scaffolding and Docker setup".

**✓ Completed:**
- Designed Dockerized Symfony Initialization Strategy (no local dependencies required).
- Created `Dockerfile` (PHP 8.2-fpm Alpine), `docker-compose.yml`, `docker/supervisord.conf`, `docker/default.conf`, and `docker/entrypoint.sh`.
- Set up `.env` placeholder with `TELEGRAM_TOKEN` and `GEMINI_API_KEY`.

## Stage 2: Wiki Persistence Service (Symfony Engineer)

Create a WikiManagerService to handle read, write, list, and appendLog.

Ensure it uses Symfony's Filesystem component.

Commit: "Stage 2: Implement WikiManagerService".

**✓ Completed:**
- Created `src/Service/WikiManager.php`.
- Set up Symfony native `Filesystem` and `Finder` component interactions.
- Implemented `listKnowledge()`, `readPage()`, `writePage()`, `searchSources()`, and `appendLog()` bridging to the `/knowledge` mount.

## Stage 3: Gemini Client & Tool Definition (Symfony Engineer & WP Specialist)

Integrate Gemini API.

Implement Function Calling (Tools) that map Gemini's requests to WikiManagerService methods.

Define the "Knowledge Custodian" System Instruction for the AI.

Commit: "Stage 3: Integrate Gemini with Function Calling".

**✓ Completed:**
- Built `src/Service/GeminiEngine.php` leveraging `HttpClientInterface` and `#[Autowire]`.
- Implemented Function Calling loops for `list_knowledge`, `read_page`, `write_page`, and `search_sources`.
- Injected "Senior WP-AI Architect" Knowledge Custodian rules recursively.

## Stage 4: Telegram Webhook Controller (Symfony Engineer)

Create a WebhookController to receive Telegram updates.

Implement a TelegramService to send responses back (MarkdownV2 support).

Commit: "Stage 4: Telegram Webhook infrastructure".

**✓ Completed:**
- Registered `/webhook` endpoint locally via `src/Controller/WebhookController.php`.
- Set up typed mapping via `src/Dto/TelegramUpdate.php`.
- Engineered `src/Service/TelegramService.php` properly handling response callbacks wrapped in `escapeMarkdownV2`.

## Stage 5: Integration & AI Loop (All Agents)

Connect the pieces: Telegram Message -> AI Engine -> Wiki Tool Call -> Response.

Implement a "Knowledge Update" logic: every major insight triggers a write_page call.

Commit: "Stage 5: Full AI-Wiki integration loop".

**✓ Completed:**
- Wired `GeminiEngine` organically directly via `WebhookController` action execution scope.
- Configured recursive AI callback processing handling Telegram text securely via `escapeMarkdownV2`.
- Knowledge Update logics natively operate via the implemented `write_page` tools recursively processed inside `GeminiEngine`.

## Stage 6: GCP Deployment (DevOps Manager)

Finalize Cloud Run config with GCS FUSE mounting.

Setup Secret Manager integration for Symfony secrets.

Commit: "Stage 6: Production-ready GCP configuration".