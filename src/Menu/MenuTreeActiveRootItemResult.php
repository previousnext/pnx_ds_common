<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\Menu;

use PreviousNext\Ds\Common\Atom\Link\Link;

final class MenuTreeActiveRootItemResult {

  public function __construct(
    public readonly MenuTreeResult $result,
    public readonly ?Link $rootLink,
  ) {}

}
