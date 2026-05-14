<?php

declare(strict_types=1);

namespace App\Services\Dto;

/**
 * SPEC-LLM-001 — Result of LlmDiscoveryService::discoverModels().
 *
 * @MX:NOTE: readonly DTO; serverType is one of openai-compatible|ollama-native|lmstudio.
 */
final readonly class DiscoveryResult
{
    /**
     * @param  list<string>  $models
     * @param  list<string>  $loaded
     */
    public function __construct(
        public array $models,
        public array $loaded,
        public string $serverType,
        public ?string $errorCode = null,
    ) {}
}
