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
            ->get(['txtline', 'txtict']);
        return ['data' => $data];
    }
}
