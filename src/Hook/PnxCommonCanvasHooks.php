<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\pnx_ds_common\PintoCanvasRender\ElementSlots;
use Pinto\Attribute\Definition;
use Pinto\PintoMapping;

final readonly class PnxCommonCanvasHooks {

  private bool $isCanvasEnabled;

  public function __construct(
    private PintoMapping $pintoMapping,
    ?ElementSlots $elementSlots = NULL,
  ) {
    $this->isCanvasEnabled = $elementSlots !== NULL;
  }

  /**
   * @phpstan-param array<string, array{"variables": array<string, mixed>}> $theme_registry
   */
  #[Hook('theme_registry_alter')]
  public function hookThemeRegistryAlter(&$theme_registry): void {
    if (FALSE === $this->isCanvasEnabled) {
      return;
    }

    // This will only work with Pinto 0.6.x series and below.
    foreach ($this->pintoMapping->getEnumClasses() as $enumClass) {
      foreach ($enumClass::cases() as $case) {
        $rCase = new \ReflectionEnumUnitCase($case::class, $case->name);
        $definitionAttr = ($rCase->getAttributes(Definition::class)[0] ?? NULL)?->newInstance();
        if (NULL === $definitionAttr) {
          continue;
        }

        // All will have a theme definition.
        if (!isset($theme_registry[$case->name()])) {
          throw new \LogicException('Expected a hooktheme for ' . $case::class . '::' . $case->name);
        }

        $hookTheme = &$theme_registry[$case->name()];
        // Add variables so Canvas picks up context in CanvasWrapperNode.
        $hookTheme['variables'] += [
          // Twig context for \Drupal\canvas\Twig\CanvasWrapperNode::compile.
          'canvas_is_preview' => NULL,
          'canvas_uuid' => NULL,
          'canvas_slot_ids' => NULL,
        ];
      }
    }
  }

}
