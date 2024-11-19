<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ProductionController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function supplyStatus(Request $request)
    {
        Logger('Mulai kalkulasi');
        $year = date('y');

        $job = $year . '-' . $request->doc . '-' . $request->itemCode;

        $isAlreadyCalculated  = false;

        $JobData = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
            ->where('SWMP_JOBNO', $job)
            ->where('SWMP_REMARK', 'OK')
            ->groupBy('SWMP_JOBNO', 'SWMP_BOMRV')
            ->first([DB::raw('RTRIM(SWMP_JOBNO) SWMP_JOBNO'), 'SWMP_BOMRV']);
        Logger('Inisialisasi $JobData 1');

        if (empty($JobData->SWMP_JOBNO)) {
            $year++;
            $job = $year . '-' . $request->doc . '-' . $request->itemCode;
            $JobData = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
                ->where('SWMP_JOBNO', $job)
                ->where('SWMP_REMARK', 'OK')
                ->groupBy('SWMP_JOBNO', 'SWMP_BOMRV')
                ->first([DB::raw('RTRIM(SWMP_JOBNO) SWMP_JOBNO'), 'SWMP_BOMRV']);

            Logger('Inisialisasi $JobData 11');

            if (empty($JobData->SWMP_JOBNO)) {
                $year -= 2;
                $job = $year . '-' . $request->doc . '-' . $request->itemCode;
                $JobData = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
                    ->where('SWMP_JOBNO', $job)
                    ->where('SWMP_REMARK', 'OK')
                    ->groupBy('SWMP_JOBNO', 'SWMP_BOMRV')
                    ->first([DB::raw('RTRIM(SWMP_JOBNO) SWMP_JOBNO'), 'SWMP_BOMRV']);

                Logger('Inisialisasi $JobData 111');

                if (empty($JobData->SWMP_JOBNO)) {
                    $status = [
                        'code' => false,
                        'message' => 'Job Number is not found',
                        'job' => $job
                    ];
                    return ['status' => $status, 'master' => $JobData, 'data' => []];
                }
            }
        }

        $XWO = DB::connection('sqlsrv_wms')->table('XWO')->where('PDPP_WONO', $JobData->SWMP_JOBNO)
            ->first();

        Logger('Inisialisasi $XWO');

        // get requirement
        $requirement = DB::connection('sqlsrv_wms')->table('VCIMS_MBOM_TBL')
            ->where('MBOM_MDLCD', $request->itemCode)
            ->where('MBOM_BOMRV', $XWO->PDPP_BOMRV)
            ->groupBy('MBOM_ITMCD', 'MBOM_SPART', 'MBOM_PROCD')
            ->get([
                DB::raw('RTRIM(MBOM_ITMCD) MBOM_ITMCD'),
                DB::raw('RTRIM(MBOM_SPART) MBOM_SPART'),
                DB::raw('RTRIM(MBOM_PROCD) MBOM_PROCD'),
                DB::raw('SUM(MBOM_QTY) PER'),
                DB::raw('SUM(MBOM_QTY)*' . $request->qty . ' REQQT'),
                DB::raw('0 FILLQT')
            ]);
        Logger('Inisialisasi $requirement');
        $anotherRequirement = new Collection();


        // get balance of Supplied Material
        $__suppliedMaterial = DB::connection('sqlsrv_wms')->table('WMS_SWPS_HIS')
            ->where('SWPS_JOBNO', $JobData->SWMP_JOBNO)
            ->where('SWPS_REMARK', 'OK')
            ->groupBy('SWPS_NITMCD', 'NQTY', 'SWPS_NUNQ', 'SWPS_NLOTNO', 'SWPS_PSNNO')
            ->select(
                DB::raw('RTRIM(SWPS_NITMCD) ITMCD'),
                DB::raw('NQTY QTY'),
                DB::raw('RTRIM(SWPS_NLOTNO) LOTNO'),
                DB::raw('RTRIM(SWPS_NUNQ) UNQ'),
                DB::raw('NQTY BAKQTY'),
                DB::raw('SWPS_PSNNO PSNNO'),
            );

        $_suppliedMaterial = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
            ->where('SWMP_JOBNO', $JobData->SWMP_JOBNO)
            ->where('SWMP_REMARK', 'OK')
            ->groupBy('SWMP_ITMCD', 'SWMP_QTY', 'SWMP_UNQ', 'SWMP_LOTNO', 'SWMP_PSNNO')
            ->select(
                DB::raw('RTRIM(SWMP_ITMCD) ITMCD'),
                DB::raw('SWMP_QTY QTY'),
                DB::raw('RTRIM(SWMP_LOTNO) LOTNO'),
                DB::raw('RTRIM(SWMP_UNQ) UNQ'),
                DB::raw('SWMP_QTY BAKQTY'),
                DB::raw('SWMP_PSNNO PSNNO'),
            );

        $suppliedMaterial = DB::connection('sqlsrv_wms')->query()
            ->fromSub($_suppliedMaterial, 'v1')
            ->union($__suppliedMaterial)->get();
        Logger('Inisialisasi $suppliedMaterial');
        // // get unique key 
        $uniqueKeyList = $suppliedMaterial->pluck('UNQ')->toArray();


        // // get work order related based on 
        $uniqueJobList = [];
        $JobOutput = [];
        if ($uniqueKeyList) {
            $__suppliedMaterialByUK = DB::connection('sqlsrv_wms')->table('WMS_SWPS_HIS')
                ->whereIn('SWPS_NUNQ', $uniqueKeyList)
                ->where('SWPS_REMARK', 'OK')
                ->groupBy('SWPS_JOBNO')
                ->select(
                    DB::raw('RTRIM(SWPS_JOBNO) JOBNO'),
                );

            $_suppliedMaterialByUK = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
                ->whereIn('SWMP_UNQ', $uniqueKeyList)
                ->where('SWMP_REMARK', 'OK')
                ->groupBy('SWMP_JOBNO')
                ->select(
                    DB::raw('RTRIM(SWMP_JOBNO) JOBNO'),
                );

            $suppliedMaterialByUK = DB::connection('sqlsrv_wms')->query()
                ->fromSub($_suppliedMaterialByUK, 'v1')
                ->union($__suppliedMaterialByUK)->get();
            Logger('Inisialisasi $suppliedMaterialByUK');

            $uniqueJobList = $suppliedMaterialByUK->pluck('JOBNO')->toArray();

            if ($uniqueJobList) {
                $JobOutput = DB::connection('sqlsrv_wms')->table('WMS_CLS_JOB')
                    ->leftJoin('XWO', 'CLS_JOBNO', '=', 'PDPP_WONO')
                    ->whereIn('CLS_JOBNO', $uniqueJobList)
                    ->groupBy('CLS_JOBNO', 'CLS_PROCD', 'CLS_MDLCD', 'PDPP_BOMRV')
                    ->get([
                        'CLS_JOBNO',
                        DB::raw("RTRIM(CLS_PROCD) CLS_PROCD"),
                        DB::raw("RTRIM(CLS_MDLCD) CLS_MDLCD"),
                        DB::raw("PDPP_BOMRV CLS_BOMRV"),
                        DB::raw("SUM(CLS_QTY) CLSQTY"),
                        DB::raw("MAX(CLS_PSNNO) CLS_PSNNO")
                    ]);
                Logger('Inisialisasi $JobOutput');

                foreach ($JobOutput as $r) {
                    if ($r->CLS_JOBNO == $JobData->SWMP_JOBNO) {
                        $_selectedClosingQty = $request->qty;
                        $isAlreadyCalculated = true;
                    } else {
                        $_selectedClosingQty = $r->CLSQTY;
                    }

                    $_requirement = DB::connection('sqlsrv_wms')->table('VCIMS_MBOM_TBL')
                        ->where('MBOM_MDLCD', $r->CLS_MDLCD)
                        ->where('MBOM_BOMRV', $r->CLS_BOMRV)
                        ->where('MBOM_PROCD', $r->CLS_PROCD)
                        ->groupBy('MBOM_ITMCD', 'MBOM_SPART', 'MBOM_PROCD')
                        ->get([
                            DB::raw("'" . $r->CLS_JOBNO . "' FLAGJOBNO"),
                            DB::raw("'" . $r->CLS_PSNNO . "' PSNNO"),
                            DB::raw('RTRIM(MBOM_ITMCD) MBOM_ITMCD'),
                            DB::raw('RTRIM(MBOM_SPART) MBOM_SPART'),
                            DB::raw('RTRIM(MBOM_PROCD) MBOM_PROCD'),
                            DB::raw('SUM(MBOM_QTY) PER'),
                            DB::raw('SUM(MBOM_QTY)*' . $_selectedClosingQty . ' REQQT'),
                            DB::raw('0 FILLQT')
                        ]);

                    $anotherRequirement = $anotherRequirement->merge($_requirement);
                }

                // // // deduct supplied material
                Logger('Plot Suplai terhadap Kebutuhan');
                foreach ($suppliedMaterial as &$s) {
                    foreach ($anotherRequirement as &$a) {
                        if ($s->ITMCD == $a->MBOM_ITMCD && $s->PSNNO == $a->PSNNO) {
                            if ($s->QTY == 0) {
                                break;
                            }

                            if ($a->REQQT == $a->FILLQT) {
                                break;
                            }

                            $_req = $a->REQQT - $a->FILLQT;
                            if ($s->QTY >= $_req) {
                                $a->FILLQT += $_req;
                                $s->QTY -= $_req;
                            } else {
                                $a->FILLQT += $s->QTY;
                                $s->QTY = 0;
                            }
                        }
                    }
                    unset($a);
                }
                unset($s);
            }
        }

        $finalOutstanding = [];

        if ($isAlreadyCalculated) {
            if ($anotherRequirement->contains('FLAGJOBNO', $JobData->SWMP_JOBNO)) {
                $_finaloutstanding = $anotherRequirement->where('FLAGJOBNO', $JobData->SWMP_JOBNO)->values();

                foreach ($_finaloutstanding as $r) {
                    $_ostQty = $r->REQQT - $r->FILLQT;
                    if ($_ostQty > 0) {
                        $finalOutstanding[] = [
                            'partCode' => $r->MBOM_ITMCD,
                            'outstandingQty' => $r->REQQT - $r->FILLQT,
                        ];
                    }
                }
            }
        } else {
            foreach ($suppliedMaterial as &$s) {
                foreach ($requirement as &$a) {
                    if ($s->ITMCD == $a->MBOM_ITMCD) {
                        if ($s->QTY == 0) {
                            break;
                        }

                        if ($a->REQQT == $a->FILLQT) {
                            break;
                        }

                        $_req = $a->REQQT - $a->FILLQT;
                        if ($s->QTY >= $_req) {
                            $a->FILLQT += $_req;
                            $s->QTY -= $_req;
                        } else {
                            $a->FILLQT += $s->QTY;
                            $s->QTY = 0;
                        }
                    }
                }
                unset($a);
            }
            unset($s);

            foreach ($requirement as $r) {
                $_ostQty = $r->REQQT - $r->FILLQT;
                if ($_ostQty > 0) {
                    $finalOutstanding[] = [
                        'partCode' => $r->MBOM_ITMCD,
                        'outstandingQty' => $r->REQQT - $r->FILLQT,
                    ];
                }
            }
        }


        $status = $finalOutstanding ? ['code' => false, 'message' => 'Supply is not enough', 'job' => $JobData->SWMP_JOBNO] :
            ['code' => true, 'message' => 'OK', 'job' => $JobData->SWMP_JOBNO];

        if ($request->outputType == 'spreadsheet') {
            $_t_requirement = json_decode(json_encode($requirement), true);
            $_t_suppliedMaterial = json_decode(json_encode($suppliedMaterial), true);
            $_t_JobOutput = json_decode(json_encode($JobOutput), true);
            $_t_anotherRequirement = json_decode(json_encode($anotherRequirement), true);

            $spreadSheet = new Spreadsheet();
            $sheet = $spreadSheet->getActiveSheet();
            $sheet->setTitle('requirement');

            $sheet->fromArray(array_keys($_t_requirement[0]), null, 'A1');
            $sheet->fromArray($_t_requirement, null, 'A2');


            $sheet = $spreadSheet->createSheet();
            $sheet->setTitle('supplied');
            $sheet->fromArray(array_keys($_t_suppliedMaterial[0]), null, 'A1');
            $sheet->fromArray($_t_suppliedMaterial, null, 'A2');

            $sheet = $spreadSheet->createSheet();
            $sheet->setTitle('JobOutput');
            $sheet->fromArray(array_keys($_t_JobOutput[0]), null, 'A1');
            $sheet->fromArray($_t_JobOutput, null, 'A2');

            $sheet = $spreadSheet->createSheet();
            $sheet->setTitle('anotherRequirement');
            $sheet->fromArray(array_keys($_t_anotherRequirement[0]), null, 'A1');
            $sheet->fromArray($_t_anotherRequirement, null, 'A2');

            $stringjudul = "Supply Status  $JobData->SWMP_JOBNO " . date('Y-m-d');
            $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
            $filename = $stringjudul;
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
            header('Cache-Control: max-age=0');
            header('Access-Control-Allow-Origin: *');
            $writer->save('php://output');
        } else {
            return [
                'status' => $status,
                'data' => $finalOutstanding
            ];
        }
    }
}
