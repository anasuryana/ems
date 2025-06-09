<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ProductionController extends Controller
{
    protected $historySplitLabel = [];

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function supplyStatus(Request $request)
    {
        Logger('Mulai kalkulasi');
        $year = date('y');
        $jobWithoutYear = strtoupper($request->doc . '-' . $request->itemCode);
        $job = $year . '-' . $jobWithoutYear;

        $isAlreadyCalculated  = false;

        $JobData = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
            ->where('SWMP_JOBNO', 'like',  $job . '%')
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

        // get process context
        $processContext = DB::connection('sqlsrv_wms')->table('XPPSN1')
            ->where('PPSN1_WONO', $JobData->SWMP_JOBNO)
            ->where('PPSN1_LINENO', $request->lineCode)
            ->first([DB::raw("RTRIM(PPSN1_PROCD) PPSN1_PROCD")]);
        if (!$processContext) {
            $processContext = DB::connection('sqlsrv_wms')->table('XPPSN1')
                ->where('PPSN1_WONO', $JobData->SWMP_JOBNO)
                ->where('PPSN1_LINENO', 'like', '%' . substr($request->lineCode, -1) . '%')
                ->first([DB::raw("RTRIM(PPSN1_PROCD) PPSN1_PROCD")]);
        }
        $psnContext = DB::connection('sqlsrv_wms')->table('XPPSN1')
            ->where('PPSN1_WONO', $JobData->SWMP_JOBNO)
            ->groupBy('PPSN1_PSNNO')
            ->get([DB::raw("RTRIM(PPSN1_PSNNO) PPSN1_PSNNO")])
            ->pluck('PPSN1_PSNNO')->toArray();



        $XWO = DB::connection('sqlsrv_wms')->table('XWO')->where('PDPP_WONO', $JobData->SWMP_JOBNO)
            ->first();

        if ($XWO->PDPP_WORQT < $request->qty) {
            return [
                'status' => ['code' => false, 'message' => 'could not greater than lot size', 'job' => $JobData->SWMP_JOBNO],
                'data' => [],
            ];
        }
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
            ->whereIn('SWPS_PSNNO', $psnContext)
            ->where('SWPS_REMARK', 'OK')
            ->groupBy('SWPS_NITMCD', 'NQTY', 'SWPS_NUNQ', 'SWPS_NLOTNO', 'SWPS_PSNNO')
            ->select(
                DB::raw('RTRIM(SWPS_NITMCD) ITMCD'),
                DB::raw('NQTY QTY'),
                DB::raw('RTRIM(SWPS_NLOTNO) LOTNO'),
                DB::raw('RTRIM(SWPS_NUNQ) UNQ'),
                DB::raw('NQTY BAKQTY'),
                DB::raw('SWPS_PSNNO PSNNO'),
                DB::raw('MIN(SWPS_LUPDT) LUPDT'),
            );

        $_suppliedMaterial = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
            ->whereIn('SWMP_PSNNO', $psnContext)
            ->where('SWMP_REMARK', 'OK')
            ->groupBy('SWMP_ITMCD', 'SWMP_QTY', 'SWMP_UNQ', 'SWMP_LOTNO', 'SWMP_PSNNO')
            ->select(
                DB::raw('RTRIM(SWMP_ITMCD) ITMCD'),
                DB::raw('SWMP_QTY QTY'),
                DB::raw('RTRIM(SWMP_LOTNO) LOTNO'),
                DB::raw('RTRIM(SWMP_UNQ) UNQ'),
                DB::raw('SWMP_QTY BAKQTY'),
                DB::raw('SWMP_PSNNO PSNNO'),
                DB::raw('MIN(SWMP_LUPDT) LUPDT'),
            );


        $xsuppliedMaterial = DB::connection('sqlsrv_wms')->query()
            ->fromSub($_suppliedMaterial, 'v1')
            ->union($__suppliedMaterial)
            ->groupBy('ITMCD', 'QTY', 'LOTNO', 'UNQ', 'BAKQTY', 'PSNNO')
            ->select('ITMCD', 'QTY', 'LOTNO', 'UNQ', 'BAKQTY', 'PSNNO', DB::raw("MIN(LUPDT) LUPDT"));

        $suppliedMaterial = DB::connection('sqlsrv_wms')->query()
            ->fromSub($xsuppliedMaterial, 'v1')
            ->groupBy('ITMCD', 'QTY', 'LOTNO', 'UNQ', 'BAKQTY', 'PSNNO')
            ->select('ITMCD', 'QTY', 'LOTNO', 'UNQ', 'BAKQTY', 'PSNNO', DB::raw("MIN(LUPDT) LUPDT"))
            ->orderBy(DB::raw("MIN(LUPDT)"))
            ->get();

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
                $_query =  DB::connection('sqlsrv_wms')->table('WMS_CLS_JOB')
                    ->leftJoin('XWO', 'CLS_JOBNO', '=', 'PDPP_WONO')
                    ->whereIn('CLS_JOBNO', $uniqueJobList)
                    ->groupBy('CLS_JOBNO', 'CLS_PROCD', 'PDPP_BOMRV')
                    ->orderBy(DB::raw('MIN(CLS_LUPDT)'));
                if ($processContext) {
                    $_query->where('CLS_PROCD', $processContext->PPSN1_PROCD);
                }
                $JobOutput = $_query->get([
                    DB::raw('UPPER(CLS_JOBNO) CLS_JOBNO'),
                    DB::raw("RTRIM(CLS_PROCD) CLS_PROCD"),
                    DB::raw("RTRIM(MAX(CLS_MDLCD)) CLS_MDLCD"),
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
                foreach ($anotherRequirement as &$a) {
                    foreach ($suppliedMaterial as &$s) {
                        if ($s->ITMCD == $a->MBOM_ITMCD && $s->PSNNO == $a->PSNNO && $s->QTY > 0) {

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
                    unset($s);
                }
                unset($a);
            }
        }

        $finalOutstanding = [];
        if ($isAlreadyCalculated) {
            foreach ($anotherRequirement as $r) {
                $_ostQty = $r->REQQT - $r->FILLQT;
                if ($_ostQty > 0) {
                    $finalOutstanding[] = [
                        'partCode' => $r->MBOM_ITMCD,
                        'outstandingQty' => $r->REQQT - $r->FILLQT,
                    ];
                }
            }
        } else {
            foreach ($requirement as &$a) {
                foreach ($suppliedMaterial as &$s) {
                    if ($s->ITMCD == $a->MBOM_ITMCD && $s->QTY > 0) {
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
                unset($s);
            }
            unset($a);

            foreach ($requirement as $r) {
                $_ostQty = $r->REQQT - $r->FILLQT;
                if ($_ostQty > 0) {
                    if ($processContext) {
                        if ($processContext->PPSN1_PROCD == $r->MBOM_PROCD) {
                            $finalOutstanding[] = [
                                'partCode' => $r->MBOM_ITMCD,
                                'outstandingQty' => $r->REQQT - $r->FILLQT,
                            ];
                        }
                    } else {
                        $finalOutstanding[] = [
                            'partCode' => $r->MBOM_ITMCD,
                            'outstandingQty' => $r->REQQT - $r->FILLQT,
                        ];
                    }
                }
            }
        }

        $isJobContextCalculationOK = true;

        if ($finalOutstanding) {
            // find alternative part
            if ($isAlreadyCalculated) {
                // find on ENG BOMSTX
                foreach ($finalOutstanding as $r) {
                    $ENGBOM = DB::connection('sqlsrv_wms')->table('ENG_BOMSTX')
                        ->select('MAIN_PART_CODE', 'EPSON_ORG_PART', 'SUB', 'SUB1')
                        ->where('MODEL_CODE', $request->itemCode)
                        ->where('MAIN_PART_CODE', $r['partCode'])
                        ->get();
                    foreach ($anotherRequirement as &$a) {
                        foreach ($ENGBOM as $alt) {

                            if ($a->MBOM_ITMCD == $alt->MAIN_PART_CODE) {
                                foreach ($suppliedMaterial as &$s) {
                                    if ($s->ITMCD == $alt->SUB && $s->PSNNO == $a->PSNNO && $s->QTY > 0) {
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
                                unset($s);
                            }
                        }
                    }
                    unset($a);
                }

                // reset what part still not filled
                $finalOutstanding = [];
                foreach ($anotherRequirement as $r) {
                    $_ostQty = $r->REQQT - $r->FILLQT;
                    if ($_ostQty > 0) {
                        $finalOutstanding[] = [
                            'partCode' => $r->MBOM_ITMCD,
                            'outstandingQty' => $r->REQQT - $r->FILLQT,
                        ];
                    }
                }

                if ($finalOutstanding) {
                    // find on main common part
                    foreach ($finalOutstanding as $r) {
                        $commonPart = DB::connection('sqlsrv_wms')->table('ENG_COMMPRT_LST')
                            ->select('ITMCDPRI', 'ITMCDALT')
                            ->where('ITMCDPRI', $r['partCode'])
                            ->get();
                        foreach ($anotherRequirement as &$a) {
                            foreach ($commonPart as $alt) {
                                if ($a->MBOM_ITMCD == $alt->ITMCDPRI) {
                                    foreach ($suppliedMaterial as &$s) {
                                        if ($s->ITMCD == $alt->ITMCDALT && $s->PSNNO == $a->PSNNO && $s->QTY > 0) {
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
                                    unset($s);
                                }
                            }
                        }
                        unset($a);
                    }

                    // reset what part still not filled
                    $finalOutstanding = [];
                    foreach ($anotherRequirement as $r) {
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

                $__isfound = false;
                foreach ($finalOutstanding as $r) {
                    $ENGBOM = DB::connection('sqlsrv_wms')->table('ENG_BOMSTX')
                        ->select('MAIN_PART_CODE', 'EPSON_ORG_PART', 'SUB', 'SUB1')
                        ->where('MODEL_CODE', $request->itemCode)
                        ->where('MAIN_PART_CODE', $r['partCode'])
                        ->first();
                    if ($ENGBOM) {
                        foreach ($requirement as &$a) {
                            if ($a->MBOM_ITMCD == $ENGBOM->MAIN_PART_CODE) {
                                foreach ($suppliedMaterial as &$s) {

                                    if ($s->ITMCD == $ENGBOM->SUB && $s->QTY > 0) {
                                        $__isfound = true;
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
                                unset($s);
                            }
                        }
                        unset($a);
                    }
                }

                // reset final outstanding when CLS Empty
                if ($__isfound) {
                    $finalOutstanding = [];
                    foreach ($requirement as $r) {
                        $_ostQty = $r->REQQT - $r->FILLQT;
                        if ($_ostQty > 0) {
                            if ($processContext) {
                                if ($processContext->PPSN1_PROCD == $r->MBOM_PROCD) {
                                    $finalOutstanding[] = [
                                        'partCode' => $r->MBOM_ITMCD,
                                        'outstandingQty' => $r->REQQT - $r->FILLQT,
                                    ];
                                }
                            } else {
                                $finalOutstanding[] = [
                                    'partCode' => $r->MBOM_ITMCD,
                                    'outstandingQty' => $r->REQQT - $r->FILLQT,
                                ];
                            }
                        }
                    }
                }

                $__isfound = false;

                if ($finalOutstanding) {
                    // find on main common part
                    foreach ($finalOutstanding as $r) {
                        $commonPart = DB::connection('sqlsrv_wms')->table('ENG_COMMPRT_LST')
                            ->select('ITMCDPRI', 'ITMCDALT')
                            ->where('ITMCDPRI', $r['partCode'])
                            ->get();
                        foreach ($requirement as &$a) {
                            foreach ($commonPart as $alt) {
                                if ($a->MBOM_ITMCD == $alt->ITMCDPRI) {
                                    foreach ($suppliedMaterial as &$s) {
                                        if ($s->ITMCD == $alt->ITMCDALT && $s->QTY > 0) {
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
                                    unset($s);
                                }
                            }
                        }
                        unset($a);
                    }

                    // reset final outstanding when CLS Empty
                    if ($__isfound) {
                        $finalOutstanding = [];
                        foreach ($requirement as $r) {
                            $_ostQty = $r->REQQT - $r->FILLQT;
                            if ($_ostQty > 0) {
                                if ($processContext) {
                                    if ($processContext->PPSN1_PROCD == $r->MBOM_PROCD) {
                                        $finalOutstanding[] = [
                                            'partCode' => $r->MBOM_ITMCD,
                                            'outstandingQty' => $r->REQQT - $r->FILLQT,
                                        ];
                                    }
                                } else {
                                    $finalOutstanding[] = [
                                        'partCode' => $r->MBOM_ITMCD,
                                        'outstandingQty' => $r->REQQT - $r->FILLQT,
                                    ];
                                }
                            }
                        }
                    }
                }
            }


            if ($finalOutstanding) {
                foreach ($anotherRequirement as $r) {
                    if ($r->REQQT - $r->FILLQT > 0 && str_contains($r->FLAGJOBNO, $jobWithoutYear)) {
                        $isJobContextCalculationOK = false;
                        break;
                    }
                }
                if ($isJobContextCalculationOK) {
                    $status = ['code' => true, 'message' => 'OK', 'job' => $JobData->SWMP_JOBNO, 'flag' => $isAlreadyCalculated, 'remark' => 'sibling job calculation might be need to check more detail'];
                } else {
                    $status = ['code' => false, 'message' => 'Supply is not enough', 'job' => $JobData->SWMP_JOBNO, 'flag' => $isAlreadyCalculated];
                }
            } else {
                $status = ['code' => true, 'message' => 'OK', 'job' => $JobData->SWMP_JOBNO];
            }
        } else {
            $status = ['code' => true, 'message' => 'OK', 'job' => $JobData->SWMP_JOBNO];
        }

        if ($request->outputType == 'spreadsheet') {
            $_t_requirement = json_decode(json_encode($requirement), true);
            $_t_suppliedMaterial = json_decode(json_encode($suppliedMaterial), true);
            $_t_JobOutput = json_decode(json_encode($JobOutput), true);
            $_t_anotherRequirement = json_decode(json_encode($anotherRequirement), true);

            $stringValueBinder = new \PhpOffice\PhpSpreadsheet\Cell\StringValueBinder();
            $stringValueBinder->setNumericConversion(false);
            \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder($stringValueBinder);

            $spreadSheet = new Spreadsheet();

            $sheet = $spreadSheet->getActiveSheet();
            $sheet->setTitle('requirement');

            $sheet->fromArray(array_keys($_t_requirement[0]), null, 'A1');
            $sheet->fromArray($_t_requirement, null, 'A2');
            foreach (range('A', 'T') as $v) {
                $sheet->getColumnDimension($v)->setAutoSize(true);
            }


            $sheet = $spreadSheet->createSheet();
            $sheet->setTitle('supplied');
            $sheet->fromArray(array_keys($_t_suppliedMaterial[0]), null, 'A1');
            $sheet->fromArray($_t_suppliedMaterial, null, 'A2');
            foreach (range('A', 'T') as $v) {
                $sheet->getColumnDimension($v)->setAutoSize(true);
            }

            $sheet = $spreadSheet->createSheet();
            $sheet->setTitle('JobOutput');
            if ($_t_JobOutput) {
                $sheet->fromArray(array_keys($_t_JobOutput[0]), null, 'A1');
                $sheet->fromArray($_t_JobOutput, null, 'A2');
                foreach (range('A', 'T') as $v) {
                    $sheet->getColumnDimension($v)->setAutoSize(true);
                }
            }

            $sheet = $spreadSheet->createSheet();
            $sheet->setTitle('anotherRequirement');
            if ($_t_anotherRequirement) {
                $sheet->fromArray(array_keys($_t_anotherRequirement[0]), null, 'A1');
                $sheet->fromArray($_t_anotherRequirement, null, 'A2');
                foreach (range('A', 'T') as $v) {
                    $sheet->getColumnDimension($v)->setAutoSize(true);
                }
            }

            $stringjudul = "Supply Status  $JobData->SWMP_JOBNO " . date('Y-m-d');
            $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
            $filename = $stringjudul;
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
            header('Cache-Control: max-age=0');
            header('Access-Control-Allow-Origin: *');
            $writer->save('php://output');
        } else {
            if ($request->isFromWeb) {
                return [
                    'status' => $status,
                    'data' => !$isJobContextCalculationOK ? $finalOutstanding : [],
                    'dataSupplied' =>  $suppliedMaterial,
                    'dataJob' =>  $JobOutput,
                    'dataRequirement' => $anotherRequirement
                ];
            } else {
                return [
                    'status' => $status,
                    'data' => !$isJobContextCalculationOK ? $finalOutstanding : [],
                ];
            }
        }
    }

    function getActiveJob(Request $request)
    {
        $data = DB::connection('sqlsrv_wms')->table('WMS_TLWS_TBL')
            ->where('TLWS_STSFG', 'ACT')
            ->where('TLWS_WONO', $request->workorder)
            ->first(
                [
                    DB::raw('RTRIM(TLWS_JOBNO) TLWS_JOBNO'),
                    'TLWS_PROCD',
                    'TLWS_LINENO'
                ]
            );

        if (!$data) {
            return ['code' => false, 'message' => 'There is no active job'];
        }

        return [
            'code' => true,
            'message' => 'Go ahead',
            'data' => [
                'job' => $data->TLWS_JOBNO,
                'processCode' => $data->TLWS_PROCD,
                'lineCode' => $data->TLWS_LINENO
            ]
        ];
    }

    function saveSensorOutput(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'line' => 'required'
        ], [
            'line.required' => ':attribute is required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $data = DB::connection('sqlsrv_wms')->table('WMS_TLWS_TBL')
            ->where('TLWS_STSFG', 'ACT')
            ->where('TLWS_LINENO', $request->line)
            ->first(
                [
                    DB::raw('RTRIM(TLWS_JOBNO) TLWS_JOBNO'),
                    'TLWS_PROCD',
                    'TLWS_LINENO'
                ]
            );

        if (!$data) {
            $data = DB::connection('sqlsrv_wms')->table('WMS_TLWS_TBL')
                ->where('TLWS_STSFG', 'ACT')
                ->where('TLWS_LINENO', 'SMT-' . $request->line)
                ->first(
                    [
                        DB::raw('RTRIM(TLWS_JOBNO) TLWS_JOBNO'),
                        'TLWS_PROCD',
                        'TLWS_LINENO'
                    ]
                );
            if (!$data) {
                return ['code' => false, 'message' => 'There is no active job'];
            }
        }

        $affectedRows = DB::connection('sqlsrv_wms')->table('keikaku_outputs')->insert([
            'created_at' => date('Y-m-d H:i:s'),
            'production_date' => date('Y-m-d'),
            'running_at' => date('Y-m-d H:i:s'),
            'wo_code' => $data->TLWS_JOBNO,
            'line_code' => str_replace('SMT-', '', $data->TLWS_LINENO),
            'process_code' => $data->TLWS_PROCD,
            'ok_qty' => 1,
            'created_by' => 'sensor',
        ]);

        return $affectedRows ? ['message' => 'Recorded successfully'] : ['message' => 'Failed, please try again'];
    }

    function getActivatedTLWS(Request $request)
    {
        $data = DB::connection('sqlsrv_wms')->table('WMS_TLWS_TBL')->where('TLWS_STSFG', 'ACT')
            ->where('TLWS_JOBNO', 'LIKE', '%' . $request->doc . '%')
            ->where('TLWS_MDLCD', $request->itemCode)
            ->get(['TLWS_SPID', 'TLWS_MDLCD', DB::raw("RTRIM(TLWS_JOBNO) TLWS_JOBNO"), 'TLWS_PROCD', 'TLWS_LUPDT', 'TLWS_LUPBY']);
        return ['data' => $data];
    }

    function setCompletionTLWS(Request $request)
    {
        if (in_array($request->groupId, ['MSPV', 'MPRC', 'ADMIN'])) {
            $affectedRows = DB::connection('sqlsrv_wms')->table('WMS_TLWS_TBL')->where('TLWS_STSFG', 'ACT')
                ->where('TLWS_SPID', $request->doc)
                ->where('TLWS_MDLCD', $request->itemCode)
                ->update(['TLWS_STSFG' => 'COM']);
        } else {
            return response()->json(['message' => 'You have read-only access'], 403);
        }

        return [
            'message' => $affectedRows ? 'Set completion successfully' : 'nothing updated',
        ];
    }

    function getSupplyStatusByPSN(Request $request)
    {
        //validator
        $validator = Validator::make(
            $request->json()->all(),
            [
                'doc' => 'required',
                'partCode' => 'required',
                'detail' => 'required|array',
                'detail.*.job' => 'required',
            ],
            [
                'doc.required' => ':attribute is required',
                'partCode.required' => ':attribute is required',
                'detail.required' => ':attribute is required',
                'detail.array' => ':attribute should be array',
                'detail.*.job.required' => ':attribute is required',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        $data = $request->json()->all();

        //supplied & scanned item
        // get balance of Supplied Material
        $__suppliedMaterial = DB::connection('sqlsrv_wms')->table('WMS_SWPS_HIS')
            ->whereIn('SWPS_PSNNO', [$data['doc']])
            ->where('SWPS_REMARK', 'OK')
            ->where('SWPS_NITMCD', $data['partCode'])
            ->groupBy('SWPS_NITMCD', 'NQTY', 'SWPS_NUNQ', 'SWPS_NLOTNO')
            ->select(
                DB::raw('RTRIM(SWPS_NITMCD) ITMCD'),
                DB::raw('NQTY QTY'),
                DB::raw('RTRIM(SWPS_NLOTNO) LOTNO'),
                DB::raw('RTRIM(SWPS_NUNQ) UNQ'),
                DB::raw('NQTY BAKQTY'),
            );

        $_suppliedMaterial = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
            ->whereIn('SWMP_PSNNO', [$data['doc']])
            ->where('SWMP_REMARK', 'OK')
            ->where('SWMP_ITMCD', $data['partCode'])
            ->groupBy('SWMP_ITMCD', 'SWMP_QTY', 'SWMP_UNQ', 'SWMP_LOTNO')
            ->select(
                DB::raw('RTRIM(SWMP_ITMCD) ITMCD'),
                DB::raw('SWMP_QTY QTY'),
                DB::raw('RTRIM(SWMP_LOTNO) LOTNO'),
                DB::raw('RTRIM(SWMP_UNQ) UNQ'),
                DB::raw('SWMP_QTY BAKQTY'),
            );

        $scannedLabels = DB::connection('sqlsrv_wms')->query()
            ->fromSub($_suppliedMaterial, 'v1')
            ->union($__suppliedMaterial)->get();

        $scannedLabelDetails = $scannedLabelID = $processRequest = [];

        foreach ($data['detail'] as $r) {
            if (!in_array($r['process'], $processRequest)) {
                $processRequest[] = $r['process'];
            }
        }
        //supplied but not scanned

        // alternative part

        // get history running time
        $uniqueJobInput = [];
        foreach ($data['detail'] as $r) {
            if (!in_array($r['job'], $uniqueJobInput)) {
                $uniqueJobInput[] = $r['job'];
            }
        }

        $historyClosingJob = DB::connection('sqlsrv_wms')->table('WMS_CLS_JOB')
            ->whereIn('CLS_JOBNO', $uniqueJobInput)
            ->whereIn('CLS_PROCD', $processRequest)
            ->orderBy('CLS_LUPDT')
            ->get(['CLS_SPID', 'CLS_MDLCD', 'CLS_BOMRV', 'CLS_JOBNO', 'CLS_QTY', 'CLS_LUPDT', 'CLS_LINENO', DB::raw("0 CLS_QTY_PLOT"), 'CLS_PROCD']);

        $xwo = DB::connection('sqlsrv_wms')->table('XWO')
            ->whereIn('PDPP_WONO', $uniqueJobInput)
            ->get([
                'PDPP_WONO',
                'PDPP_MDLCD',
                'PDPP_BOMRV',
                DB::raw("0 CLS_QTY_PLOT")
            ]);

        // bom
        $anotherRequirement = new Collection();
        foreach ($data['detail'] as $r) {
            $_MDLCD = $_BOMRV =  $_LUPDT = $_LINE = '';
            $_countClosingRowPerJob = 0;
            $_totalClosingQtyPerJob = [];
            $_LUPDT_CLOSING_ = [];
            $_LUPDT_CLOSING = date('Y-m-d H:i:s');
            foreach ($historyClosingJob as $h) {
                if ($r['job'] == $h->CLS_JOBNO && $r['process'] == $h->CLS_PROCD) {
                    $_MDLCD = $h->CLS_MDLCD;
                    $_BOMRV = $h->CLS_BOMRV;
                    $_LUPDT .= $h->CLS_LUPDT . ',';
                    $_LINE .= $h->CLS_LINENO . ',';
                    $_totalClosingQtyPerJob[] = $h->CLS_QTY;
                    $_countClosingRowPerJob++;
                    $_LUPDT_CLOSING = $h->CLS_LUPDT;
                    $_LUPDT_CLOSING_[] = $h->CLS_LUPDT;
                }
            }
            if (empty($_MDLCD)) {
                foreach ($xwo as $h) {
                    if ($r['job'] == $h->PDPP_WONO) {
                        $_MDLCD = $h->PDPP_MDLCD;
                        $_BOMRV = $h->PDPP_BOMRV;
                        $_LUPDT .= ',';
                        $_LINE .=  ',';
                    }
                }
            }
            if (empty($_MDLCD)) {
                continue;
            }

            if ($_countClosingRowPerJob > 1) {

                for ($_i = 0; $_i < $_countClosingRowPerJob; $_i++) {
                    $_requirement = DB::connection('sqlsrv_wms')->table('VCIMS_MBOM_TBL')
                        ->where('MBOM_MDLCD', $_MDLCD)
                        ->where('MBOM_BOMRV', $_BOMRV)
                        ->where('MBOM_ITMCD', $data['partCode'])
                        ->whereIn('MBOM_PROCD', $processRequest)
                        ->groupBy('MBOM_ITMCD', 'MBOM_SPART', 'MBOM_PROCD')
                        ->get([
                            DB::raw("'" . $r['job'] . "' FLAGJOBNO"),
                            DB::raw("'" . $_LUPDT . "' LUPDT"),
                            DB::raw("'" . $_LINE . "' LINEPROD"),
                            DB::raw('RTRIM(MBOM_ITMCD) MBOM_ITMCD'),
                            DB::raw('RTRIM(MBOM_SPART) MBOM_SPART'),
                            DB::raw('RTRIM(MBOM_PROCD) MBOM_PROCD'),
                            DB::raw('SUM(MBOM_QTY) PER'),
                            DB::raw('SUM(MBOM_QTY)*' . (int)$_totalClosingQtyPerJob[$_i] . ' REQQT'),
                            DB::raw('0 FILLQT'),
                            DB::raw("'" . $_LUPDT_CLOSING_[$_i] . "' LUPDTR")
                        ]);
                    $anotherRequirement = $anotherRequirement->merge($_requirement);
                }
            } else {

                $_requirement = DB::connection('sqlsrv_wms')->table('VCIMS_MBOM_TBL')
                    ->where('MBOM_MDLCD', $_MDLCD)
                    ->where('MBOM_BOMRV', $_BOMRV)
                    ->where('MBOM_ITMCD', $data['partCode'])
                    ->whereIn('MBOM_PROCD', $processRequest)
                    ->groupBy('MBOM_ITMCD', 'MBOM_SPART', 'MBOM_PROCD')
                    ->get([
                        DB::raw("'" . $r['job'] . "' FLAGJOBNO"),
                        DB::raw("'" . $_LUPDT . "' LUPDT"),
                        DB::raw("'" . $_LINE . "' LINEPROD"),
                        DB::raw('RTRIM(MBOM_ITMCD) MBOM_ITMCD'),
                        DB::raw('RTRIM(MBOM_SPART) MBOM_SPART'),
                        DB::raw('RTRIM(MBOM_PROCD) MBOM_PROCD'),
                        DB::raw('SUM(MBOM_QTY) PER'),
                        DB::raw('SUM(MBOM_QTY)*' . (int)$r['qty'] . ' REQQT'),
                        DB::raw('0 FILLQT'),
                        DB::raw("'" . $_LUPDT_CLOSING . "' LUPDTR")
                    ]);
                $anotherRequirement = $anotherRequirement->merge($_requirement);
            }
        }

        $anotherRequirement = $anotherRequirement->sortBy('LUPDTR');

        // calculateprocesS
        foreach ($anotherRequirement as $h) {
            $__suppliedMaterial = DB::connection('sqlsrv_wms')->table('WMS_SWPS_HIS')
                ->whereIn('SWPS_PSNNO', [$data['doc']])
                ->where('SWPS_REMARK', 'OK')
                ->where('SWPS_NITMCD', $data['partCode'])
                ->where('SWPS_JOBNO', $h->FLAGJOBNO)
                ->groupBy('SWPS_NITMCD', 'NQTY', 'SWPS_NUNQ', 'SWPS_NLOTNO')
                ->select(
                    DB::raw('RTRIM(SWPS_NITMCD) ITMCD'),
                    DB::raw('NQTY QTY'),
                    DB::raw('RTRIM(SWPS_NLOTNO) LOTNO'),
                    DB::raw('RTRIM(SWPS_NUNQ) UNQ'),
                    DB::raw('NQTY BAKQTY'),
                    DB::raw('MIN(SWPS_LUPDT) LUPDT'),
                );

            $_suppliedMaterial = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
                ->whereIn('SWMP_PSNNO', [$data['doc']])
                ->where('SWMP_REMARK', 'OK')
                ->where('SWMP_ITMCD', $data['partCode'])
                ->where('SWMP_JOBNO', $h->FLAGJOBNO)
                ->groupBy('SWMP_ITMCD', 'SWMP_QTY', 'SWMP_UNQ', 'SWMP_LOTNO')
                ->select(
                    DB::raw('RTRIM(SWMP_ITMCD) ITMCD'),
                    DB::raw('SWMP_QTY QTY'),
                    DB::raw('RTRIM(SWMP_LOTNO) LOTNO'),
                    DB::raw('RTRIM(SWMP_UNQ) UNQ'),
                    DB::raw('SWMP_QTY BAKQTY'),
                    DB::raw('MIN(SWMP_LUPDT) LUPDT'),
                );

            $__labelRelatedJOB = DB::connection('sqlsrv_wms')->query()
                ->fromSub($_suppliedMaterial, 'v1')
                ->union($__suppliedMaterial)
                ->orderBy('LUPDT')
                ->get();
            foreach ($__labelRelatedJOB as $l) {
                $reqBal = $h->REQQT - $h->FILLQT;
                if ($reqBal > 0) {
                    foreach ($scannedLabels as &$lm) {
                        if ($l->UNQ == $lm->UNQ) {
                            if ($lm->QTY > 0) {
                                $_qtyContextUseLabel = $reqBal;
                                if ($lm->QTY >= $reqBal) {
                                    $h->FILLQT += $reqBal;
                                    $lm->QTY -= $reqBal;
                                } else {
                                    $_qtyContextUseLabel = $lm->QTY;
                                    $h->FILLQT += $lm->QTY;
                                    $lm->QTY = 0;
                                }
                                $scannedLabelDetails[] = [
                                    'ITMCD' => $lm->ITMCD,
                                    'QTY' => $l->QTY,
                                    'UNQ' => $lm->UNQ,
                                    'LINE' => '',
                                    'CLS_LUPDT' => '',
                                    'CALCULATE_USE' => $_qtyContextUseLabel,
                                    'BALANCE_LABEL' => $lm->QTY,
                                    'RESULT' => '',
                                ];
                                if (!in_array($lm->UNQ, $scannedLabelID)) {
                                    $scannedLabelID[] = $lm->UNQ;
                                }
                            }
                            break;
                        }
                    }
                    unset($lm);
                } else {
                    break;
                }
            }
        }

        $psnO = DB::connection('sqlsrv_wms')->table('SPLSCN_TBL')->where('SPLSCN_DOC', $data['doc'])
            ->where('SPLSCN_ITMCD', $data['partCode'])
            ->get(['SPLSCN_UNQCODE']);
        $neccessaryCode = [];
        foreach ($psnO as $o) {
            $this->_findChild($o->SPLSCN_UNQCODE, $data['partCode']);

            foreach ($this->historySplitLabel as $r) {
                if ($r['status'] != '') {
                    if (!str_contains($r['status'], '✔')) {
                        if (!in_array($r['code'], $neccessaryCode)) {
                            $neccessaryCode[] = $r['code'];
                        }
                    }
                }
            }
        }

        // splitted && necessary
        $neccessaryCodeO = DB::connection('sqlsrv_wms')->table('raw_material_labels')
            ->whereIn('code', $neccessaryCode)
            ->get([
                DB::raw('item_code ITMCD'),
                DB::raw('quantity QTY'),
                DB::raw('code UNQ'),
                DB::raw("'' LINE"),
                DB::raw("NULL CLS_LUPDT"),
                DB::raw("NULL CALCULATE_USE"),
                DB::raw("NULL BALANCE_LABEL"),
                DB::raw("NULL RESULT"),
            ]);

        //  not splitted && not scanned

        // this variable handle on-progres scanning (not closed yet)
        $suppliedMaterial1 = DB::connection('sqlsrv_wms')->table('WMS_SWPS_HIS')
            ->whereIn('SWPS_PSNNO', [$data['doc']])
            ->where('SWPS_REMARK', 'OK')
            ->where('SWPS_NITMCD', $data['partCode'])
            ->whereNotIn('SWPS_NUNQ', $scannedLabelID)
            ->groupBy('SWPS_NITMCD', 'NQTY', 'SWPS_NUNQ', 'SWPS_NLOTNO')
            ->select(
                DB::raw('RTRIM(SWPS_NITMCD) ITMCD'),
                DB::raw('NQTY QTY'),
                DB::raw('RTRIM(SWPS_NLOTNO) LOTNO'),
                DB::raw('RTRIM(SWPS_NUNQ) UNQX'),
                DB::raw('NQTY BAKQTY'),
                DB::raw('MIN(SWPS_LUPDT) LUPDT'),
                DB::raw('MIN(SWPS_LINENO) MINLINE'),
            );

        // this variable handle on-progres scanning (not closed yet)
        $suppliedMaterial2 = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
            ->whereIn('SWMP_PSNNO', [$data['doc']])
            ->where('SWMP_REMARK', 'OK')
            ->where('SWMP_ITMCD', $data['partCode'])
            ->whereNotIn('SWMP_UNQ', $scannedLabelID)
            ->groupBy('SWMP_ITMCD', 'SWMP_QTY', 'SWMP_UNQ', 'SWMP_LOTNO')
            ->select(
                DB::raw('RTRIM(SWMP_ITMCD) ITMCD'),
                DB::raw('SWMP_QTY QTY'),
                DB::raw('RTRIM(SWMP_LOTNO) LOTNO'),
                DB::raw('RTRIM(SWMP_UNQ) UNQX'),
                DB::raw('SWMP_QTY BAKQTY'),
                DB::raw('MIN(SWMP_LUPDT) LUPDT'),
                DB::raw('MIN(SWMP_LINENO) MINLINE'),
            );

        $labelRelatedJOB = DB::connection('sqlsrv_wms')->query()
            ->fromSub($suppliedMaterial1, 'v1')
            ->union($suppliedMaterial2);

        $neccessaryCodeFreshO = DB::connection('sqlsrv_wms')
            ->table('raw_material_labels')
            ->leftJoin('SPLSCN_TBL', 'SPLSCN_TBL.SPLSCN_UNQCODE', '=', 'raw_material_labels.code')
            ->leftJoinSub($labelRelatedJOB, 'vx', 'UNQX', '=', 'code')
            ->where('SPLSCN_TBL.SPLSCN_DOC', $data['doc'])
            ->whereNull('splitted')
            ->whereNotIn('code', $scannedLabelID)
            ->where('raw_material_labels.item_code', $data['partCode'])
            ->get([
                DB::raw('item_code ITMCD'),
                DB::raw('quantity QTY'),
                DB::raw('code UNQ'),
                DB::raw("'' LINE"),
                DB::raw("NULL CLS_LUPDT"),
                DB::raw("NULL CALCULATE_USE"),
                DB::raw("NULL BALANCE_LABEL"),
                DB::raw("NULL RESULT"),
                DB::raw("UNQX"),
            ]);

        $scannedLabelID = array_merge($scannedLabelID, $neccessaryCodeFreshO->unique('UNQ')->pluck('UNQ')->toArray());

        // for get line information
        $suppliedMaterial1 = DB::connection('sqlsrv_wms')->table('WMS_SWPS_HIS')
            ->whereIn('SWPS_PSNNO', [$data['doc']])
            ->where('SWPS_REMARK', 'OK')
            ->where('SWPS_NITMCD', $data['partCode'])
            ->whereIn('SWPS_NUNQ', $scannedLabelID)
            ->groupBy('SWPS_NUNQ', 'SWPS_LINENO', 'SWPS_LUPDT')
            ->select(
                DB::raw('RTRIM(SWPS_NUNQ) UNQX'),
                DB::raw('SWPS_LUPDT LUPDT'),
                DB::raw('SWPS_LINENO MINLINE'),
            );

        // this variable handle on-progres scanning (not closed yet)
        $suppliedMaterial2 = DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
            ->whereIn('SWMP_PSNNO', [$data['doc']])
            ->where('SWMP_REMARK', 'OK')
            ->where('SWMP_ITMCD', $data['partCode'])
            ->whereIn('SWMP_UNQ', $scannedLabelID)
            ->groupBy('SWMP_UNQ', 'SWMP_LINENO', 'SWMP_LUPDT')
            ->select(
                DB::raw('RTRIM(SWMP_UNQ) UNQX'),
                DB::raw('SWMP_LUPDT LUPDT'),
                DB::raw('SWMP_LINENO MINLINE'),
            );

        $labelRelatedJOB = DB::connection('sqlsrv_wms')->query()
            ->fromSub($suppliedMaterial1, 'v1')
            ->union($suppliedMaterial2)
            ->orderBy('LUPDT')
            ->get();

        $completedLabel = [];
        foreach ($scannedLabelDetails as &$d) {
            $d['IS_COMPLETED'] = '';

            if ($d['BALANCE_LABEL'] == 0) {
                if (!in_array($d['UNQ'], $completedLabel)) {
                    $completedLabel[] = $d['UNQ'];
                }
            }
            foreach ($labelRelatedJOB as $l) {
                if ($d['UNQ'] == $l->UNQX) {
                    $d['LINE'] .=  $l->MINLINE . ',';
                    $d['CLS_LUPDT'] .=  $l->LUPDT . ',';
                }
            }
        }
        unset($d);
        // end for

        foreach ($scannedLabelDetails as &$d) {
            foreach ($completedLabel as $n) {
                if ($d['UNQ'] == $n) {
                    $d['IS_COMPLETED'] = 1;
                    break;
                }
            }
        }
        unset($d);

        $message = '';


        foreach ($anotherRequirement as $r) {
            $balance = $r->REQQT - $r->FILLQT;
            if ($balance > 0) {
                $message .= '👉 Supply is not enough for ' . $r->FLAGJOBNO . ' Req : ' . (int)$r->REQQT . ', Supplied : ' . (int)$r->FILLQT . ', balance : ' . $balance . ' <br>';
            }
        }
        if (empty($message)) {
            $message = 'OK';
        }

        return [
            'data' => $scannedLabelDetails,
            'dataReff' => $neccessaryCodeO,
            'message' => $message,
            'dataFreshReff' => $neccessaryCodeFreshO
        ];
    }

    function getTreeInside(Request $request)
    {
        $psnO = DB::connection('sqlsrv_wms')->table('SPLSCN_TBL')->where('SPLSCN_DOC', $request->doc)
            ->where('SPLSCN_ITMCD', $request->item_code)
            ->get(['SPLSCN_UNQCODE']);
        $neccessaryCode = [];
        $treeInside = '';

        foreach ($psnO as $o) {
            $treeInside = $this->_findChild($o->SPLSCN_UNQCODE, $request->item_code);
        }
        foreach ($this->historySplitLabel as $r) {
            if ($r['status'] != '') {
                if (!str_contains($r['status'], '✔')) {
                    if (!in_array($r['code'], $neccessaryCode)) {
                        $neccessaryCode[] = $r['code'];
                    }
                }
            }
        }

        $neccessaryCodeO = DB::connection('sqlsrv_wms')->table('raw_material_labels')
            ->whereIn('code', $neccessaryCode)
            ->get([
                DB::raw('item_code ITMCD'),
                DB::raw('quantity QTY'),
                DB::raw('code UNQ'),
                DB::raw("'' LINE"),
                DB::raw("NULL CLS_LUPDT"),
                DB::raw("NULL CALCULATE_USE"),
                DB::raw("NULL BALANCE_LABEL"),
                DB::raw("NULL RESULT"),
            ]);

        return [
            'psnO' => $psnO,
            'data' => $treeInside,
            'historySplitLabel' => $this->historySplitLabel,
            'neccessaryCode' => $neccessaryCode,
            '$neccessaryCodeO' =>  $neccessaryCodeO
        ];
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
                $this->historySplitLabel[] = [
                    'code' => $r->code,
                    'parent_code' => $r->parent_code,
                    'status' => $status,
                ];
                $_treeInside[] = [
                    'code' => $r->code,
                    'parent_code' => $r->parent_code,
                ];
                $_treeInside[] = $this->_findChild($r->code, $item_code);
            }
        }
        return $_treeInside;
    }

    function getActivedJobFromWO(Request $request)
    {
        $data = DB::connection('sqlsrv_wms')->table('WMS_TLWS_TBL')
            ->leftJoin('WMS_SWMP_HIS', 'TLWS_SPID', '=', 'SWMP_SPID')
            ->leftJoin('WMS_CLS_JOB', 'TLWS_SPID', '=', 'CLS_SPID')
            ->leftJoin('XWO', 'TLWS_JOBNO', '=', 'PDPP_WONO')
            ->where('TLWS_STSFG', 'ACT')
            ->where('TLWS_WONO',  $request->doc)
            ->groupBy('TLWS_JOBNO', 'SWMP_CLS', 'CLS_QTY', 'TLWS_SPID')
            ->get([
                'TLWS_SPID',
                DB::raw("RTRIM(TLWS_JOBNO) TLWS_JOBNO"),
                DB::raw("ISNULL(SWMP_CLS,0) SWMP_CLS"),
                DB::raw("ISNULL(CLS_QTY,0) CLS_QTY"),
                DB::raw("MAX(PDPP_WORQT) WOR_QTY"),
            ]);
        return ['data' => $data];
    }

    function adjustQtyCrossAndCompletion(Request $request)
    {
        $isCrossQtyChanged = ($request->cross_qty != $request->cross_qty_before);
        $isCompletionQtyChanged = ($request->completion_qty != $request->completion_qty_before);
        if ($isCrossQtyChanged || $isCompletionQtyChanged) {
            $affectedRows = 0;
            if ($isCrossQtyChanged) {
                $affectedRows += DB::connection('sqlsrv_wms')->table('WMS_SWMP_HIS')
                    ->where('SWMP_SPID', $request->spid)
                    ->where('SWMP_REMARK', 'OK')
                    ->update(['SWMP_CLS' => $request->cross_qty]);
                logger('update cross qty by ' . $request->nik .  ', from ' . $request->cross_qty_before . ' to ' . $request->cross_qty);
            }
            if ($isCompletionQtyChanged) {
                $affectedRows += DB::connection('sqlsrv_wms')->table('WMS_CLS_JOB')
                    ->where('CLS_SPID', $request->spid)
                    ->update([
                        'CLS_QTY' => $request->completion_qty,
                        'CLS_LUPDT' => date('Y-m-d H:i:s'),
                        'CLS_LUPBY' => $request->nik
                    ]);
                logger('update completion qty by ' . $request->nik .  ', from ' . $request->completion_qty_before . ' to ' . $request->completion_qty);
            }
            return ['message' => $affectedRows ? 'Updated successfully' : 'Nothing updated'];
        } else {
            return ['message' => 'Nothing updated.'];
        }
    }
}
