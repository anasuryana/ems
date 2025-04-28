<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    function getProcess(Request $request)
    {
        $data = DB::connection('sqlsrv_wms')->table('VCIMS_MBLA_TBL')->where('MBLA_MDLCD', $request->itemCode)
            ->where('MBLA_BOMRV', $request->bomRev)
            ->groupBy('MBLA_PROCD', 'MBLA_LINENO')
            ->get([
                DB::raw('RTRIM(MBLA_PROCD) MBLA_PROCD'),
                DB::raw('RTRIM(MBLA_LINENO) MBLA_LINENO'),
            ]);

        $processes = $data->unique('MBLA_PROCD')->pluck('MBLA_PROCD')->toArray();

        return ['data' => $processes, 'reff' => $data];
    }
}
