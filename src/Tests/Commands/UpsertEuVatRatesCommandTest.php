<?php

namespace Crm\PaymentsModule\Tests\Commands;

use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Commands\UpsertEuVatRatesCommand;
use Crm\PaymentsModule\Models\Api\VatStack\Client;
use Crm\PaymentsModule\Repositories\VatRatesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class UpsertEuVatRatesCommandTest extends DatabaseTestCase
{
    public const VATSTACK_PUBLIC_KEY = 'vatstack_public_key';

    private UpsertEuVatRatesCommand $command;

    private VatRatesRepository $vatRatesRepository;

    protected function requiredRepositories(): array
    {
        return [
            CountriesRepository::class,
            VatRatesRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            CountriesSeeder::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->vatRatesRepository = $this->getRepository(VatRatesRepository::class);
    }

    public static function dataProviderInputArgumentsSuccess()
    {
        return [
            // key is set in config.test.neon
            'AllCountries_ApiSetByConfig' => [
                'input' => '',
            ],
            'AllCountries_ApiProvided' => [
                'input' => '--' . UpsertEuVatRatesCommand::API_KEY_OPTION . '=' . self::VATSTACK_PUBLIC_KEY,
                'apiKey' => true,
            ],
            'OneCountry_ApiSetByConfig' => [
                'input' => '--' . UpsertEuVatRatesCommand::COUNTRY_CODE_OPTION . '=' . 'sk',
                'apiKey' => false,
                'onlySlovakia' => true,
            ],
        ];
    }

    #[DataProvider('dataProviderInputArgumentsSuccess')]
    public function testSuccess($input, bool $apiKey = false, bool $onlySlovakia = false): void
    {
        // vat_rates table should be empty
        $this->assertEquals(0, $this->vatRatesRepository->totalCount());

        // ********************************************************************
        // prepare mock

        $clientMock = $this->getMockBuilder(Client::class)->getMock();

        // if $apiKey is provided by command; check if it was set
        // if it was pre set by config, we won't catch it in mock (it was already set by DI into original non-mocked client)
        if ($apiKey) {
            $clientMock->expects($this->once())
                ->method('setApiKey')
                ->with(self::VATSTACK_PUBLIC_KEY)
                ->willReturnSelf();
        }

        // $onlySlovakia controls if one country or all should be preset as mock's response
        $vatStackResponse = self::getVatStackJsonResponse($onlySlovakia);
        $clientMock->expects($this->once())
            ->method('getVats')
            // expect 'sk' iso code if only slovakia should be returned
            ->with(
                memberStates: true,
                limit: 100,
                countryIsoCode: $onlySlovakia ? 'sk' : null
            )
            ->willReturn(new Response(
                200,
                [],
                $vatStackResponse['json_response'],
            ));

        // load command with injected mock
        $this->command = new UpsertEuVatRatesCommand(
            $this->getRepository(CountriesRepository::class),
            $this->getRepository(VatRatesRepository::class),
            $clientMock
        );

        // ********************************************************************
        // start test

        $returnCode = $this->command->run(new StringInput($input), new NullOutput());
        $this->assertEquals(Command::SUCCESS, $returnCode);

        // just check number of inserted countries; repository (upsert) has it's own test -> VatRatesRepositoryTest
        $this->assertEquals($vatStackResponse['countries_count'], $this->vatRatesRepository->totalCount());
    }

    public function testFailure(): void
    {
        // vat_rates table should be empty
        $this->assertEquals(0, $this->vatRatesRepository->totalCount());

        // ********************************************************************
        // prepare mock

        $clientMock = $this->getMockBuilder(Client::class)->getMock();

        $vatStackResponse = self::getVatStackJsonResponse();
        $clientMock->expects($this->once())
            ->method('getVats')
            // expect 'sk' iso code if only slovakia should be returned
            ->with(
                memberStates: true,
                limit: 100,
                countryIsoCode: null
            )
            ->willReturn(new Response(
                500,
                [],
            ));

        // load command with injected mock
        $this->command = new UpsertEuVatRatesCommand(
            $this->getRepository(CountriesRepository::class),
            $this->getRepository(VatRatesRepository::class),
            $clientMock
        );

        // ********************************************************************
        // start test

        $returnCode = $this->command->run(new StringInput(''), new NullOutput());
        // TODO: add logger mock to check output of command
        $this->assertEquals(Command::FAILURE, $returnCode);

        // just check number of inserted countries - should be zero, nothing added
        $this->assertEquals(0, $this->vatRatesRepository->totalCount());
    }

    public function testMissingCountry(): void
    {
        // vat_rates table should be empty
        $this->assertEquals(0, $this->vatRatesRepository->totalCount());

        // ********************************************************************
        // prepare mock

        $clientMock = $this->getMockBuilder(Client::class)->getMock();

        $vatStackResponse = self::getVatStackJsonResponse(onlySlovakia: true);
        $clientMock->expects($this->once())
            ->method('getVats')
            // expect 'sk' iso code if only slovakia should be returned
            ->with(
                memberStates: true,
                limit: 100,
                countryIsoCode: 'sk'
            )
            ->willReturn(new Response(
                200,
                [],
                $vatStackResponse['json_response'],
            ));

        // load command with injected mock
        $this->command = new UpsertEuVatRatesCommand(
            $this->getRepository(CountriesRepository::class),
            $this->getRepository(VatRatesRepository::class),
            $clientMock
        );

        // remove SK country from table
        /** @var CountriesRepository $countriesRepository */
        $countriesRepository = $this->getRepository(CountriesRepository::class);
        $country = $countriesRepository->findByIsoCode('sk');
        $countriesRepository->delete($country);

        // ********************************************************************
        // start test

        $returnCode = $this->command->run(new StringInput('--' . UpsertEuVatRatesCommand::COUNTRY_CODE_OPTION . '=sk'), new NullOutput());
        // TODO: add logger mock to check output of command
        $this->assertEquals(Command::FAILURE, $returnCode);

        // just check number of inserted countries - should be zero, nothing added
        $this->assertEquals(0, $this->vatRatesRepository->totalCount());
    }

    /**
     * Helper method to get JSON response identical to VAT Stack response.
     */
    private static function getVatStackJsonResponse(bool $onlySlovakia = false): array
    {
        if ($onlySlovakia) {
            return [
                'countries_count' => 1,
                'json_response' => <<<JSON
{"has_more":false,"rates_count":1,"rates":[{"abbreviation":"IČ DPH","categories":{"audiobook":20,"broadcasting":20,"ebook":20,"eperiodical":20,"eservice":20,"telecommunication":20},"country_code":"SK","country_name":"Slovakia","currency":"EUR","local_name":"Identifikačné číslo pre daň z pridanej hodnoty","member_state":true,"reduced_rates":[10],"standard_rate":20,"vat_abbreviation":"DPH","vat_local_name":"Daň z pridanej hodnoty"}]}
JSON
            ];
        }

        return [
            'countries_count' => 27,
            'json_response' => <<<JSON
{"has_more":false,"rates_count":27,"rates":[{"abbreviation":"UID","categories":{"audiobook":10,"broadcasting":10,"ebook":10,"eperiodical":10,"eservice":20,"telecommunication":20},"country_code":"AT","country_name":"Austria","currency":"EUR","local_name":"Umsatzsteuer-Identifikationsnummer","member_state":true,"reduced_rates":[10,13],"standard_rate":20,"vat_abbreviation":"MwSt.","vat_local_name":"Mehrwertsteuer"},{"abbreviation":"BTW-nr","categories":{"audiobook":6,"broadcasting":21,"ebook":6,"eperiodical":0,"eservice":21,"telecommunication":21},"country_code":"BE","country_name":"Belgium","currency":"EUR","local_name":"BTW identificatienummer","member_state":true,"reduced_rates":[0,6,12],"standard_rate":21,"vat_abbreviation":"BTW","vat_local_name":"Belasting over de toegevoegde waarde"},{"abbreviation":"ДДС номер","categories":{"audiobook":9,"broadcasting":20,"ebook":9,"eperiodical":9,"eservice":20,"telecommunication":20},"country_code":"BG","country_name":"Bulgaria","currency":"BGN","local_name":"Идентификационен номер по ДДС","member_state":true,"reduced_rates":[0,9],"standard_rate":20,"vat_abbreviation":"ДДС","vat_local_name":"Данък добавена стойност"},{"abbreviation":"PDV-ID","categories":{"audiobook":5,"broadcasting":25,"ebook":5,"eperiodical":5,"eservice":25,"telecommunication":25},"country_code":"HR","country_name":"Croatia","currency":"HRK","local_name":"Porez na dodanu vrijednost identifikacijski broj","member_state":true,"reduced_rates":[5,13],"standard_rate":25,"vat_abbreviation":"PDV","vat_local_name":"Porez na dodanu vrijednost"},{"abbreviation":"ΦΠΑ","categories":{"audiobook":19,"broadcasting":19,"ebook":19,"eperiodical":19,"eservice":19,"telecommunication":19},"country_code":"CY","country_name":"Cyprus","currency":"EUR","local_name":"Αριθμός Εγγραφής Φ.Π.Α.","member_state":true,"reduced_rates":[5,9],"standard_rate":19,"vat_abbreviation":"ΦΠΑ","vat_local_name":"Φόρος Προστιθέμενης Αξίας"},{"abbreviation":"DIČ","categories":{"audiobook":10,"broadcasting":21,"ebook":10,"eperiodical":10,"eservice":21,"telecommunication":21},"country_code":"CZ","country_name":"Czech Republic","currency":"CZK","local_name":"Daňové identifikační číslo","member_state":true,"reduced_rates":[10,15],"standard_rate":21,"vat_abbreviation":"DPH","vat_local_name":"Daň z přidané hodnoty"},{"abbreviation":"CVR","categories":{"audiobook":25,"broadcasting":25,"ebook":25,"eperiodical":25,"eservice":25,"telecommunication":25},"country_code":"DK","country_name":"Denmark","currency":"DKK","local_name":"Momsregistreringsnummer","member_state":true,"reduced_rates":[0],"standard_rate":25,"vat_abbreviation":"moms","vat_local_name":"Meromsætningsafgift"},{"abbreviation":"KMKR","categories":{"audiobook":9,"broadcasting":20,"ebook":9,"eperiodical":9,"eservice":20,"telecommunication":20},"country_code":"EE","country_name":"Estonia","currency":"EUR","local_name":"Käibemaksukohustuslase number","member_state":true,"reduced_rates":[9],"standard_rate":20,"vat_abbreviation":"km","vat_local_name":"käibemaks"},{"abbreviation":"ALV nro","categories":{"audiobook":10,"broadcasting":24,"ebook":10,"eperiodical":10,"eservice":24,"telecommunication":24},"country_code":"FI","country_name":"Finland","currency":"EUR","local_name":"Arvonlisäveronumero","member_state":true,"reduced_rates":[10,14],"standard_rate":24,"vat_abbreviation":"ALV","vat_local_name":"Arvonlisävero"},{"abbreviation":"n° TVA","categories":{"audiobook":5.5,"broadcasting":20,"ebook":5.5,"eperiodical":2.1,"eservice":20,"telecommunication":20},"country_code":"FR","country_name":"France","currency":"EUR","local_name":"Numéro d'identification à la taxe sur la valeur ajoutée","member_state":true,"reduced_rates":[2.1,5.5,10],"standard_rate":20,"vat_abbreviation":"TVA","vat_local_name":"taxe sur la valeur ajoutée"},{"abbreviation":"USt-IdNr.","categories":{"audiobook":7,"broadcasting":19,"ebook":7,"eperiodical":7,"eservice":19,"telecommunication":19},"country_code":"DE","country_name":"Germany","currency":"EUR","local_name":"Umsatzsteuer-Identifikationsnummer","member_state":true,"reduced_rates":[0,7],"standard_rate":19,"vat_abbreviation":"MwSt.","vat_local_name":"Mehrwertsteuer"},{"abbreviation":"ΑΦΜ","categories":{"audiobook":6,"broadcasting":24,"ebook":6,"eperiodical":6,"eservice":24,"telecommunication":24},"country_code":"GR","country_name":"Greece","currency":"EUR","local_name":"Αριθμός Φορολογικού Μητρώου","member_state":true,"reduced_rates":[6,13],"standard_rate":24,"vat_abbreviation":"ΦΠΑ","vat_local_name":"Φόρος Προστιθέμενης Αξίας"},{"abbreviation":"ANUM","categories":{"audiobook":27,"broadcasting":27,"ebook":27,"eperiodical":27,"eservice":27,"telecommunication":27},"country_code":"HU","country_name":"Hungary","currency":"HUF","local_name":"Közösségi adószám","member_state":true,"reduced_rates":[0,5,18],"standard_rate":27,"vat_abbreviation":"áfa","vat_local_name":"általános forgalmi adó"},{"abbreviation":"VAT ID no.","categories":{"audiobook":9,"broadcasting":23,"ebook":9,"eperiodical":9,"eservice":23,"telecommunication":23},"country_code":"IE","country_name":"Ireland","currency":"EUR","local_name":"Value added tax identification no.","member_state":true,"reduced_rates":[0,4.8,9,13.5],"standard_rate":23,"vat_abbreviation":"VAT","vat_local_name":"Value Added Tax"},{"abbreviation":"P.IVA","categories":{"audiobook":4,"broadcasting":22,"ebook":4,"eperiodical":4,"eservice":22,"telecommunication":22},"country_code":"IT","country_name":"Italy","currency":"EUR","local_name":"Partita Imposta sul Valore Aggiunto","member_state":true,"reduced_rates":[4,5,10],"standard_rate":22,"vat_abbreviation":"IVA","vat_local_name":"Imposta sul Valore Aggiunto"},{"abbreviation":"PVN","categories":{"audiobook":5,"broadcasting":21,"ebook":5,"eperiodical":5,"eservice":21,"telecommunication":21},"country_code":"LV","country_name":"Latvia","currency":"EUR","local_name":"Pievienotās vērtības nodokļa reģistrācijas numurs","member_state":true,"reduced_rates":[0,5,12],"standard_rate":21,"vat_abbreviation":"PVN","vat_local_name":"Pievienotās vērtības nodoklis"},{"abbreviation":"PVM kodas","categories":{"audiobook":21,"broadcasting":21,"ebook":21,"eperiodical":21,"eservice":21,"telecommunication":21},"country_code":"LT","country_name":"Lithuania","currency":"EUR","local_name":"Pridėtinės vertės mokestis mokėtojo kodas","member_state":true,"reduced_rates":[5,9],"standard_rate":21,"vat_abbreviation":"PVM","vat_local_name":"Pridėtinės vertės mokestis"},{"abbreviation":"No. TVA","categories":{"audiobook":3,"broadcasting":3,"ebook":3,"eperiodical":3,"eservice":17,"telecommunication":17},"country_code":"LU","country_name":"Luxembourg","currency":"EUR","local_name":"Numéro d'identification à la taxe sur la valeur ajoutée","member_state":true,"reduced_rates":[3,8,14],"standard_rate":17,"vat_abbreviation":"TVA","vat_local_name":"Taxe sur la Valeur Ajoutée"},{"abbreviation":"Vat No.","categories":{"audiobook":5,"broadcasting":18,"ebook":5,"eperiodical":5,"eservice":18,"telecommunication":18},"country_code":"MT","country_name":"Malta","currency":"EUR","local_name":"Vat reg. no.","member_state":true,"reduced_rates":[0,5,7],"standard_rate":18,"vat_abbreviation":"VAT","vat_local_name":"Taxxa tal-Valur Miżjud"},{"abbreviation":"Btw-nr.","categories":{"audiobook":9,"broadcasting":21,"ebook":9,"eperiodical":9,"eservice":21,"telecommunication":21},"country_code":"NL","country_name":"Netherlands","currency":"EUR","local_name":"Btw-nummer","member_state":true,"reduced_rates":[0,9],"standard_rate":21,"vat_abbreviation":"BTW","vat_local_name":"Belasting over de toegevoegde waarde"},{"abbreviation":"NIP","categories":{"audiobook":5,"broadcasting":23,"ebook":5,"eperiodical":8,"eservice":23,"telecommunication":8},"country_code":"PL","country_name":"Poland","currency":"PLN","local_name":"Numer Identyfikacji Podatkowej","member_state":true,"reduced_rates":[0,5,8],"standard_rate":23,"vat_abbreviation":"PTU","vat_local_name":"Podatek od towarów i usług"},{"abbreviation":"NIPC","categories":{"audiobook":6,"broadcasting":23,"ebook":6,"eperiodical":6,"eservice":23,"telecommunication":23},"country_code":"PT","country_name":"Portugal","currency":"EUR","local_name":"Número de Identificação de Pessoa Colectiva","member_state":true,"reduced_rates":[6,13],"standard_rate":23,"vat_abbreviation":"IVA","vat_local_name":"Imposto sobre o Valor Acrescentado"},{"abbreviation":"CIF","categories":{"audiobook":5,"broadcasting":19,"ebook":5,"eperiodical":5,"eservice":19,"telecommunication":19},"country_code":"RO","country_name":"Romania","currency":"RON","local_name":"Codul de identificare fiscală","member_state":true,"reduced_rates":[0,5,9],"standard_rate":19,"vat_abbreviation":"TVA","vat_local_name":"Taxa pe valoarea adăugată"},{"abbreviation":"IČ DPH","categories":{"audiobook":20,"broadcasting":20,"ebook":20,"eperiodical":20,"eservice":20,"telecommunication":20},"country_code":"SK","country_name":"Slovakia","currency":"EUR","local_name":"Identifikačné číslo pre daň z pridanej hodnoty","member_state":true,"reduced_rates":[10],"standard_rate":20,"vat_abbreviation":"DPH","vat_local_name":"Daň z pridanej hodnoty"},{"abbreviation":"ID za DDV","categories":{"audiobook":5,"broadcasting":22,"ebook":5,"eperiodical":5,"eservice":22,"telecommunication":22},"country_code":"SI","country_name":"Slovenia","currency":"EUR","local_name":"Identifikacijsko številko za davek na dodano vrednost","member_state":true,"reduced_rates":[5,9.5],"standard_rate":22,"vat_abbreviation":"DDV","vat_local_name":"Davek na dodano vrednost"},{"abbreviation":"NIF","categories":{"audiobook":4,"broadcasting":21,"ebook":4,"eperiodical":4,"eservice":21,"telecommunication":21},"country_code":"ES","country_name":"Spain","currency":"EUR","local_name":"Número de Identificación Fiscal","member_state":true,"reduced_rates":[0,4,10],"standard_rate":21,"vat_abbreviation":"IVA","vat_local_name":"Impuesto sobre el Valor Añadido"},{"abbreviation":"Momsnr.","categories":{"audiobook":6,"broadcasting":25,"ebook":6,"eperiodical":6,"eservice":25,"telecommunication":25},"country_code":"SE","country_name":"Sweden","currency":"SEK","local_name":"Momsregistreringsnummer","member_state":true,"reduced_rates":[0,6,12],"standard_rate":25,"vat_abbreviation":"MOMS","vat_local_name":"Mervärdesskatt"}]}
JSON
            ];
    }
}
