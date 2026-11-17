<?php

namespace App\Robot;

use App\Entity\PageVeille;
use App\Entity\PrixIntrant;
use App\Entity\PrixMarche;
use App\Entity\SourceVeille;
use App\Entity\Zone;
use App\Entity\ZoneStat;
use App\IA\AnalyseurIA;
use App\Repository\PageVeilleRepository;
use App\Repository\ZoneRepository;
use App\Repository\ZoneStatRepository;
use App\Satellite\ProfilsCultures;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Veille : le robot lit régulièrement les sites configurés par l'équipe (admin → Sources de veille) :
 * sites officiels (ministère, offices, INS, ONAGRI), marchés de gros, vendeurs d'intrants d'une zone.
 * Pour chaque site : page d'accueil + jusqu'à 5 pages liées contenant les mots-clés ; une page déjà lue
 * dont le contenu n'a pas changé n'est pas ré-analysée. L'IA extrait prix, prix d'intrants et statistiques,
 * enregistrés avec l'adresse de la page comme source.
 */
class VeilleService
{
    private const MAX_PAGES = 6;
    private const UA = 'PeasantGo.AI-veille/1.0 (+https://peasantgo.tn)';

    public function __construct(
        private HttpClientInterface $http,
        private EntityManagerInterface $em,
        private PageVeilleRepository $pages,
        private ZoneRepository $zones,
        private ZoneStatRepository $stats,
        private AnalyseurIA $ia,
        private LoggerInterface $logger,
    ) {
    }

    /** @return array{pages: int, nouvelles: int, prix: int, intrants: int, statistiques: int, erreurs: list<string>} */
    public function lancer(?SourceVeille $seule = null): array
    {
        $bilan = ['pages' => 0, 'nouvelles' => 0, 'prix' => 0, 'intrants' => 0, 'statistiques' => 0, 'erreurs' => []];
        $sources = $seule ? [$seule] : $this->em->getRepository(SourceVeille::class)->findBy(['active' => true]);

        foreach ($sources as $src) {
            $res = ['pages' => 0, 'prix' => 0, 'intrants' => 0, 'statistiques' => 0];
            try {
                [$texteAccueil, $liens] = $this->lire($src->getUrl());
                $aLire = [$src->getUrl() => $texteAccueil];
                foreach ($this->liensUtiles($liens, $src) as $url) {
                    if (count($aLire) >= self::MAX_PAGES) {
                        break;
                    }
                    try {
                        [$t] = $this->lire($url);
                        $aLire[$url] = $t;
                    } catch (\Throwable $e) {
                        $this->logger->info('Page de veille illisible', ['url' => $url, 'erreur' => $e->getMessage()]);
                    }
                }
                foreach ($aLire as $url => $texte) {
                    ++$res['pages'];
                    $hash = hash('sha256', $texte);
                    $page = $this->pages->findOneBy(['urlHash' => hash('sha256', $url)]);
                    if ($page && $page->getContenuHash() === $hash) {
                        continue; // rien de nouveau
                    }
                    $page ??= new PageVeille($src, $url);
                    $this->em->persist($page);
                    ++$bilan['nouvelles'];
                    $extrait = $this->ia->extraire($texte, $src->getType(), $url);
                    $n = $this->enregistrer($extrait, $src, $url);
                    foreach ($n as $k => $v) {
                        $res[$k] += $v;
                    }
                    $page->setContenuHash($hash)->setNbExtraits(array_sum($n));
                    $this->em->flush();
                }
                $src->setDernierResultat(sprintf('%d page(s), %d prix, %d intrant(s), %d statistique(s)', $res['pages'], $res['prix'], $res['intrants'], $res['statistiques']));
            } catch (\Throwable $e) {
                $bilan['erreurs'][] = $src->getNom() . ' : ' . $e->getMessage();
                $src->setDernierResultat('Erreur : ' . $e->getMessage());
            }
            $src->setDerniereVisite(new \DateTime());
            $this->em->flush();
            foreach (['pages', 'prix', 'intrants', 'statistiques'] as $k) {
                $bilan[$k] += $res[$k];
            }
        }
        return $bilan;
    }

    /** @return array{0: string, 1: list<array{href: string, texte: string}>} texte lisible + liens */
    private function lire(string $url): array
    {
        $resp = $this->http->request('GET', $url, ['headers' => ['User-Agent' => self::UA, 'Accept' => 'text/html'], 'timeout' => 20, 'max_duration' => 30, 'max_redirects' => 3]);
        $type = $resp->getHeaders()['content-type'][0] ?? '';
        if (!str_contains($type, 'html') && !str_contains($type, 'text')) {
            throw new \RuntimeException('contenu non HTML (' . $type . ')');
        }
        $html = mb_substr($resp->getContent(), 0, 2_000_000);

        $liens = [];
        if (preg_match_all('/<a\s[^>]*href=["\']([^"\'#]+)["\'][^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $liens[] = ['href' => $this->absolue($x[1], $url), 'texte' => trim(strip_tags($x[2]))];
            }
        }
        $html = preg_replace('#<(script|style|noscript|svg|nav|footer)[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace(['#</t[dh]>#i', '#</tr>|<br\s*/?>|</p>|</li>|</h\d>|</div>#i'], [' | ', "\n"], $html);
        $texte = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texte = trim(preg_replace(["/[ \t\x{00A0}]+/u", "/\n\s*\n+/"], [' ', "\n"], $texte));
        return [$texte, $liens];
    }

    private function liensUtiles(array $liens, SourceVeille $src): array
    {
        $hote = parse_url($src->getUrl(), PHP_URL_HOST);
        $mots = array_map(fn ($m) => ZoneRepository::normaliser($m), $src->getMotsCles());
        $out = [];
        foreach ($liens as $l) {
            if (parse_url($l['href'], PHP_URL_HOST) !== $hote || preg_match('/\.(pdf|jpe?g|png|zip|docx?|xlsx?)$/i', $l['href'])) {
                continue;
            }
            $cible = ZoneRepository::normaliser($l['texte'] . ' ' . urldecode($l['href']));
            foreach ($mots as $m) {
                if ($m !== '' && str_contains($cible, $m)) {
                    $out[$l['href']] = true;
                    break;
                }
            }
        }
        return array_keys($out);
    }

    /** @return array{prix: int, intrants: int, statistiques: int} */
    private function enregistrer(array $x, SourceVeille $src, string $url): array
    {
        $n = ['prix' => 0, 'intrants' => 0, 'statistiques' => 0];
        $auj = new \DateTime('today');

        foreach ($x['prix_marche'] ?? [] as $p) {
            $cle = ProfilsCultures::depuisDeclaration(null, (string) ($p['produit'] ?? ''));
            $prix = (float) ($p['prix_dt_kg'] ?? 0);
            if (!$cle || !Referentiel::existe($cle) || $prix <= 0 || $prix > 200) {
                continue;
            }
            $pm = (new PrixMarche($cle, self::date($p['date'] ?? null) ?? $auj, $prix, 'veille'))
                ->setType((string) ($p['type'] ?? 'gros'))
                ->setZone($this->zone($p['lieu'] ?? null) ?? $src->getZone())
                ->setLieuTexte($p['lieu'] ?? null)
                ->setSourceUrl($url);
            $this->em->persist($pm);
            ++$n['prix'];
        }

        foreach ($x['intrants'] ?? [] as $i) {
            [$cle, $diviseur] = self::intrant((string) ($i['intrant'] ?? ''), (string) ($i['libelle'] ?? ''), (string) ($i['unite'] ?? ''));
            $prix = (float) ($i['prix_dt'] ?? 0);
            if (!$cle || $prix <= 0) {
                continue;
            }
            $pi = (new PrixIntrant($cle, round($prix / $diviseur, 4), self::date($i['date'] ?? null) ?? $auj, 'veille'))
                ->setZone($this->zone($i['lieu'] ?? null) ?? $src->getZone())
                ->setVendeur($i['vendeur'] ?? $src->getNom())
                ->setSourceUrl($url);
            $this->em->persist($pi);
            ++$n['intrants'];
        }

        foreach ($x['statistiques'] ?? [] as $s) {
            $cle = ProfilsCultures::depuisDeclaration(null, (string) ($s['produit'] ?? ''));
            $annee = (int) ($s['annee'] ?? 0);
            $zone = $this->zone($s['zone'] ?? null, true);
            if (!$cle || !Referentiel::existe($cle) || $annee < 1990 || $annee > (int) date('Y') + 1 || !$zone) {
                continue;
            }
            $zs = $this->stats->findOneBy(['zone' => $zone, 'cultureCle' => $cle, 'annee' => $annee, 'source' => 'officiel']) ?? new ZoneStat($zone, $cle, $annee, 'officiel');
            foreach (['production_t' => 'setProductionT', 'surface_ha' => 'setSurfaceHa', 'consommation_kg_hab' => 'setConsommationKgHab'] as $k => $setter) {
                if (isset($s[$k]) && is_numeric($s[$k]) && $s[$k] > 0) {
                    $zs->$setter((float) $s[$k]);
                }
            }
            $zs->setSourceUrl($url);
            $this->em->persist($zs);
            ++$n['statistiques'];
        }
        return $n;
    }

    private function zone(?string $nom, bool $paysParDefaut = false): ?Zone
    {
        if ($nom) {
            $n = ZoneRepository::normaliser($nom);
            if (in_array($n, ['tunisie', 'tunisia', 'national', 'nationale'], true)) {
                return $this->zones->tunisie();
            }
            if ($z = $this->zones->gouvernoratParNom($nom)) {
                return $z;
            }
        }
        return $paysParDefaut ? $this->zones->tunisie() : null;
    }

    /** @return array{0: ?string, 1: float} clé d'intrant, diviseur pour ramener à l'unité de PrixIntrant */
    private static function intrant(string $type, string $libelle, string $unite): array
    {
        $t = ZoneRepository::normaliser($type . ' ' . $libelle);
        $cle = match (true) {
            str_contains($t, 'ammonitrate') => 'ammonitrate',
            str_contains($t, 'dap') || str_contains($t, '18 46') => 'dap',
            str_contains($t, 'potasse') || str_contains($t, 'potassium') => 'potasse',
            str_contains($t, 'gasoil') || str_contains($t, 'gazoil') => 'gasoil',
            str_contains($t, 'moisson') => 'moisson_ha',
            str_contains($t, 'tracteur') => 'machine_heure',
            str_contains($t, 'transport') => 'transport_tkm',
            str_contains($t, 'eau') => 'eau_irrigation',
            str_contains($t, 'traitement') || str_contains($t, 'fongicide') || str_contains($t, 'insecticide') || str_contains($t, 'herbicide') => 'traitement',
            str_contains($t, 'pomme de terre') => 'plant_pomme_terre',
            str_contains($t, 'mais') => 'semence_mais',
            str_contains($t, 'tournesol') || str_contains($t, 'colza') => 'semence_oleagineux',
            str_contains($t, 'pois') || str_contains($t, 'feve') || str_contains($t, 'lentille') => 'semence_legumineuse',
            str_contains($t, 'plant') => 'plant_maraichage',
            str_contains($t, 'tomate') || str_contains($t, 'piment') || str_contains($t, 'oignon') || str_contains($t, 'pasteque') => 'semence_maraichage',
            str_contains($t, 'semence') || str_contains($t, 'ble') || str_contains($t, 'orge') => 'semence_cereale',
            default => null,
        };
        $u = ZoneRepository::normaliser($unite);
        $diviseur = match (true) {
            str_contains($u, '50') => 50.0,
            str_contains($u, '25') => 25.0,
            str_contains($u, 'quintal') => 100.0,
            str_contains($u, 'tonne') => 1000.0,
            default => 1.0,
        };
        return [$cle, $diviseur];
    }

    private static function date(mixed $v): ?\DateTime
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? (\DateTime::createFromFormat('!Y-m-d', $v) ?: null) : null;
    }

    private function absolue(string $href, string $base): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $p = parse_url($base);
        $racine = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($href, '//')) {
            return $p['scheme'] . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $racine . $href;
        }
        $dir = isset($p['path']) ? preg_replace('#/[^/]*$#', '/', $p['path']) : '/';
        return $racine . $dir . $href;
    }
}
