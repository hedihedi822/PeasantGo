<?php

namespace App\Security;

use App\Entity\Admin;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Formulaire de connexion (/login) :
 *  - accountType = "person"  → identifiant = email (_email)
 *  - accountType = "company" → identifiant = code société (_company_code)
 * Le mot de passe est vérifié par Symfony (hash "auto").
 */
class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UtilisateurRepository $utilisateurs,
        private EntityManagerInterface $em,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $isCompany = $request->request->get('accountType') === 'company';
        $identifier = trim((string) $request->request->get($isCompany ? '_company_code' : '_email', ''));
        $password = (string) $request->request->get('_password', '');

        if ($identifier === '' || $password === '') {
            throw new CustomUserMessageAuthenticationException('Veuillez remplir tous les champs.');
        }

        // Garde l'identifiant saisi pour le réafficher en cas d'erreur
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $identifier);
        $request->getSession()->set('login_account_type', $isCompany ? 'company' : 'person');

        $badges = [new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token'))];
        if ($request->request->getBoolean('_remember_me')) {
            $badges[] = (new RememberMeBadge())->enable();
        }

        return new Passport(
            new UserBadge($identifier, function (string $id) use ($isCompany): Utilisateur {
                $user = $isCompany
                    ? $this->utilisateurs->findSocieteByCode($id)
                    : (str_contains($id, '@')
                        ? $this->utilisateurs->findOneByEmail($id)
                        // les comptes de l'équipe peuvent aussi se connecter avec leur nom (ex. « PeasantGo.AI »)
                        : $this->em->getRepository(\App\Entity\Admin::class)->findOneBy(['nom' => $id]));

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
                }
                return $user;
            }),
            new PasswordCredentials($password),
            $badges
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof Utilisateur) {
            $user->setLastLoginAt(new \DateTime());
            $this->em->flush();
        }

        // connexion depuis la page des formateurs, ou formateur qui n'a pas de terrain : son espace
        if ($request->request->get('_espace') === 'formateur'
            || ($user instanceof Utilisateur && !$user instanceof Admin && $user->isFormateur() && $user->getTerrains()->isEmpty())) {
            return new RedirectResponse($this->urlGenerator->generate('app_espace_formateur'));
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate(
            $user instanceof Admin ? 'app_admin' : 'app_choix_terrain'
        ));
    }

    /** Non connecté : redirection vers /login, sauf pour les appels fetch() vers /api (réponse 401 JSON). */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse(['success' => false, 'message' => 'Session expirée, veuillez vous reconnecter.'], 401);
        }
        return parent::start($request, $authException);
    }

    protected function getLoginUrl(Request $request): string
    {
        // en cas d'erreur, le formateur revient sur sa propre page de connexion
        if ($request->request->get('_espace') === 'formateur') {
            return $this->urlGenerator->generate('app_formateur_login');
        }
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
