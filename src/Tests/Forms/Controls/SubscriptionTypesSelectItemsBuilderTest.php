<?php declare(strict_types=1);

namespace Crm\PaymentsModule\Tests\Forms\Controls;

use Crm\ApplicationModule\Helpers\PriceHelper;
use Crm\ApplicationModule\Models\Database\ActiveRowFactory;
use Crm\ApplicationModule\Tests\CrmTestCase;
use Crm\PaymentsModule\Forms\Controls\SubscriptionTypesSelectItemsBuilder;

class SubscriptionTypesSelectItemsBuilderTest extends CrmTestCase
{
    private ActiveRowFactory $activeRowFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activeRowFactory = $this->inject(ActiveRowFactory::class);
    }

    public function testBuildSimple(): void
    {
        $subscriptionTypes = $this->activeRowFactory->createMultiple([
            [
                'id' => 1,
                'name' => 'This is default subscription',
                'default' => 1,
                'visible' => 1,
                'sorting' => 1,
            ],
            [
                'id' => 2,
                'name' => 'This is non-default subscription',
                'default' => 0,
                'visible' => 1,
                'sorting' => 2,
            ],
            [
                'id' => 3,
                'name' => 'This is default but hidden subscription',
                'default' => 1,
                'visible' => 0,
                'sorting' => 4, // test sorting
            ],
            [
                'id' => 4,
                'name' => 'This is hidden non-default subscription',
                'default' => 0,
                'visible' => 0,
                'sorting' => 3,
            ],
        ]);

        $priceHelper = $this->createMock(PriceHelper::class);

        $builder = new SubscriptionTypesSelectItemsBuilder($priceHelper);
        $items = $builder->buildSimple($subscriptionTypes);

        $this->assertSame([
            'payments.form.controls.subscription_types.categories.default' => [
                1 => 'This is default subscription',
            ],
            'payments.form.controls.subscription_types.categories.non_default' => [
                2 => 'This is non-default subscription',
            ],
            'payments.form.controls.subscription_types.categories.hidden' => [
                4 => 'This is hidden non-default subscription',
                3 => 'This is default but hidden subscription',
            ],
        ], $items);
    }

    public function testBuildWithDescription(): void
    {
        $subscriptionTypes = $this->activeRowFactory->createMultiple([
            [
                'id' => 1,
                'name' => 'This is default subscription',
                'price' => 12.34,
                'code' => 'default_subscription_code',
                'default' => 1,
                'visible' => 1,
                'sorting' => 1,
            ],
            [
                'id' => 2,
                'name' => 'This is non-default subscription',
                'price' => 23.45,
                'code' => 'non_default_subscription_code',
                'default' => 0,
                'visible' => 1,
                'sorting' => 2,
            ],
            [
                'id' => 3,
                'name' => 'This is default but hidden subscription',
                'price' => 34.56,
                'code' => 'default_hidden_subscription_code',
                'default' => 1,
                'visible' => 0,
                'sorting' => 3,
            ],
            [
                'id' => 4,
                'name' => 'This is hidden non-default subscription',
                'price' => 45.67,
                'code' => 'hidden_non_default_subscription_code',
                'default' => 0,
                'visible' => 0,
                'sorting' => 4,
            ],
        ]);

        $priceHelper = $this->createMock(PriceHelper::class);
        $priceHelper->expects($this->exactly(4))
            ->method('getFormattedPrice')
            ->willReturnCallback(static fn ($price) => sprintf('%.2f CUR', $price));

        $builder = new SubscriptionTypesSelectItemsBuilder($priceHelper);
        $items = $builder->buildWithDescription($subscriptionTypes);

        $this->assertSame([
            'payments.form.controls.subscription_types.categories.default' => [
                1 => 'This is default subscription / 12.34 CUR <small>(default_subscription_code)</small>',
            ],
            'payments.form.controls.subscription_types.categories.non_default' => [
                2 => 'This is non-default subscription / 23.45 CUR <small>(non_default_subscription_code)</small>',
            ],
            'payments.form.controls.subscription_types.categories.hidden' => [
                3 => 'This is default but hidden subscription / 34.56 CUR <small>(default_hidden_subscription_code)</small>',
                4 => 'This is hidden non-default subscription / 45.67 CUR <small>(hidden_non_default_subscription_code)</small>',
            ],
        ], $items);
    }
}
