<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    function getByNik(Request $request)
    {
        $data = DB::connection('sqlsrv_wms')->table('MSTEMP_TBL')
            ->where('MSTEMP_ID', $request->nik)
            ->first('MSTEMP_FNM', 'MSTEMP_LNM');
        return ['data' => $data];
    }
}
