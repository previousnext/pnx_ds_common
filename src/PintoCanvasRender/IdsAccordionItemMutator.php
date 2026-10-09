<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\PintoCanvasRender;

use Drupal\canvas_pinto\PintoCanvasRender\Event\MutateComponent;
use PreviousNext\Ds\Common\Atom\Html\Html;
use PreviousNext\Ds\Common\Component as CommonComponents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * When previewing in Canvas, pry AccordionItem open and add a message.
 */
final class IdsAccordionItemMutator implements EventSubscriberInterface {

  public function mutateComponent(MutateComponent $mutateComponent): void {
    if (!$mutateComponent->component->isPreview || !$mutateComponent->component->component instanceof CommonComponents\Accordion\Accordion) {
      return;
    }

    foreach ($mutateComponent->component->component as $accordionItem) {
      if (!$accordionItem->open) {
        $suffix = ' (🔼 Will be Closed)';
        if ($accordionItem->title instanceof Html) {
          $accordionItem->title[] = $suffix;
        }
        else {
          $accordionItem->title = ($accordionItem->title ?? '') . $suffix;
        }
      }
      $accordionItem->open = TRUE;
    }
  }

  /**
   * @internal will be changed to #[AsEventListener] when available.
   * @see https://www.drupal.org/project/drupal/issues/3376163
   */
  public static function getSubscribedEvents(): array {
    return [
      MutateComponent::class => 'mutateComponent',
    ];
  }

}
