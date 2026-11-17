<?php

namespace App\Controller;

use App\Entity\MessageContact;
use App\Entity\Societe;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ProfilController extends AbstractController
{
    use JsonApiTrait;

    private const AVATAR_DIR = '/public/uploads/avatars';

    #[Route('/profil', name: 'app_profil', methods: ['GET'])]
    #[Route('/parametres', name: 'app_parametres', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('profil/profil.html.twig', [
            'user' => $this->user(),
            'onglet' => $request->attributes->get('_route') === 'app_parametres' ? 'settings' : ($request->query->get('onglet') ?: 'profile'),
            'sujets' => MessageContact::SUJETS,
            'priorites' => MessageContact::PRIORITES,
        ]);
    }

    /** Onglet "Profil" : modification des informations personnelles / société. */
    #[Route('/api/profil', name: 'api_profil_update', methods: ['POST'])]
    public function update(Request $request, EntityManagerInterface $em, UtilisateurRepository $utilisateurs): JsonResponse
    {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        $user = $this->user();
        $errors = [];

        $email = mb_strtolower(self::str($d, 'email', 180));
        $tel = preg_replace('/[\s.\-()]/', '', self::str($d, 'telephone', 30));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalide';
        } elseif (($o = $utilisateurs->findOneByEmail($email)) && $o !== $user) {
            $errors['email'] = 'Cet email est déjà utilisé';
        }
        if ($tel !== '' && !preg_match('/^\+?\d{8,15}$/', $tel)) {
            $errors['telephone'] = 'Numéro de téléphone invalide';
        } elseif ($tel !== '' && ($o = $utilisateurs->findOneByTelephone($tel)) && $o !== $user) {
            $errors['telephone'] = 'Ce numéro est déjà utilisé';
        }

        if ($user instanceof Societe) {
            $nom = self::str($d, 'nomSociete', 35);
            if (mb_strlen($nom) < 2) {
                $errors['nomSociete'] = 'Nom de société trop court';
            }
        } else {
            $prenom = self::str($d, 'prenom', 100);
            $nom = self::str($d, 'nom', 100);
            if (mb_strlen($prenom) < 2) {
                $errors['prenom'] = 'Prénom trop court';
            }
            if (mb_strlen($nom) < 2) {
                $errors['nom'] = 'Nom trop court';
            }
            $naissance = null;
            if (!empty($d['dateNaissance'])) {
                $naissance = \DateTime::createFromFormat('!Y-m-d', (string) $d['dateNaissance']) ?: null;
                if (!$naissance || $naissance > new \DateTime('-10 years')) {
                    $errors['dateNaissance'] = 'Date de naissance invalide';
                }
            }
        }

        if ($errors) {
            return $this->fail('Veuillez corriger les champs indiqués.', 422, $errors);
        }

        $user->setEmail($email)->setTelephone($tel)->setAdresse(self::str($d, 'adresse', 1000) ?: null);
        if ($user instanceof Societe) {
            $user->setNomSociete($nom);
        } else {
            $user->setPrenom($prenom)->setNom($nom)->setDateNaissance($naissance);
        }
        $em->flush();

        return $this->ok(['message' => 'Profil mis à jour avec succès !', 'nomAffiche' => $user->getNomAffiche()]);
    }

    #[Route('/api/profil/password', name: 'api_profil_password', methods: ['POST'])]
    public function password(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $em): JsonResponse
    {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        $user = $this->user();

        if (!$hasher->isPasswordValid($user, (string) ($d['currentPassword'] ?? ''))) {
            return $this->fail('Le mot de passe actuel est incorrect.', 422, ['currentPassword' => 'Mot de passe incorrect']);
        }
        $new = (string) ($d['newPassword'] ?? '');
        if (!preg_match(SignUpController::PASSWORD_REGEX, $new)) {
            return $this->fail('Le nouveau mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.', 422, ['newPassword' => 'Mot de passe trop faible']);
        }
        if ($new !== ($d['confirmPassword'] ?? null)) {
            return $this->fail('Les mots de passe ne correspondent pas.', 422, ['confirmPassword' => 'Ne correspond pas']);
        }

        $user->setMotDePasse($hasher->hashPassword($user, $new));
        $em->flush();
        return $this->ok(['message' => 'Mot de passe modifié avec succès !']);
    }

    #[Route('/api/profil/avatar', name: 'api_profil_avatar', methods: ['POST'])]
    public function avatar(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertSameOrigin($request);
        $file = $request->files->get('avatar');
        if (!$file || !$file->isValid()) {
            return $this->fail('Aucune image reçue.');
        }
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$file->getMimeType()] ?? null;
        if (!$ext) {
            return $this->fail('Format accepté : JPG, PNG, WEBP ou GIF.');
        }
        if ($file->getSize() > 2 * 1024 * 1024) {
            return $this->fail('Image trop lourde (2 Mo maximum).');
        }

        $user = $this->user();
        $dir = $this->getParameter('kernel.project_dir') . self::AVATAR_DIR;
        $this->supprimerAvatar($user);
        $name = sprintf('u%d-%s.%s', $user->getId(), bin2hex(random_bytes(6)), $ext);
        $file->move($dir, $name);
        $user->setAvatar('/uploads/avatars/' . $name);
        $em->flush();

        return $this->ok(['avatar' => $user->getAvatar(), 'message' => 'Photo de profil mise à jour !']);
    }

    #[Route('/api/profil/preferences', name: 'api_profil_preferences', methods: ['POST'])]
    public function preferences(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        $user = $this->user();
        $prefs = $user->getPreferences();
        foreach (['emailNotifications', 'pushNotifications', 'darkMode', 'alertesEmail', 'alertesSms'] as $k) {
            if (array_key_exists($k, $d)) {
                $prefs[$k] = (bool) $d[$k];
            }
        }
        if (!empty($d['alertesSms']) && !\App\Robot\AlerteService::telephone($user)) {
            return $this->fail('Ajoutez d\'abord un numéro de téléphone valide dans votre profil pour recevoir les SMS.');
        }
        if (isset($d['langue']) && \App\Langue\Langues::valide($d['langue'])) {
            $prefs['langue'] = $d['langue'];
            $request->getSession()->set(\App\Langue\Langues::SESSION, $d['langue']);
        }
        if (array_key_exists('newsletter', $d)) {
            $user->setNewsletter((bool) $d['newsletter']);
        }
        $user->setPreferences($prefs);
        $em->flush();
        return $this->ok(['preferences' => $prefs, 'message' => 'Paramètres sauvegardés !']);
    }

    /** Zone dangereuse : suppression définitive du compte (+ terrains, machines... en cascade). */
    #[Route('/api/profil', name: 'api_profil_delete', methods: ['DELETE'])]
    public function delete(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        if (($d['confirmation'] ?? '') !== 'SUPPRIMER') {
            return $this->fail('Tapez SUPPRIMER pour confirmer.');
        }
        $user = $this->user();
        if (!$hasher->isPasswordValid($user, (string) ($d['password'] ?? ''))) {
            return $this->fail('Mot de passe incorrect.', 422);
        }

        $this->supprimerAvatar($user);
        $em->remove($user);
        $em->flush();

        // Déconnexion
        $this->container->get('security.token_storage')->setToken(null);
        $request->getSession()->invalidate();

        return $this->ok(['redirect' => $this->generateUrl('app_home_no')]);
    }

    /** Onglet "Contact" : message à l'administration (visible dans le panneau admin). */
    #[Route('/api/contact', name: 'api_contact', methods: ['POST'])]
    public function contact(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        $sujet = (string) ($d['sujet'] ?? '');
        $priorite = (string) ($d['priorite'] ?? 'medium');
        $message = self::str($d, 'message', 5000);

        if (!isset(MessageContact::SUJETS[$sujet])) {
            return $this->fail('Choisissez un sujet.');
        }
        if (mb_strlen($message) < 10) {
            return $this->fail('Le message doit contenir au moins 10 caractères.');
        }

        $em->persist((new MessageContact())
            ->setUtilisateur($this->user())
            ->setSujet($sujet)
            ->setPriorite(isset(MessageContact::PRIORITES[$priorite]) ? $priorite : 'medium')
            ->setMessage($message));
        $em->flush();

        return $this->ok(['message' => 'Message envoyé avec succès ! Nous vous répondrons sous 24h.']);
    }

    private function supprimerAvatar(Utilisateur $user): void
    {
        $old = $user->getAvatar();
        if ($old && str_starts_with($old, '/uploads/avatars/')) {
            $path = $this->getParameter('kernel.project_dir') . '/public' . $old;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function user(): Utilisateur
    {
        /** @var Utilisateur $u */
        $u = $this->getUser();
        return $u;
    }
}
