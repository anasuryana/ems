<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class RatingController extends Controller
{
    function getPercentagePerLineMachinePeriod(Request $request)
    {
        $data = $this->getPercentagePerLineMachinePeriodData($request);
        return ['data' => $data];
    }

    function getPercentagePerLineMachinePeriodData(Request $request)
    {
        $data = DB::connection('mysql_engtrial')->table('tbl_passrate')
            ->where('txttgl', '>=', $request->dateFrom)
            ->where('txttgl', '<=', $request->dateTo)
            ->where('txtmesin', $request->machineBrand)
            ->groupBy(
                'txtline',
                'txtict',
            )
            ->orderBy('txtline')
            ->orderBy('txtict')
            ->get([
                'txtline',
                'txtict',
                DB::raw('SUM(txtcheck) txtcheck'),
                DB::raw('SUM(txtpass) txtpass'),
                DB::raw('SUM(txtpass)/SUM(txtcheck)*100 txtpercen')
            ]);
        return $data;
    }

    function getPercentagePerLineMachinePeriodPSBOXData(Request $request)
    {
        $data = DB::connection('mysql_engtrial_qpit')->table('tbl_passrate')
            ->where('txttgl', '>=', $request->dateFrom)
            ->where('txttgl', '<=', $request->dateTo)
            ->where('txtcustomer', $request->customer)
            ->groupBy(
                'txtline',
                'txtps',
            )
            ->orderBy('txtline')
            ->orderBy('txtps')
            ->get([
                'txtline',
                'txtps',
                DB::raw('SUM(txtcheck) txtcheck'),
                DB::raw('SUM(txtpass) txtpass'),
                DB::raw('SUM(txtpass)/SUM(txtcheck)*100 txtpercen')
            ]);
        return $data;
    }

    function getPercentagePerLineMachinePeriodPSBOX(Request $request)
    {
        $data = $this->getPercentagePerLineMachinePeriodPSBOXData($request);
        return ['data' => $data];
    }

    function getPercentagePerLineMachinePeriodDetail1(Request $request)
    {
        $data = $this->getPercentagePerLineMachinePeriodDetail1Data($request);
        return ['data' => $data];
    }

    function getPercentagePerLineMachinePeriodDetail1Data(Request $request)
    {
        $additionalWhere = [];
        if ($request->lineCode) {
            $additionalWhere[] = ['txtline', $request->lineCode];
        }
        if ($request->lineCode) {
            $additionalWhere[] = ['txtict', $request->machineCode];
        }
        $data = DB::connection('mysql_engtrial')->table('tbl_passrate')
            ->where('txttgl', '>=', $request->dateFrom)
            ->where('txttgl', '<=', $request->dateTo)
            ->where('txtmesin', $request->machineBrand)
            ->where($additionalWhere)
            ->groupBy(
                'txttgl',
                'txtline',
                'txtict',
                'txtjig',
                'txtmodel',
            )
            ->orderBy('txttgl')
            ->orderBy('txtline')
            ->orderBy('txtict')
            ->get([
                'txttgl',
                'txtline',
                'txtict',
                'txtjig',
                'txtmodel',
                DB::raw('SUM(txtcheck) txtcheck'),
                DB::raw('SUM(txtpass) txtpass'),
                DB::raw('SUM(txtpass)/SUM(txtcheck)*100 txtpercen')
            ]);
        return $data;
    }

    function getPercentagePerLineMachinePeriodDetail2(Request $request)
    {
        $data = $this->getPercentagePerLineMachinePeriodDetail2Data($request);
        return ['data' => $data];
    }

    function getPercentagePerLineMachinePeriodDetail2Data(Request $request)
    {
        $additionalWhere = [];
        if ($request->lineCode) {
            $additionalWhere[] = ['txtline', $request->lineCode];
        }
        if ($request->lineCode) {
            $additionalWhere[] = ['txtps', $request->ps];
        }
        $data = DB::connection('mysql_engtrial_qpit')->table('tbl_passrate')
            ->where('txttgl', '>=', $request->dateFrom)
            ->where('txttgl', '<=', $request->dateTo)
            ->where('txtcustomer', $request->customer)
            ->where($additionalWhere)
            ->groupBy(
                'txttgl',
                'txtline',
                'txtps',
                'txtjig',
                'txtmodel',
            )
            ->orderBy('txttgl')
            ->orderBy('txtline')
            ->orderBy('txtps')
            ->get([
                'txttgl',
                'txtline',
                'txtps',
                'txtjig',
                'txtmodel',
                DB::raw('SUM(txtcheck) txtcheck'),
                DB::raw('SUM(txtpass) txtpass'),
                DB::raw('SUM(txtpass)/SUM(txtcheck)*100 txtpercen')
            ]);
        return $data;
    }

    function getPercentagePerLineMachinePeriodtoSpreadsheet(Request $request)
    {
        $data = $this->getPercentagePerLineMachinePeriodData($request);
        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $sheet->setTitle('Main View');
        $sheet->freezePane('A2');
        $sheet->setCellValue([1, 1], 'Line');
        $sheet->setCellValue([2, 1], 'Machine Number');
        $sheet->setCellValue([3, 1], 'Check');
        $sheet->setCellValue([4, 1], 'Pass');
        $sheet->setCellValue([5, 1], 'Pass Rate');
        $rowAt = 2;
        foreach ($data as $r) {
            $sheet->setCellValue([1, $rowAt], $r->txtline);
            $sheet->setCellValue([2, $rowAt], $r->txtict);
            $sheet->setCellValue([3, $rowAt], $r->txtcheck);
            $sheet->setCellValue([4, $rowAt], $r->txtpass);
            $sheet->setCellValue([5, $rowAt], $r->txtpercen);
            $rowAt++;
        }

        $data = $this->getPercentagePerLineMachinePeriodDetail1Data($request);

        $sheet = $spreadSheet->createSheet();
        $sheet->setTitle('Detail View');
        $sheet->freezePane('A2');
        $sheet->setCellValue([1, 1], 'Date');
        $sheet->setCellValue([2, 1], 'Line');
        $sheet->setCellValue([3, 1], 'Machine Number');
        $sheet->setCellValue([4, 1], 'Jig Number');
        $sheet->setCellValue([5, 1], 'Model');
        $sheet->setCellValue([6, 1], 'Check');
        $sheet->setCellValue([7, 1], 'Pass');
        $sheet->setCellValue([8, 1], 'Pass Rate');

        $rowAt = 2;
        foreach ($data as $r) {
            $sheet->setCellValue([1, $rowAt], $r->txttgl);
            $sheet->setCellValue([2, $rowAt], $r->txtline);
            $sheet->setCellValue([3, $rowAt], $r->txtict);
            $sheet->setCellValue([4, $rowAt], $r->txtjig);
            $sheet->setCellValue([5, $rowAt], $r->txtmodel);
            $sheet->setCellValue([6, $rowAt], $r->txtcheck);
            $sheet->setCellValue([7, $rowAt], $r->txtpass);
            $sheet->setCellValue([8, $rowAt], $r->txtpercen);
            $rowAt++;
        }



        $stringjudul = "Rating Logs, " . date('H:i:s');
        $filename = $stringjudul;

        header('Cache-Control: max-age=0');
        header('Access-Control-Allow-Origin: *');

        $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');

        $writer->save('php://output');
    }
    function getPercentagePerLineMachinePeriodPSBOXtoSpreadsheet(Request $request)
    {
        $data = $this->getPercentagePerLineMachinePeriodPSBOXData($request);
        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $sheet->setTitle('Main View');
        $sheet->freezePane('A2');
        $sheet->setCellValue([1, 1], 'Line');
        $sheet->setCellValue([2, 1], 'Machine Number');
        $sheet->setCellValue([3, 1], 'Check');
        $sheet->setCellValue([4, 1], 'Pass');
        $sheet->setCellValue([5, 1], 'Pass Rate');
        $rowAt = 2;
        foreach ($data as $r) {
            $sheet->setCellValue([1, $rowAt], $r->txtline);
            $sheet->setCellValue([2, $rowAt], $r->txtps);
            $sheet->setCellValue([3, $rowAt], $r->txtcheck);
            $sheet->setCellValue([4, $rowAt], $r->txtpass);
            $sheet->setCellValue([5, $rowAt], $r->txtpercen);
            $rowAt++;
        }

        $data = $this->getPercentagePerLineMachinePeriodDetail2Data($request);

        $sheet = $spreadSheet->createSheet();
        $sheet->setTitle('Detail View');
        $sheet->freezePane('A2');
        $sheet->setCellValue([1, 1], 'Date');
        $sheet->setCellValue([2, 1], 'Line');
        $sheet->setCellValue([3, 1], 'Machine Number');
        $sheet->setCellValue([4, 1], 'Jig Number');
        $sheet->setCellValue([5, 1], 'Model');
        $sheet->setCellValue([6, 1], 'Check');
        $sheet->setCellValue([7, 1], 'Pass');
        $sheet->setCellValue([8, 1], 'Pass Rate');

        $rowAt = 2;
        foreach ($data as $r) {
            $sheet->setCellValue([1, $rowAt], $r->txttgl);
            $sheet->setCellValue([2, $rowAt], $r->txtline);
            $sheet->setCellValue([3, $rowAt], $r->txtps);
            $sheet->setCellValue([4, $rowAt], $r->txtjig);
            $sheet->setCellValue([5, $rowAt], $r->txtmodel);
            $sheet->setCellValue([6, $rowAt], $r->txtcheck);
            $sheet->setCellValue([7, $rowAt], $r->txtpass);
            $sheet->setCellValue([8, $rowAt], $r->txtpercen);
            $rowAt++;
        }



        $stringjudul = "Rating Logs, " . date('H:i:s');
        $filename = $stringjudul;

        header('Cache-Control: max-age=0');
        header('Access-Control-Allow-Origin: *');

        $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');

        $writer->save('php://output');
    }

    function getModel(Request $request)
    {
        $data = DB::connection('mysql_engtrial_qpit')->table('tbl_passrate')
            ->groupBy('txtmodel')
            ->get([DB::raw('LTRIM(RTRIM(txtmodel)) txtmodel')]);
        return ['data' => $data];
    }
}
