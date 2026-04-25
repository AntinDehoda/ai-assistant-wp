<?php

namespace App\Controller;

use App\Dto\TelegramUpdate;
use App\Service\TelegramService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class WebhookController extends AbstractController
{
    #[Route('/webhook', name: 'telegram_webhook', methods: ['POST'])]
    public function index(
        Request $request, 
        TelegramService $telegramService, 
        \App\Service\GeminiEngine $geminiEngine,
        #[Autowire(env: 'TELEGRAM_WEBHOOK_SECRET')] string $webhookSecret
    ): JsonResponse {
        $providedSecret = $request->headers->get('X-Telegram-Bot-Api-Secret-Token');
        if ($providedSecret !== $webhookSecret) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data = $request->toArray();
        $update = new TelegramUpdate($data);
        
        if ($update->chatId && $update->text) {
            // Process the message through the Knowledge Custodian AI
            $aiResponse = $geminiEngine->process($update->text, (string) $update->chatId);
            
            // Send raw text to Telegram
            $telegramService->sendMessage($update->chatId, $aiResponse);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
