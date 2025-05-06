<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common;

use PreviousNext\Ds\Common\List\CommonAtoms;
use PreviousNext\Ds\Common\List\CommonComponents;
use PreviousNext\Ds\Common\List\CommonLayouts;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Service provider.
 */
final class PnxDsCommonCompilerPass implements CompilerPassInterface {

  public function process(ContainerBuilder $container): void {
    $pintoLists = $container->getParameter('pinto.lists');
    $pintoLists[] = CommonAtoms::class;
    $pintoLists[] = CommonComponents::class;
    $pintoLists[] = CommonLayouts::class;
    $container->setParameter('pinto.lists', $pintoLists);
  }

}
