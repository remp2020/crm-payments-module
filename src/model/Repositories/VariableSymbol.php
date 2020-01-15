<?php

namespace Crm\PaymentsModule\Repository;

use Crm\PaymentsModule\VariableSymbolInterface;

class VariableSymbol implements VariableSymbolInterface
{
    /** @var \Nette\Database\Context */
    private $database;

    public function __construct(\Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function getNew(): string
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

    private function getTable()
    {
        return $this->database->table('variable_symbols');
    }

    private function generateRandom($length = 10)
    {
        $characters = '0123456789';
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $result[] = $characters[rand(0, strlen($characters) - 1)];
        }
        return implode('', $result);
    }

    private function available($variableSymbol)
    {
        return $this->getTable()->where(['variable_symbol' => $variableSymbol])->count('*') == 0;
    }
}
