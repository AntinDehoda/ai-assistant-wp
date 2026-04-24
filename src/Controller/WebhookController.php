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
    public function index(Request $request, TelegramService $telegramService, \App\Service\GeminiEngine $geminiEngine): JsonResponse
    {
        $data = $request->toArray();
        $update = new TelegramUpdate($data);
        
        if ($update->chatId && $update->text) {
            // Process the message through the Knowledge Custodian AI
            $aiResponse = $geminiEngine->process($update->text, (string) $update->chatId);
            
            // To prevent MarkdownV2 reserved characters from crashing the API
            $safeText = $telegramService->escapeMarkdownV2($aiResponse);
            
            $telegramService->sendMessage($update->chatId, $safeText);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
