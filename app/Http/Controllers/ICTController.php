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
            ->orderBy('ICTDATE')
            ->orderBy('ICT_Time');
        switch ($request->self_check) {
            case '1':
                if ($request->user()->role_id == 7) {
                    $data->whereNull('ICT_LupdtApp');
                } else {
                    if ($request->user()->role_id < 7) {
                        $data->whereDate('ICT_Lupdt' . $request->user()->role_id, '<', '2024-01-01');
                    }
                }
                break;
            case '2':
                if ($request->user()->role_id == 7) {
                    $data->whereNotNull('ICT_LupdtApp');
                } else {
                    if ($request->user()->role_id < 7) {
                        $data->whereDate('ICT_Lupdt' . $request->user()->role_id, '>=', '2024-01-01');
                    }
                }
                break;
        }

        return ['data' => $data->paginate(500)];
    }

    function _filterRequest($request)
    {
        $whereParam = [];
        if ($request->period1) {
            $whereParam[] = ['ICTDATE', '>=', $request->period1];
        }
        if ($request->period2) {
            $whereParam[] = ['ICTDATE', '<=', $request->period2];
        }
        if ($request->ict_no) {
            $whereParam[] = ['ICT_No', 'like', '%' . $request->ict_no . '%'];
        }
        if ($request->model) {
            $whereParam[] = ['ICT_Model', 'like', '%' . $request->model . '%'];
        }
        if ($request->step) {
            $whereParam[] = ['ICT_Step', 'like', '%' . $request->step . '%'];
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

    function _filterRequestApproval($request)
    {
        $whereParam = [];
        if ($request->ict_no) {
            $whereParam[] = ['ICT_No', 'like', '%' . $request->ict_no . '%'];
        }
        if ($request->model) {
            $whereParam[] = ['ICT_Model', 'like', '%' . $request->model . '%'];
        }
        if ($request->step) {
            $whereParam[] = ['ICT_Step', 'like', '%' . $request->step . '%'];
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
            ->orderBy('ICTDATE')
            ->orderBy('ICT_Time');
        switch ($request->self_check) {
            case '1':
                if ($request->user()->role_id == 7) {
                    $data->whereNull('ICT_LupdtApp');
                } else {
                    if ($request->user()->role_id < 7) {
                        $data->whereDate('ICT_Lupdt' . $request->user()->role_id, '<', '2024-01-01');
                    }
                }
                break;
            case '2':
                if ($request->user()->role_id == 7) {
                    $data->whereNotNull('ICT_LupdtApp');
                } else {
                    if ($request->user()->role_id < 7) {
                        $data->whereDate('ICT_Lupdt' . $request->user()->role_id, '>=', '2024-01-01');
                    }
                }
                break;
        }
        $datas = $data->get();
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
        $sheet->setCellValue([20, 1], 'Approval');
        $sheet->setCellValue([20, 2], 'Mr Syofyan');
        $sheet->setCellValue([21, 1], 'Remark');

        $i = 3;
        foreach ($datas as $r) {
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
            $sheet->setCellValue([12, $i], $r->ICT_Level);
            $sheet->setCellValue([13, $i], $r->ICT_PFile);
            $sheet->setCellValue([14, $i], $this->_displayDate($r->ICT_Lupdt1));
            $sheet->setCellValue([15, $i], $this->_displayDate($r->ICT_Lupdt2));
            $sheet->setCellValue([16, $i], $this->_displayDate($r->ICT_Lupdt3));
            $sheet->setCellValue([17, $i], $this->_displayDate($r->ICT_Lupdt4));
            $sheet->setCellValue([18, $i], $this->_displayDate($r->ICT_Lupdt5));
            $sheet->setCellValue([19, $i], $this->_displayDate($r->ICT_Lupdt6));
            $sheet->setCellValue([20, $i], $this->_displayDate($r->ICT_LupdtApp));
            $sheet->setCellValue([21, $i], $r->ICT_Remark);
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

    function _displayDate($data)
    {
        return substr($data, 0, 4) == '1900' ? null : $data;
    }

    function setCheck(Request $request)
    {
        $affectedRows = DB::connection('sqlsrv_lot_trace')->table('ICT_CDT')
            ->where('ICT_Date', $request->ICT_Date)
            ->where('ICT_Time', $request->ICT_Time)
            ->where('ICT_No', $request->ICT_No)
            ->where('ICT_Model', $request->ICT_Model)
            ->where('ICT_NFile', $request->ICT_NFile)
            ->where('ICT_Step', $request->ICT_Step ?? '')
            ->where('ICT_Device', $request->ICT_Device ?? '')
            ->where('ICT_Item', $request->ICT_Item)
            ->where('ICT_BValue', $request->ICT_BValue ?? '')
            ->where('ICT_AValue', $request->ICT_AValue ?? '')
            ->update(['ICT_Lupdt' . ($request->user()->role_id == 7 ? 'App' : $request->user()->role_id) => date('Y-m-d H:i:s')]);
        $responseCode = 200;
        if ($affectedRows > 0) {
            $message = 'Updated Successfully';
        } else {
            $responseCode = 500;
            $message = 'Fail to update';
        }
        return response()->json(['message' => $message, 'data' => $affectedRows], $responseCode);
    }

    function setCheckSome(Request $request)
    {
        $whereParam = $this->_filterRequest($request);
        $dataFirst = DB::connection('sqlsrv_lot_trace')->table('VICT_CDT')
            ->where($whereParam)
            ->whereNull('ICT_LupdtApp')
            ->whereDate('ICT_Lupdt6', '>', '2024-01-01')
            ->first('ICT_Date');

        $whereParamUpdate = $this->_filterRequestApproval($request);
        $whereParamUpdate[] = ['ICT_Date', '=', $dataFirst->ICT_Date];

        DB::connection('sqlsrv_lot_trace')->table('ICT_CDT')
            ->where($whereParamUpdate)
            ->whereNull('ICT_LupdtApp')
            ->whereDate('ICT_Lupdt6', '>', '2024-01-01')
            ->update(['ICT_LupdtApp' => date('Y-m-d H:i:s')]);
        return ['message' => 'Approved successfully', $whereParamUpdate];
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
            ->where('ICT_AValue', $request->ICT_AValue ?? '')
            ->first('*');

        $newRemarkFix = NULL;
        $cek = '1';

        if ($currentRemark->ICT_Remark ?? '') {
            $cek = '2';
            $_remark = json_decode($currentRemark->ICT_Remark, true);
            $_remark[] = ['userid' => $request->user()->nick_name, 'remark' => $request->ICT_RemarkNew];
            $newRemarkFix = json_encode($_remark);
        } else {
            $cek = '3';
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
            ->where('ICT_AValue', $request->ICT_AValue ?? '')
            ->update(['ICT_Remark' => $newRemarkFix]);
        return ['message' => 'Update successfully', $request->all(), 'cek' => $cek];
    }

    function reminderAsSpreadsheet(Request $request)
    {
        $totalEditData = DB::connection('sqlsrv_lot_trace')
            ->table('VICT_CDT')->whereYear('ICTDATE', date('Y'))
            ->whereMonth('ICTDATE', date('m'))->count();
        $data = DB::connection('sqlsrv_lot_trace')
            ->table('VICT_CDT')->whereYear('ICTDATE', date('Y'))
            ->whereMonth('ICTDATE', date('m'))
            ->groupBy('ICT_Item')
            ->orderBy('ICT_Item')
            ->get([
                'ICT_Item',
                DB::raw("COUNT(*) TOTAL_EDITED"),
                DB::raw("SUM(CASE WHEN CONVERT(DATE,ICT_Lupdt6) > '2024-01-01' THEN 1 ELSE 0 END) TOTAL_CHECKED"),
                DB::raw("SUM(CASE WHEN CONVERT(DATE,ICT_Lupdt6) < '2024-01-01' THEN 1 ELSE 0 END) TOTAL_NOT_YET_CHECKED"),
            ]);
        $yesterDayDate = date('Y-m-d', strtotime("-1 days"));
        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $sheet->setTitle('ICT Log');

        $sheet->setCellValue([1, 1], 'Period ' . date('F Y'));
        $sheet->setCellValue([1, 2], 'Total Edit data : ' . $totalEditData);
        $sheet->setCellValue([2, 3], 'Item Edit :');
        $sheet->setCellValue([4, 3], 'Total Edit');
        $sheet->setCellValue([5, 3], 'Checked');
        $sheet->setCellValue([6, 3], 'Not Yet Checked');

        $rowAt = 4;
        foreach ($data as $r) {
            $sheet->setCellValue([3, $rowAt], $r->ICT_Item);
            $sheet->setCellValue([4, $rowAt], $r->TOTAL_EDITED);
            $sheet->setCellValue([5, $rowAt], $r->TOTAL_CHECKED);
            $sheet->setCellValue([6, $rowAt], $r->TOTAL_NOT_YET_CHECKED);
            $rowAt++;
        }

        $rowAt++;
        $sheet->setCellValue([1, $rowAt], 'Yesterday (' . $yesterDayDate . ')');
        $rowAt++;
        $sheet->setCellValue([2, $rowAt], 'Item Edit :');
        $sheet->setCellValue([4, $rowAt], 'Total Edit');
        $sheet->setCellValue([5, $rowAt], 'Checked');
        $sheet->setCellValue([6, $rowAt], 'Not Yet Checked');
        $data2 = DB::connection('sqlsrv_lot_trace')
            ->table('VICT_CDT')->where('ICTDATE', $yesterDayDate)
            ->groupBy('ICT_Item')
            ->orderBy('ICT_Item')
            ->get([
                'ICT_Item',
                DB::raw("COUNT(*) TOTAL_EDITED"),
                DB::raw("SUM(CASE WHEN CONVERT(DATE,ICT_Lupdt6) > '2024-01-01' THEN 1 ELSE 0 END) TOTAL_CHECKED"),
                DB::raw("SUM(CASE WHEN CONVERT(DATE,ICT_Lupdt6) < '2024-01-01' THEN 1 ELSE 0 END) TOTAL_NOT_YET_CHECKED"),
            ]);
        $rowAt++;
        foreach ($data2 as $r) {
            $sheet->setCellValue([3, $rowAt], $r->ICT_Item);
            $sheet->setCellValue([4, $rowAt], $r->TOTAL_EDITED);
            $sheet->setCellValue([5, $rowAt], $r->TOTAL_CHECKED);
            $sheet->setCellValue([6, $rowAt], $r->TOTAL_NOT_YET_CHECKED);
            $rowAt++;
        }

        $stringjudul = "ICT Logs Reminder, " . date('Y-m-d');
        $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
        $filename = $stringjudul;

        if ($request->saveOutput == 1) {
            $writer->save(env('APP_ICT_LOGGER_REPORT_FILE_PATH') . $filename . '.xlsx');
        } else {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
            header('Cache-Control: max-age=0');
            header('Access-Control-Allow-Origin: *');
            $writer->save('php://output');
        }
    }

    function trace(Request $request)
    {
        $data =  DB::connection('sqlsrv_lot_trace')
            ->table('W_ICT_Test_Table')->whereDate('Test_Time', '>=', $request->period1)
            ->whereDate('Test_Time', '<=', $request->period2)
            ->where('Production_Control_No', 'like', '%' . $request->production_control . '%')
            ->where('AssyNo', 'like', '%' . $request->assy_no . '%')
            ->where('BoardNo', 'like', '%' . $request->type . '%')
            ->where('PdtNo', 'like', '%' . $request->model . '%')
            ->where('Test_Result', 'like', '%' . $request->test_result . '%')
            ->where('Line_Name', 'like', '%' . $request->line . '%')
            ->select(
                'Test_Time',
                'Test_Process',
                'Production_Control_No',
                DB::raw("REPLACE(AssyNo, '-','') AssyNo"),
                'BoardNo',
                'PdtNo',
                'Test_Result',
                'Error_Class',
                'Error_Address',
                'Error_Details',
                'Notes',
                'Line_Name',
                'Shift_Name',
                'ICT_No',
                'Jig_No',
                'Operator_Name'
            )
            ->orderBy('Test_Time')->paginate(500);
        return ['data' => $data];
    }

    function traceToSpreadsheet(Request $request)
    {
        $data =  DB::connection('sqlsrv_lot_trace')
            ->table('W_ICT_Test_Table')->whereDate('Test_Time', '>=', $request->period1)
            ->whereDate('Test_Time', '<=', $request->period2)
            ->where('Production_Control_No', 'like', '%' . $request->production_control . '%')
            ->where('AssyNo', 'like', '%' . $request->assy_no . '%')
            ->where('BoardNo', 'like', '%' . $request->type . '%')
            ->where('PdtNo', 'like', '%' . $request->model . '%')
            ->where('Test_Result', 'like', '%' . $request->test_result . '%')
            ->where('Line_Name', 'like', '%' . $request->line . '%')->orderBy('Test_Time');

        $datas = $data->get([
            'Test_Time',
            'Test_Process',
            'Production_Control_No',
            DB::raw("REPLACE(AssyNo, '-','') AssyNo"),
            'BoardNo',
            'PdtNo',
            'Test_Result',
            'Error_Class',
            'Error_Address',
            'Error_Details',
            'Notes',
            'Line_Name',
            'Shift_Name',
            'ICT_No',
            'Jig_No',
            'Operator_Name'
        ]);
        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $sheet->setTitle('ICT Log');
        $sheet->setCellValue([1, 1], 'Test Time');
        $sheet->setCellValue([2, 1], 'Test Process');
        $sheet->setCellValue([3, 1], 'Production Control No');
        $sheet->setCellValue([4, 1], 'Assy No');
        $sheet->setCellValue([5, 1], 'Type');
        $sheet->setCellValue([6, 1], 'Model');
        $sheet->setCellValue([7, 1], 'Test Result');
        $sheet->setCellValue([8, 1], 'Error Class');
        $sheet->setCellValue([9, 1], 'Error Address');
        $sheet->setCellValue([10, 1], 'Error Details');
        $sheet->setCellValue([11, 1], 'Notes');
        $sheet->setCellValue([12, 1], 'Line');
        $sheet->setCellValue([13, 1], 'Shift');
        $sheet->setCellValue([14, 1], 'ICT No');
        $sheet->setCellValue([15, 1], 'JIG No');
        $sheet->setCellValue([16, 1], 'Operator');

        $i = 2;
        foreach ($datas as $r) {
            $sheet->setCellValue([1, $i], $r->Test_Time);
            $sheet->setCellValue([2, $i], $r->Test_Process);
            $sheet->setCellValue([3, $i], $r->Production_Control_No);
            $sheet->setCellValue([4, $i], $r->AssyNo);
            $sheet->setCellValue([5, $i], $r->BoardNo);
            $sheet->setCellValue([6, $i], $r->PdtNo);
            $sheet->setCellValue([7, $i], $r->Test_Result);
            $sheet->setCellValue([8, $i], $r->Error_Class);
            $sheet->setCellValue([9, $i], $r->Error_Address);
            $sheet->setCellValue([10, $i], $r->Error_Details);
            $sheet->setCellValue([11, $i], $r->Notes);
            $sheet->setCellValue([12, $i], $r->Line_Name);
            $sheet->setCellValue([13, $i], $r->Shift_Name);
            $sheet->setCellValue([14, $i], $r->ICT_No);
            $sheet->setCellValue([15, $i], $r->Jig_No);
            $sheet->setCellValue([16, $i], $r->Operator_Name);
            $i++;
        }

        foreach (range('A', 'T') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        $stringjudul = "ICT Logs, " . date('H:i:s');
        $filename = $stringjudul;
        header('Cache-Control: max-age=0');
        header('Access-Control-Allow-Origin: *');
        if ($request->file_type == 'xlsx') {
            $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        } else {
            $writer = IOFactory::createWriter($spreadSheet, 'Csv');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="' . $filename . '.csv"');
        }

        $writer->save('php://output');
    }
}
