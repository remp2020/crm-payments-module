<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\ApplicationModule\Models\NowTrait;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;

class VatRatesRepository extends Repository
{
    use NowTrait;

    protected $tableName = 'vat_rates';

    public function __construct(
        Explorer $database,
    ) {
        parent::__construct($database);
    }

    final public function upsert(
        ActiveRow $country,
        float $standard,
        ?float $ePeriodical = null,
        ?float $eBook = null,
        array $reduced = [],
    ): ?ActiveRow {
        $now = $this->getNow();

        // sort & encode reduced rates before comparison & storing
        sort($reduced);
        $reduced = Json::encode($reduced);

        $countryVats = $this->getByCountry($country);
        if ($countryVats !== null) {
            $changed = false;
            if ($standard !== $countryVats->standard) {
                $changed = true;
            } elseif ($ePeriodical !== $countryVats->eperiodical) {
                $changed = true;
            } elseif ($eBook !== $countryVats->ebook) {
                $changed = true;
            } else {
                // remove spaces added into json by database and compare with current vats
                $currentReducedVats = $countryVats?->reduced !== null ? str_replace(' ', '', $countryVats->reduced) : Json::encode([]);
                if ($reduced !== $currentReducedVats) {
                    $changed = true;
                }
            }

            // nothing changed; no need to create new entry
            if ($changed === false) {
                return $countryVats;
            }

            // else retire current entry
            parent::update($countryVats, [
                'valid_to' => $now,
            ]);
        }

        $inserted = parent::insert([
            'country_id' => $country->id,
            'standard' => $standard,
            'reduced' => $reduced,
            'eperiodical' => $ePeriodical,
            'ebook' => $eBook,
            'valid_from' => $now, // new VAT has always set valid from to NOW; VatStack doesn't provide date of VAT change
            'valid_to' => null,
            'created_at' => $now,
        ]);
        return ($inserted instanceof ActiveRow) ? $inserted : null;
    }

    final public function insert($data): ?ActiveRow
    {
        // We don't want to allow "simple" insert of VAT rates.
        // Old entry has to be retired by setting `valid_to` and new entry with new rates added.
        throw new \Exception('Insert is not supported. Use `VatRatesRepository->upsert()`.');
    }

    final public function update(ActiveRow &$row, $data): bool
    {
        // We don't want to allow update of VAT rates.
        // Old entry has to be retired by setting `valid_to` and new entry with new rates added.
        throw new \Exception('Update is not supported. Use `VatRatesRepository->upsert()`.');
    }

    /**
     * Returns VATs for all countries with stored VAT.
     */
    final public function getVatRates(): Selection
    {
        return $this->getTable()
            ->where([
                'valid_to IS NULL', // valid_to is set for "expired" VAT rates; current VAT has valid_to set to NULL
            ])->order('country.name ASC');
    }

    /**
     * Returns country's last valid VATs
     *
     * Note: To keep data current, run UpsertEuVatRatesCommand regularly.
     */
    final public function getByCountry(ActiveRow $country): ?ActiveRow
    {
        return $this->getTable()->where([
            'country_id' => $country->id,
            'valid_to IS NULL', // valid_to is set for "expired" VAT rates; current VAT has valid_to set to NULL
        ])->fetch();
    }

    /**
     * Returns country's VAT which was valid when payment was created
     */
    final public function getByCountryAndDate(ActiveRow $country, \DateTime $createdAt): ?ActiveRow
    {
        $current = $this->getByCountry($country);
        if ($current !== null && $current->valid_from <= $createdAt) {
            return $current;
        }

        // otherwise search for older VAT
        return $this->getTable()->where([
                'country_id' => $country->id,
                'valid_from < ?' => $createdAt,
                'valid_to >= ?' => $createdAt,
            ])
            ->order('valid_to')
            ->fetch();
    }
}
