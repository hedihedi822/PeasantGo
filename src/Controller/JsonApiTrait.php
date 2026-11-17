<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Petites aides communes aux endpoints JSON appelés par le JavaScript des pages.
 */
trait JsonApiTrait
{
    /**
     * Lit le corps de la requête : JSON (fetch + JSON.stringify) ou formulaire (FormData).
     */
    private function payload(Request $request): array
    {
        if (str_contains((string) $request->headers->get('Content-Type'), 'application/json')) {
            $content = $request->getContent();
            if ($content === '') {
                return [];
            }
            try {
                $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new BadRequestHttpException('JSON invalide');
            }
            return is_array($data) ? $data : [];
        }
        return $request->request->all();
    }

    /**
     * Protection CSRF des appels fetch() : on refuse les requêtes qui viennent d'un autre site.
     * Les cookies de session sont en SameSite=Lax et le JS envoie l'en-tête X-Requested-With.
     */
    private function assertSameOrigin(Request $request): void
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && $origin !== $request->getSchemeAndHttpHost()) {
            throw new AccessDeniedHttpException('Origine non autorisée');
        }
        if ($request->headers->get('X-Requested-With') !== 'XMLHttpRequest') {
            throw new AccessDeniedHttpException('Requête non autorisée');
        }
    }

    private function ok(array $data = [], int $status = 200): JsonResponse
    {
        return new JsonResponse(['success' => true] + $data, $status);
    }

    private function fail(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return new JsonResponse(['success' => false, 'message' => $message, 'errors' => (object) $errors], $status);
    }

    private static function str(array $data, string $key, int $max = 255): string
    {
        return mb_substr(trim((string) ($data[$key] ?? '')), 0, $max);
    }
}
