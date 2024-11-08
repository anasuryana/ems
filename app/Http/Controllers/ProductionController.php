<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function supplyStatus(Request $request)
    {

        $year = date('y');

        $job = $year . '-' . $request->doc . '-' . $request->itemCode;

        $JobData = DB::connection('sqlsrv_wms')->table('WMS_CLS_JOB')
            ->where('CLS_JOBNO', $job)
            ->groupBy('CLS_JOBNO')
            ->first(['CLS_JOBNO', DB::raw('SUM(CLS_QTY) CLS_QTY')]);

        if (empty($JobData->CLS_JOBNO)) {
            $year++;
            $job = $year . '-' . $request->doc . '-' . $request->itemCode;
            $JobData = DB::connection('sqlsrv_wms')->table('WMS_CLS_JOB')
                ->where('CLS_JOBNO', $job)
                ->groupBy('CLS_JOBNO')
                ->first(['CLS_JOBNO', DB::raw('SUM(CLS_QTY) CLS_QTY')]);

            if (empty($JobData->CLS_JOBNO)) {
                $year -= 2;
                $job = $year . '-' . $request->doc . '-' . $request->itemCode;
                $JobData = DB::connection('sqlsrv_wms')->table('WMS_CLS_JOB')
                    ->where('CLS_JOBNO', $job)
                    ->groupBy('CLS_JOBNO')
                    ->first(['CLS_JOBNO', DB::raw('SUM(CLS_QTY) CLS_QTY')]);

                if (empty($JobData->CLS_JOBNO)) {
                    $status = [
                        'code' => false,
                        'message' => 'Supply is not enough!',
                        'job' => $job
                    ];
                    return ['status' => $status, 'master' => $JobData];
                }
            }
        }

        $status = $JobData->CLS_QTY >= $request->qty ?
            [
                'code' => true,
                'message' => 'OK',
                'job' => $job
            ] :
            [
                'code' => false,
                'message' => 'Supply is not enough',
                'job' => $job
            ];

        return ['status' => $status, 'master' => $JobData];
    }
}
