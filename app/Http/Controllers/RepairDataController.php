<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class RepairDataController extends Controller
{
    function trace(Request $request)
    {
        $data =  DB::connection('sqlsrv_lot_trace')
            ->table('W_MECHA_TEST_TABLE')->where('Repair_date', '>=', $request->period1)
            ->where('Repair_date', '<=', $request->period2)
            ->where('Repair_JMCode', 'like', '%' . $request->production_control . '%')
            ->where('AssyNo', 'like', '%' . $request->assy_no . '%')
            ->where('BoardNo', 'like', '%' . $request->type . '%')
            ->where('PdtNo', 'like', '%' . $request->model . '%')
            ->where('Repair_line', 'like', '%' . $request->line . '%')
            ->orderBy('Repair_date')->paginate(500);
        return ['data' => $data];
    }

    function traceToSpreadsheet(Request $request)
    {
        $data =  $data =  DB::connection('sqlsrv_lot_trace')
            ->table('W_MECHA_TEST_TABLE')->where('Repair_date', '>=', $request->period1)
            ->where('Repair_date', '<=', $request->period2)
            ->where('Repair_JMCode', 'like', '%' . $request->production_control . '%')
            ->where('AssyNo', 'like', '%' . $request->assy_no . '%')
            ->where('BoardNo', 'like', '%' . $request->type . '%')
            ->where('PdtNo', 'like', '%' . $request->model . '%')
            ->where('Repair_line', 'like', '%' . $request->line . '%')
            ->orderBy('Repair_date');

        $datas = $data->get();
        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $sheet->setTitle('Repair Data Log');
        $sheet->setCellValue([1, 1], 'Date');
        $sheet->setCellValue([2, 1], 'Line');
        $sheet->setCellValue([3, 1], 'Model');
        $sheet->setCellValue([4, 1], 'Assy No');
        $sheet->setCellValue([5, 1], 'Type');
        $sheet->setCellValue([6, 1], 'NG Station');
        $sheet->setCellValue([7, 1], 'PCB Serial');
        $sheet->setCellValue([8, 1], 'PCB Number');
        $sheet->setCellValue([9, 1], 'JM Barcode');
        $sheet->setCellValue([10, 1], 'Phenomenon');
        $sheet->setCellValue([11, 1], 'Defect');
        $sheet->setCellValue([12, 1], 'Loc1');
        $sheet->setCellValue([13, 1], 'Loc2');
        $sheet->setCellValue([14, 1], 'Loc3');
        $sheet->setCellValue([15, 1], 'Loc4');
        $sheet->setCellValue([16, 1], 'Loc5');
        $sheet->setCellValue([17, 1], 'WEEK');
        $sheet->setCellValue([18, 1], 'Category');

        $i = 2;
        foreach ($datas as $r) {
            $sheet->setCellValue([1, $i], $r->Repair_date);
            $sheet->setCellValue([2, $i], $r->Repair_line);
            $sheet->setCellValue([3, $i], $r->PdtNo);
            $sheet->setCellValue([4, $i], $r->AssyNo);
            $sheet->setCellValue([5, $i], $r->BoardNo);
            $sheet->setCellValue([6, $i], $r->Repair_NGStsn);
            $sheet->setCellValue([7, $i], $r->Repair_PCBSrl);
            $sheet->setCellValue([8, $i], $r->Repair_PCBNo);
            $sheet->setCellValue([9, $i], $r->Repair_JMCode);
            $sheet->setCellValue([10, $i], $r->Repair_pnmn);
            $sheet->setCellValue([11, $i], $r->Repair_defect);
            $sheet->setCellValue([12, $i], $r->Repair_Loc1);
            $sheet->setCellValue([13, $i], $r->Repair_loc2);
            $sheet->setCellValue([14, $i], $r->Repair_loc3);
            $sheet->setCellValue([15, $i], $r->Repair_loc4);
            $sheet->setCellValue([16, $i], $r->Repair_loc5);
            $sheet->setCellValue([17, $i], $r->Repair_week);
            $sheet->setCellValue([18, $i], $r->Repair_cat);
            $i++;
        }

        foreach (range('A', 'T') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }

        $sheet->freezePane('A2');
        $stringjudul = "Repair Data Logs, " . date('H:i:s');
        $filename = $stringjudul;
        header('Cache-Control: max-age=0');
        header('Access-Control-Allow-Origin: *');
        if ($request->file_type == 'xlsx') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
            $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
        } else {
            $writer = IOFactory::createWriter($spreadSheet, 'Csv');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="' . $filename . '.csv"');
        }
        $writer->save('php://output');
    }
}
