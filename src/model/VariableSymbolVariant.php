<?php

namespace Crm\PaymentsModule;

class VariableSymbolVariant
{
    public function variableSymbolsVariants($variableSymbols)
    {
        $result = [];
        foreach ($variableSymbols as $variableSymbol) {
            $result = array_merge($result, $this->variableSymbolVariants($variableSymbol));
        }
        return $result;
    }

    public function variableSymbolVariants($variableSymbol, $max = 10)
    {
        $result = [$variableSymbol];
        $length = strlen($variableSymbol);
        if ($length < $max) {
            for ($i = $length + 1; $i <= $max; $i++) {
                $result[] = str_pad($variableSymbol, $i, '0', STR_PAD_LEFT);
            }
        }
        return $result;
    }
}
