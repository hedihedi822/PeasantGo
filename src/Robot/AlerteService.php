<?php

namespace App\Robot;

use App\Entity\AlerteEstimation;
use App\Entity\CodeTerrain;
use App\Entity\EstimationTerrain;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Alertes importantes du robot, par email et/ou SMS, UNIQUEMENT si l'utilisateur les a activées
 * dans ses paramètres (« Alertes du robot par email / par SMS »). Rien n'est mis dans la cloche.
 * Chaque alerte a une clé : la même alerte n'est jamais envoyée deux fois.
 */
class AlerteService
{
    /** baisse de prix (ou de réussite) qui déclenche une alerte sur une culture prévue */
    private const SEUIL_BAISSE = 0.15;

    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private TexterInterface $texter,
        private UrlGeneratorInterface $urls,
        private LoggerInterface $logger,
    ) {
    }

    public static function canaux(Utilisateur $u): array
    {
        $p = $u->getPreferences();
        $c = [];
        if (!empty($p['alertesEmail'])) {
            $c[] = 'email';
        }
        if (!empty($p['alertesSms']) && self::telephone($u)) {
            $c[] = 'sms';
        }
        return $c;
    }

    /**
     * Compare la nouvelle analyse d'un terrain à la précédente et prévient l'agriculteur de ce qui compte.
     * @param EstimationTerrain[] $ancien
     * @param EstimationTerrain[] $nouveau
     */
    public function apresAnalyse(CodeTerrain $t, array $ancien, array $nouveau, array $meteo): void
    {
        $u = $t->getProprietaire();
        if (!$u || !self::canaux($u)) {
            return;
        }
        $parCle = fn (array $l) => array_combine(array_map(fn (EstimationTerrain $e) => $e->getCultureCle(), $l), $l);
        $avant = $ancien ? $parCle($ancien) : [];
        $apres = $parCle($nouveau);
        $mois = date('Ym');

        // 1. La meilleure culture a changé
        $top = fn (array $l) => array_values(array_filter($l, fn (EstimationTerrain $e) => $e->isRecommande() && !Referentiel::estAnimal($e->getCultureCle())))[0] ?? null;
        $tAvant = $top($ancien);
        $tApres = $top($nouveau);
        if ($tAvant && $tApres && $tAvant->getCultureCle() !== $tApres->getCultureCle()) {
            $this->envoyer($u, $t, "reco-{$t->getCodeTerrain()}-{$tApres->getCultureCle()}-$mois",
                sprintf('%s : %s devient la culture la plus rentable', $t->getNom(), $tApres->getCultureNom()),
                sprintf('Avec les dernières données, %s est estimée à %s DT de bénéfice (%d %% de réussite), devant %s (%s DT). Ouvrez PeasantGo.AI pour voir le détail.',
                    $tApres->getCultureNom(), number_format($tApres->getBenefice(), 0, ',', ' '), round($tApres->getTauxReussite()),
                    $tAvant->getCultureNom(), number_format($apres[$tAvant->getCultureCle()]?->getBenefice() ?? $tAvant->getBenefice(), 0, ',', ' ')));
        }

        // 2. Cultures prévues / en cours : prix ou réussite en forte baisse, bénéfice qui devient négatif
        $semaine = date('oW');
        foreach ($this->culturesSuivies($t) as $cle => $nom) {
            $a = $avant[$cle] ?? null;
            $n = $apres[$cle] ?? null;
            if (!$a || !$n) {
                continue;
            }
            $raisons = [];
            if ($a->getPrix() > 0 && $n->getPrix() < $a->getPrix() * (1 - self::SEUIL_BAISSE)) {
                $raisons[] = sprintf('le prix estimé passe de %s à %s DT/kg (offre en hausse dans la zone)', number_format($a->getPrix(), 2, ',', ' '), number_format($n->getPrix(), 2, ',', ' '));
            }
            if ($n->getTauxReussite() < $a->getTauxReussite() - self::SEUIL_BAISSE * 100) {
                $raisons[] = sprintf('la réussite estimée passe de %d %% à %d %%', round($a->getTauxReussite()), round($n->getTauxReussite()));
            }
            if ($a->getBenefice() > 0 && $n->getBenefice() < 0) {
                $raisons[] = 'le bénéfice estimé devient négatif';
            }
            if ($raisons) {
                $this->envoyer($u, $t, "marche-{$t->getCodeTerrain()}-$cle-$semaine",
                    sprintf('Attention %s sur « %s »', $nom, $t->getNom()),
                    ucfirst(implode(' ; ', $raisons)) . '. Il est encore temps de revoir votre plan.');
            }
        }

        // 3. Météo des 3 prochains jours pour ce qui est en terre ou en élevage
        $suivies = $this->culturesSuivies($t, true);
        foreach (array_slice($meteo, 0, 3) as $j) {
            foreach ($suivies as $cle => $nom) {
                $ref = Referentiel::get($cle);
                $quoi = null;
                if (isset($ref['tGel']) && $j['tmin'] < $ref['tGel']) {
                    $quoi = sprintf('gel prévu (%.1f °C)', $j['tmin']);
                } elseif ($j['tmax'] > $ref['tChaleur']) {
                    $quoi = sprintf('forte chaleur prévue (%.1f °C)', $j['tmax']);
                } elseif ($j['pluie'] > 40) {
                    $quoi = sprintf('très forte pluie prévue (%d mm)', $j['pluie']);
                }
                if ($quoi) {
                    $date = (new \DateTime($j['date']))->format('d/m');
                    $this->envoyer($u, $t, "meteo-{$t->getCodeTerrain()}-$cle-{$j['date']}",
                        sprintf('%s le %s sur « %s »', ucfirst($quoi), $date, $t->getNom()),
                        sprintf('%s : %s le %s. Protégez votre %s (%s).', $t->getNom(), $quoi, $date, mb_strtolower($nom), Referentiel::estAnimal($cle) ? 'ventilation, eau, ombre' : 'irrigation, voile, report des traitements'));
                }
            }
        }
        $this->em->flush();
    }

    /** Envoie une alerte (une seule fois par clé). @return list<string> canaux utilisés */
    public function envoyer(Utilisateur $u, ?CodeTerrain $t, string $cle, string $titre, string $message, ?string $lien = null): array
    {
        $canaux = self::canaux($u);
        if (!$canaux) {
            return [];
        }
        $cle = mb_substr($cle, 0, 150);
        if ($this->em->getRepository(AlerteEstimation::class)->findOneBy(['utilisateur' => $u, 'cle' => $cle])) {
            return [];
        }
        $faits = [];
        if (in_array('email', $canaux, true)) {
            try {
                $this->mailer->send((new TemplatedEmail())
                    ->to($u->getEmail())
                    ->subject('PeasantGo.AI – ' . $titre)
                    ->htmlTemplate('emails/notification.html.twig')
                    ->context([
                        'nom' => $u->getNomAffiche(),
                        'titre' => $titre,
                        'message' => $message,
                        'lien' => $this->urls->generate('app_home_user', [], UrlGeneratorInterface::ABSOLUTE_URL),
                        'parametres' => $this->urls->generate('app_parametres', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    ]));
                $faits[] = 'email';
            } catch (\Throwable $e) {
                $this->logger->error('Alerte email non envoyée', ['user' => $u->getId(), 'erreur' => $e->getMessage()]);
            }
        }
        if (in_array('sms', $canaux, true)) {
            try {
                $this->texter->send(new SmsMessage(self::telephone($u), mb_substr('PeasantGo.AI : ' . $titre . '. ' . $message, 0, 300)));
                $faits[] = 'sms';
            } catch (\Throwable $e) {
                $this->logger->error('Alerte SMS non envoyée', ['user' => $u->getId(), 'erreur' => $e->getMessage()]);
            }
        }
        $this->em->persist(new AlerteEstimation($u, $t, $cle, mb_substr($titre, 0, 150), $message, $faits));
        $this->logger->info('Alerte du robot', ['user' => $u->getId(), 'cle' => $cle, 'canaux' => $faits]);
        return $faits;
    }

    /** Numéro au format international (+216 par défaut pour un numéro tunisien à 8 chiffres). */
    public static function telephone(Utilisateur $u): ?string
    {
        $n = preg_replace('/[^\d+]/', '', (string) $u->getTelephone());
        if ($n === '') {
            return null;
        }
        if (str_starts_with($n, '00')) {
            $n = '+' . substr($n, 2);
        }
        if (preg_match('/^\d{8}$/', $n)) {
            $n = '+216' . $n;
        }
        return preg_match('/^\+\d{8,15}$/', $n) ? $n : null;
    }

    /**
     * Cultures / élevages prévus ou en cours sur le terrain.
     * @return array<string, string> clé => nom
     */
    private function culturesSuivies(CodeTerrain $t, bool $enTerreSeulement = false): array
    {
        $out = [];
        foreach ($t->getCultivations() as $c) {
            if (!$c->isActif() || $c->getStatut() === 'termine' || !Referentiel::existe($c->getCultureCle())) {
                continue;
            }
            if ($enTerreSeulement && $c->getDateDebut() > new \DateTime('+3 days')) {
                continue;
            }
            $out[$c->getCultureCle()] = $c->getCultureNom();
        }
        return $out;
    }
}
