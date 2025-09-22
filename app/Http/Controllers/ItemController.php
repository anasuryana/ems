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

    function synchronizeXRayItem()
    {
        $data = DB::connection('sqlsrv_wms')->table('MITM_TBL')->leftJoin('YMITM_V', 'MITM_ITMCD', '=', 'item_code')
            ->whereRaw("MITM_SPTNO!=item_name")
            ->get([
                DB::raw('RTRIM(MITM_ITMCD) MITM_ITMCD'),
                DB::raw('RTRIM(MITM_SPTNO) MITM_SPTNO'),
                'item_name'
            ]);

        $affectedRows = 0;
        foreach ($data as $r) {
            $affectedRows += DB::connection('mysql_xray')->table('items')
                ->where('item_code', $r->MITM_ITMCD)
                ->update(['item_name' => $r->MITM_SPTNO]);
        }

        return ['data' => $data, 'Affected Rows' => $affectedRows];
    }
}
