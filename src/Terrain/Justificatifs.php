<?php

namespace App\Terrain;

use App\Entity\CodeTerrain;
use App\Entity\Utilisateur;
use App\IA\GeminiClient;
use App\Repository\ZoneRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Justificatif de propriété / de location d'un terrain.
 *
 * Le fichier est gardé hors du dossier public (var/justificatifs) : seuls le propriétaire et l'équipe le voient.
 * Le robot fait une PRÉ-vérification :
 *  - avec l'IA (Gemini) : lecture du document (type, titulaire, CIN, surface, lieu, n° de titre, date) et comparaison
 *    avec le compte ; tout est cohérent → « pré-vérifié par le robot » ; sinon → « en attente » pour l'équipe ;
 *  - une terre = un seul compte : si un autre compte a déclaré la même terre (même fichier, même n° de titre foncier
 *    ou contours qui se chevauchent), c'est le document le plus récent qui garde la terre ; l'autre terrain est
 *    retiré (refusé avec le motif) et les deux agriculteurs sont prévenus.
 * Un terrain dont le justificatif est accepté ou en cours d'examen n'en demande pas d'autre.
 * L'équipe peut toujours valider ou refuser.
 */
class Justificatifs
{
    public const TAILLE_MAX = 8 * 1024 * 1024;
    public const TYPES_MIME = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic'];

    public function __construct(
        private EntityManagerInterface $em,
        private GeminiClient $gemini,
        private ZoneRepository $zones,
        private NotificationService $notifications,
        private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/var/justificatifs')] private string $dossier,
    ) {
    }

    /** @throws \InvalidArgumentException message à afficher */
    public function enregistrer(CodeTerrain $t, string $type, ?UploadedFile $f): void
    {
        if ($t->getJustificatifFichier() && $t->isUtilisable()) {
            throw new \InvalidArgumentException('Ce terrain a déjà son justificatif : inutile d\'en envoyer un autre.');
        }
        if (!$f || !$f->isValid()) {
            throw new \InvalidArgumentException('Joignez le certificat de propriété ou le contrat de location (PDF ou photo).');
        }
        if ($f->getSize() > self::TAILLE_MAX) {
            throw new \InvalidArgumentException('Le fichier dépasse 8 Mo : prenez une photo moins lourde ou un PDF.');
        }
        $mime = $f->getMimeType() ?? '';
        if (!isset(self::TYPES_MIME[$mime])) {
            throw new \InvalidArgumentException('Format accepté : PDF, JPG, PNG ou WEBP.');
        }
        if (!isset(CodeTerrain::JUSTIFICATIFS[$type])) {
            throw new \InvalidArgumentException('Choisissez le type de justificatif.');
        }
        if (!is_dir($this->dossier)) {
            mkdir($this->dossier, 0750, true);
        }
        $hash = hash_file('sha256', $f->getPathname());
        $nom = bin2hex(random_bytes(16)) . '.' . self::TYPES_MIME[$mime];
        $f->move($this->dossier, $nom);

        $ancien = $t->getJustificatifFichier();
        $t->setJustificatif($type, $nom, $mime);
        $t->setJustificatifStatut('en_attente', ['hash' => $hash, 'nomOriginal' => mb_substr($f->getClientOriginalName(), 0, 120)]);
        if ($ancien && $ancien !== $nom && is_file($this->dossier . '/' . $ancien)) {
            @unlink($this->dossier . '/' . $ancien);
        }
    }

    public function chemin(CodeTerrain $t): ?string
    {
        $f = $t->getJustificatifFichier();
        return $f && is_file($this->dossier . '/' . basename($f)) ? $this->dossier . '/' . basename($f) : null;
    }

    /** Pré-vérification par le robot (à appeler après flush : le terrain doit avoir un id). */
    public function verifier(CodeTerrain $t): void
    {
        $chemin = $this->chemin($t);
        if (!$chemin) {
            return;
        }
        $u = $t->getProprietaire();
        $motifs = [];
        $v = $t->getJustificatifVerification() ?? [];

        $hash = $v['hash'] ?? hash_file('sha256', $chemin);

        // 3. Lecture du document par l'IA
        $lu = null;
        if ($this->gemini->estDisponible()) {
            try {
                $lu = $this->gemini->json(
                    'Tu vérifies des justificatifs fonciers tunisiens (certificat de propriété / شهادة ملكية, titre foncier, contrat de location / عقد كراء). '
                    . 'Tu lis le document et tu réponds uniquement en JSON, sans inventer : null si une information n\'est pas lisible.',
                    'Lis ce document. Réponds : {"type_document": "propriete|location|autre|sans_rapport|illisible", "noms_titulaires": ["..."], '
                    . '"cin": ["..."], "surface_ha": nombre|null, "lieu": "...|null", "date": "AAAA-MM-JJ|null", "numero_titre": "...|null", '
                    . '"semble_modifie": true|false, "confiance": 0..1, "remarques": "1 phrase"}. Une surface en m² ou en ares doit être convertie en hectares.',
                    [['mime' => $t->getJustificatifMime(), 'data' => file_get_contents($chemin)]]
                );
                $motifs = array_merge($motifs, $this->comparer($t, $u, $lu));
            } catch (\Throwable $e) {
                $this->logger->warning('Lecture IA du justificatif impossible', ['terrain' => $t->getCodeTerrain(), 'erreur' => $e->getMessage()]);
                $motifs[] = 'Le robot n\'a pas pu lire le document : vérification par l\'équipe.';
            }
        } else {
            $motifs[] = 'Vérification par l\'équipe (lecture automatique non configurée).';
        }

        $ok = $lu && !$motifs && (float) ($lu['confiance'] ?? 0) >= 0.7;
        $t->setJustificatifStatut($ok ? 'verifie_ia' : 'en_attente', [
            'hash' => $hash,
            'lu' => $lu,
            'motifs' => $motifs,
            'robotLe' => (new \DateTime())->format('Y-m-d H:i'),
        ]);

        // La même terre déclarée par un autre compte : le document le plus récent garde la terre
        foreach ($this->memeTerre($t) as [$autre, $raison]) {
            [$gagnant, $perdant] = self::dateDocument($t) >= self::dateDocument($autre) ? [$t, $autre] : [$autre, $t];
            $this->retirer($perdant, $gagnant, $raison);
            if ($perdant === $t) {
                $this->em->flush();
                return; // ce terrain n'est pas gardé : l'agriculteur a déjà été prévenu
            }
        }
        $this->notifications->envoyerA($u, 'compte',
            $ok ? sprintf('Justificatif de « %s » pré-vérifié', $t->getNom()) : sprintf('Justificatif de « %s » reçu', $t->getNom()),
            $ok ? 'Le robot a lu votre document : il correspond à votre compte et à votre terrain. L\'équipe le validera définitivement.'
                : 'Votre document sera vérifié par l\'équipe. Vous pouvez déjà utiliser ce terrain.' . ($motifs ? ' Remarque : ' . $motifs[0] : ''),
            '/mes-terrains');
        $this->em->flush();
    }

    /**
     * Terrains d'AUTRES comptes qui désignent la même terre : même fichier, même numéro de titre foncier lu
     * sur le document, ou contours qui se chevauchent.
     * @return list<array{0: CodeTerrain, 1: string}>
     */
    private function memeTerre(CodeTerrain $t): array
    {
        $v = $t->getJustificatifVerification() ?? [];
        $titre = self::numeroTitre($v['lu']['numero_titre'] ?? null);
        $out = [];
        foreach ($this->em->getRepository(CodeTerrain::class)->findAll() as $o) {
            if ($o === $t || $o->getProprietaire() === $t->getProprietaire() || !$o->isUtilisable() || !$o->getJustificatifFichier()) {
                continue;
            }
            $ov = $o->getJustificatifVerification() ?? [];
            $raison = match (true) {
                isset($v['hash'], $ov['hash']) && $v['hash'] === $ov['hash'] => 'le même document',
                $titre !== null && $titre === self::numeroTitre($ov['lu']['numero_titre'] ?? null) => 'le même numéro de titre foncier (' . $titre . ')',
                $this->seChevauchent($t, $o) => 'des contours qui se chevauchent',
                default => null,
            };
            if ($raison) {
                $out[] = [$o, $raison];
            }
        }
        return $out;
    }

    /** Le terrain dont le document est le plus ancien est retiré (refusé) ; les deux agriculteurs sont prévenus. */
    private function retirer(CodeTerrain $perdant, CodeTerrain $gagnant, string $raison): void
    {
        $motif = sprintf('Cette terre a été déclarée sur un autre compte avec un document plus récent (%s, %s).', self::dateDocument($gagnant)->format('d/m/Y'), $raison);
        $perdant->setJustificatifStatut('refuse', ['equipe' => ['decision' => 'refuse', 'motif' => $motif, 'par' => 'robot', 'le' => (new \DateTime())->format('Y-m-d H:i')]]);
        $vg = $gagnant->getJustificatifVerification() ?? [];
        $vg['motifs'] = array_merge($vg['motifs'] ?? [], [sprintf('A remplacé le terrain « %s » d\'un autre compte (document plus ancien, %s).', $perdant->getNom(), $raison)]);
        $gagnant->setJustificatifStatut($gagnant->getJustificatifStatut(), $vg);
        $this->notifications->envoyerA($perdant->getProprietaire(), 'compte', sprintf('Terrain « %s » retiré de votre compte', $perdant->getNom()),
            $motif . ' Si c\'est une erreur, envoyez un document plus récent depuis « Mes terrains » ou contactez l\'équipe.', '/mes-terrains');
        $this->logger->info('Même terre sur deux comptes', ['garde' => $gagnant->getCodeTerrain(), 'retire' => $perdant->getCodeTerrain(), 'raison' => $raison]);
    }

    /** Date du document lue par l'IA, sinon date d'envoi. */
    private static function dateDocument(CodeTerrain $t): \DateTimeInterface
    {
        $d = $t->getJustificatifVerification()['lu']['date'] ?? null;
        if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && ($x = \DateTime::createFromFormat('!Y-m-d', $d)) && $x <= new \DateTime()) {
            return $x;
        }
        return $t->getJustificatifLe() ?? new \DateTime('2000-01-01');
    }

    private static function numeroTitre(mixed $n): ?string
    {
        $n = is_scalar($n) ? preg_replace('/[^0-9A-Za-z]/', '', (string) $n) : '';
        return strlen($n) >= 3 ? strtoupper($n) : null;
    }

    private function seChevauchent(CodeTerrain $a, CodeTerrain $b): bool
    {
        if (!$a->getGeometrie() || !$b->getGeometrie()) {
            return false;
        }
        return self::dansPolygone($a->getCentre(), $b->getGeometrie()['coordinates'][0]) || self::dansPolygone($b->getCentre(), $a->getGeometrie()['coordinates'][0]);
    }

    /** Décision de l'équipe (valider / refuser), avec message à l'agriculteur. */
    public function decider(CodeTerrain $t, bool $valide, ?string $motif, Utilisateur $admin): void
    {
        $t->setJustificatifStatut($valide ? 'verifie' : 'refuse', [
            'equipe' => ['decision' => $valide ? 'valide' : 'refuse', 'motif' => $motif, 'par' => $admin->getEmail(), 'le' => (new \DateTime())->format('Y-m-d H:i')],
        ]);
        $this->notifications->envoyerA($t->getProprietaire(), 'compte',
            $valide ? sprintf('Terrain « %s » vérifié', $t->getNom()) : sprintf('Justificatif de « %s » refusé', $t->getNom()),
            $valide ? 'L\'équipe PeasantGo.AI a validé votre justificatif. Merci !'
                : 'Votre justificatif n\'a pas été accepté' . ($motif ? ' : ' . $motif : '.') . ' Envoyez un nouveau document depuis « Mes terrains » pour continuer à utiliser ce terrain.',
            '/mes-terrains');
        $this->em->flush();
    }

    /** @return list<string> incohérences entre le document lu et le compte / le terrain */
    private function comparer(CodeTerrain $t, Utilisateur $u, array $lu): array
    {
        $m = [];
        $type = $lu['type_document'] ?? null;
        if (in_array($type, ['sans_rapport', 'illisible', null], true)) {
            return ['Le document n\'est pas lisible ou ne ressemble pas à un justificatif foncier.'];
        }
        if ($type !== $t->getJustificatifType() && $t->getJustificatifType() !== 'autre') {
            $m[] = sprintf('Le document ressemble à « %s » et non à « %s ».', CodeTerrain::JUSTIFICATIFS[$type] ?? $type, CodeTerrain::JUSTIFICATIFS[$t->getJustificatifType()]);
        }
        if (!empty($lu['semble_modifie'])) {
            $m[] = 'Le document semble avoir été modifié.';
        }
        // Nom du titulaire (ou du locataire) : au moins le nom de famille et un prénom en commun
        $mots = fn (string $s) => array_filter(preg_split('/\s+/', ZoneRepository::normaliser($s)), fn ($w) => mb_strlen($w) > 2 && !in_array($w, ['ben', 'bent', 'ibn', 'mohamed'], true));
        $compte = $mots(trim(($u->getPrenom() ?? '') . ' ' . ($u->getNom() ?? '')));
        $trouve = false;
        foreach ((array) ($lu['noms_titulaires'] ?? []) as $nom) {
            if (is_string($nom) && count(array_intersect($compte, $mots($nom))) >= min(2, count($compte))) {
                $trouve = true;
                break;
            }
        }
        $cinOk = $u->getCin() && in_array(preg_replace('/\D/', '', (string) $u->getCin()), array_map(fn ($c) => preg_replace('/\D/', '', (string) $c), (array) ($lu['cin'] ?? [])), true);
        if (!$trouve && !$cinOk) {
            $m[] = 'Le nom (ou le CIN) du titulaire lu sur le document ne correspond pas à votre compte.';
        }
        if (isset($lu['surface_ha']) && is_numeric($lu['surface_ha']) && $lu['surface_ha'] > 0 && $t->getSurface() > (float) $lu['surface_ha'] * 1.15) {
            $m[] = sprintf('La surface déclarée (%s ha) dépasse celle du document (%s ha).', $t->getSurface(), $lu['surface_ha']);
        }
        return $m;
    }

    /** @param array{0: float, 1: float} $p [lat, lon] ; $ring en [lon, lat] */
    private static function dansPolygone(array $p, array $ring): bool
    {
        [$lat, $lon] = $p;
        $in = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if ((($yi > $lat) !== ($yj > $lat)) && ($lon < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $in = !$in;
            }
        }
        return $in;
    }
}
