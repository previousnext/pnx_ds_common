<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\Components;

use Drupal\components\Template\ComponentsRegistry;

/**
 * Replaces \Drupal\components\Template\ComponentsRegistry.
 *
 * The original service does not adhere to an interface.
 *
 * Adds feature from https://www.drupal.org/project/components/issues/3519047.
 *
 * @todo remove when 3519047 is merged and tagged.
 * @todo add a composer conflict to lower version.
 */
final class PnxDsCommonComponentsRegistry extends ComponentsRegistry {

  public function getTemplate(string $name): ?string {
    return parent::getTemplate($name)
      // Try a version with `.twig` instead of `.html.twig`.
      ?? $this->registry[$this->themeManager->getActiveTheme()->getName()][\str_replace('.html.twig', '.twig', $name)]
      ?? NULL;
  }

}
