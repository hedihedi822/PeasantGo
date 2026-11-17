<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mot de passe oublié en 3 étapes :
 *  1. /api/password/send-code   : génère un code à 6 chiffres (valable 5 min) et l'envoie par email
 *  2. /api/password/verify-code : vérifie le code (5 essais max)
 *  3. /api/password/reset       : enregistre le nouveau mot de passe
 * L'état est gardé en session (id de l'utilisateur + code vérifié).
 */
final class PasswordforgettenController extends AbstractController
{
    use JsonApiTrait;

    private const SESSION_USER = 'reset_user_id';
    private const SESSION_VERIFIED = 'reset_verified';
    private const CODE_TTL = 300;
    private const MAX_ATTEMPTS = 5;

    #[Route('/password/forgetten', name: 'app_passwordforgetten', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('passwordforgetten/passwordforgetten.html.twig');
    }

    #[Route('/api/password/send-code', name: 'api_password_send_code', methods: ['POST'])]
    public function sendCode(
        Request $request,
        UtilisateurRepository $utilisateurs,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        LoggerInterface $logger,
    ): JsonResponse {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        $method = ($d['method'] ?? 'email') === 'phone' ? 'phone' : 'email';
        $contact = self::str($d, 'contact', 180);

        $user = $method === 'email' ? $utilisateurs->findOneByEmail($contact) : $utilisateurs->findOneByTelephone($contact);

        $session = $request->getSession();
        $session->remove(self::SESSION_VERIFIED);

        // Réponse identique que le compte existe ou non (évite de révéler les comptes existants)
        $response = ['message' => 'Si un compte correspond, un code de vérification a été envoyé.'];

        if ($user instanceof Utilisateur) {
            $code = (string) random_int(100000, 999999);
            $user->setResetCode(password_hash($code, PASSWORD_DEFAULT), new \DateTime('+' . self::CODE_TTL . ' seconds'));
            $em->flush();
            $session->set(self::SESSION_USER, $user->getId());

            // Pas de passerelle SMS configurée : le code part toujours sur l'email du compte.
            $mailer->send((new Email())
                ->to($user->getEmail())
                ->subject('PeasantGo.AI - Code de vérification')
                ->text("Bonjour,\n\nVotre code de réinitialisation est : $code\nIl expire dans 5 minutes.\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n\nL'équipe PeasantGo.AI"));

            $logger->info('Code de réinitialisation envoyé', ['user' => $user->getId()]);

            // En développement (MAILER_DSN=null://null) le code est renvoyé pour pouvoir tester.
            if ($this->getParameter('kernel.environment') === 'dev') {
                $response['devCode'] = $code;
            }
        } else {
            $session->remove(self::SESSION_USER);
        }

        return $this->ok($response + ['expiresIn' => self::CODE_TTL]);
    }

    #[Route('/api/password/verify-code', name: 'api_password_verify_code', methods: ['POST'])]
    public function verifyCode(Request $request, UtilisateurRepository $utilisateurs, EntityManagerInterface $em): JsonResponse
    {
        $this->assertSameOrigin($request);
        $code = preg_replace('/\D/', '', (string) ($this->payload($request)['code'] ?? ''));
        $session = $request->getSession();
        $user = ($id = $session->get(self::SESSION_USER)) ? $utilisateurs->find($id) : null;

        if (!$user || !$user->getResetCodeHash()) {
            return $this->fail('Code incorrect ou expiré. Demandez un nouveau code.');
        }
        if ($user->getResetCodeExpiresAt() < new \DateTime() || $user->getResetAttempts() >= self::MAX_ATTEMPTS) {
            $user->setResetCode(null, null);
            $em->flush();
            return $this->fail('Le code a expiré ou trop d\'essais. Demandez un nouveau code.', 410);
        }
        if (!password_verify($code, $user->getResetCodeHash())) {
            $user->incrementResetAttempts();
            $em->flush();
            return $this->fail('Code incorrect. Veuillez réessayer.');
        }

        $session->set(self::SESSION_VERIFIED, true);
        return $this->ok();
    }

    #[Route('/api/password/reset', name: 'api_password_reset', methods: ['POST'])]
    public function reset(
        Request $request,
        UtilisateurRepository $utilisateurs,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        $session = $request->getSession();
        $user = ($id = $session->get(self::SESSION_USER)) ? $utilisateurs->find($id) : null;

        if (!$user || !$session->get(self::SESSION_VERIFIED) || !$user->getResetCodeHash() || $user->getResetCodeExpiresAt() < new \DateTime('-10 minutes')) {
            return $this->fail('Session de réinitialisation expirée. Recommencez.', 403);
        }

        $password = (string) ($d['password'] ?? '');
        if (!preg_match(SignUpController::PASSWORD_REGEX, $password)) {
            return $this->fail('Le mot de passe ne respecte pas les exigences.');
        }
        if (($d['confirmPassword'] ?? null) !== $password) {
            return $this->fail('Les mots de passe ne correspondent pas.');
        }

        $user->setMotDePasse($hasher->hashPassword($user, $password));
        $user->setResetCode(null, null);
        $em->flush();
        $session->remove(self::SESSION_USER);
        $session->remove(self::SESSION_VERIFIED);

        $this->addFlash('success', 'Mot de passe mis à jour. Connectez-vous avec votre nouveau mot de passe.');
        return $this->ok(['redirect' => $this->generateUrl('app_login')]);
    }
}
