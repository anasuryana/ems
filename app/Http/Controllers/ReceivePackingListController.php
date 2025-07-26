<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ReceivePackingListController extends Controller
{
    function uploadSpreadsheet(Request $request)
    {
        $DONumber = NULL;
        $data = NULL;

        $validator = Validator::make($request->all(), [
            'file_upload.*' => 'required|file|mimes:xlsx,xls|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 406);
        }

        // 2. Cek apakah ada file yang diupload
        if ($request->hasFile('file_upload')) {
            $uploadedFiles = $request->file('file_upload'); // Ini akan mengembalikan array objek UploadedFile   
            $notStandardList = [];
            foreach ($uploadedFiles as $file) {
                $filePath = $file->getRealPath();
                $extension = $file->getClientOriginalExtension();
                $fileName = $file->getClientOriginalName();
                $reader = IOFactory::createReader(ucfirst($extension));
                $spreadsheet = $reader->load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $DONumber = [];

                $rowIndex = 14;
                $Pallet = '';
                $data = [];

                // periksa tipe template
                $templateType = 1;
                if ($sheet->getCell('A3')->getCalculatedValue()) {
                    $templateType = 2;
                }

                if ($templateType == 1) {

                    $DONumberHeader = $sheet->getCell('X7')->getCalculatedValue();
                    if (empty($DONumberHeader)) {
                        $notStandardList[] = [
                            'case' => 'template_format',
                            'message' => 'DO Number is empty, please check the format',
                            'file' => $fileName
                        ];
                        continue;
                    }

                    $DONumber[] = $DONumberHeader;
                    while (!empty($sheet->getCell('F' . $rowIndex)->getCalculatedValue())) { // patokan dari kolom F                
                        $_pallet = trim($sheet->getCell('AI' . $rowIndex)->getCalculatedValue());
                        if ($Pallet != $_pallet && $_pallet != '') {
                            $Pallet = trim($sheet->getCell('AI' . $rowIndex)->getCalculatedValue());
                        }

                        // validate date
                        $_date = trim($sheet->getCell('M' . $rowIndex)->getValue());
                        if (empty($_date)) {
                            $notStandardList[] = [
                                'case' => 'date',
                                'message' => 'Date on line ' . $rowIndex . ' is empty',
                                'file' => $fileName
                            ];
                            $rowIndex += 2;
                            continue;
                        }
                        $_date_o = \Carbon\Carbon::instance(Date::excelToDateTimeObject($_date));

                        // validate item code                        
                        $_itemColumnRow2 = $sheet->getCell('F' . $rowIndex + 1)->getCalculatedValue();
                        if (empty($_itemColumnRow2)) {
                            $notStandardList[] = [
                                'case' => 'date',
                                'message' => 'Column F at row ' . $rowIndex . ' is not as usual, please check the file.',
                                'file' => $fileName
                            ];
                            $rowIndex += 2;
                            continue;
                        }


                        $data[] = [
                            'delivery_doc' => $DONumberHeader,
                            'created_by' => 'ane',
                            'created_at' => date('Y-m-d H:i:s'),
                            'item_code' => trim($sheet->getCell('F' . $rowIndex)->getCalculatedValue()),
                            'delivery_date' => $_date_o->format('Y-m-d'),
                            'delivery_quantity' => str_replace(',', '', trim($sheet->getCell('P' . $rowIndex)->getCalculatedValue())),
                            'ship_quantity' => trim($sheet->getCell('U' . $rowIndex)->getCalculatedValue()),
                            'pallet' => $Pallet,
                            'item_name' => trim($sheet->getCell('F' . $rowIndex + 1)->getCalculatedValue())
                        ];

                        $rowIndex += 2;
                    }
                } else {
                    $rowIndex = 4;

                    while (!empty($sheet->getCell('F' . $rowIndex)->getCalculatedValue())) { // patokan dari kolom F                
                        $_pallet = '';

                        $_do_number = $sheet->getCell('J' . $rowIndex)->getCalculatedValue();
                        $_date = trim($sheet->getCell('B' . $rowIndex)->getValue());
                        if (empty($_date)) {
                            $notStandardList[] = [
                                'case' => 'date',
                                'message' => 'Date on line ' . $rowIndex . ' is empty',
                                'file' => $fileName
                            ];
                            $rowIndex++;
                            continue;
                        }
                        $_date_o = \Carbon\Carbon::instance(Date::excelToDateTimeObject($_date));

                        $_qty = str_replace(',', '', trim($sheet->getCell('F' . $rowIndex)->getCalculatedValue()));
                        $data[] = [
                            'delivery_doc' => $_do_number,
                            'created_by' => 'ane',
                            'created_at' => date('Y-m-d H:i:s'),
                            'item_code' => trim($sheet->getCell('C' . $rowIndex)->getCalculatedValue()),
                            'delivery_date' => $_date_o->format('Y-m-d'),
                            'delivery_quantity' => $_qty,
                            'ship_quantity' => $_qty,
                            'pallet' => $Pallet,
                            'item_name' => ''
                        ];

                        $rowIndex++;
                        $DONumber[] = $_do_number;
                    }
                }



                if ($data && empty($notStandardList)) {
                    try {
                        DB::connection('sqlsrv_wms')->beginTransaction();
                        DB::connection('sqlsrv_wms')->table('receive_p_l_s')
                            ->whereNull('deleted_at')->whereIn('delivery_doc', $DONumber)
                            ->update(['deleted_by' => 'ane', 'deleted_at' => date('Y-m-d H:i:s')]);

                        $TOTAL_COLUMN = 8;
                        $insert_data = collect($data);
                        $chunks = $insert_data->chunk(2000 / $TOTAL_COLUMN);
                        foreach ($chunks as $chunk) {
                            DB::connection('sqlsrv_wms')->table('receive_p_l_s')->insert($chunk->toArray());
                        }
                        DB::connection('sqlsrv_wms')->commit();
                    } catch (Exception $e) {
                        DB::connection('sqlsrv_wms')->rollBack();
                        return response()->json([
                            'message' => $e->getMessage(),
                            'data' => $notStandardList,
                            'doc' => $DONumberHeader
                        ], 400);
                    }
                }
            }
            if ($notStandardList) {
                return response()->json([
                    'message' => 'Sorry we could not recognize the template files',
                    'data' => $notStandardList
                ], 400);
            }
        }

        return [
            'message' => 'Uploaded successfully',
            'doc' => $DONumber,
            'data' => $data
        ];
    }

    function getDiffWithMaster(Request $request)
    {
        $data0 = DB::connection('sqlsrv_wms')->table('receive_p_l_s')
            ->whereNull('deleted_at')
            ->where('item_name', '!=', '')
            ->select('delivery_doc', 'item_code', 'item_name');

        $data = DB::connection('sqlsrv_wms')->query()->fromSub($data0, 'v1')
            ->leftJoin('MITM_TBL', 'item_code', '=', 'MITM_ITMCD')
            ->whereRaw("item_name!=MITM_SPTNO")
            ->orderBy('delivery_doc')
            ->orderBy('item_code')
            ->get(['v1.*', DB::raw("RTRIM(MITM_SPTNO) MITM_SPTNO")]);
        return ['data' => $data];
    }
}
