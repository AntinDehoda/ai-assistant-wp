# Gemini API & Symfony HttpClient Configuration

## Context
During the initial webhook and Gemini API integration testing, two distinct errors were encountered that required fixing to successfully receive an AI response via Telegram.

## Issues and Solutions

### 1. Symfony HttpClient Missing and Autowiring Error
**Error**: `Cannot autowire service "App\Service\TelegramService": argument "$httpClient" of method "__construct()" references interface "Symfony\Contracts\HttpClient\HttpClientInterface" but no such service exists.`
**Cause**: The `symfony/http-client` component was not fully installed/configured within the framework. Although the `HttpClientInterface` was requested by `GeminiEngine` and `TelegramService`, the container didn't know how to resolve it.
**Solution**:
- Installed the component: `composer require symfony/http-client`
- Explicitly enabled the HTTP Client in the FrameworkBundle by creating `config/packages/http_client.yaml`:
  ```yaml
  framework:
      http_client: ~
  ```

### 2. Gemini 1.5 Pro Deprecation / 404 Error
**Error**: `HTTP/2 404 returned for "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=..."`
**Cause**: The model `gemini-1.5-pro` is no longer supported or available on the current API version/key setup.
**Solution**:
- Checked available models via `https://generativelanguage.googleapis.com/v1beta/models?key=YOUR_KEY`.
- Updated the hardcoded API endpoint in `src/Service/GeminiEngine.php` to use `gemini-2.5-pro`.

## Best Practices Established
- Always verify model availability via the API before hardcoding version strings.
- Ensure `http_client: ~` is set under `framework` config when using `HttpClientInterface` for autowiring in Symfony 7.
