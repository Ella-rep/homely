<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Devine une icône de pièce (cuisine / bain / chambre / salon / générique)
 * à partir de son nom, pour l'affichage uniquement (aucun impact métier).
 */
final class RoomIconExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('room_icon_key', [$this, 'guessIconKey']),
        ];
    }

    public function guessIconKey(string $name): string
    {
        $n = mb_strtolower($name);

        return match (true) {
            (bool) preg_match('/cuisin/u', $n) => 'kitchen',
            (bool) preg_match('/bain|douche|toilette|wc/u', $n) => 'bath',
            (bool) preg_match('/chambre|lit/u', $n) => 'bed',
            (bool) preg_match('/salon|s[ée]jour|tv/u', $n) => 'sofa',
            default => 'default',
        };
    }
}
