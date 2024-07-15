<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ICTController extends Controller
{
    function searchPaginate(Request $request)
    {
        $whereParam = [];
        if ($request->period1) {
            $whereParam[] = ['ICT_Date', '>=',  $request->period1];
        }
        if ($request->period2) {
            $whereParam[] = ['ICT_Date', '<=',  $request->period2];
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

        $data = DB::connection('sqlsrv_lot_trace')->table('ICT_CDT')
            ->where($whereParam)
            ->orderBy('ICT_Date')->paginate(250);
        return ['data' => $data];
    }

    function toSpreadsheet()
    {
    }
}
