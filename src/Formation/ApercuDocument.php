<?php

namespace App\Formation;

/**
 * Aperçu des documents Word (DOCX, ODT) des formations payantes : le texte est lu sur le serveur
 * et seule la première partie (25 %) est envoyée au navigateur. Le fichier entier n'est jamais servi avant l'achat.
 * (Les PDF sont affichés page par page dans le navigateur, limités à 25 % des pages.)
 */
final class ApercuDocument
{
    /**
     * @return array{blocs: list<array{titre: bool, texte: string}>, total: int, montres: int}|null  null si le format n'est pas lisible
     */
    public static function word(string $fichier, float $part): ?array
    {
        $ext = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));
        if (!in_array($ext, ['docx', 'odt'], true) || !class_exists(\ZipArchive::class)) {
            return null;
        }
        $zip = new \ZipArchive();
        if ($zip->open($fichier) !== true) {
            return null;
        }
        $xml = $zip->getFromName($ext === 'docx' ? 'word/document.xml' : 'content.xml');
        $zip->close();
        if ($xml === false || $xml === '') {
            return null;
        }
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            return null;
        }
        $xp = new \DOMXPath($dom);
        $blocs = [];
        if ($ext === 'docx') {
            $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            foreach ($xp->query('//w:body//w:p') as $p) {
                $texte = '';
                foreach ($xp->query('.//w:t|.//w:tab|.//w:br', $p) as $n) {
                    $texte .= $n->localName === 't' ? $n->textContent : ($n->localName === 'tab' ? "\t" : "\n");
                }
                $style = (string) $xp->evaluate('string(w:pPr/w:pStyle/@w:val)', $p);
                $blocs[] = ['titre' => (bool) preg_match('/^(heading|titre|title)/i', $style), 'texte' => $texte];
            }
        } else {
            $xp->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
            foreach ($xp->query('//text:h|//text:p') as $p) {
                $blocs[] = ['titre' => $p->localName === 'h', 'texte' => $p->textContent];
            }
        }
        $blocs = array_values(array_filter(array_map(
            fn ($b) => ['titre' => $b['titre'], 'texte' => trim(preg_replace('/[ \t]+/', ' ', $b['texte']))],
            $blocs
        ), fn ($b) => $b['texte'] !== ''));
        $total = count($blocs);
        $montres = $total ? max(1, (int) floor($total * $part)) : 0;
        return ['blocs' => array_slice($blocs, 0, $montres), 'total' => $total, 'montres' => $montres];
    }
}
