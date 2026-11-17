<?php

namespace App\Langue;

use App\Entity\Utilisateur;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Langue de l'interface : français (par défaut), arabe (de droite à gauche) ou anglais.
 * Ordre : choix enregistré en session → cookie (visiteurs) → préférence du compte (reprise à la connexion) → français.
 * Les textes à traduire sont dans translations/messages.ar.yaml et messages.en.yaml (clé = texte français).
 */
final class Langues
{
    public function __construct(private \Doctrine\ORM\EntityManagerInterface $em)
    {
    }

    public const DISPONIBLES = ['fr' => 'Français', 'ar' => 'العربية', 'en' => 'English'];
    public const SESSION = 'pg_langue';
    public const COOKIE = 'pg_langue';

    public static function valide(?string $code): ?string
    {
        return isset(self::DISPONIBLES[(string) $code]) ? $code : null;
    }

    /** Avant le LocaleListener de Symfony (priorité 16) pour que le traducteur prenne la bonne langue. */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
    public function surRequete(RequestEvent $event): void
    {
        $r = $event->getRequest();
        $langue = null;
        if ($r->hasPreviousSession()) {
            $langue = self::valide($r->getSession()->get(self::SESSION));
        }
        $langue ??= self::valide($r->cookies->get(self::COOKIE)) ?? 'fr';
        $r->setLocale($langue);
        $r->attributes->set('_locale', $langue);
    }

    /**
     * À la connexion : la langue choisie sur cet appareil (cookie) l'emporte et est enregistrée dans le compte ;
     * sans choix sur l'appareil, on reprend celle des paramètres du compte.
     */
    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function surConnexion(LoginSuccessEvent $event): void
    {
        $u = $event->getUser();
        if (!$u instanceof Utilisateur) {
            return;
        }
        $r = $event->getRequest();
        $choix = self::valide($r->cookies->get(self::COOKIE));
        if ($choix) {
            if (($u->getPreferences()['langue'] ?? null) !== $choix) {
                $u->setPreferences(['langue' => $choix] + $u->getPreferences());
                $this->em->flush();
            }
        } else {
            $choix = self::valide($u->getPreferences()['langue'] ?? null) ?? 'fr';
        }
        $r->getSession()->set(self::SESSION, $choix);
    }

    public static function appliquer(Request $r, Response $response, string $langue): void
    {
        $r->getSession()->set(self::SESSION, $langue);
        $response->headers->setCookie(Cookie::create(self::COOKIE, $langue, new \DateTime('+1 year'), '/', null, $r->isSecure(), true, false, Cookie::SAMESITE_LAX));
    }
}
