<?php

namespace App\Controller;

use App\Entity\RegularUtilisateur;
use App\Entity\Societe;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class SignUpController extends AbstractController
{
    use JsonApiTrait;

    public const PASSWORD_REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/';

    #[Route('/sign/up', name: 'app_sign_up', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home_user');
        }
        return $this->render('sign_up/sign_up.html.twig');
    }

    /**
     * Inscription appelée en fetch() par sign_up.html.twig.
     * Répond { success: true } ou { success: false, message, errors: {champ: message} }.
     */
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UtilisateurRepository $utilisateurs,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        NotificationService $notifications,
    ): JsonResponse {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        $isCompany = ($d['accountType'] ?? 'person') === 'company';
        $errors = [];

        $email = mb_strtolower(self::str($d, 'email', 180));
        $telephone = preg_replace('/[\s.\-()]/', '', self::str($d, 'telephone', 30));
        $password = (string) ($d['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Veuillez entrer une adresse email valide';
        } elseif ($utilisateurs->findOneByEmail($email)) {
            $errors['email'] = 'Un compte existe déjà avec cet email';
        }
        if (!preg_match('/^\+?\d{8,15}$/', $telephone)) {
            $errors['telephone'] = 'Le numéro de téléphone est invalide (ex: +21690123456)';
        } elseif ($utilisateurs->findOneByTelephone($telephone)) {
            $errors['telephone'] = 'Ce numéro est déjà utilisé';
        }
        if (!preg_match(self::PASSWORD_REGEX, $password)) {
            $errors['password'] = 'Au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial';
        }
        if (($d['confirmPassword'] ?? null) !== $password) {
            $errors['confirmPassword'] = 'Les mots de passe ne correspondent pas';
        }
        if (empty($d['termsConditions'])) {
            $errors['termsConditions'] = 'Vous devez accepter les termes et conditions';
        }

        if ($isCompany) {
            $user = new Societe();
            $nomSociete = self::str($d, 'companyName', 35);
            $taxCode = strtoupper(self::str($d, 'taxCode', 20));
            $siret = self::str($d, 'siret', 14);
            $code = strtoupper(self::str($d, 'companyCode', 20));
            $repo = $em->getRepository(Societe::class);

            if (mb_strlen($nomSociete) < 2) {
                $errors['companyName'] = 'Le nom de la société doit contenir au moins 2 caractères';
            }
            if (!preg_match('/^\d{7}\/[A-Z]\/\d{3}$/', $taxCode)) {
                $errors['taxCode'] = 'Le code fiscal doit suivre le format 1234567/A/000';
            } elseif ($repo->findOneBy(['codeFiscal' => $taxCode])) {
                $errors['taxCode'] = 'Ce code fiscal est déjà enregistré';
            }
            if (!preg_match('/^\d{14}$/', $siret)) {
                $errors['siret'] = 'Le numéro SIRET doit contenir 14 chiffres';
            } elseif ($repo->findOneBy(['numeroSiret' => $siret])) {
                $errors['siret'] = 'Ce numéro SIRET est déjà enregistré';
            }
            if (!preg_match('/^[A-Z0-9]{3,20}$/', $code)) {
                $errors['companyCode'] = 'Le code société doit contenir 3 à 20 lettres ou chiffres';
            } elseif ($repo->findOneBy(['codeSociete' => $code])) {
                $errors['companyCode'] = 'Ce code société est déjà utilisé';
            }

            if (!$errors) {
                $user->setNomSociete($nomSociete)->setCodeFiscal($taxCode)->setNumeroSiret($siret)->setCodeSociete($code);
            }
        } else {
            $user = new RegularUtilisateur();
            $prenom = self::str($d, 'prenom', 100);
            $nom = self::str($d, 'nom', 100);
            $cin = self::str($d, 'cin', 8);

            if (mb_strlen($prenom) < 2) {
                $errors['prenom'] = 'Le prénom doit contenir au moins 2 caractères';
            }
            if (mb_strlen($nom) < 2) {
                $errors['nom'] = 'Le nom doit contenir au moins 2 caractères';
            }
            if (!preg_match('/^\d{8}$/', $cin)) {
                $errors['cin'] = 'Le CIN doit contenir exactement 8 chiffres';
            } elseif ($utilisateurs->findOneBy(['cin' => (int) $cin])) {
                $errors['cin'] = 'Ce CIN est déjà enregistré';
            }

            if (!$errors) {
                $user->setPrenom($prenom)->setNom($nom)->setCin((int) $cin);
            }
        }

        if ($errors) {
            return $this->fail('Veuillez corriger les champs indiqués.', 422, $errors);
        }

        /** @var Utilisateur $user */
        $user->setEmail($email)
            ->setTelephone($telephone)
            ->setNewsletter(!empty($d['newsletter']));
        $user->setMotDePasse($hasher->hashPassword($user, $password));

        $em->persist($user);
        $notifications->envoyerA($user, 'compte', 'Bienvenue sur PeasantGo.AI !',
            "Votre compte est prêt. Ajoutez votre premier terrain dans « Mes terrains », entrez-y et le robot calcule pour chaque culture la réussite, la production, le coût, le prix et le bénéfice chez vous. Les nouvelles statistiques agricoles arriveront ici.",
            '/mes-terrains');
        $em->flush();

        $this->addFlash('success', 'Votre compte a été créé. Vous pouvez maintenant vous connecter.');

        return $this->ok(['redirect' => $this->generateUrl('app_login')], 201);
    }
}
