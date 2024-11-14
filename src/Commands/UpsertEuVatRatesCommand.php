<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\PaymentsModule\Models\Api\VatStack\Client as VatStackApiClient;
use Crm\PaymentsModule\Repositories\VatRatesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IResponse;
use Nette\Utils\Json;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;
use Tracy\ILogger;

class UpsertEuVatRatesCommand extends Command
{
    use DecoratedCommandTrait;

    public const COUNTRY_CODE_OPTION = 'country_code';

    public const API_KEY_OPTION = 'vatstack_api_key';

    public function __construct(
        private CountriesRepository $countriesRepository,
        private VatRatesRepository $vatRatesRepository,
        private VatStackApiClient $vatStackApiClient,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('payments:upsert_eu_vat_rates')
            ->setDescription('Insert/Update VAT rates of EU member countries')
            ->addOption(
                self::COUNTRY_CODE_OPTION,
                'c',
                InputOption::VALUE_REQUIRED,
                "Provide ISO code of specific country if you don't want to update VAT rates of all member countries.",
            )
            ->addOption(
                self::API_KEY_OPTION,
                'k',
                InputOption::VALUE_REQUIRED,
                "Provide public VatStack API key. Overrides API key set by neon configs and DI.",
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        // use API key provided by command option, or from config (set by DI)
        $apiKey = $input->getOption(self::API_KEY_OPTION) ?? null;
        if ($apiKey !== null) {
            $this->vatStackApiClient->setApiKey($apiKey);
        }

        // fetch VAT rates

        $selectedCountry = $input->getOption(self::COUNTRY_CODE_OPTION) ?? null;
        if ($selectedCountry) {
            $this->line("Fetching VAT rate only for provided country [{$selectedCountry}].");
        } else {
            $this->line("Fetching VAT rates for all EU member countries.");
        }

        try {
            $response = $this->vatStackApiClient->getVats(countryIsoCode: $selectedCountry);
        } catch (ConnectException $e) {
            return $this->logErrorAndFail(sprintf(
                'Unable to fetch VAT rates from vatstack.com, connection exception returned. Try later. Error: [%s]',
                $e->getMessage(),
            ));
        } catch (ClientException|ServerException $e) {
            // 400 level & 500 level status codes
            return $this->logErrorAndFail(sprintf(
                'Unable to fetch VAT rates from vatstack.com. Status code: [%s]. Error: [%s].',
                $e->getResponse()->getStatusCode(),
                $e->getResponse()->getBody()->getContents(),
            ));
        }

        if ($response->getStatusCode() !== IResponse::S200_OK) {
            return $this->logErrorAndFail(sprintf(
                'Unable to fetch VAT rates from vatstack.com. Status code: [%s]. Response: [%s]',
                $response->getStatusCode(),
                $response->getBody()->getContents(),
            ));
        }

        $vatRates = Json::decode($response->getBody()->getContents(), Json::FORCE_ARRAY);

        // save VAT rates

        foreach ($vatRates['rates'] as $vatRate) {
            $this->line("  * <info>{$vatRate['country_name']}</info> [{$vatRate['country_code']}]:");

            $country = $this->countriesRepository->findByIsoCode($vatRate['country_code']);
            if ($country === null) {
                return $this->logErrorAndFail(sprintf(
                    'Unable to find country with ISO code [%s]. VAT rate element: [%s].',
                    $vatRate['country_code'],
                    print_r($vatRate, true),
                ));
            }
            $currentVats = $this->vatRatesRepository->getByCountry($country);

            $standardRate = $vatRate['standard_rate'];
            $reducedRates = $vatRate['reduced_rates'] ?? [];
            $ePeriodicalRate = $vatRate['categories']['eperiodical'] ?? null;
            $eBookRate = $vatRate['categories']['ebook'] ?? null;

            $this->printVatLines($currentVats, $standardRate, $ePeriodicalRate, $eBookRate, $reducedRates);

            $vatRateRow = $this->vatRatesRepository->upsert($country, $standardRate, $ePeriodicalRate, $eBookRate, $reducedRates);
            if ($vatRateRow === null) {
                return $this->logErrorAndFail(sprintf(
                    'Unable to upsert VAT rates for country code [%s]. VAT rate element: [%s]',
                    $vatRate['country_code'],
                    print_r($vatRate, true),
                ));
            }

            if ($currentVats === null) {
                $status = '<comment>added</comment>';
            } elseif ($currentVats->id !== $vatRateRow->id) {
                $status = '<comment>updated</comment>';
            } else {
                $status = '<info>unchanged</info>';
            }
            $this->line("    - Status: {$status}.");
        }

        $this->line("Done.");

        return Command::SUCCESS;
    }

    private function logErrorAndFail(string $message): int
    {
        Debugger::log($message, ILogger::ERROR);
        $this->error($message);
        return Command::FAILURE;
    }

    private function printVatLines(
        ?ActiveRow $currentVats,
        ?float $standardRate,
        ?float $ePeriodicalRate,
        ?float $eBookRate,
        array $reducedRates = []
    ): void {
        $this->printVatLine(
            vatName: 'standard',
            vat: $standardRate,
            oldVat: (float) $currentVats?->standard !== $standardRate ? $currentVats?->standard : null,
        );
        $this->printVatLine(
            vatName: 'e-periodical',
            vat: $ePeriodicalRate,
            oldVat: (float) $currentVats?->eperiodical !== $ePeriodicalRate ? $currentVats?->eperiodical : null,
        );
        $this->printVatLine(
            vatName: 'e-book',
            vat: $eBookRate,
            oldVat: (float) $currentVats?->ebook !== $eBookRate ? $currentVats?->ebook : null,
        );

        // check reduced rates

        // sort new reduced rates and encode them to json for simple comparison
        sort($reducedRates);
        $newReducedVat = Json::encode($reducedRates);
        // reduced vats are already sorted and stored as JSON; just remove spaces added by database
        $oldReducedVat = $currentVats?->reduced !== null ? str_replace(' ', '', $currentVats->reduced) : [];

        $this->printVatLine(
            vatName: 'reduced',
            vat: $newReducedVat,
            oldVat: $newReducedVat !== $oldReducedVat ? $oldReducedVat : null,
        );
    }

    private function printVatLine(string $vatName, $vat, $oldVat): void
    {
        $highlight = $oldVat ? "comment" : "info";
        $numberFormat = is_float($vat) ? "    - %s: <%s>%.2f</%s>" : "    - %s: <%s>%s</%s>";
        $message = sprintf($numberFormat, $vatName, $highlight, $vat, $highlight);

        if ($oldVat) {
            $numberFormat = is_float($oldVat) ? " (previously: %.2f)" : " (previously: %s)";
            $message .= sprintf($numberFormat, $oldVat);
        }

        $this->line($message);
    }
}
