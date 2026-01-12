<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\Menu;

use Drupal\Core\Menu\MenuTreeParameters;

final class MenuTreeSpecification {

  /**
   * Build a specification of the menu and items.
   *
   * @param string $menuName
   *   The Drupal menu name.
   * @param bool $activeLinkFromMenuActiveTrail
   *   Use the active trail service to determine the active link.
   * @param bool $collectLinkToMenuLinkData
   *   Whether to collect original menu link render arrays.
   * @param \Drupal\Core\Menu\MenuTreeParameters $menuTreeParameters
   *   How to shape and load the Drupal menu.
   * @param array<array{callable: string}> $manipulators
   *   Manipulators to filter menu items.
   */
  public function __construct(
    public string $menuName,
    public bool $activeLinkFromMenuActiveTrail = TRUE,
    public bool $collectLinkToMenuLinkData = FALSE,
    public MenuTreeParameters $menuTreeParameters = new MenuTreeParameters(),
    public array $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ],
  ) {}

}
