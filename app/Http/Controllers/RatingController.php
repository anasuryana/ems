<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    function getPercentagePerLineMachinePeriod(Request $request)
    {
        $data = DB::connection('mysql_engtrial')->table('tbl_passrate')
            ->where('txttgl', '>=', $request->dateFrom)
            ->where('txttgl', '<=', $request->dateTo)
            ->where('txtmesin', $request->machineBrand)
            ->groupBy(
                'txtline',
                'txtict',
            )
            ->orderBy('txtline')
            ->orderBy('txtict')
            ->get([
                'txtline',
                'txtict',
                DB::raw('SUM(txtcheck) txtcheck'),
                DB::raw('SUM(txtpass) txtpass'),
                DB::raw('SUM(txtpass)/SUM(txtcheck)*100 txtpercen')
            ]);
        return ['data' => $data];
    }

    function getPercentagePerLineMachinePeriodDetail1(Request $request)
    {
        $data = DB::connection('mysql_engtrial')->table('tbl_passrate')
            ->where('txttgl', '>=', $request->dateFrom)
            ->where('txttgl', '<=', $request->dateTo)
            ->where('txtmesin', $request->machineBrand)
            ->where('txtline', $request->lineCode)
            ->where('txtict', $request->machineCode)
            ->groupBy(
                'txttgl',
                'txtline',
                'txtict',
            )
            ->orderBy('txttgl')
            ->orderBy('txtline')
            ->orderBy('txtict')
            ->get([
                'txttgl',
                'txtline',
                'txtict',
                DB::raw('SUM(txtcheck) txtcheck'),
                DB::raw('SUM(txtpass) txtpass'),
                DB::raw('SUM(txtpass)/SUM(txtcheck)*100 txtpercen')
            ]);
        return ['data' => $data];
    }
}
