<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\Hook;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Hook\Attribute\Hook;
use PreviousNext\Ds\Common\List\CommonComponents;
use PreviousNext\Ds\Common\Utility;

final class PnxCommonHooks {

  public const RENDER_ARRAY_KEY_TO_TWIG_TYPE = '__twigTypeVar';

  /**
   * Implements hook_system_info_alter().
   */
  #[Hook('system_info_alter')]
  public function systemInfoAlter(array &$info, Extension $file, string $type): void {
    if ('pnx_ds_common' === $file->getName()) {
      $r = new \ReflectionClass(CommonComponents::class);
      $fileName = $r->getFileName();

      // In components/ComponentsRegistry, ltrim disallows absolute dirs, so we
      // must recompute where vendor is in relation to the DrupalRoot, even if
      // it means navigating below Drupal.
      // https://www.drupal.org/project/components/issues/3210853
      // '/' indicates relative to DRUPAL_ROOT, not disk-root.
      $packageRoot = \realpath(\dirname($fileName) . '/..');
      $info['components']['namespaces'][Utility\Twig::NAMESPACE] = '/' . Utility\Twig::computePathFromDrupalRootTo($packageRoot);
    }
  }

  /**
   * Implements hook_preprocess().
   *
   * @phpstan-param array<string, mixed> $variables
   */
  #[Hook('preprocess')]
  public function preprocess(array &$variables): void {
    if (\array_key_exists(static::RENDER_ARRAY_KEY_TO_TWIG_TYPE, $variables) && ($variables['type'] ?? NULL) === NULL) {
      // Can't set '#type' on render array so we juggle variable names.
      $variables['type'] = $variables[static::RENDER_ARRAY_KEY_TO_TWIG_TYPE];
    }
  }

}
