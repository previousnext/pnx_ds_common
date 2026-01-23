<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\Menu;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
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
    /** @var \SplObjectStorage<\PreviousNext\Ds\Common\Atom\Link\Link, MenuItem>|null $map */
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
            $map[$menuTree->link] = $item;
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

  /**
   * Creates a link and series of menus where:
   *   - In a menu hierarchy, only the active trail link is added in at the root level.
   *   - All menus under that root item are made available (unless $specification is modified).
   *   - The deepest active link is made available.
   *
   * Example:
   *
   * - A
   *   - AA
   * - B
   *   - BA
   *   - BB
   *     - CA (**active**)
   *     - CB
   *   - BC
   * - C
   * - D
   *
   * The following result is made available.
   *
   * $result->rootLink is: B
   * $result->result->activeLink is: CA
   * $result->result->menuTrees are a series of trees:
   * - BA
   * - BB
   *   - CA (**active**)
   *   - CB
   * - BC
   */
  public function menusUnderActiveTrailRootItem(MenuTreeSpecification $specification): MenuTreeActiveRootItemResult {
    // Build a tiny menu tree just for the root.
    $menuTreeParameters = (new MenuTreeParameters())
      ->setMinDepth(0)
      ->setMaxDepth(1);

    $rootResult = $this->createMenuTrees(new MenuTreeSpecification(
      menuName: $specification->menuName,
      activeLinkFromMenuActiveTrail: TRUE,
      collectLinkToMenuLinkData: TRUE,
      menuTreeParameters: $menuTreeParameters,
      manipulators: $specification->manipulators,
    ));

    $linkToMenuLinkData = $rootResult->linkToMenuLinkData[$rootResult->activeLink] ?? throw new \LogicException('Expected to exist.');
    $pluginIdOfRootMenuItem = $linkToMenuLinkData['original_link']->getPluginId();

    // Validate $specification provided to the method.
    // This should prevent dev WTF's.
    // $specification will be the specification for the non-root menu, ensure it didn't change
    // root/minDepth/activeLinkFromMenuActiveTrail as we'll be setting them.
    $defaultMenuTreeParameters = new MenuTreeParameters();
    if ($specification->activeLinkFromMenuActiveTrail !== TRUE || $specification->menuTreeParameters->root !== $defaultMenuTreeParameters->root || $specification->menuTreeParameters->minDepth !== $defaultMenuTreeParameters->minDepth) {
      throw new \LogicException(\sprintf('Changes to active/root/min-depth will not take effect as %s will override them.', __FUNCTION__));
    }

    // The non-root menu.
    $specification->activeLinkFromMenuActiveTrail = TRUE;
    $specification->menuTreeParameters->setRoot($pluginIdOfRootMenuItem);
    $specification->menuTreeParameters->setMinDepth(1);

    $result = $this->createMenuTrees($specification);
    $result->cachability->addCacheableDependency($rootResult->cachability);

    return new MenuTreeActiveRootItemResult(
      result: $result,
      rootLink: $rootResult->activeLink,
    );
  }

}
