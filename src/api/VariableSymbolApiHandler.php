<?php

namespace Crm\PaymentsModule\Api;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Api\ApiHandler;
use Crm\PaymentsModule\Repository\VariableSymbol;
use Nette\Http\Response;

class VariableSymbolApiHandler extends ApiHandler
{
    /** @var VariableSymbol  */
    private $variableSymbol;

    public function __construct(VariableSymbol $variableSymbol)
    {
        $this->variableSymbol = $variableSymbol;
    }

    public function params()
    {
        return [];
    }

    /**
     * @param ApiAuthorizationInterface $authorization
     * @return \Nette\Application\IResponse
     */
    public function handle(ApiAuthorizationInterface $authorization)
    {
        $response = new JsonResponse(['status' => 'ok', 'variable_symbol' => $this->variableSymbol->getNew()]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
