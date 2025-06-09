<?php

use App\Http\Controllers\ConsignmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ICTController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\QPITController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReceivePackingListController;
use App\Http\Controllers\RepairDataController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::prefix('ict')->group(function () {
        Route::get('search-paginate', [ICTController::class, 'searchPaginate']);
        Route::get('to-spreadsheet', [ICTController::class, 'toSpreadsheet']);
        Route::put('check', [ICTController::class, 'setCheck']);
        Route::put('check-some', [ICTController::class, 'setCheckSome']);
        Route::put('remark', [ICTController::class, 'setRemark']);
        Route::get('trace-paginate', [ICTController::class, 'trace']);
        Route::get('trace-to-spreadsheet', [ICTController::class, 'traceToSpreadsheet']);
    });
    Route::prefix('qpit')->group(function () {
        Route::get('trace-paginate', [QPITController::class, 'trace']);
        Route::get('trace-to-spreadsheet', [QPITController::class, 'traceToSpreadsheet']);
    });
    Route::prefix('repair')->group(function () {
        Route::get('trace-paginate', [RepairDataController::class, 'trace']);
        Route::get('trace-to-spreadsheet', [RepairDataController::class, 'traceToSpreadsheet']);
    });

    Route::prefix('users')->group(function () {
        Route::delete('logout', [UserController::class, 'logout']);
    });
});

Route::prefix('ict')->group(function () {
    Route::get('to-spreadsheet-as-reminder', [ICTController::class, 'reminderAsSpreadsheet']);
});
Route::prefix('production')->group(function () {
    Route::get('supply-status', [ProductionController::class, 'supplyStatus']);
    Route::post('supply-status-by-psn', [ProductionController::class, 'getSupplyStatusByPSN']);
    Route::get('supply-status-by-uc', [ProductionController::class, 'getSupplyStatusByUniqueKey']);
    Route::get('active', [ProductionController::class, 'getActiveJob']);
    Route::post('output', [ProductionController::class, 'saveSensorOutput']);
    Route::get('active-tlws', [ProductionController::class, 'getActivatedTLWS']);
    Route::put('active-tlws', [ProductionController::class, 'setCompletionTLWS']);
    Route::get('active-job-from-wo', [ProductionController::class, 'getActivedJobFromWO']);
    Route::put('adjust-qty', [ProductionController::class, 'adjustQtyCrossAndCompletion']);
});
Route::prefix('employee')->group(function () {
    Route::get('name-from-nik', [EmployeeController::class, 'getByNik']);
});

Route::prefix('password')->group(function () {
    Route::get('generate', function () {
        return Hash::make('S!Paling131');
    });
});

Route::prefix('users')->group(function () {
    Route::post('login', [UserController::class, 'login']);
});

Route::prefix('engtrial')->group(function () {
    Route::get('report1', [RatingController::class, 'getPercentagePerLineMachinePeriod']);
    Route::get('report2', [RatingController::class, 'getPercentagePerLineMachinePeriodPSBOX']);
    Route::get('reportd1', [RatingController::class, 'getPercentagePerLineMachinePeriodDetail1']);
    Route::get('reportd2', [RatingController::class, 'getPercentagePerLineMachinePeriodDetail2']);
    Route::get('report1-to-spreadsheet', [RatingController::class, 'getPercentagePerLineMachinePeriodtoSpreadsheet']);
    Route::get('report2-to-spreadsheet', [RatingController::class, 'getPercentagePerLineMachinePeriodPSBOXtoSpreadsheet']);
    Route::get('qpit-model', [RatingController::class, 'getModel']);
});

Route::prefix('consignment')->group(function () {
    Route::get('children', [ConsignmentController::class, 'getChildConsignments']);
    Route::post('default-child', [ConsignmentController::class, 'setDefaultConsignment']);
});
Route::prefix('item')->group(function () {
    Route::get('process', [ItemController::class, 'getProcess']);
});

Route::prefix('receiving')->group(function () {
    Route::post('upload-pl', [ReceivePackingListController::class, 'uploadSpreadsheet']);
});

Route::post('/welcome', function () {
    return 'login dulu';
})->name('login');

Route::get('/welcome', function () {
    return 'login dulud';
})->name('login');

Route::prefix('label')->group(function () {
    Route::get('history-tree', [LabelController::class, 'splitTreeHistory']);
    Route::get('history-tree-psn', [ProductionController::class, 'getTreeInside']);
});
