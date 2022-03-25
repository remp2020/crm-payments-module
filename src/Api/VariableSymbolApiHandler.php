<?php

namespace Crm\PaymentsModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\PaymentsModule\VariableSymbolInterface;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class VariableSymbolApiHandler extends ApiHandler
{
    /** @var VariableSymbolInterface  */
    private $variableSymbol;

    public function __construct(VariableSymbolInterface $variableSymbol)
    {
        $this->variableSymbol = $variableSymbol;
    }

    public function params(): array
    {
        return [];
    }


    public function handle(array $params): ResponseInterface
    {
        $response = new JsonApiResponse(Response::S200_OK, ['status' => 'ok', 'variable_symbol' => $this->variableSymbol->getNew(null)]);

        return $response;
    }
}
