<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class DisposeItemController extends Controller
{
    function breakdown(Request $request)
    {
        ini_set('max_execution_time', '-1');
        $ith_data = DB::connection('sqlsrv_wms')->table('v_ith_tblc')
            ->where('ITH_WH', $request->wh)
            ->where('ITH_DATEC', '<=', $request->cutoff_date)
            ->groupBy('ITH_SER')
            ->havingRaw("SUM(ITH_QTY)>0")
            ->select('ITH_SER');
        $ith_data_d = DB::connection('sqlsrv_wms')->table('v_ith_tblc')
            ->where('ITH_WH', $request->wh)
            ->where('ITH_DATEC', '<=', $request->cutoff_date)
            ->groupBy('ITH_SER')
            ->havingRaw("SUM(ITH_QTY)>0")
            ->select('ITH_SER', DB::raw("SUM(ITH_QTY) ITH_QTY_SUM"));

        $serd2_data = DB::connection('sqlsrv_wms')->table('SERD2_TBL as SERDA')
            ->whereIn("SERD2_SER", $ith_data)
            ->groupBy('SERD2_JOB', 'SERD2_SER')
            ->select(
                "SERD2_JOB",
                DB::raw("SUM(SERD2_QTY)/MAX(SERD2_FGQTY) SERD2_QTPER_SUM"),
                DB::raw("max(SERD2_SER) SERD2_SER_SUM"),
                DB::raw("max(SERD2_FGQTY) SERD2_FGQTY")
            );
        $serd2_detail_data = DB::connection('sqlsrv_wms')->table('SERD2_TBL as SERDB')
            ->whereIn("SERD2_SER", $ith_data)
            ->groupBy('SERD2_SER', 'SERD2_ITMCD')
            ->select(
                "SERD2_SER",
                "SERD2_ITMCD",
                DB::raw("SUM(SERD2_QTY) SERD2_QTY_SUM"),
            );

        $main_data = DB::connection('sqlsrv_wms')->query()
            ->fromSub($ith_data_d, 'V1')
            ->leftJoin('SER_TBL', 'ITH_SER', '=', 'SER_ID')
            ->leftJoin('WOH_TBL', 'SER_DOC', '=', 'WOH_CD')
            ->leftJoinSub($serd2_data, 'VCAL', 'ITH_SER', '=', 'SERD2_SER_SUM')
            ->leftJoinSub($serd2_detail_data, 'VCALD', 'ITH_SER', '=', 'SERD2_SER')
            ->select('V1.*', 'SER_DOC', 'SERD2_QTPER_SUM', 'SERD2_ITMCD', 'SERD2_QTY_SUM')
            ->whereRaw("ISNULL(WOH_TTLUSE,1)=ISNULL(SERD2_QTPER_SUM,0)")
            ->where("SER_QTY", '!=', 0);

        $will_join_data = DB::connection('sqlsrv_wms')->query()
            ->fromSub($ith_data_d, 'V1')
            ->leftJoin('SER_TBL', 'ITH_SER', '=', 'SER_ID')
            ->leftJoin('WOH_TBL', 'SER_DOC', '=', 'WOH_CD')
            ->leftJoinSub($serd2_data, 'VCAL', 'ITH_SER', '=', 'SERD2_SER_SUM')
            ->select('V1.*', 'SER_DOC', 'SERD2_FGQTY')
            ->whereRaw("ISNULL(WOH_TTLUSE,1)=ISNULL(SERD2_QTPER_SUM,0)")
            ->where("SER_QTY", '=', 0);

        $splitted_data = DB::connection('sqlsrv_wms')->query()
            ->fromSub($ith_data_d, 'V1')
            ->leftJoin('SER_TBL', 'ITH_SER', '=', 'SER_ID')
            ->leftJoin('WOH_TBL', 'SER_DOC', '=', 'WOH_CD')
            ->leftJoinSub($serd2_data, 'VCAL', 'ITH_SER', '=', 'SERD2_SER_SUM')
            ->select('V1.*', 'SER_DOC', 'SERD2_FGQTY')
            ->whereRaw("ISNULL(WOH_TTLUSE,1)!=0")
            ->where("SER_QTY", '=', 0);

        $composed_data = DB::connection('sqlsrv_wms')->query()
            ->fromSub($ith_data_d, 'V1')
            ->leftJoin('SER_TBL', 'ITH_SER', '=', 'SER_ID')
            ->leftJoin('WOH_TBL', 'SER_DOC', '=', 'WOH_CD')
            ->leftJoinSub($serd2_data, 'VCAL', 'ITH_SER', '=', 'SERD2_SER_SUM')
            ->select('V1.*', 'SER_DOC', 'SERD2_FGQTY')
            ->where("SER_DOC", 'like', '%-C%');

        // return ['data' => $composed_data->get()];

        $_main_data = $composed_data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue([1, 1], 'unique_code');
        $sheet->setCellValue([2, 1], 'unique_code_qty');
        $sheet->setCellValue([3, 1], 'item_code');
        $sheet->setCellValue([4, 1], 'item_qty');
        $sheet->setCellValue([5, 1], 'REFF');

        $rowAt = 2;
        foreach ($_main_data as $r) {
            $sheet->setCellValue([1, $rowAt], $r->ITH_SER);

            // SPLITTED
            // $_sub = $this->simulateCalculation(['job' => $r->SER_DOC, 'qty_ser' => $r->ITH_QTY_SUM]);
            // foreach ($_sub as $_r) {
            //     $sheet->setCellValue([1, $rowAt], $r->ITH_SER);
            //     $sheet->setCellValue([2, $rowAt], $r->ITH_QTY_SUM);
            //     $sheet->setCellValue([3, $rowAt], $_r->SERD_MPART);
            //     $sheet->setCellValue([4, $rowAt], $_r->SERREQQTY);
            //     $rowAt++;
            // }
            // END SPLLITED

            // WILL JOIN
            // $_sub = $this->getCalculation(['id' => $r->ITH_SER]);
            // foreach ($_sub as $_r) {
            //     $sheet->setCellValue([1, $rowAt], $r->ITH_SER);
            //     $sheet->setCellValue([2, $rowAt], $r->ITH_QTY_SUM);
            //     $sheet->setCellValue([3, $rowAt], $_r->SERD2_ITMCD);
            //     $sheet->setCellValue([4, $rowAt], $_r->SERREQQTY);
            //     $rowAt++;
            // }
            // END WILL JOIN

            // COMPOSED JOIN
            $_sub = $this->getComposition(['id' => $r->ITH_SER]);
            foreach ($_sub as $_r) {
                $sheet->setCellValue([1, $rowAt], $r->ITH_SER);
                $sheet->setCellValue([2, $rowAt], $r->ITH_QTY_SUM);
                $sheet->setCellValue([3, $rowAt], $_r->SERD2_ITMCD);
                $sheet->setCellValue([4, $rowAt], $_r->SERREQQTY);
                $sheet->setCellValue([5, $rowAt], $_r->SERC_COMID);
                $rowAt++;
            }
            // END COMPOSED
            $rowAt++;
        }

        $stringjudul = "disposal Logs, " . date('H:i:s');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Access-Control-Allow-Origin: *');
        $writer->save('php://output');
    }


    private function simulateCalculation($data)
    {
        $req = DB::connection('sqlsrv_wms')->table('SERD_TBL')
            ->where('SERD_JOB', $data['job'])
            ->groupBy(
                'SERD_LINENO',
                'SERD_FR',
                'SERD_JOB',
                'SERD_QTPER',
                'SERD_MC',
                'SERD_MCZ',
                'SERD_PROCD',
                'SERD_MPART'
            )->select(
                'SERD_LINENO',
                'SERD_FR',
                'SERD_JOB',
                'SERD_QTPER',
                'SERD_MC',
                'SERD_MCZ',
                'SERD_PROCD',
                'SERD_MPART',
                DB::raw("MAX(SERD_CAT) SERD_CAT"),
                DB::raw($data['qty_ser'] . "*SERD_QTPER SERREQQTY"),
                DB::raw("0 SUPSERQTY")
            );
        return $req->get();
    }

    private function getCalculation($data)
    {
        $req = DB::connection('sqlsrv_wms')->table('SERD2_TBL')
            ->where('SERD2_SER', $data['id'])
            ->groupBy(
                'SERD2_ITMCD',
            )->select(
                'SERD2_ITMCD',
                DB::raw("SUM(SERD2_QTY) SERREQQTY"),
            );
        return $req->get();
    }

    private function getComposition($data)
    {
        $req = DB::connection('sqlsrv_wms')->table('SERC_TBL')
            ->leftJoin('SERD2_TBL', 'SERC_COMID', '=', 'SERD2_SER')
            ->where('SERC_NEWID', $data['id'])
            ->groupBy(
                'SERC_COMID',
                'SERD2_ITMCD',
            )->select(
                'SERC_COMID',
                'SERD2_ITMCD',
                DB::raw("SUM(SERD2_QTY) SERREQQTY"),
            );
        return $req->get();
    }
}
