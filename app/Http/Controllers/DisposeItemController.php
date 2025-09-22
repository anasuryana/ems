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

    function checkMultipleResponse(Request $request)
    {
        ini_set('max_execution_time', '-1');
        $results = DB::connection('sqlsrv_wms')->select("
                    select VX.*,VR.RPSTOCK_QTY,VR.id from (
                        SELECT
                        VBC.*,
                        ISNULL(BOMQT, BOMQT2) BOMQT,
                        RESPONSEQT + ISNULL(BOMQT, BOMQT2) BAL,
                        ISNULL(FGQT, FGQT2) FGQT,
                        ISNULL(BOMQT, BOMQT2) / ISNULL(FGQT, FGQT2) PER,
                        DELVPURPOSE,
                        DLV_ZNOMOR_AJU
                        FROM
                        (
                            SELECT
                            RPSTOCK_REMARK,
                            RTRIM(RPSTOCK_ITMNUM) RPSTOCK_ITMNUM,
                            sum(RPSTOCK_QTY) RESPONSEQT
                            FROM
                            ZRPSAL_BCSTOCK
                            WHERE
                            RPSTOCK_QTY < 0
                            AND RPSTOCK_ITMNUM in (select compare1.ItemCode from compare1)
                            GROUP BY
                            RPSTOCK_REMARK,
                            RPSTOCK_ITMNUM
                        ) VBC
                        LEFT JOIN (
                            SELECT
                            DLV_ID,
                            SERD2_ITMCD,
                            SUM(SERD2_FGQTY) FGQT,
                            SUM(SERD2_QTY) BOMQT,
                            MAX(DLV_PURPOSE) DELVPURPOSE,
                            MAX(DLV_ZNOMOR_AJU) DLV_ZNOMOR_AJU
                            FROM
                            DLV_TBL
                            LEFT JOIN SERD2_TBL ON DLV_SER = SERD2_SER
                            WHERE
                            SERD2_ITMCD in (select compare1.ItemCode from compare1)
                            GROUP BY
                            DLV_ID,
                            SERD2_ITMCD
                        ) VDELV ON RPSTOCK_REMARK = DLV_ID
                        AND RPSTOCK_ITMNUM = SERD2_ITMCD
                        LEFT JOIN (
                            SELECT
                            DLV_ID DLV_ID2,
                            SERD2_ITMCD SERD2_ITMCD2,
                            SUM(SERD2_FGQTY) FGQT2,
                            SUM(SERD2_QTY) BOMQT2,
                            MAX(DLV_PURPOSE) DELVPURPOSE2
                            FROM
                            serml_tbl
                            LEFT JOIN dlv_tbl ON serml_newid = dlv_Ser
                            LEFT JOIN SERD2_TBL ON serml_comid = serd2_ser
                            WHERE
                            serd2_itmcd in (select compare1.ItemCode from compare1)
                            GROUP BY
                            DLV_ID,
                            SERD2_ITMCD
                        ) VDELV2 ON RPSTOCK_REMARK = DLV_ID2
                        AND RPSTOCK_ITMNUM = SERD2_ITMCD2
                        WHERE
                        abs(ISNULL(RESPONSEQT, 0)/2) = ISNULL(BOMQT, ISNULL(BOMQT2, 0))
                        ) VX
                        LEFT JOIN ZRPSAL_BCSTOCK VR ON VX.RPSTOCK_ITMNUM=VR.RPSTOCK_ITMNUM AND VX.RPSTOCK_REMARK=VR.RPSTOCK_REMARK
                        ORDER BY
                        VX.RPSTOCK_ITMNUM,VX.RPSTOCK_REMARK
                ");
        $_result = collect($results);

        $combinedSet = $_result->map(function ($item) {
            return [
                'RPSTOCK_ITMNUM' => $item->RPSTOCK_ITMNUM,
                'RPSTOCK_REMARK' => $item->RPSTOCK_REMARK,
                'DLV_ZNOMOR_AJU' => $item->DLV_ZNOMOR_AJU,
            ];
        })->unique(function ($item) {
            return $item['RPSTOCK_ITMNUM'] . '_' . $item['RPSTOCK_REMARK']; // Gabung jadi satu key untuk unik
        })->values();


        // check is contain two rows
        $resume = [];

        $counter = 0;
        foreach ($combinedSet as $r) {
            $_r = $_result->where('RPSTOCK_ITMNUM', $r['RPSTOCK_ITMNUM'])->where('RPSTOCK_REMARK', $r['RPSTOCK_REMARK']);
            $_countRows = $_r->count();

            if ($_countRows == 2) {
                $_id = $_r->first()->id;

                $_resume = ['total_rows' => $_countRows, 'params' => $r, 'firstID' => $_id];
                // $bcstock_row = DB::connection('sqlsrv_it_inventory')->table('RPSAL_BCSTOCK')->where('id', $_id)->first();
                $affected_rows = DB::connection('sqlsrv_it_inventory')->table('RPSAL_BCSTOCK')->where('id', $_id)->update([
                    'deleted_at' => date('Y-m-d H:i:s')
                ]);

                if ($affected_rows) {
                    // $_resume['status'] = $bcstock_row;
                    $_resume['updated_at'] = date('Y-m-d H:i:s');
                } else {
                    $_resume['status'] = NULL;
                }

                $resume[] = $_resume;
                $counter++;
            } else {
                if ($_countRows > 2) {
                    $resume[] = ['total_rows' => $_countRows, 'params' => $r, 'firstID' => $_r->first()->id, 'status' => 'not-restored'];
                    $counter++;
                }
            }

            if ($counter == 1000) break;
        }

        return ['resume' => $resume];
    }

    function upload()
    {
        $base = json_decode(Storage::disk('public')->get('to_upload2.json'), FALSE);

        $uniqueItem = [];
        $tobeSaved = [];
        foreach ($base as $r) {
            if (!in_array($r->item_code, $uniqueItem)) {
                $uniqueItem[] = $r->item_code;
                $tobeSaved[] = [
                    'ItemCode' => $r->item_code,
                    'Candidate_Dispose' => $r->plan_qty_dispose,
                    'balance_from_all' => $r->balance_from_all,
                    'Remark' => $r->remark,
                ];
            }
        }

        if (!empty($uniqueItem)) {
            $chunks = collect($tobeSaved)->chunk(2000 / 4);
            foreach ($chunks as $chunk) {
                DB::connection('sqlsrv_wms')->table("compare1")->insert($chunk->toArray());
            }
        }

        return ['data' => $tobeSaved];
    }
}
