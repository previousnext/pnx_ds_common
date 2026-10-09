<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\PintoCanvasRender;

use Drupal\canvas_pinto\PintoCanvasRender;
use PreviousNext\Ds\Common\Component\Accordion\Accordion;
use PreviousNext\Ds\Common\Component\Accordion\AccordionItem\AccordionItem;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Adds component-child glue for specific components.
 */
#[AsTaggedItem(priority: 512)]
final class IdsCustomBehaviourResolver implements PintoCanvasRender\ComponentResolverInterface {

  /**
   * @var \WeakMap<PintoCanvasRender\ChildBySlot, (\Closure(PintoCanvasRender\CanvasComponentMetadata, PintoCanvasRender\ChildBySlot): void)|null>
   */
  public \WeakMap $handleChildren;

  public function __construct() {
    $this->handleChildren = new \WeakMap();
  }

  public function supportsRelationship(object $component, PintoCanvasRender\ChildBySlot $childBySlot): bool {
    $this->handleChildren[$childBySlot] = match (TRUE) {
      // Force `mixed` accordion children into accordionitem.
      $component instanceof Accordion && !$childBySlot->child instanceof AccordionItem => static function (PintoCanvasRender\CanvasComponentMetadata $componentMetadata, PintoCanvasRender\ChildBySlot $childBySlot): void {
        $component = $componentMetadata->component;
        \assert($component instanceof Accordion);
        // We technically don't allow this, but we're able to force items into their own accordionitem so they are still
        // drag-and-droppable. The faultly title is enough to encourage editors to correct things.
        // This is basically a workaround until Canvas supports limiting component children.
        $component[] = $item = AccordionItem::create($componentMetadata->isPreview ? 'Unsupported child, add an Accordion item first!' : '');
        $item[] = $childBySlot->child;
      },
      default => NULL,
    };

    return $this->handleChildren[$childBySlot] !== NULL;
  }

  public function handleChild(PintoCanvasRender\CanvasComponentMetadata $component, PintoCanvasRender\ChildBySlot $childBySlot): void {
    $handler = $this->handleChildren[$childBySlot] ?? throw new \LogicException('Guaranteed by prior supportsRelationship call.');
    $handler($component, $childBySlot);
  }

}
