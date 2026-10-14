<?php

namespace App\Twig;

use App\Entity\Foyer;
use App\Service\CurrentFoyer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $appVersion,
        private readonly CurrentFoyer $currentFoyer,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('app_version', $this->getAppVersion(...)),
            new TwigFunction('current_foyer', $this->getCurrentFoyer(...)),
        ];
    }

    public function getAppVersion(): string
    {
        return $this->appVersion;
    }

    public function getCurrentFoyer(): ?Foyer
    {
        return $this->currentFoyer->get();
    }
}
