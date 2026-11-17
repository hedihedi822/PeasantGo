<?php

namespace App\IA;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client minimal de l'API Gemini (Google AI Studio) – https://ai.google.dev
 * Clé : GEMINI_API_KEY ; modèle : GEMINI_MODEL (par défaut l'alias « gemini-flash-latest »).
 * Toutes les réponses sont demandées en JSON (responseMimeType = application/json).
 */
class GeminiClient
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(default::GEMINI_API_KEY)%')] private ?string $apiKey = null,
        #[Autowire('%env(default::GEMINI_MODEL)%')] private ?string $modele = null,
    ) {
    }

    public function estDisponible(): bool
    {
        return (bool) $this->apiKey;
    }

    public function getModele(): string
    {
        return $this->modele ?: 'gemini-flash-latest';
    }

    /**
     * @param list<array{mime: string, data: string}> $images images brutes (octets) jointes à la question
     * @return array<mixed> réponse JSON décodée
     */
    public function json(string $consigne, string $question, array $images = []): array
    {
        if (!$this->estDisponible()) {
            throw new \LogicException('GEMINI_API_KEY non configurée');
        }
        $parts = [['text' => $question]];
        foreach ($images as $img) {
            $parts[] = ['inline_data' => ['mime_type' => $img['mime'], 'data' => base64_encode($img['data'])]];
        }

        $res = $this->http->request('POST', sprintf(self::URL, rawurlencode($this->getModele())), [
            'headers' => ['x-goog-api-key' => $this->apiKey],
            'json' => [
                'systemInstruction' => ['parts' => [['text' => $consigne]]],
                'contents' => [['role' => 'user', 'parts' => $parts]],
                'generationConfig' => ['responseMimeType' => 'application/json'],
            ],
            'timeout' => 90,
        ])->toArray();

        $texte = '';
        foreach ($res['candidates'][0]['content']['parts'] ?? [] as $p) {
            $texte .= $p['text'] ?? '';
        }
        $texte = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($texte)));
        $data = json_decode($texte, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Réponse Gemini illisible : ' . mb_substr($texte, 0, 200));
        }
        return $data;
    }
}
