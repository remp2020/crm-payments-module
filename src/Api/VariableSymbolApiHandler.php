<?php

namespace Crm\PaymentsModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\PaymentsModule\VariableSymbolInterface;
use Nette\Http\Response;

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


    public function handle(array $params): ApiResponseInterface
    {
        $response = new JsonResponse(['status' => 'ok', 'variable_symbol' => $this->variableSymbol->getNew(null)]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
