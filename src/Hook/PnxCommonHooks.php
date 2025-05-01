<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\Hook;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Hook\Attribute\Hook;
use PreviousNext\Ds\Common\BundleClass\Registry;
use PreviousNext\Ds\Common\List\CommonComponents;
use PreviousNext\Ds\Common\Utility;

final class PnxCommonHooks {

  /**
   * Implements hook_system_info_alter().
   */
  #[Hook('system_info_alter')]
  function systemInfoAlter(array &$info, Extension $file, string $type): void {
    if ('pnx_ds_common' === $file->getName()) {
      $r = new \ReflectionClass(CommonComponents::class);
      $fileName = $r->getFileName();

      // In components/ComponentsRegistry, ltrim disallows absolute dirs, so we
      // must recompute where vendor is in relation to the DrupalRoot, even if
      // it means navigating below Drupal.
      //https://www.drupal.org/project/components/issues/3210853
      // '/' indicates relative to DRUPAL_ROOT, not disk-root.
      $packageRoot = realpath(dirname($fileName) . '/..');
      $info['components']['namespaces'][Utility\Twig::Namespace] = '/' . Utility\Twig::computePathFromDrupalRootTo($packageRoot);
    }
  }


  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  function entityBundleInfoAlter(array &$bundles): void {
    // @todo: figure out a way for us to not clobber when downstream projects need to extend bundle classes, because we need to allow that.
    foreach (Registry::bundleClasses() as $bundleClass => $metadata) {
      $entityType = $metadata->entityTypeId;
      $bundle = $metadata->bundle;
      if (isset($bundles[$entityType][$bundle])) {
        $bundles[$entityType][$bundle]['class'] = $bundleClass;
      }
    }
  }

}
