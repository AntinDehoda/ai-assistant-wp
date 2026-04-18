<?php

namespace App\Controller;

use App\Dto\TelegramUpdate;
use App\Service\TelegramService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    #[Route('/webhook', name: 'telegram_webhook', methods: ['POST'])]
    public function index(Request $request, TelegramService $telegramService): JsonResponse
    {
        $data = $request->toArray();
        $update = new TelegramUpdate($data);
        
        if ($update->chatId && $update->text) {
            // Stage 4 focuses purely on infrastructure. We are just echoing to prove the webhook works.
            // In Stage 5, this simple echo will be replaced by GeminiEngine call.
            
            // To prevent MarkdownV2 reserved characters in user text from crashing the echo, we escape it
            $safeText = $telegramService->escapeMarkdownV2("System acknowledged receipt of: " . $update->text);
            
            $telegramService->sendMessage($update->chatId, $safeText);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
