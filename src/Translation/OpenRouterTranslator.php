<?php

declare(strict_types=1);

namespace Sunrice\Translation;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** OpenRouter chat completions (OpenAI-compatible), for any model it hosts. */
class OpenRouterTranslator extends LlmTranslator
{
    protected function complete(string $system, string $user): string
    {
        $response = Http::timeout($this->timeout)
            ->retry(2, 1000, throw: false)
            ->withToken($this->apiKey)
            ->withHeaders(['HTTP-Referer' => (string) config('app.url'), 'X-Title' => (string) config('app.name')])
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenRouter request failed ('.$response->status().'): '.$response->json('error.message', $response->body()));
        }

        return (string) $response->json('choices.0.message.content', '');
    }
}
