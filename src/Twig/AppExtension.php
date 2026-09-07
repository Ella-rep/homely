<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(private readonly string $appVersion)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('app_version', $this->getAppVersion(...)),
        ];
    }

    public function getAppVersion(): string
    {
        return $this->appVersion;
    }
}
