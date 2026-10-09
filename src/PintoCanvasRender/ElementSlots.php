<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\PintoCanvasRender;

use Drupal\canvas_pinto\PintoCanvasRender;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Makes data available for Pinto components to consume.
 *
 * - The $wrapElements and $extraChildren props bypass the official Pinto way of working and go straight into Twig.
 * - For the Canvas slot HTML markup, we're relying on Canvas' Twig visitor to add it, but we need to get away from it
 *   because it's not adding the markup in all cases.
 * - Also, Canvas is adding wrappers to every variable known, instead of only components, which is likely a bug, and
 *   unnecessary performance loss.
 * - The Canvas visitor also doesn't work with some Twigs as they don't have valid HTML due to an element being defined
 *   by a variable like `<{{ element }} />. This should be seen as a bug in Canvas also.
 */
final class ElementSlots implements EventSubscriberInterface {

  /**
   * Canvas component UUID, Slot IDs, and isPreview keyed by object instance.
   *
   * @var \SplObjectStorage<object, PintoCanvasRender\CanvasComponentMetadata>
   */
  public \SplObjectStorage $canvasData;

  /**
   * Canvas component UUID, Slot IDs, and isPreview keyed by object instance.
   *
   * @var \SplObjectStorage<object, array{mixed, mixed}>
   */
  public \SplObjectStorage $wrapElements;

  /**
   * @var \SplObjectStorage<object, array<PintoCanvasRender\ChildBySlot>>
   */
  public \SplObjectStorage $extraChildren;

  public function __construct() {
    $this->canvasData = new \SplObjectStorage();
    $this->wrapElements = new \SplObjectStorage();
    $this->extraChildren = new \SplObjectStorage();
  }

  /**
   * @phpstan-return $this
   */
  public function setWrapper(object $component, mixed $prefix, mixed $suffix): static {
    $this->wrapElements[$component] = [$prefix, $suffix];
    return $this;
  }

  /**
   * @phpstan-return $this
   * @throws \Exception
   */
  public function copyWrapperFromTo(object $existingComponent, object $newComponent): static {
    $this->wrapElements[$newComponent] = ($this->wrapElements[$existingComponent] ?? throw new \Exception('Cannot copy wrapper element from another as it does not exist.'));

    return $this;
  }

  /**
   * @phpstan-return $this
   */
  public function addExtraChildren(object $component, PintoCanvasRender\ChildBySlot $childBySlot): static {
    $extra = $this->extraChildren[$component] ?? [];
    $extra[] = $childBySlot;
    $this->extraChildren[$component] = $extra;
    return $this;
  }

  /**
   * @phpstan-param array<string, mixed> $built
   * @see \PreviousNext\Ds\Common\Utility\ObjectTrait::postModifyRenderArray
   */
  public function postModifyRenderArray(object $component, array &$built): void {
    $built = [
      // Wrappers on a different layer so we dont conflict with any of the objects' slots.
      '_wrapped_by_ElementSlots' => $built,
    ];

    $wrapper = $this->wrapElements[$component] ?? NULL;
    // A wrapper may only be present in preview routes.
    if ($wrapper !== NULL) {
      [$built['#prefix'], $built['#suffix']] = $wrapper;
    }

    $canvasData = $this->canvasData[$component] ?? NULL;
    if ($canvasData !== NULL) {
      $built['_wrapped_by_ElementSlots'] += [
        // Twig context for \Drupal\canvas\Twig\CanvasWrapperNode::compile.
        // PnxCommonCanvasHooks::hookThemeRegistryAlter is responsible for
        // altering and adding hook_theme variables:
        '#canvas_uuid' => $canvasData->componentUuid,
        '#canvas_slot_ids' => $canvasData->slotIds,
        '#canvas_is_preview' => $canvasData->isPreview,
      ];
    }

    foreach (($this->extraChildren[$component] ?? []) as $childBySlot) {
      // If its null/not-set then initialize it as an array, otherwise if its some other value then throw an exception.
      // We do this check as appending onto a non array will have other unexpected failure states.
      $built['_wrapped_by_ElementSlots']['#' . $childBySlot->slotName] ??= [];
      if (FALSE === \is_array($built['_wrapped_by_ElementSlots']['#' . $childBySlot->slotName])) {
        throw new \LogicException(\sprintf('Expected slot %s in %s to be an array', $childBySlot->slotName, $component::class));
      }

      $built['_wrapped_by_ElementSlots']['#' . $childBySlot->slotName][] = $childBySlot->child;
    }
  }

  public function receiveCanvasComponentMetadata(PintoCanvasRender\CanvasComponentMetadata $componentMetadata): void {
    $this->canvasData[$componentMetadata->component] = $componentMetadata;
  }

  /**
   * @internal change to #[AsEventListener] when available.
   * @see https://www.drupal.org/project/drupal/issues/3376163
   */
  public static function getSubscribedEvents(): array {
    return [
      PintoCanvasRender\CanvasComponentMetadata::class => 'receiveCanvasComponentMetadata',
    ];
  }

}
