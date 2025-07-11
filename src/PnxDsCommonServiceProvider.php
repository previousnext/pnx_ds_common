<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Service provider.
 */
final class PnxDsCommonServiceProvider implements ServiceProviderInterface {

  public function register(ContainerBuilder $container): void {
    $container->addCompilerPass(new PnxDsCommonCompilerPass(), priority: 100);
  }

}
