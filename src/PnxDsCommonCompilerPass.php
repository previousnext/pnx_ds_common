<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common;

use Drupal\canvas_pinto\PintoCanvasRender\ComponentResolverInterface;
use PreviousNext\Ds\Common\List\CommonLists;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PnxDsCommonCompilerPass implements CompilerPassInterface {

  public function process(ContainerBuilder $container): void {
    $pintoLists = $container->getParameter('pinto.lists');
    \array_push($pintoLists, ...CommonLists::Lists);
    $container->setParameter('pinto.lists', $pintoLists);

    // Remove services if Pinto Canvas is not present.
    if (FALSE === \interface_exists(ComponentResolverInterface::class)) {
      $container->removeDefinition(PintoCanvasRender\ElementSlots::class);
      $container->removeDefinition(PintoCanvasRender\IdsCustomBehaviourResolver::class);
      $container->removeDefinition(PintoCanvasRender\IdsGenericResolver::class);
    }
  }

}
