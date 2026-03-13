<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

// Autenticación y Administración de la Arrendadora
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Admin\CompaniesController;
use App\Http\Controllers\Api\Admin\CompanyUsersController;
use App\Http\Controllers\Api\Admin\Dashboard\SummaryController;

// LENDERS: Contratos y Control de Riesgo
use App\Http\Controllers\LeaseContractController;

// OPERACIÓN
use App\Http\Controllers\Api\Vehicles\VehicleController;
use App\Http\Controllers\Api\Drivers\DriverController;
use App\Http\Controllers\Api\Device\DeviceActivationController;
use App\Http\Controllers\Api\Device\CommandController;
use App\Http\Controllers\Api\Config\GroupController;

// MONITOREO Y SEGURIDAD
use App\Http\Controllers\Api\Geoference\GeofenceController;
use App\Http\Controllers\Api\Alerts\AlertRuleController;
use App\Http\Controllers\Api\Alerts\AlertLogController;
use App\Http\Controllers\Api\History\HistoryController;
use App\Http\Controllers\Api\Reports\ReportController;
use App\Http\Controllers\Api\Fine\FineController;

// FACTURACIÓN (Solo para que la Arrendadora descargue sus facturas de Track GPX)
use App\Http\Controllers\Api\Billing\BillingInfoController;
use App\Http\Controllers\Api\Billing\InvoiceController;

use App\Http\Controllers\Api\Risk\RiskAlertController;
use App\Http\Controllers\Api\Customer\CustomerController;
use App\Http\Controllers\Api\Credit\CreditScoreController;
use App\Http\Controllers\Api\Lease\LeasePaymentController;
use App\Http\Controllers\Api\Reports\CollectionReportController;
// HARDWARE & WEBHOOKS
use App\Http\Controllers\GpsController;
use App\Http\Controllers\SharedLinkController;
use App\Http\Controllers\Api\Scraping\ScrapingWebhookController;
use App\Http\Middleware\VerifyApiKey;


/*
|--------------------------------------------------------------------------
| Public Routes (GPS Hardware & Login)
|--------------------------------------------------------------------------
*/

Route::get('/ping', fn() => response()->json(['status' => 'ok']));
Route::post('auth/login', [AuthController::class, 'login']);

Route::any('gps/position', [GpsController::class, 'receivePosition']);
Route::any('gps/event', [GpsController::class, 'receiveEvent']);
Route::post('de/register', [DeviceActivationController::class, 'register']);


Route::prefix('devices')->middleware([VerifyApiKey::class])->group(function () {
    Route::post('hear/{imei}/heartbeat', [DeviceActivationController::class, 'heartbeat']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('auth/me', [AuthController::class, 'me']);

    Route::prefix('dashboard')->group(function () {
        Route::get('summary', [SummaryController::class, 'getDashboardSummary']);
    });

    // --- MI CUENTA (Arrendadora) ---
    Route::prefix('admin/company')->group(function () {
        Route::post('companies', [CompaniesController::class, 'store_NEW']);
        Route::get('my-company', [CompaniesController::class, 'myCompany']);
        Route::put('update', [CompaniesController::class, 'updateMyCompanie']);
        Route::post('logo', [CompaniesController::class, 'uploadLogo']);
        Route::delete('logo', [CompaniesController::class, 'deleteLogo']);

        Route::get('users', [CompanyUsersController::class, 'index']);
        Route::post('users', [CompanyUsersController::class, 'store']);
        Route::put('users/{id}', [CompanyUsersController::class, 'update']);
        Route::delete('users/{id}', [CompanyUsersController::class, 'destroy']);
        Route::patch('suspend/{id}', [CompanyUsersController::class, 'suspend']);
    });

    // --- CONTRATOS DE ARRENDAMIENTO (Lenders Core) ---
    // Aquí es donde vinculan al cliente con el auto y controlan el bloqueo
    Route::prefix('lease-contracts')->group(function () {
        Route::get('/', [LeaseContractController::class, 'index']);
        Route::post('/', [LeaseContractController::class, 'store']);
        Route::get('{id}', [LeaseContractController::class, 'show']); // <--- FALTA: Ver detalle de un solo contrato
        Route::put('{id}', [LeaseContractController::class, 'update']); // <--- FALTA: Editar montos o fechas

        Route::post('{id}/toggle-lock', [LeaseContractController::class, 'toggleLock']);

        // Corregido: Para que coincida con tu service (registerPayment)
        // Cambié 'payment' por 'payments' para que sea plural y estándar
        Route::post('{id}/payments', [LeaseContractController::class, 'registerPayment']);

        Route::get('{id}/command-history', [LeaseContractController::class, 'getCommandHistory']);
        Route::get('{id}/customer-profile', [LeaseContractController::class, 'showCustomerProfile']);
    });

    // 2. Ruta para el listado general de pagos (PaymentsScreen)
    Route::get('lease-payments', [LeasePaymentController::class, 'index']);
    Route::get('available-for-contract', [VehicleController::class, 'availableVehicles']);


    Route::prefix('risk')->group(function () {
        Route::get('alerts/high-priority', [RiskAlertController::class, 'getHighPriorityAlerts']);
        Route::get('summary', [RiskAlertController::class, 'getRiskSummary']);
        Route::get('offline-units', [RiskAlertController::class, 'getOfflineUnitsReport']);

        // NUEVOS ENDPOINTS PARA EL HEATMAP
        Route::get('heatmap/incidents', [RiskAlertController::class, 'getIncidentHeatmap']); // Puntos para el mapa
        Route::get('stats/danger-zones', [RiskAlertController::class, 'getDangerZoneStats']); // Estadísticas de zonas
    });
    // --- GESTIÓN DE ACTIVOS ---
    Route::apiResource('drivers', DriverController::class);

    Route::prefix('vehicles')->group(function () {
        // 1. Rutas de utilidad / Filtros (SIEMPRE PRIMERO)

        // 2. Rutas estándar
        Route::get('get-all', [VehicleController::class, 'index']);

        Route::post('create-vehicles', [VehicleController::class, 'store']);


        // 3. Acciones específicas
        Route::get('get-by-id/{id}', [VehicleController::class, 'show']);
        Route::put('update-vehicles/{id}', [VehicleController::class, 'update']);
        Route::delete('delete-vehicles/{id}', [VehicleController::class, 'destroy']);
        Route::post('{id}/assign-driver', [VehicleController::class, 'assignDriver']);
        Route::delete('{id}/unassign-driver', [VehicleController::class, 'unassignDriver']);
        Route::post('{id}/assign-device', [VehicleController::class, 'assignDevice']);
        Route::delete('{id}/unassign-device', [VehicleController::class, 'unassignDevice']);
    });
    /* Route::prefix('vehicles')->group(function () {

        Route::get('get-all', [VehicleController::class, 'index']);
        Route::get('get-all-contract', [VehicleController::class, 'availableVehicles']);
        Route::get('get-by-id/{id}', [VehicleController::class, 'show']);
        Route::post('create-vehicles', [VehicleController::class, 'store']);
        Route::put('update-vehicles/{id}', [VehicleController::class, 'update']);
        Route::delete('delete-vehicles/{id}', [VehicleController::class, 'destroy']);

        // Asignar/desasignar conductor
        Route::post('assign/{id}/assign-driver', [VehicleController::class, 'assignDriver']);
        Route::delete('unassig/{id}/assign-driver', [VehicleController::class, 'unassignDriver']);

        // Asignar/desasignar dispositivo GPS
        Route::post('assignD/{id}/assign-device', [VehicleController::class, 'assignDevice']);
        Route::delete('unassignD/{id}/assign-device', [VehicleController::class, 'unassignDevice']);
    }); */


    Route::prefix('alerts')->group(function () {
        Route::get('get-all', [AlertRuleController::class, 'index']);
        Route::post('create', [AlertRuleController::class, 'store']);
        Route::delete('delete/{id}', [AlertRuleController::class, 'destroy']);
        Route::patch('update-toggle/{id}/toggle', [AlertRuleController::class, 'toggle']);
    });

    Route::prefix('alert-logs')->group(function () {
        Route::get('get-all', [AlertLogController::class, 'index']);
        Route::post('mark-all-read', [AlertLogController::class, 'markAllAsRead']);
        Route::post('reading/{id}/read', [AlertLogController::class, 'markAsRead']);
    });

    Route::prefix('devices')->group(function () {
        Route::get('available', [DeviceActivationController::class, 'available']);
        Route::post('get-all', [DeviceActivationController::class, 'index']);
        Route::post('send-command/{deviceId}', [CommandController::class, 'send']);
        Route::post('get-all-devices', [DeviceActivationController::class, 'index']);
    });

    Route::prefix('customers')->group(function () {
        Route::get('customer', [CustomerController::class, 'index']);          // Listado con filtros de score
        Route::post('/', [CustomerController::class, 'store']);         // Alta de nuevo cliente
        Route::get('{id}', [CustomerController::class, 'show']);        // Detalle y expediente
        Route::put('{id}', [CustomerController::class, 'update']);      // Edición de datos
        Route::get('{id}/history', [CustomerController::class, 'getHistory']); // Historial de créditos
    });

    Route::prefix('credit-score')->group(function () {
        Route::get('/summary', [CreditScoreController::class, 'getSummary']);
        Route::get('/rankings', [CreditScoreController::class, 'getRankings']);
        Route::post('/recalculate/{id}', [CreditScoreController::class, 'recalculate']);
    });
    // --- ADMINISTRACIÓN Y FISCAL ---
    // La arrendadora entra aquí para ver cuánto le debe a Track GPX 
    // y descargar sus facturas de las transferencias realizadas.
    Route::prefix('billing')->group(function () {
        Route::get('info', [BillingInfoController::class, 'index']); // Sus datos de facturación
        Route::put('info/{id}', [BillingInfoController::class, 'update']);

        Route::get('invoices', [InvoiceController::class, 'index']); // Historial de facturas enviadas por Hugo
        Route::get('invoices/{id}/xml', [InvoiceController::class, 'downloadXML']);
        Route::get('invoices/{id}/pdf', [InvoiceController::class, 'downloadPDF']);
    });

    // --- MONITOREO ---
    Route::get('history/route', [HistoryController::class, 'getRoute']);
    Route::get('reports/stops', [ReportController::class, 'getStops']);
    Route::apiResource('geofences', GeofenceController::class);

    Route::prefix('alerts')->group(function () {
        Route::get('logs', [AlertLogController::class, 'index']);
        Route::post('mark-all-read', [AlertLogController::class, 'markAllAsRead']);
    });

    Route::prefix('collection')->group(function () {
        Route::get('management', [CollectionReportController::class, 'getCollectionManagement']);
        Route::get('recover', [CollectionReportController::class, 'getUnitsToRecover']);
        Route::get('history', [CollectionReportController::class, 'getRecoveryHistory']);
    });

    // --- MULTAS (SCRAPING) ---
    Route::get('fines', [FineController::class, 'index']);
    Route::post('fines/{id}/pay', [FineController::class, 'markAsPaid']);
    Route::post('webhooks/scraping/fines', [ScrapingWebhookController::class, 'receiveFines']);
    Route::post('fines/{id}/pay', [FineController::class, 'markAsPaid']);
    Route::get('fines/history', [FineController::class, 'history']);
    Route::get('fines/export', [FineController::class, 'export']);

    //-- Reportes ---
    Route::get('reports/portfolio-health', [ReportController::class, 'portfolioHealth']);
    Route::get('reports/units-at-risk',    [ReportController::class, 'unitsAtRisk']);
    Route::get('reports/offline-units',    [ReportController::class, 'offlineUnits']);
});

// Externos
Route::post('webhooks/scraping/fines', [ScrapingWebhookController::class, 'receiveFines']);
