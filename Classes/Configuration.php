<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class Configuration
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function get(string $option): string
    {
        try {
            return $this->extensionConfiguration->get('matomo_tracking', $option) ?: '';
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
        }

        return '';
    }

    public function enablesFeature(string $feature): bool
    {
        try {
            $features = $this->extensionConfiguration->get('matomo_tracking', 'features') ?: [];
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return false;
        }

        return (bool)($features[$feature] ?? false);
    }
}
