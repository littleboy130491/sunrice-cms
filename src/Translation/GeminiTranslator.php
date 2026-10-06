<?php

declare(strict_types=1);

namespace Sunrice\Translation;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Google Gemini API (generateContent). */
class GeminiTranslator extends LlmTranslator
{
    protected function complete(string $system, string $user): string
    {
        $response = Http::timeout($this->timeout)
            ->retry(2, 1000, throw: false)
            ->withHeaders(['x-goog-api-key' => $this->apiKey])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent", [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini request failed ('.$response->status().'): '.$response->json('error.message', $response->body()));
        }

        return (string) $response->json('candidates.0.content.parts.0.text', '');
    }
}
