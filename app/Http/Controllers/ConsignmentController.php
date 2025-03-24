<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsignmentController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function getChildConsignments(Request $request)
    {
        $subData = DB::connection('sqlsrv_wms')->table('sub_consigments')
            ->whereNull('deleted_at')
            ->where('as_default', 'Y')
            ->where('parent_code', $request->parent_code);

        $data = DB::connection('sqlsrv_wms')->table('MDEL_TBL')
            ->leftJoinSub($subData, 'v1', 'MDEL_DELCD', '=', 'code')
            ->where('PARENT_DELCD', $request->parent_code)
            ->get(['MDEL_TBL.*', 'as_default']);
        return ['data' => $data];
    }

    function setDefaultConsignment(Request $request)
    {
        $affectedRows = -1;
        if ($request->child_code == '-') {
            $affectedRows = DB::connection('sqlsrv_wms')->table('sub_consigments')
                ->where('parent_code', $request->parent_code)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => date('Y-m-d H:i:s')
                ]);
        } else {

            $dataCount = DB::connection('sqlsrv_wms')->table('sub_consigments')
                ->where('parent_code', $request->parent_code)
                ->where('as_default', 'Y')
                ->whereNull('deleted_at')
                ->count();


            if ($dataCount > 0) {
                DB::connection('sqlsrv_wms')->table('sub_consigments')
                    ->where('parent_code', $request->parent_code)
                    ->where('as_default', 'Y')
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => date('Y-m-d H:i:s')
                    ]);
            }

            $affectedRows = DB::connection('sqlsrv_wms')->table('sub_consigments')
                ->insert([
                    'code' => $request->child_code,
                    'parent_code' => $request->parent_code,
                    'as_default' => 'Y',
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $request->user_id,
                ]);
        }

        return ['message' => $affectedRows ? 'Successfully actived' : 'sorry, please contact admin'];
    }
}
