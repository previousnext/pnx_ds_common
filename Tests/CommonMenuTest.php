<?php

declare(strict_types=1);

namespace PreviousNext\PnxDsCommon\Tests;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Url;
use Drupal\pnx_ds_common\Menu\MenuToMenuTree;
use Drupal\pnx_ds_common\Menu\MenuTreeSpecification;
use PHPUnit\Framework\TestCase;
use Pinto\PintoMapping;
use PreviousNext\Ds\Common as Common;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CommonMenuTest extends TestCase {

  public function testMenuTrees(): void {
    $url = \Mockery::mock(Url::class);
    $url->expects('toString')->andReturn('http://example.com/');

    // Skip testing internals of Drupal's menu system specifically. Skips directly to returning a render array in
    // MenuLinkTreeInterface::build().
    $menuActiveTrail = \Mockery::mock(MenuActiveTrailInterface::class);
    $menuActiveTrail->expects('getActiveTrailIds')->once()->andReturn([]);
    $menuTree = \Mockery::mock(MenuLinkTreeInterface::class);
    $menuTree->expects('load')->once()->andReturn([]);
    $menuTree->expects('transform')->once()->andReturn([]);

    $service = new MenuToMenuTree($menuActiveTrail, $menuTree);

    // \PreviousNext\Ds\Common\Atom\Link\Link will reach out to container:
    $container = new ContainerBuilder();
    $container->set(PintoMapping::class, new PintoMapping([], [], [], [], []));
    \Drupal::setContainer($container);

    $menuTree->expects('build')->andReturn([
      '#cache' => [
        'contexts' => [
          'user.permissions',
        ],
        'tags' => [
          'config:system.menu.main',
        ],
        'max-age' => -1,
      ],
      '#sorted' => TRUE,
      '#theme' => 'menu__main',
      '#menu_name' => 'main',
      '#items' => [
        'menu_link_content:1' => $item1 = [
          'in_active_trail' => FALSE,
          'title' => 'Item 1',
          'url' => $url,
          'below' => [],
        ],
        'menu_link_content:2' => $item2 = [
          'in_active_trail' => TRUE,
          'title' => 'Item 2',
          'url' => $url,
          'below' => [
            'menu_link_content:2A' => $item2a = [
              'in_active_trail' => FALSE,
              'title' => 'Item 2A',
              'url' => $url,
              'below' => [],
            ],
            'menu_link_content:2B' => $item2b = [
              'in_active_trail' => TRUE,
              'title' => 'Item 2B',
              'url' => $url,
              'below' => [
                'menu_link_content:2BA' => $item2ba = [
                  'in_active_trail' => FALSE,
                  'title' => 'Item 2BA',
                  'url' => $url,
                  'below' => [],
                ],
                'menu_link_content:2BB' => $item2bb = [
                  'in_active_trail' => TRUE,
                  'title' => 'Item 2BB',
                  'url' => $url,
                  'below' => [],
                ],
              ],
            ],
            'menu_link_content:2C' => $item2c = [
              'in_active_trail' => FALSE,
              'title' => 'Item 2C',
              'url' => $url,
              'below' => [],
            ],
          ],
        ],
        'menu_link_content:3' => $item3 = [
          'in_active_trail' => FALSE,
          'title' => 'Item 3',
          'url' => $url,
          'below' => [
            'menu_link_content:3A' => $item3a = [
              'in_active_trail' => FALSE,
              'title' => 'Item 3A',
              'url' => $url,
              'below' => [],
            ],
          ],
        ],
      ],
    ]);

    $specification = new MenuTreeSpecification(
      menuName: 'menu',
      collectLinkToMenuLinkData: TRUE,
    );
    $menuTreesResult = $service->createMenuTrees($specification);

    static::assertEquals(Common\Atom\Link\Link::create(
      'Item 2BB',
      $url,
    ), $menuTreesResult->activeLink);

    static::assertEquals(
      (new CacheableMetadata())
        ->setCacheContexts(['user.permissions'])
        ->setCacheTags(['config:system.menu.main'])
        ->setCacheMaxAge(-1),
      $menuTreesResult->cachability,
    );

    // Level 1:
    $expectedMenuTree = new Common\Vo\MenuTree\MenuTrees([
      Common\Vo\MenuTree\MenuTree::create(Common\Atom\Link\Link::create(
        'Item 1',
        $url,
      )),
      $tree2 = Common\Vo\MenuTree\MenuTree::create(Common\Atom\Link\Link::create(
        'Item 2',
        $url,
      )),
      $tree3 = Common\Vo\MenuTree\MenuTree::create(Common\Atom\Link\Link::create(
        'Item 3',
        $url,
      )),
    ]);

    // Level 2:
    $tree2[] = Common\Vo\MenuTree\MenuTree::create(Common\Atom\Link\Link::create(
      'Item 2A',
      $url,
    ));
    $tree2[] = $tree2b = Common\Vo\MenuTree\MenuTree::create(Common\Atom\Link\Link::create(
      'Item 2B',
      $url,
    ));
    $tree2[] = Common\Vo\MenuTree\MenuTree::create(Common\Atom\Link\Link::create(
      'Item 2C',
      $url,
    ));
    $tree3[] = Common\Vo\MenuTree\MenuTree::create(Common\Atom\Link\Link::create(
      'Item 3A',
      $url,
    ));

    // Level 3:
    $tree2b[] = Common\Vo\MenuTree\MenuTree::create(Common\Atom\Link\Link::create(
      'Item 2BA',
      $url,
    ));
    $tree2b[] = Common\Vo\MenuTree\MenuTree::create(Common\Atom\Link\Link::create(
      'Item 2BB',
      $url,
    ));

    static::assertEquals($expectedMenuTree, $menuTreesResult->menuTrees);

    // Legacy data:
    $menuData = $menuTreesResult->linkToMenuLinkData;
    static::assertNotNull($menuData);
    static::assertEquals($item1, $menuData[$menuTreesResult->menuTrees[0]]);
    static::assertEquals($item2, $menuData[$menuTreesResult->menuTrees[1]]);
    static::assertEquals($item2a, $menuData[$menuTreesResult->menuTrees[1][0]]);
    static::assertEquals($item2b, $menuData[$menuTreesResult->menuTrees[1][1]]);
    static::assertEquals($item2ba, $menuData[$menuTreesResult->menuTrees[1][1][0]]);
    static::assertEquals($item2bb, $menuData[$menuTreesResult->menuTrees[1][1][1]]);
    static::assertEquals($item2c, $menuData[$menuTreesResult->menuTrees[1][2]]);
    static::assertEquals($item3, $menuData[$menuTreesResult->menuTrees[2]]);
    static::assertEquals($item3a, $menuData[$menuTreesResult->menuTrees[2][0]]);
  }

}
