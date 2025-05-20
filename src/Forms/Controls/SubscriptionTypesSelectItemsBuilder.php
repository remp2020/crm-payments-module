<?php declare(strict_types=1);

namespace Crm\PaymentsModule\Forms\Controls;

use Crm\ApplicationModule\Helpers\PriceHelper;
use Exception;
use Nette\Database\Table\ActiveRow;

class SubscriptionTypesSelectItemsBuilder
{
    private const CATEGORY_DEFAULT = 'payments.form.controls.subscription_types.categories.default';
    private const CATEGORY_NON_DEFAULT = 'payments.form.controls.subscription_types.categories.non_default';
    private const CATEGORY_HIDDEN = 'payments.form.controls.subscription_types.categories.hidden';

    public function __construct(
        private readonly PriceHelper $priceHelper,
    ) {
    }

    /**
     * @param ActiveRow[] $subscriptionTypes
     */
    public function buildSimple(array $subscriptionTypes): array
    {
        return $this->build(
            $subscriptionTypes,
            static fn (ActiveRow $subscriptionType) => $subscriptionType->name,
        );
    }

    /**
     * @param ActiveRow[] $subscriptionTypes
     */
    public function buildWithDescription(array $subscriptionTypes): array
    {
        $labelCallback = function (ActiveRow $subscriptionType) {
            $price = $this->priceHelper->getFormattedPrice($subscriptionType->price);

            return sprintf(
                "%s / %s <small>(%s)</small>",
                $subscriptionType->name,
                $price,
                $subscriptionType->code,
            );
        };

        return $this->build($subscriptionTypes, $labelCallback);
    }

    /**
     * @param ActiveRow[] $subscriptionTypes
     * @param callable(ActiveRow $subscriptionType): string $subscriptionTypeLabelCallback
     */
    private function build(array $subscriptionTypes, callable $subscriptionTypeLabelCallback): array
    {
        usort($subscriptionTypes, static function (ActiveRow $firstSubscriptionType, ActiveRow $secondSubscriptionType) {
            return $firstSubscriptionType->sorting <=> $secondSubscriptionType->sorting;
        });

        // to force the order of categories
        $categories = [
            self::CATEGORY_DEFAULT => [],
            self::CATEGORY_NON_DEFAULT => [],
            self::CATEGORY_HIDDEN => [],
        ];

        foreach ($subscriptionTypes as $subscriptionType) {
            $categoryName = $this->getCategoryName($subscriptionType);

            $categories[$categoryName][$subscriptionType->id] = $subscriptionTypeLabelCallback($subscriptionType);
        }

        return $categories;
    }

    private function getCategoryName(ActiveRow $subscriptionType): string
    {
        $isDefault = $subscriptionType->default === 1;
        $isVisible = $subscriptionType->visible === 1;

        return match (true) {
            $isDefault && $isVisible => self::CATEGORY_DEFAULT,
            $isVisible => self::CATEGORY_NON_DEFAULT,
            !$isVisible => self::CATEGORY_HIDDEN,
            default => throw new Exception('Unknown subscription type category'),
        };
    }
}
