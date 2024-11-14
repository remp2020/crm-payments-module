<?php

namespace Crm\PaymentsModule\Models\Api\VatStack;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

class Client
{
    public const API_VATS_URL = 'https://api.vatstack.com/v1/rates';

    private ?string $apiKey;

    public function setApiKey(?string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * @param string $countryIsoCode Set ISO code of country, if only one country should be returned.
     * @param bool $memberStates If true, only EU member countries will be returned.
     * @param int $limit How many countries you want to receive. 100 is max allowed limit (there are 27 member EU countries).
     * @throws GuzzleException
     */
    public function getVats(bool $memberStates = true, int $limit = 100, string $countryIsoCode = null): ResponseInterface
    {
        if ($this->apiKey === null) {
            throw new \Exception('Unable to fetch VAT rates. VatStack API key is missing. Set it with setApiKey().');
        }

        // API options
        $options = [
            RequestOptions::HEADERS => [
                'X-API-KEY' => $this->apiKey,
            ],
            RequestOptions::QUERY => [
                'member_state' => $memberStates ? 'true' : 'false', // must be string; otherwise API won't accept it
                'limit' => $limit,
            ],
        ];

        if ($countryIsoCode !== null) {
            $options[RequestOptions::QUERY]['country_code'] = $countryIsoCode;
        }

        return (new GuzzleHttpClient())->get(self::API_VATS_URL, $options);
    }
}
