<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\Menu;

use Drupal\Core\Cache\CacheableMetadata;
use PreviousNext\Ds\Common\Atom\Link\Link;
use PreviousNext\Ds\Common\Vo\MenuTree\MenuTrees;

/**
 * @phpstan-import-type MenuItem from \Drupal\pnx_ds_common\Menu\MenuToMenuTree
 */
final class MenuTreeResult {

  /**
   * @phpstan-param \SplObjectStorage<\PreviousNext\Ds\Common\Atom\Link\Link, MenuItem>|null $linkToMenuLinkData
   */
  public function __construct(
    public readonly MenuTrees $menuTrees,
    public readonly MenuTreeSpecification $specification,
    public readonly ?Link $activeLink,
    public readonly CacheableMetadata $cachability,
    public readonly ?\SplObjectStorage $linkToMenuLinkData,
  ) {}

}
