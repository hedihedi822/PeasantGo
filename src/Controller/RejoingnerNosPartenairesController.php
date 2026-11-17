<?php

namespace App\Controller;

use App\Entity\DemandePartenariat;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RejoingnerNosPartenairesController extends AbstractController
{
    use JsonApiTrait;

    private const AIDES = ['promotion', 'content', 'feedback', 'technical', 'other'];
    private const SERVICES = ['service1', 'service2', 'service3', 'service4', 'service5', 'service6'];
    private const SOURCES = ['social', 'friend', 'search', 'event', 'other'];

    #[Route('/rejoingner/nos/partenaires', name: 'app_rejoingner_nos_partenaires', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('rejoingner_nos_partenaires/rejoingner_nos_partenaires.html.twig');
    }

    #[Route('/api/partenariat', name: 'api_partenariat', methods: ['POST'])]
    public function envoyer(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $email = mb_strtolower(self::str($d, 'email', 180));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Veuillez entrer une adresse email valide.', 422, ['email' => 'Email invalide']);
        }
        foreach (['facebook', 'instagram'] as $k) {
            $v = self::str($d, $k);
            if ($v !== '' && !filter_var($v, FILTER_VALIDATE_URL)) {
                return $this->fail("Le lien $k n'est pas une URL valide.", 422, [$k => 'URL invalide']);
            }
        }
        $aides = array_values(array_intersect((array) ($d['help_method'] ?? []), self::AIDES));
        $services = array_values(array_intersect((array) ($d['free_services'] ?? []), self::SERVICES));
        if (!$aides && self::str($d, 'other_help') === '') {
            return $this->fail('Indiquez au moins une façon dont vous pouvez nous aider.', 422, ['help_method' => 'Obligatoire']);
        }

        $dp = (new DemandePartenariat())
            ->setUtilisateur($user)
            ->setEmail($email)
            ->setTelephone(self::str($d, 'phone', 30) ?: null)
            ->setFacebook(self::str($d, 'facebook') ?: null)
            ->setInstagram(self::str($d, 'instagram') ?: null)
            ->setWhatsapp(self::str($d, 'whatsapp', 30) ?: null)
            ->setAutreContact(self::str($d, 'other_contact') ?: null)
            ->setMethodesAide($aides)
            ->setAutreAide(self::str($d, 'other_help', 2000) ?: null)
            ->setServicesGratuits($services)
            ->setAPropos(self::str($d, 'about', 3000) ?: null)
            ->setSource(in_array($d['referral_source'] ?? null, self::SOURCES, true) ? $d['referral_source'] : null);

        $em->persist($dp);
        $em->flush();

        return $this->ok(['id' => $dp->getId()], 201);
    }
}
