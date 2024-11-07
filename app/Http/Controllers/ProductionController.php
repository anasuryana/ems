<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductionController extends Controller
{
    function supplyStatus(Request $request)
    {
        $data = $request->simulate == '1' ? [] : [
            ['partCode' => 'A', 'outstandingQty' => 300],
            ['partCode' => 'B', 'outstandingQty' => 150],
        ];

        $job = '24-' . $request->doc . '-' . $request->itemCode;

        $status = $request->simulate == '1' ? [
            'code' => true,
            'message' => 'OK',
            'job' => $job
        ] :
            [
                'code' => false,
                'message' => 'Supply is not enough',
                'job' => $job
            ];
        return ['status' => $status, 'data' => $data];
    }
}
