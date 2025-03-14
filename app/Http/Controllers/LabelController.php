<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LabelController extends Controller
{
    function splitTreeHistory(Request $request)
    {

        $tree = [];

        $code = $request->code;
        $item_code = $request->item_code;

        // get balance of Supplied Material
        $__suppliedMaterial = DB::connection('sqlsrv_wms')->table('WMS_SWPS_HIS')
            ->where('SWPS_NITMCD', $item_code)
            ->where('SWPS_REMARK', 'OK')
            ->groupBy('SWPS_NUNQ')
            ->select(
                DB::raw('RTRIM(SWPS_NUNQ) TRACE_UNQ'),
            );

        $_suppliedMaterial = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
            ->where('SWMP_ITMCD', $item_code)
            ->where('SWMP_REMARK', 'OK')
            ->groupBy('SWMP_UNQ')
            ->select(
                DB::raw('RTRIM(SWMP_UNQ) TRACE_UNQ'),
            );

        $suppliedMaterial = DB::connection('sqlsrv_wms')->query()
            ->fromSub($_suppliedMaterial, 'v1')
            ->union($__suppliedMaterial);

        $rootData = DB::connection('sqlsrv_wms')->table('raw_material_labels')
            ->leftJoinSub($suppliedMaterial, 'v2', 'TRACE_UNQ', '=', 'code')
            ->where('code', $request->code)->first();
        if (!$rootData) {
            return ['message' => 'Not found'];
        }
        $status = '';
        if (!$rootData->splitted) {
            $status = 'Status:NOT SPLITTED';
        }
        if ($rootData->TRACE_UNQ) {
            $status = 'Status:Scanned Tracebility ✔';
        }
        $tree = ['text' => [
            'name' => $rootData->code,
            'desc' =>  $status,
            'data' => 'QTY:' . (int)$rootData->quantity
        ], 'children' => []];

        $_data = DB::connection('sqlsrv_wms')->table('raw_material_labels')
            ->leftJoinSub($suppliedMaterial, 'v2', 'TRACE_UNQ', '=', 'code')
            ->where('parent_code', $code)
            ->get(['code', 'parent_code', 'quantity', 'splitted', 'TRACE_UNQ']);

        if (!$_data->isEmpty()) {
            foreach ($_data as $r) {
                $status = '';
                if (!$r->splitted) {
                    $status = 'Status:NOT SPLITTED';
                }
                if ($r->TRACE_UNQ) {
                    $status = 'Status:Scanned Tracebility ✔';
                }
                $tree['children'][] = [
                    'text' => [
                        'name' => $r->code,
                        'desc' =>  $status,
                        'data' => 'QTY:' . (int)$r->quantity
                    ],
                    'children' => $this->_findChild($r->code, $item_code)
                ];
            }
        } else {
        }

        return ['data' => $tree, 'rootData' => $rootData];
    }

    function _findChild($code, $item_code)
    {
        $_treeInside = [];

        // get balance of Supplied Material
        $__suppliedMaterial = DB::connection('sqlsrv_wms')->table('WMS_SWPS_HIS')
            ->where('SWPS_NITMCD', $item_code)
            ->where('SWPS_REMARK', 'OK')
            ->groupBy('SWPS_NUNQ')
            ->select(
                DB::raw('RTRIM(SWPS_NUNQ) TRACE_UNQ'),
            );

        $_suppliedMaterial = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
            ->where('SWMP_ITMCD', $item_code)
            ->where('SWMP_REMARK', 'OK')
            ->groupBy('SWMP_UNQ')
            ->select(
                DB::raw('RTRIM(SWMP_UNQ) TRACE_UNQ'),
            );

        $suppliedMaterial = DB::connection('sqlsrv_wms')->query()
            ->fromSub($_suppliedMaterial, 'v1')
            ->union($__suppliedMaterial);

        $_data = DB::connection('sqlsrv_wms')->table('raw_material_labels')
            ->leftJoinSub($suppliedMaterial, 'v2', 'TRACE_UNQ', '=', 'code')
            ->where('parent_code', $code)
            ->get(['code', 'parent_code', 'quantity', 'splitted', 'TRACE_UNQ']);
        if (!$_data->isEmpty()) {
            foreach ($_data as $r) {
                $status = '';
                if (!$r->splitted) {
                    $status = 'Status:NOT SPLITTED';
                }
                if ($r->TRACE_UNQ) {
                    $status = 'Status:Scanned Tracebility ✔';
                }
                $_treeInside[] = [
                    'text' => [
                        'name' => $r->code,
                        'desc' =>  $status,
                        'data' => 'QTY:' . (int)$r->quantity
                    ],
                    'children' => $this->_findChild($r->code, $item_code),
                ];
            }
        }
        return $_treeInside;
    }
}
