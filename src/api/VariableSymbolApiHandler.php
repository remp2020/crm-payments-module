<?php

namespace Crm\PaymentsModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
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

    public function params()
    {
        return [];
    }

    /**
     * @param ApiAuthorizationInterface $authorization
     * @return \Nette\Application\Response
     */
    public function handle(ApiAuthorizationInterface $authorization)
    {
        $response = new JsonResponse(['status' => 'ok', 'variable_symbol' => $this->variableSymbol->getNew(null)]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
