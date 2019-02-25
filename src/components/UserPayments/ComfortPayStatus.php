<?php

namespace Crm\PaymentsModule\Components;

class ComfortPayStatus
{
    public static function getStatusHtml($status)
    {
        $result = '';
        $label = 'label';
        if ($status != null) {
            switch ($status) {
                case '00':
                    $result .= 'transakcia prijata';
                    $label = 'success';
                    break;
                case '17':
                    $result .= 'zrusena transakcia';
                    $label = 'danger';
                    break;
                case '19':
                case '76':
                case '77':
                case '78':
                case '79':
                case '82':
                case '84':
                case '85':
                case '87':
                case 'N1':
                case 'N2':
                case 'N9':
                case 'O0':
                case 'O1':
                case 'O2':
                case 'O3':
                case 'O4':
                case 'O5':
                case 'O6':
                case 'O7':
                case 'O8':
                case 'O9':
                case 'P3':
                case 'P4':
                case 'P5':
                case 'P6':
                case 'P7':
                case 'P8':
                case 'P9':
                case 'Q0':
                case 'Q2':
                case 'Q3':
                case 'Q4':
                case 'Q5':
                case 'Q6':
                case 'Q7':
                case 'Q8':
                case 'Q9':
                case 'R0':
                case 'R1':
                case 'R2':
                case 'R3':
                case 'R4':
                case 'R5':
                case 'R6':
                case 'R7':
                case 'R8':
                case 'S5':
                case 'S6':
                case 'S7':
                case 'S8':
                case 'S9':
                case 'T1':
                case 'T2':
                case 'T3':
                case 'T4':
                case 'T5':
                case 'T7':
                case 'N6':
                case 'N5':
                case 'N8':
                case 'N0':
                    $result .= 'zopakujte transakciu';
                    $label = 'info';
                    break;
                case '66':
                    $result .= 'zamietnuta transakcia';
                    $label = 'danger';
                    break;
                case '38':
                    $result .= 'Zadrzte kartu! Vela PIN pokusov';
                    $label = 'danger';
                    break;
                case '43':
                    $result .= 'zadrzte kartu, volajte Tatra banku:02/6866 1000-kod 10';
                    $label = 'danger';
                    break;
                case '61':
                    $result .= 'Zadajte mensiu ciastku';
                    $label = 'info';
                    break;
                case '31':
                    $result .= 'vydavatel docasne nedostupny';
                    $label = 'danger';
                    break;
                case '91':
                    $result .= 'vydavatel docasne nedostupny';
                    $label = 'danger';
                    break;
                case '92':
                    $result .= 'vydavatel docasne nedostupny';
                    $label = 'danger';
                    break;
                case '01':
                    $result .= 'volajte banku vydavatela';
                    $label = 'danger';
                    break;
                case '02':
                    $result .= 'volajte banku vydavatela';
                    $label = 'danger';
                    break;
                case 'XA':
                    $result .= 'volajte banku vydavatela';
                    $label = 'danger';
                    break;
                case 'XD':
                    $result .= 'volajte banku vydavatela';
                    $label = 'danger';
                    break;
                case '32':
                    $result .= 'uskutocnene po castiach';
                    $label = 'danger';
                    break;
                case 'N7':
                    $result .= 'transakcia zamietnuta, nespravny CV kod';
                    $label = 'danger';
                    break;
                case '04':
                case '07':
                case '33':
                case '37':
                case '41':
                    $result .= 'Transakcia zamietnuta bankou. Pre ziskanie blizsich informacii kontaktujte Tatra banku.';
                    $label = 'info';
                    break;
                case '57':
                    $result .= 'Transakcia zamietnuta bankou drzitela karty. Pre ziskanie blizsich informacii kontaktujte Tatra banku.';
                    $label = 'info';
                    break;
                case '05':
                case '13':
                case '14':
                case 'N4':
                case '54':
                case '36':
                case '39':
                case '51':
                case '52':
                case '53':
                case '62':
                case '64':
                    $result .= 'Transakcia zamietnuta bankou drzitela karty.';
                    $label = 'danger';
                    break;
                case '67':
                case '94':
                case '95':
                case 'P0':
                case 'P1':
                case 'S4':
                case 'T6':
                case 'Z3':
                case '11':
                case '93':
                    $result .= 'transakcia zamietnuta';
                    $label = 'danger';
                    break;
                case '68':
                    $result .= 'transakcia stornovana';
                    $label = 'danger';
                    break;
                case '21':
                    $result .= 'transakcia nevykonana';
                    $label = 'danger';
                    break;
                case '08':
                    $result .= 'preverte identifikaciu';
                    $label = 'danger';
                    break;
                case '65':
                    $result .= 'prekroceny pocet pokusov';
                    $label = 'danger';
                    break;
                case '75':
                    $result .= 'prekroceny pocet PIN pokusov';
                    $label = 'danger';
                    break;
                case '-1':
                    $result .= 'paycheck';
                    $label = 'danger';
                    break;
                case '99':
                    $result .= 'Overte transakciu';
                    $label = 'danger';
                    break;
                case '55':
                    $result .= 'nespravny pin';
                    $label = 'danger';
                    break;
                case '80':
                    $result .= 'nespravny datum';
                    $label = 'danger';
                    break;
                case '12':
                    $result .= 'Nepovoleny typ transakcie. Pre ziskanie blizsich informacii kontaktujte Tatra banku.';
                    $label = 'danger';
                    break;
                case 'N3':
                    $result .= 'nepovoleny typ transakcie ';
                    $label = 'danger';
                    break;
                case '58':
                    $result .= 'nepovolena transakcia terminalu';
                    $label = 'danger';
                    break;
                case '15':
                    $result .= 'neexistujuci vydavatel';
                    $label = 'danger';
                    break;
                case 'P2':
                    $result .= 'chybny ucet';
                    $label = 'danger';
                    break;
                case '96':
                case '06':
                    $result .= 'chyba, zopakujte transakciu';
                    $label = 'danger';
                    break;
                case '83':
                case '86':
                    $result .= 'chyba overenia PIN';
                    $label = 'danger';
                    break;
                case 'Q1':
                    $result .= 'chyba overenia karty';
                    $label = 'danger';
                    break;
                case '30':
                    $result .= 'chyba formatu, volajte Tatra banku: 02/ 6866 1000';
                    $label = 'danger';
                    break;
                case '56':
                case '03':
                case '81':
                case '88':
                case '89':
                    $result .= 'chyba autorizacie, volajte Tatra banku: 02/ 6866 1000';
                    $label = 'danger';
                    break;
            }
        } else {
            $label = 'info';
            $result = 'Caka na spracovanie';
        }
        return ['label' => $label, 'text' => $result];
    }
}
