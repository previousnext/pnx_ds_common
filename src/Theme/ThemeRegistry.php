<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\Theme;

use Drupal\Core\Theme\Registry;

/**
 * Modifies theme registry to protect Pinto theme definitions from override.
 *
 * This should probably be reworked into a Render element, much like SDC.
 *
 * @see https://git.drupalcode.org/project/pinto/-/merge_requests/28
 *
 * @internal
 */
final class ThemeRegistry extends Registry {

  /**
   * @var string[]
   */
  private array $pintoThemeHookIds = [];

  public function setPintoHookTheme(array $pintoHookTheme) {
    $this->pintoThemeHookIds = \array_keys($pintoHookTheme);
  }

  #[\Override] protected function processExtension(array &$cache, $name, $type, $theme, $path): void {
    if ($name === 'pinto') {
      parent::processExtension($cache, $name, $type, $theme, $path);
      return;
    }

    $originalCache = $cache;
    $keep = \array_intersect_key($originalCache, \array_flip($this->pintoThemeHookIds));
    parent::processExtension($cache, $name, $type, $theme, $path);
    // Get the keys from new cache, checking the keys still exist:
    foreach (\array_keys(\array_intersect_key($cache, \array_flip($this->pintoThemeHookIds))) as $cacheKey) {
      $cache[$cacheKey] = $keep[$cacheKey];
    }
  }

}
