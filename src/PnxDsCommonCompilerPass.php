<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common;

use PreviousNext\Ds\Common\List\CommonLists;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PnxDsCommonCompilerPass implements CompilerPassInterface {

  public function process(ContainerBuilder $container): void {
    $pintoLists = $container->getParameter('pinto.lists');
    \array_push($pintoLists, ...CommonLists::Lists);
    $container->setParameter('pinto.lists', $pintoLists);
  }

}
