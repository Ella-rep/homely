<?php

namespace App\Twig;

use App\Service\RoomCatalog;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Helpers d'affichage uniquement (aucun impact métier) : icône de pièce devinée
 * à partir de son nom, libellé textuel d'un statut.
 */
final class RoomIconExtension extends AbstractExtension
{
    private const STATUS_LABELS = [
        'clean' => 'À jour',
        'due_soon' => 'À faire bientôt',
        'overdue' => 'En retard',
    ];

    public function getFunctions(): array
    {
        return [
            new TwigFunction('room_icon_key', [$this, 'guessIconKey']),
            new TwigFunction('status_label', [$this, 'statusLabel']),
            new TwigFunction('room_palette', static fn (): array => RoomCatalog::PALETTE),
        ];
    }

    public function guessIconKey(string $name): string
    {
        $n = mb_strtolower($name);

        return match (true) {
            (bool) preg_match('/cuisin/u', $n) => 'kitchen',
            (bool) preg_match('/\bwc\b|toilette/u', $n) => 'wc',
            (bool) preg_match('/bain|douche/u', $n) => 'bath',
            (bool) preg_match('/chambre|\blit\b/u', $n) => 'bed',
            (bool) preg_match('/salon|s[ée]jour|\btv\b/u', $n) => 'sofa',
            (bool) preg_match('/bureau|travail/u', $n) => 'desk',
            (bool) preg_match('/balcon|jardin|terrasse|v[ée]randa/u', $n) => 'plant',
            default => 'default',
        };
    }

    public function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? '';
    }
}
