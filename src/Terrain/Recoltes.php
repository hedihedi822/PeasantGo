<?php

namespace App\Terrain;

use App\Entity\Cultivation;
use App\Entity\PrixMarche;
use App\Robot\Referentiel;
use App\Satellite\ProfilsCultures;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Déclaration d'une récolte (ou d'une vente d'élevage) : contrôle des valeurs, conversion des unités,
 * prix observé ajouté à l'historique des prix de la localité (le robot s'en sert pour estimer les prix).
 *
 * Unités saisies par l'agriculteur : cultures → tonnes et DT/kg ;
 * élevage → l'unité naturelle du produit (kg vif, litres, œufs, kg de miel) et le prix de cette unité.
 */
class Recoltes
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return array{0: string, 1: float} unité saisie, kg par unité */
    public static function unite(string $cle): array
    {
        return Referentiel::estAnimal($cle) ? Referentiel::get($cle)['affichage'] : ['t', 1000.0];
    }

    /**
     * @param array $d quantite, prixVente, coutReel (ou couts{} détaillés), date, resultat, remarque, canal
     * @return array<string, string> erreurs par champ (vide si tout est enregistré)
     */
    public function declarer(Cultivation $c, array $d): array
    {
        $cle = ProfilsCultures::depuisDeclaration($c->getCultureCle(), $c->getCultureNom()) ?? $c->getCultureCle();
        [$unite, $kgParUnite] = self::unite($cle);
        $num = fn ($v) => ($v ?? '') !== '' ? (float) str_replace([',', ' '], ['.', ''], (string) $v) : null;

        $quantite = $num($d['quantite'] ?? null);
        $prix = $num($d['prixVente'] ?? null);
        $couts = is_array($d['couts'] ?? null) ? $d['couts'] : [];
        $cout = $num($d['coutReel'] ?? null);
        $date = self::date($d['date'] ?? null) ?? new \DateTime('today');
        $resultat = (string) ($d['resultat'] ?? '');
        $errors = [];

        $tonnes = $quantite !== null ? $quantite * $kgParUnite / 1000 : null;
        $prixKg = $prix !== null ? $prix / $kgParUnite : null;
        if ($tonnes === null || $tonnes < 0 || $tonnes > 100000) {
            $errors['quantite'] = sprintf('Quantité invalide (en %s).', $unite === 't' ? 'tonnes' : $unite);
        }
        if ($prixKg !== null && ($prixKg <= 0 || $prixKg > 200)) {
            $errors['prixVente'] = sprintf('Prix invalide (DT par %s).', $unite === 't' ? 'kg' : rtrim($unite, 's'));
        }
        if ($cout !== null && $cout < 0) {
            $errors['coutReel'] = 'Coût invalide.';
        }
        if (!isset(Cultivation::RESULTATS[$resultat])) {
            $errors['resultat'] = 'Choisissez le résultat.';
        }
        if ($date < $c->getDateDebut() || $date > new \DateTime('+1 day')) {
            $errors['date'] = 'La date de récolte doit être entre le début et aujourd\'hui.';
        }
        if ($errors) {
            return $errors;
        }

        $c->setDetailCouts($couts);
        if ($cout === null && $c->getDetailCouts()) {
            $cout = array_sum($c->getDetailCouts());
        }
        $c->setCanalVente($d['canal'] ?? null);
        $c->declarerRecolte($tonnes, $prixKg !== null ? round($prixKg, 4) : null, $cout, $date, $resultat, mb_substr(trim((string) ($d['remarque'] ?? '')), 0, 2000) ?: null);

        if ($prixKg && Referentiel::existe($cle) && $c->getCanalVente() !== 'autoconsommation') {
            $t = $c->getTerrain();
            $type = in_array($c->getCanalVente(), ['marche_gros', 'export'], true) ? 'gros' : 'producteur';
            $this->em->persist((new PrixMarche($cle, $date, round($prixKg, 4), 'recolte'))->setType($type)
                ->setZone($t->getLocalite() ?? $t->getGouvernorat())->setLieuTexte($t->getLocalite()?->getNom() ?? $t->getLocalisation()));
        }
        return [];
    }

    public static function date(mixed $v): ?\DateTime
    {
        if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $v);
        return $d ?: null;
    }
}
