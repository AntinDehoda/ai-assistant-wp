<?php

namespace App\Controller;

use App\Dto\TelegramUpdate;
use App\Service\GeminiEngine;
use App\Service\TelegramFormatter;
use App\Service\TelegramService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    public function __construct(private readonly TelegramService $telegramService, private readonly GeminiEngine $geminiEngine, private readonly TelegramFormatter $formatter)
    {
    }

    #[Route('/webhook', name: 'telegram_webhook', methods: ['POST'])]
    public function index(
        Request $request,
        #[Autowire(env: 'TELEGRAM_WEBHOOK_SECRET')] string $webhookSecret,
    ): JsonResponse {
        $providedSecret = $request->headers->get('X-Telegram-Bot-Api-Secret-Token');
        if ($providedSecret !== $webhookSecret) {
            return new JsonResponse(['error' => 'Unauthorized'], \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
        }

        $data = $request->toArray();
        $update = new TelegramUpdate($data);

        if ($update->chatId && $update->text) {
            // Process the message through the Knowledge Custodian AI
            $aiResponse = $this->geminiEngine->process($update->text, (string) $update->chatId);

            // Format LLM output to Telegram HTML
            $safeText = $this->formatter->markdownToHtml($aiResponse);

            // Send the safely escaped text to Telegram
            $this->telegramService->sendMessage($update->chatId, $safeText, 'HTML');
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
