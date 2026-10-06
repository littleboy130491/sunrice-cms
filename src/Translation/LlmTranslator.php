<?php

declare(strict_types=1);

namespace Sunrice\Translation;

use RuntimeException;
use Sunrice\Support\Locales;

/**
 * Shared prompt, batching and response parsing for LLM translators.
 * Strings are sent as a JSON object with short numeric keys and must come
 * back as a JSON object with the same keys.
 */
abstract class LlmTranslator implements Translator
{
    public function __construct(
        protected string $apiKey,
        protected string $model,
        protected int $batchSize = 40,
        protected int $timeout = 120,
    ) {
        if ($this->apiKey === '') {
            throw new RuntimeException(static::class.' needs an API key. Set it in your .env file (see docs/translation.md).');
        }
    }

    /** Send one prompt and return the model's raw text answer. */
    abstract protected function complete(string $system, string $user): string;

    public function translate(array $strings, string $from, string $to, ?string $context = null): array
    {
        $translated = [];

        foreach (array_chunk($strings, max(1, $this->batchSize), true) as $batch) {
            $keys = array_keys($batch);
            $payload = array_combine(array_map('strval', array_keys($keys)), array_values($batch));

            $answer = $this->complete(
                $this->systemPrompt($from, $to, $context),
                (string) json_encode($payload, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            );

            foreach ($this->parse($answer) as $index => $value) {
                if (is_string($value) && isset($keys[(int) $index]) && ctype_digit((string) $index)) {
                    $translated[$keys[(int) $index]] = $value;
                }
            }
        }

        return $translated;
    }

    protected function systemPrompt(string $from, string $to, ?string $context): string
    {
        $source = Locales::name($from).' ('.$from.')';
        $target = Locales::name($to).' ('.$to.')';

        return trim(<<<PROMPT
            You are a professional website translator. Translate every value of the JSON object from {$source} to {$target}.
            Rules:
            - Reply with a JSON object only, using exactly the same keys. Do not add, drop or rename keys. No explanations.
            - Keep HTML tags and attributes exactly as they are; translate only the visible text.
            - Keep placeholders unchanged, for example :name, :Name, :NAME, {name}, {{ name }}, %s, %d.
            - Keep Laravel pluralisation structure such as "{0} ...|{1} ...|[2,*] ..." including the | separators.
            - Keep URLs, email addresses, code and brand or product names unchanged.
            - Use natural, fluent wording and keep the tone of the original.
            PROMPT
            .($context ? "\nContext: {$context}" : ''));
    }

    /** @return array<array-key, mixed> */
    protected function parse(string $answer): array
    {
        $json = trim($answer);
        // Some models wrap JSON in a Markdown code fence.
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $json, $match) === 1) {
            $json = $match[1];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('The translation model did not return a JSON object.');
        }

        return $decoded;
    }
}
