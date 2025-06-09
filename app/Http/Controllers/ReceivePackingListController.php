<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ReceivePackingListController extends Controller
{
    function uploadSpreadsheet(Request $request)
    {
        $reader = IOFactory::createReader(ucfirst('Xls'));
        $spreadsheet = $reader->load(storage_path('app/public/MODEL 1 SHP-187770 OK.xls'));
        $sheet = $spreadsheet->getActiveSheet();
        $rowIndex = 14;
        $data = [];
        $DONumber = $sheet->getCell('X7')->getCalculatedValue();
        $Pallet = '';

        while (!empty($sheet->getCell('F' . $rowIndex)->getCalculatedValue())) { // patokan dari kolom F
            $_pallet = trim($sheet->getCell('AI' . $rowIndex)->getCalculatedValue());
            if ($Pallet != $_pallet && $_pallet != '') {
                $Pallet = trim($sheet->getCell('AI' . $rowIndex)->getCalculatedValue());
            }

            $_date = trim($sheet->getCell('M' . $rowIndex)->getValue());
            $_date_o = \Carbon\Carbon::instance(Date::excelToDateTimeObject($_date));

            $data[] = [
                'item_code' => trim($sheet->getCell('F' . $rowIndex)->getCalculatedValue()),
                'delivery_date' => $_date_o->format('Y-m-d'),
                'delivery_qty' => str_replace(',', '', trim($sheet->getCell('P' . $rowIndex)->getCalculatedValue())),
                'ship_qty' => trim($sheet->getCell('U' . $rowIndex)->getCalculatedValue()),
                'pallet' => $Pallet,
            ];

            $rowIndex += 2;
        }

        return ['doc' => $DONumber, 'data' => $data];
    }
}
