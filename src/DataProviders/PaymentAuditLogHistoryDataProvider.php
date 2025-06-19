<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Helpers\PriceHelper;
use Crm\ApplicationModule\Models\DataProvider\AuditLogHistoryDataProviderInterface;
use Crm\ApplicationModule\Models\DataProvider\AuditLogHistoryDataProviderItem;
use Crm\ApplicationModule\Models\DataProvider\AuditLogHistoryItemChangeIndicatorEnum;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Nette\Utils\Json;

class PaymentAuditLogHistoryDataProvider implements AuditLogHistoryDataProviderInterface
{
    private const WATCHED_COLUMNS = [
        'additional_amount',
        'additional_type',
        'subscription_type_id',
        'user_id',
        'upgrade_type',
    ];

    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
        private readonly CountriesRepository $countriesRepository,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly DataProviderManager $dataProviderManager,
        private readonly PriceHelper $priceHelper,
    ) {
    }

    public function provide(string $tableName, string $signature): array
    {
        if ($tableName !== 'payments') {
            return [];
        }

        $paymentHistory = $this->auditLogRepository->getByTableAndSignature($tableName, $signature)
            ->order('created_at ASC, id ASC')
            ->fetchAll();

        $payment = $this->paymentsRepository->find(intval($signature));
        if (!$payment) {
            return [];
        }

        // handle payment changes
        /** @var AuditLogHistoryDataProviderItem[] $results */
        $results = [];
        foreach ($paymentHistory as $item) {
            $itemKey = strval($item->created_at) . $item->user?->id;
            $auditLogHistoryDataProviderItem = $results[$itemKey] ?? new AuditLogHistoryDataProviderItem(
                $item->created_at,
                $item->operation,
                $item->user,
            );

            $changes = Json::decode($item->data, true);

            // handle status changes
            if (isset($changes['to']['status']) && $changes['to']['status'] === PaymentStatusEnum::Paid->value) {
                $auditLogHistoryDataProviderItem->addMessage(
                    'payments.data_provider.payment_audit_log_history.status_change.paid',
                );
                $auditLogHistoryDataProviderItem->setChangeIndicator(AuditLogHistoryItemChangeIndicatorEnum::Success);
            } elseif (isset($changes['to']['status']) && $changes['to']['status'] === PaymentStatusEnum::Fail->value) {
                $auditLogHistoryDataProviderItem->addMessage(
                    'payments.data_provider.payment_audit_log_history.status_change.fail',
                );
                $auditLogHistoryDataProviderItem->setChangeIndicator(AuditLogHistoryItemChangeIndicatorEnum::Danger);
            } elseif (isset($changes['to']['status']) && $changes['to']['status'] === PaymentStatusEnum::Refund->value) {
                $auditLogHistoryDataProviderItem->addMessage(
                    'payments.data_provider.payment_audit_log_history.status_change.refund',
                );
                $auditLogHistoryDataProviderItem->setChangeIndicator(AuditLogHistoryItemChangeIndicatorEnum::Info);
            }

            // handle payment amount change
            if (isset($changes['from']['amount']) && isset($changes['to']['amount'])) {
                $auditLogHistoryDataProviderItem->addMessage(
                    'payments.data_provider.payment_audit_log_history.amount_change',
                    [
                        'from' => $this->priceHelper->getFormattedPrice($changes['from']['amount']),
                        'to' => $this->priceHelper->getFormattedPrice($changes['to']['amount']),
                    ],
                );
            }

            // handle payment note change
            if (isset($changes['to']['note'])) {
                $auditLogHistoryDataProviderItem->addMessage(
                    'payments.data_provider.payment_audit_log_history.note_change',
                    [
                        'note' => $changes['to']['note'],
                    ],
                );
            }

            // handle payment country changed
            if (isset($changes['from']['payment_country_id']) && isset($changes['to']['payment_country_id'])) {
                $fromCountry = $this->countriesRepository->find($changes['from']['payment_country_id']);
                $toCountry = $this->countriesRepository->find($changes['to']['payment_country_id']);

                $auditLogHistoryDataProviderItem->addMessage(
                    'payments.data_provider.payment_audit_log_history.country_change',
                    [
                        'from' => $fromCountry->name,
                        'to' => $toCountry->name,
                    ],
                );
            }

            // handle payment address changed
            if (isset($changes['to']['address_id'])) {
                $auditLogHistoryDataProviderItem->addMessage(
                    'payments.data_provider.payment_audit_log_history.address_change',
                );
            }

            // handle variable symbol change
            if (isset($changes['from']['variable_symbol']) && isset($changes['to']['variable_symbol'])) {
                $auditLogHistoryDataProviderItem->addMessage(
                    'payments.data_provider.payment_audit_log_history.variable_symbol_change',
                    [
                        'from' => $changes['from']['variable_symbol'],
                        'to' => $changes['to']['variable_symbol'],
                    ],
                );
            }

            // if there are no messages, but we have changes, add a default message
            if ($item->operation === 'update' && empty($auditLogHistoryDataProviderItem->getMessages())) {
                // Filter only watched columns
                $changedColumns = array_intersect(array_keys($changes['to']), self::WATCHED_COLUMNS);
                if (!empty($changedColumns)) {
                    $auditLogHistoryDataProviderItem->addMessage(
                        'payments.data_provider.payment_audit_log_history.columns_changed',
                        [
                            'columns' => implode(', ', $changedColumns),
                        ],
                    );
                }
            }

            $results[$itemKey] = $auditLogHistoryDataProviderItem;
        }

        $results = array_values($results);

        /** @var PaymentAuditLogHistoryDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'payments.dataprovider.payment_audit_log_history',
            PaymentAuditLogHistoryDataProviderInterface::class,
        );
        foreach ($providers as $provider) {
            $results = array_merge($results, $provider->provide($payment));
        }

        return array_filter($results);
    }
}
