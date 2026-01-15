<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\Menu;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use PreviousNext\Ds\Common as Common;

/**
 * Service to create MenuTrees from Drupal menus, suitable for navigation components.
 *
 * @phpstan-type MenuItem array{
 *    is_expanded: bool,
 *    is_collapsed: bool,
 *    in_active_trail?: bool,
 *    attributes: \Drupal\Core\Template\Attribute,
 *    title: string,
 *    url: \Drupal\Core\Url,
 *    below: array<string, mixed>,
 *    original_link: \Drupal\Core\Menu\MenuLinkInterface,
 *  }
 */
final class MenuToMenuTree {

  public function __construct(
    private readonly MenuActiveTrailInterface $menuActiveTrail,
    private readonly MenuLinkTreeInterface $menuTree,
  ) {
  }

  public function createMenuTrees(MenuTreeSpecification $specification): MenuTreeResult {
    $parameters = $specification->menuTreeParameters;

    if ($specification->activeLinkFromMenuActiveTrail) {
      $activeTrail = $this->menuActiveTrail->getActiveTrailIds($specification->menuName);
      $parameters->setActiveTrail($activeTrail);
    }

    $tree = $this->menuTree->load($specification->menuName, $parameters);
    $tree = $this->menuTree->transform(tree: $tree, manipulators: $specification->manipulators);

    /** @var array{"#cache": array<mixed>, "#items"?: array<MenuItem>} $menuBuild */
    $menuBuild = $this->menuTree->build($tree);

    // Map the render elements from build().
    /** @var \SplObjectStorage<\PreviousNext\Ds\Common\Vo\MenuTree\MenuTree, MenuItem>|null $map */
    // @phpstan-ignore varTag.nativeType
    $map = $specification->collectLinkToMenuLinkData ? new \SplObjectStorage() : NULL;

    // The last assignment of this will be the deepest, as recursion is root->bottom.
    $activeLink = NULL;

    $menuTrees = new Common\Vo\MenuTree\MenuTrees(
      data: \iterator_to_array(($loop = static function (array $build) use (&$loop, &$map, &$activeLink): \Generator {
        /** @var array<MenuItem> $build */
        foreach ($build as $item) {
          yield $menuTree = Common\Vo\MenuTree\MenuTree::create(Common\Atom\Link\Link::create($item['title'], $item['url']));

          // Optionally record element and trail.
          if ($map !== NULL) {
            $map[$menuTree] = $item;
          }
          // Needs to be before the loop:
          if (TRUE === ($item['in_active_trail'] ?? NULL)) {
            $activeLink = $menuTree->link;
          }

          foreach ($loop($item['below']) as $below) {
            $menuTree[] = $below;
          }
        }
      })($menuBuild['#items'] ?? [])),
    );

    return new MenuTreeResult(
      menuTrees: $menuTrees,
      specification: $specification,
      activeLink: $activeLink,
      cachability: CacheableMetadata::createFromRenderArray($menuBuild),
      linkToMenuLinkData: $map,
    );
  }

}
