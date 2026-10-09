<?php

declare(strict_types=1);

namespace Drupal\pnx_ds_common\PintoCanvasRender;

use Drupal\canvas\Entity\Component;
use Drupal\canvas_pinto\PintoCanvasRender;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use GoodPhp\Reflection\Type\NamedType;
use Nette\Utils\Type;
use Pinto\Exception\PintoMissingObjectMapping;
use Pinto\PintoMapping;
use PreviousNext\Ds\Common\Atom as CommonAtoms;
use PreviousNext\Ds\Common\Component as CommonComponents;
use PreviousNext\Ds\Common\Modifier\Lookup\CollectionGenerics;
use PreviousNext\Ds\Common\Utility\CommonObjectInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Adds generic component-child glue for all components as a fallback.
 */
#[AsTaggedItem(priority: 0)]
final class IdsGenericResolver implements PintoCanvasRender\ComponentResolverOwnerInterface {

  /**
   * Memoize whether this service is responding to a component class.
   *
   * @var array<class-string, bool>
   */
  private array $isHandling = [];

  /**
   * Record a closure after determining if this service is responding to a component-child combo.
   *
   * Reduces computation between supportsRelationship and handleChild.
   *
   * @var \WeakMap<PintoCanvasRender\ChildBySlot, (\Closure(object, PintoCanvasRender\ChildBySlot): void)|null>
   */
  public \WeakMap $handleChildren;

  public function __construct(
    private PintoMapping $pintoMapping,
    private CollectionGenerics $collectionGenerics,
    private ElementSlots $elementSlots,
  ) {
    $this->handleChildren = new \WeakMap();
  }

  public function supports(object $component): bool {
    return $this->isHandlingClass($component::class);
  }

  public function wrap(object $component, $prefix, $suffix): void {
    $this->elementSlots->setWrapper($component, $prefix, $suffix);
  }

  public function supportsRelationship(object $component, PintoCanvasRender\ChildBySlot $childBySlot): bool {
    if (FALSE === $this->isHandlingClass($component::class)) {
      // Fail fast if parent is not an IDS component.
      return FALSE;
    }

    // Designated extra children, which bypasses all Pinto API and appends on the Twig variable.
    $addExtraChildren = function (object $pintoObject, PintoCanvasRender\ChildBySlot $childBySlot): void {
      $this->elementSlots->addExtraChildren($pintoObject, $childBySlot);
    };

    $apt = $this->collectionGenerics->appendType($component::class);
    $child = $childBySlot->child;

    $this->handleChildren[$childBySlot] = match (TRUE) {
      // If it's the special empty placeholder, do not try to add to children. Always append to the Twig variable,
      // in case the children get wrapped in some way.
      ($child instanceof MarkupInterface && \str_contains((string) $child, 'canvas--slot-empty-placeholder')) => static function (object $component, PintoCanvasRender\ChildBySlot $childBySlot) use ($addExtraChildren): void {
        $addExtraChildren($component, $childBySlot->replaceChild(['#markup' => $childBySlot->child]));
      },

      // Mixed accepts everything.
      (((string) $apt) === 'mixed') => static function (object $component, PintoCanvasRender\ChildBySlot $childBySlot): void {
        \assert($component instanceof \ArrayAccess);
        $child = $childBySlot->child;
        $component[] = ($child instanceof MarkupInterface ? ['#markup' => $child] : $child);
      },

      // Ensure append type matches type of child.
      (
        \is_object($child) &&
        $apt instanceof NamedType &&
        (Type::fromString($apt->name)->allows(Type::fromString($child::class)))
      ) => static function (object $component, PintoCanvasRender\ChildBySlot $childBySlot): void {
        \assert($component instanceof \ArrayAccess);
        $component[] = $childBySlot->child;
      },
      default => NULL,
    };

    return $this->handleChildren[$childBySlot] !== NULL;
  }

  public function handleChild(PintoCanvasRender\CanvasComponentMetadata $component, PintoCanvasRender\ChildBySlot $childBySlot): void {
    $handler = $this->handleChildren[$childBySlot] ?? throw new \LogicException('Guaranteed by prior supportsRelationship call.');
    $handler($component->component, $childBySlot);
  }

  public function lastChanceHandleInvalidChild(PintoCanvasRender\CanvasComponentMetadata $componentMetadata, PintoCanvasRender\ChildBySlot $childBySlot): void {
    $component = $componentMetadata->component;
    $isPreview = $componentMetadata->isPreview;

    $transferWrapperFromUnsupported = function (object $replacementChild, mixed $existingChild): void {
      // Copy wrapper to the new element we just created, so users can manipulate it still.
      if (\is_object($existingChild)) {
        $this->elementSlots->copyWrapperFromTo($existingChild, $replacementChild);
      }
      // Handle non Pinto components:
      elseif (\is_array($existingChild)) {
        $this->elementSlots->setWrapper($replacementChild, $existingChild['#prefix'], $existingChild['#suffix']);
      }
      else {
        throw new \LogicException('Expected the existing child to be an object or render array.');
      }
    };

    $label = static function () use ($component, $childBySlot): TranslatableMarkup {
      if (\is_object($childBySlot->child)) {
        $r = new \ReflectionClass($childBySlot->child::class);
        $child = $r->getShortName();
      }
      else {
        $sourcePluginId = $childBySlot->sourcePluginId ?? throw new \LogicException('Expected a sourcePluginId at this point.');
        $componentConfig = Component::load($sourcePluginId) ?? throw new \LogicException('Unable to load component: ' . $sourcePluginId);
        // Fall back to the plugin ID when the component has no label.
        $child = $componentConfig->label() ?? $sourcePluginId;
      }

      return new TranslatableMarkup('🚨 An invalid child "@child" component was inserted into a "@component" component.', [
        '@child' => $child,
        '@component' => (new \ReflectionClass($component::class))->getShortName(),
      ]);
    };

    if ($component instanceof CommonComponents\Steps\Steps) {
      if ($isPreview) {
        $component[] = $new = CommonComponents\Steps\Step\Step::create(CommonAtoms\Html\Html::create($label()));
        $transferWrapperFromUnsupported($new, $childBySlot->child);
      }
      return;
    }

    if ($component instanceof CommonComponents\LinkList\LinkList) {
      if ($isPreview) {
        $component[] = $new = CommonAtoms\Link\Link::create((string) $label(), Url::fromRoute('<nolink>'));
        $transferWrapperFromUnsupported($new, $childBySlot->child);
      }
      return;
    }

    throw new \Exception('wasnt able to handle this child');
  }

  public function finalizeRoot(object $component): void {
    // Nothing.
  }

  /**
   * @phpstan-param class-string<object> $componentClass
   */
  private function isHandlingClass(string $componentClass): bool {
    if (!\is_a($componentClass, CommonObjectInterface::class, allow_string: TRUE)) {
      return FALSE;
    }

    return $this->isHandling[$componentClass] ??= (function (string $componentClass) {
      try {
        $this->pintoMapping->getByClass($componentClass);
        return TRUE;
      }
      catch (PintoMissingObjectMapping) {
        return FALSE;
      }
    })($componentClass);
  }

}
