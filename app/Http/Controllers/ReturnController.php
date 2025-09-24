<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReturnController extends Controller
{
    function generateLogicalBalanceReport(Request $request)
    {
        $spreadSheet = new Spreadsheet();

        $sheet = $spreadSheet->getActiveSheet();
        $sheet->setTitle('data');

        $sheet->setCellValue([1, 3], 'Conform Date');
        $sheet->setCellValue([2, 3], 'PSN Doc');
        $sheet->setCellValue([3, 3], 'Item Code');
        $sheet->setCellValue([4, 3], 'Logical Return Qty');
        $sheet->setCellValue([5, 3], 'Actual Return Qty');
        $sheet->setCellValue([6, 3], 'Gap Qty');

        $conditional = new Conditional();
        $conditional->setConditionType(Conditional::CONDITION_CELLIS);
        $conditional->setOperatorType(Conditional::OPERATOR_LESSTHAN); // Jika nilai < 0
        $conditional->addCondition(0); // Nilai kurang dari 0
        $conditional->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
        $conditional->getStyle()->getFill()->getStartColor()->setRGB(Color::COLOR_RED); // Mengubah background menjadi merah

        $dataReturn = DB::connection('sqlsrv_wms')->table('RETSCN_TBL')
            ->where('RETSCN_CNFRMDT', '>=', $request->dateFrom)
            ->where('RETSCN_CNFRMDT', '<=', $request->dateTo)
            ->groupBy('RETSCN_SPLDOC', 'RETSCN_ITMCD')
            ->select(
                'RETSCN_SPLDOC',
                'RETSCN_ITMCD',
                DB::raw("MAX(RETSCN_CNFRMDT) RETSCN_CNFRMDT_MAX"),
                DB::raw("SUM(RETSCN_QTYAFT) RETSCN_QTYAFT_SUM"),
            );

        $dataReturnResume = DB::connection('sqlsrv_wms')->table('RETSCN_TBL')
            ->where('RETSCN_CNFRMDT', '>=', $request->dateFrom)
            ->where('RETSCN_CNFRMDT', '<=', $request->dateTo)
            ->groupBy('RETSCN_SPLDOC', 'RETSCN_ITMCD')
            ->get([
                'RETSCN_SPLDOC',
                DB::raw("MAX(RETSCN_CNFRMDT) RETSCN_CNFRMDT_MAX"),
            ]);

        $uniquePSN = $dataReturnResume->pluck('RETSCN_SPLDOC')->unique()->toArray();

        $dataReq = DB::connection('sqlsrv_wms')->table("SPL_TBL")
            ->whereIn('SPL_ITMCD', $request->rm)
            ->whereIn('SPL_DOC', $uniquePSN)
            ->groupBy('SPL_DOC', 'SPL_ITMCD')->select(
                'SPL_DOC',
                'SPL_ITMCD',
                DB::raw("SUM(SPL_QTYREQ) REQQT")
            );

        $dataSup = DB::connection('sqlsrv_wms')->table("SPLSCN_TBL")
            ->whereIn('SPLSCN_ITMCD', $request->rm)
            ->groupBy('SPLSCN_DOC', 'SPLSCN_ITMCD')->select(
                'SPLSCN_DOC',
                'SPLSCN_ITMCD',
                DB::raw("SUM(SPLSCN_QTY) SUPQT")
            );

        $data = DB::connection('sqlsrv_wms')->query()->fromSub($dataReturn, 'sub_ret')
            ->rightJoinSub($dataReq, 'v1', function ($join) {
                $join->on('RETSCN_SPLDOC', '=', 'SPL_DOC')->on('RETSCN_ITMCD', '=', 'SPL_ITMCD');
            })->leftJoinSub($dataSup, 'v2', function ($join) {
                $join->on('SPL_DOC', '=', 'SPLSCN_DOC')->on('SPL_ITMCD', '=', 'SPLSCN_ITMCD');
            })
            ->orderBy('RETSCN_CNFRMDT_MAX')
            ->orderBy('RETSCN_SPLDOC')
            ->orderBy('RETSCN_ITMCD')
            ->get([
                'sub_ret.*',
                'SPL_DOC',
                'SPL_ITMCD',
                'REQQT',
                'SUPQT',
                DB::raw("ISNULL(SUPQT,0)-ISNULL(REQQT,0) LOGRETQT")
            ]);

        $rowAt = 4;
        $sheet->freezePane('A' . $rowAt);
        foreach ($data as $r) {
            $sheet->setCellValue([1, $rowAt], $r->RETSCN_CNFRMDT_MAX ?? $dataReturnResume->where('RETSCN_SPLDOC', $r->SPL_DOC)->first()->RETSCN_CNFRMDT_MAX);
            $sheet->setCellValue([2, $rowAt], $r->SPL_DOC);
            $sheet->setCellValue([3, $rowAt], $r->SPL_ITMCD);
            $sheet->setCellValue([4, $rowAt], $r->LOGRETQT);
            $sheet->setCellValue([5, $rowAt], $r->RETSCN_QTYAFT_SUM ?? 0);
            $sheet->setCellValue([6, $rowAt], "=E" . $rowAt . "-D" . $rowAt);
            $rowAt++;
        }

        foreach (range('A', 'Z') as $r) {
            $sheet->getColumnDimension($r)->setAutoSize(true);
        }

        // Menerapkan conditional formatting
        $sheet->getStyle('F1:F' . $rowAt)->setConditionalStyles([$conditional]);

        $stringjudul = "Logical Return Report from " . $request->dateFrom . " to " . $request->dateTo;
        $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Access-Control-Allow-Origin: *');
        $writer->save('php://output');
    }
}
