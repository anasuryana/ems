<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ICTController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function searchPaginate(Request $request)
    {
        $whereParam = $this->_filterRequest($request);

        $data = DB::connection('sqlsrv_lot_trace')->table('VICT_CDT')
            ->where($whereParam)
            ->orderBy('ICTDATE')->paginate(250);
        return ['data' => $data];
    }

    function _filterRequest($request)
    {
        $whereParam = [];
        if ($request->period1) {
            $whereParam[] = ['ICTDATE', '>=',  $request->period1];
        }
        if ($request->period2) {
            $whereParam[] = ['ICTDATE', '<=',  $request->period2];
        }
        if ($request->ict_no) {
            $whereParam[] = ['ICT_No', 'like', '%' . $request->ict_no . '%'];
        }
        if ($request->model) {
            $whereParam[] = ['ICT_Model', 'like', '%' . $request->model . '%'];
        }
        if ($request->file_name) {
            $whereParam[] = ['ICT_NFile', 'like', '%' . $request->file_name . '%'];
        }
        if ($request->item) {
            $whereParam[] = ['ICT_Item', 'like', '%' . $request->item . '%'];
        }
        if ($request->operator_name) {
            $whereParam[] = ['ICT_Lupby', 'like', '%' . $request->operator_name . '%'];
        }
        return $whereParam;
    }

    function toSpreadsheet(Request $request)
    {
        $whereParam = $this->_filterRequest($request);
        $data = DB::connection('sqlsrv_lot_trace')->table('VICT_CDT')
            ->where($whereParam)
            ->orderBy('ICTDATE')->get();
        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $sheet->setTitle('ICT Log');
        $sheet->setCellValue([1, 1], 'Date');
        $sheet->setCellValue([2, 1], 'Time');
        $sheet->setCellValue([3, 1], 'ICT No');
        $sheet->setCellValue([4, 1], 'Model');
        $sheet->setCellValue([5, 1], 'File Name');
        $sheet->setCellValue([6, 1], 'Step');
        $sheet->setCellValue([7, 1], 'Device');
        $sheet->setCellValue([8, 1], 'Item');
        $sheet->setCellValue([9, 1], 'Before Value');
        $sheet->setCellValue([10, 1], 'After Value');
        $sheet->setCellValue([11, 1], 'Operator Name');
        $sheet->setCellValue([12, 1], 'User Level');
        $sheet->setCellValue([13, 1], 'Program File');
        $sheet->setCellValue([14, 1], 'Checked By');
        $sheet->setCellValue([14, 2], 'Gilang');
        $sheet->setCellValue([15, 2], 'Rico');
        $sheet->setCellValue([16, 2], 'Adi S');
        $sheet->setCellValue([17, 2], 'Sanawi');
        $sheet->setCellValue([18, 2], 'Kiswanto');
        $sheet->setCellValue([19, 2], 'Muttaqin');
        $sheet->setCellValue([20, 1], 'Remark');

        $i = 3;
        foreach ($data as $r) {
            $sheet->setCellValue([1, $i], $r->ICTDATE);
            $sheet->setCellValue([2, $i], $r->ICT_Time);
            $sheet->setCellValue([3, $i], $r->ICT_No);
            $sheet->setCellValue([4, $i], $r->ICT_Model);
            $sheet->setCellValue([5, $i], $r->ICT_NFile);
            $sheet->setCellValue([6, $i], $r->ICT_Step);
            $sheet->setCellValue([7, $i], $r->ICT_Device);
            $sheet->setCellValue([8, $i], $r->ICT_Item);
            $sheet->setCellValue([9, $i], $r->ICT_BValue);
            $sheet->setCellValue([10, $i], $r->ICT_AValue);
            $sheet->setCellValue([11, $i], $r->ICT_Lupby);
            $i++;
        }

        foreach (range('A', 'T') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }

        $sheet->freezePane('A3');

        $stringjudul = "ICT Logs, " . date('H:i:s');
        $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Access-Control-Allow-Origin: *');
        $writer->save('php://output');
    }

    function setCheck(Request $request)
    {
        DB::connection('sqlsrv_lot_trace')->table('ICT_CDT')
            ->where('ICT_Date', $request->ICT_Date)
            ->where('ICT_Time', $request->ICT_Time)
            ->where('ICT_No', $request->ICT_No)
            ->where('ICT_Model', $request->ICT_Model)
            ->where('ICT_NFile', $request->ICT_NFile)
            ->where('ICT_Step', $request->ICT_Step)
            ->where('ICT_Device', $request->ICT_Device)
            ->where('ICT_Item', $request->ICT_Item)
            ->where('ICT_BValue', $request->ICT_BValue)
            ->where('ICT_AValue', $request->ICT_AValue)
            ->update(['ICT_Lupdt' . ($request->user()->role_id == 7 ? 'App' : $request->user()->role_id)  => date('Y-m-d H:i:s')]);
        return ['message' => 'Update successfully'];
    }

    function setRemark(Request $request)
    {
        $currentRemark = DB::connection('sqlsrv_lot_trace')->table('ICT_CDT')
            ->where('ICT_Date', $request->ICT_Date)
            ->where('ICT_Time', $request->ICT_Time)
            ->where('ICT_No', $request->ICT_No)
            ->where('ICT_Model', $request->ICT_Model)
            ->where('ICT_NFile', $request->ICT_NFile)
            ->where('ICT_Step', $request->ICT_Step)
            ->where('ICT_Device', $request->ICT_Device)
            ->where('ICT_Item', $request->ICT_Item)
            ->where('ICT_BValue', $request->ICT_BValue)
            ->where('ICT_AValue', $request->ICT_AValue)
            ->first('ICT_Remark');

        $newRemarkFix = NULL;

        if ($currentRemark->ICT_Remark) {
            $_remark = json_decode($currentRemark->ICT_Remark, true);
            $_remark[] = ['userid' => $request->user()->nick_name, 'remark' => $request->ICT_RemarkNew];
            $newRemarkFix = json_encode($_remark);
        } else {
            $rowRemark = [];
            $rowRemark[] = ['userid' => $request->user()->nick_name, 'remark' => $request->ICT_RemarkNew];
            $newRemarkFix = json_encode($rowRemark);
        }
        DB::connection('sqlsrv_lot_trace')->table('ICT_CDT')
            ->where('ICT_Date', $request->ICT_Date)
            ->where('ICT_Time', $request->ICT_Time)
            ->where('ICT_No', $request->ICT_No)
            ->where('ICT_Model', $request->ICT_Model)
            ->where('ICT_NFile', $request->ICT_NFile)
            ->where('ICT_Step', $request->ICT_Step)
            ->where('ICT_Device', $request->ICT_Device)
            ->where('ICT_Item', $request->ICT_Item)
            ->where('ICT_BValue', $request->ICT_BValue)
            ->where('ICT_AValue', $request->ICT_AValue)
            ->update(['ICT_Remark' => $newRemarkFix]);
        return ['message' => 'Update successfully', $request->all()];
    }
}
