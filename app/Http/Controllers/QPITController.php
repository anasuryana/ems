<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class QPITController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function trace(Request $request)
    {
        $data =  DB::connection('sqlsrv_lot_trace')
            ->table('W_QPIT-PC_Test_Table')->whereDate('Test_Time', '>=', $request->period1)
            ->whereDate('Test_Time', '<=', $request->period2)
            ->where('Production_Control_No', 'like', '%' . $request->production_control . '%')
            ->where('AssyNo', 'like', '%' . $request->assy_no . '%')
            ->where('BoardNo', 'like', '%' . $request->type . '%')
            ->where('PdtNo', 'like', '%' . $request->model . '%')
            ->where('Test_Result', 'like', '%' . $request->test_result . '%')
            ->where('Line_Name', 'like', '%' . $request->line . '%')
            ->orderBy('Test_Time')->paginate(500);
        return ['data' => $data];
    }

    function traceToSpreadsheet(Request $request)
    {
        $data =  DB::connection('sqlsrv_lot_trace')
            ->table('W_QPIT-PC_Test_Table')->whereDate('Test_Time', '>=', $request->period1)
            ->whereDate('Test_Time', '<=', $request->period2)
            ->where('Production_Control_No', 'like', '%' . $request->production_control . '%')
            ->where('AssyNo', 'like', '%' . $request->assy_no . '%')
            ->where('BoardNo', 'like', '%' . $request->type . '%')
            ->where('PdtNo', 'like', '%' . $request->model . '%')
            ->where('Test_Result', 'like', '%' . $request->test_result . '%')
            ->where('Line_Name', 'like', '%' . $request->line . '%')->orderBy('Test_Time');

        $datas = $data->get();
        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $sheet->setTitle('QPIT Log');
        $sheet->setCellValue([1, 1], 'Test Time');
        $sheet->setCellValue([2, 1], 'Test Process');
        $sheet->setCellValue([3, 1], 'Production Control No');
        $sheet->setCellValue([4, 1], 'Assy No');
        $sheet->setCellValue([5, 1], 'Type');
        $sheet->setCellValue([6, 1], 'Model');
        $sheet->setCellValue([7, 1], 'Test Result');
        $sheet->setCellValue([8, 1], 'Error Code');
        $sheet->setCellValue([9, 1], 'Error Class');
        $sheet->setCellValue([10, 1], 'Error Details');
        $sheet->setCellValue([11, 1], 'Error Address');
        $sheet->setCellValue([12, 1], 'Error Pin No');
        $sheet->setCellValue([13, 1], 'Line');
        $sheet->setCellValue([14, 1], 'Shift');
        $sheet->setCellValue([15, 1], 'PC No');
        $sheet->setCellValue([16, 1], 'JIG No');
        $sheet->setCellValue([17, 1], 'Power Box No');
        $sheet->setCellValue([18, 1], 'QPITPC System Program Ver');
        $sheet->setCellValue([19, 1], 'Target Program Ver');
        $sheet->setCellValue([20, 1], 'Test Program Ver');
        $sheet->setCellValue([21, 1], 'Detail Setting');
        $sheet->setCellValue([22, 1], 'Function Test Sum');
        $sheet->setCellValue([23, 1], 'Operator');
        $sheet->setCellValue([24, 1], 'Password Ver');


        $i = 3;
        foreach ($datas as $r) {
            $sheet->setCellValue([1, $i], $r->Test_Time);
            $sheet->setCellValue([2, $i], $r->Test_Process);
            $sheet->setCellValue([3, $i], $r->Production_Control_No);
            $sheet->setCellValue([4, $i], $r->AssyNo);
            $sheet->setCellValue([5, $i], $r->BoardNo);
            $sheet->setCellValue([6, $i], $r->PdtNo);
            $sheet->setCellValue([7, $i], $r->Test_Result);
            $sheet->setCellValue([8, $i], $r->Error_Code);
            $sheet->setCellValue([9, $i], $r->Error_Class);
            $sheet->setCellValue([10, $i], $r->Error_Address);
            $sheet->setCellValue([11, $i], $r->Error_Details);
            $sheet->setCellValue([12, $i], $r->Error_Pin_No);
            $sheet->setCellValue([13, $i], $r->Line_Name);
            $sheet->setCellValue([14, $i], $r->Shift_Name);
            $sheet->setCellValue([15, $i], $r->PC_No);
            $sheet->setCellValue([16, $i], $r->Jig_No);
            $sheet->setCellValue([17, $i], $r->Power_Box_No);
            $sheet->setCellValue([18, $i], $r->QPITPC_System_Program_Ver);
            $sheet->setCellValue([19, $i], $r->Target_Program_Ver);
            $sheet->setCellValue([20, $i], $r->Test_Program_Ver);
            $sheet->setCellValue([21, $i], $r->Detailed_Setting);
            $sheet->setCellValue([22, $i], $r->Function_Test_Sum);
            $sheet->setCellValue([23, $i], $r->Operator_Name);
            $sheet->setCellValue([24, $i], $r->Password_Ver);
            $i++;
        }

        foreach (range('A', 'T') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }

        $sheet->freezePane('A3');

        $stringjudul = "QPIT Logs, " . date('H:i:s');
        $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Access-Control-Allow-Origin: *');
        $writer->save('php://output');
    }
}
