<?php

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\PaymentsModule\Models\VariableSymbolInterface;
use Nette\Database\Table\ActiveRow;

class VariableSymbolRepository extends Repository implements VariableSymbolInterface
{
    protected $tableName = 'variable_symbols';

    public function getNew(?ActiveRow $paymentGateway): string
    {
        do {
            $variableSymbol = $this->generateRandom();
        } while (!$this->available($variableSymbol));

        // tu moze nastat error
        // ak by medzi tym niekto vyrobil ten isty variabilny symbol a inserol ho
        // padlo by to na uniq indexe
        // bolo by dobre to osetrit...

        $this->getTable()->insert([
            'created_at' => new \DateTime(),
            'variable_symbol' => $variableSymbol,
        ]);

        return $variableSymbol;
    }

    private function generateRandom($length = 10)
    {
        $characters = '0123456789';
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $result[] = $characters[random_int(0, strlen($characters) - 1)];
        }
        return implode('', $result);
    }

    private function available($variableSymbol)
    {
        return $this->getTable()->where(['variable_symbol' => $variableSymbol])->count('*') == 0;
    }
}
