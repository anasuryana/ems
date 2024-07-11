<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ICTController extends Controller
{
    function search(Request $request) {
        $data = DB::connection('sqlsrv_lot_trace')->table('ICT_CDT');
        return ['data' => $data->get()];
    }
}
