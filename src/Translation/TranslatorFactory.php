<?php

declare(strict_types=1);

namespace Sunrice\Translation;

use InvalidArgumentException;

/** Builds the configured translator; the command can override driver and model. */
class TranslatorFactory
{
    public function make(?string $driver = null, ?string $model = null): Translator
    {
        $driver ??= (string) config('sunrice.translation.driver', 'gemini');
        $model = $model ?: (config('sunrice.translation.model') ?: config("sunrice.translation.{$driver}.model"));
        $batchSize = (int) config('sunrice.translation.batch_size', 40);
        $timeout = (int) config('sunrice.translation.timeout', 120);
        $key = (string) config("sunrice.translation.{$driver}.key", '');

        return match ($driver) {
            'gemini' => new GeminiTranslator($key, (string) $model, $batchSize, $timeout),
            'openrouter' => new OpenRouterTranslator($key, (string) $model, $batchSize, $timeout),
            default => throw new InvalidArgumentException("Unknown translation driver [{$driver}]. Use gemini or openrouter."),
        };
    }
}
