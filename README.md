# Project Specification: Wiki-Driven AI CRM Architect (Symfony Edition)

## 1. Project Concept
The **Wiki-Driven AI CRM Architect** is an autonomous Telegram agent specializing in WordPress AI integrations. It maintains an incremental, persistent knowledge base (**LLM-Wiki**) to track architectural decisions, plugin conflicts, and optimized PHP patterns.

## 2. The LLM-Wiki Architecture
The project follows the "**Karpathy Pattern**," where the agent acts as the sole maintainer of a structured Markdown knowledge base.

### Directory Structure
* `raw/`: Immutable source materials (Plugin docs, API specifications, error logs).
* `wiki/`: AI-synthesized concept pages (e.g., `FluentCRM_Gemini_Scoring.md`, `Non-blocking_API_Calls.md`).
* `index.md`: A dynamically updated map of all wiki content.
* `log.md`: A chronological append-only log of agent actions and insights.

## 3. Agent Capabilities (Function Calling)
The **Symfony-based agent** is equipped with services to interact with the Wiki:
* `list_knowledge()`: Scans the `/wiki` directory via **Symfony Finder** and returns the `index.md`.
* `read_page(filename)`: Retrieves the content of a specific knowledge page using the **Symfony Filesystem component**.
* `write_page(filename, content)`: Creates or updates a synthesized knowledge page.
* `search_sources(query)`: Performs a keyword search across the `raw/` directory.

## 4. Operational Logic (The Agent Loop)
When a user submits a query, the agent follows this cognitive workflow:
1.  **Analyze & Search:** Identify key entities and search the `index.md` for existing patterns.
2.  **Context Loading:** Read relevant pages from `/wiki` to ensure consistency with previous advice.
3.  **Synthesis:** Process the request using **Gemini 1.5 Pro/Flash** via **Symfony HttpClient**, combining Wiki knowledge with base training.
4.  **Response & Commit:** Deliver the solution and **proactively update** the Wiki/Log if a new pattern was discovered.

## 5. Technical Stack
* **Runtime:** Google Cloud Run (**Dockerized PHP 8.2+ / Symfony 7 / Nginx**).
* **Intelligence:** Google Gemini API (with System Instructions for Knowledge Custodianship).
* **Persistence:** * *Option A:* **Google Cloud Storage (GCS)** mounted via FUSE for high-speed file I/O.
    * *Option B:* **GitHub API** to allow the agent to "Commit" knowledge directly to the repo.
* **Security:** **GCP Secret Manager** for Telegram and Gemini credentials.

## 6. System Instructions for the Agent
**Role:** Senior WP-AI Architect & Knowledge Custodian.
**Core Mission:** Maintain a persistent LLM-Wiki about WordPress CRM integrations while assisting the user.

### Knowledge Management Rules:
1. **Never Re-derive:** Before answering, use `read_page` to check if we have an existing pattern.
2. **Incremental Updates:** Proactively use `write_page` to document new solutions in the `/wiki/` directory.
3. **Link Everything:** Use standard Markdown links `[[page-name]]` to connect related concepts.
4. **Consistency:** Ensure new entries do not contradict previous entries in `index.md`.

### Technical Guardrails:
* Focus on PHP 8.2+, WordPress Hooks, and Secure AI integration.
* Always check `log.md` for the history of previous architectural decisions.

## 7. Data Flow Diagram
[User Interaction] 
      |
[Telegram Webhook] -> [Symfony Controller (Nginx)]
                             |
                    [Read index.md / wiki/*.md] <---> [Cloud Storage / GitHub]
                             |
                    [Gemini API Processing]
                             |
                    [Update wiki/*.md & log.md] ----> [Cloud Storage / GitHub]
                             |
[Response to User] <---------+
