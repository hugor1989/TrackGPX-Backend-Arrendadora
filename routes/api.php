<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Auth\TwoFactorAuthController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Admin\CompaniesController;
use App\Http\Controllers\Api\Admin\CompanyUsersController;
use App\Http\Controllers\Api\Billing\PaymentMethodController;
use App\Http\Controllers\Api\Billing\SubscriptionController;
use App\Http\Controllers\Api\Billing\BillingInfoController;
use App\Http\Controllers\Api\Admin\PlanController;
use App\Http\Controllers\Api\Device\DeviceActivationController;
use App\Http\Controllers\Api\Drivers\DriverController;
use App\Http\Controllers\Api\Vehicles\VehicleController;
use App\Http\Controllers\Api\Billing\PaymentController;
use App\Http\Controllers\Api\Billing\InvoiceController;
use App\Http\Controllers\Api\Geoference\GeofenceController;
use App\Http\Controllers\Api\Alerts\AlertRuleController;
use App\Http\Controllers\Api\Alerts\AlertLogController;
use App\Http\Controllers\Api\History\HistoryController;
use App\Http\Controllers\Api\Reports\ReportController;
use App\Http\Controllers\Api\Mileage\MileageController;
use App\Http\Controllers\Api\DriverScore\DriverScoreController;
use App\Http\Controllers\Api\Finance\FinancialReportController;
use App\Http\Controllers\Api\Scraping\ScrapingWebhookController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Fine\FineController;
use App\Http\Middleware\VerifyApiKey;
use App\Http\Controllers\Api\Config\GroupController;
use App\Http\Controllers\SharedLinkController;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\GpsController;
use App\Http\Controllers\Api\Device\CommandController;

Route::get('/ping', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('auth/login', [AuthController::class, 'login']);
Route::post('de/register', [DeviceActivationController::class, 'register']);

Route::prefix('devices')->middleware([VerifyApiKey::class])->group(function () {

    Route::post('hear/{imei}/heartbeat', [DeviceActivationController::class, 'heartbeat']);
});

Route::any('gps/position', [GpsController::class, 'receivePosition']);
Route::any('gps/event',    [GpsController::class, 'receiveEvent']);

Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {


    Route::get('auth/me', [AuthController::class, 'me']);

    //Datos de la compañía del usuario autenticado en front admin
    Route::get('companie/my-company', [CompaniesController::class, 'myCompany']);
    Route::put('companie/updateMyCompanie', [CompaniesController::class, 'updateMyCompanie']);
    Route::post('company/update-logo', [CompaniesController::class, 'uploadLogo']);
    Route::delete('company/delete/logo', [CompaniesController::class, 'deleteLogo']);

    Route::post('shared-links', [SharedLinkController::class, 'store']);

    // ==============================
    // COMPANIES MODULE
    // ==============================

    Route::get('companies/get-All', [CompaniesController::class, 'index']);

    Route::post('companies/create', [CompaniesController::class, 'store_NEW']);

    Route::get('companies/get-by-id', [CompaniesController::class, 'show']);

    Route::put('companies/update/{id}', [CompaniesController::class, 'update'])->middleware('same.company');

    Route::patch('companies/{id}/suspend', [CompaniesController::class, 'suspend'])->middleware('same.company');
    // ==============================
    // TWO FACTOR AUTHENTICATION
    // ==============================

    Route::post('2fa/enable', [TwoFactorAuthController::class, 'enable']);
    Route::post('2fa/verify', [TwoFactorAuthController::class, 'confirm']);
    Route::post('2fa/check-login-code', [TwoFactorAuthController::class, 'checkLoginCode']);


    // ==============================
    // COMPANY USERS MODULE
    // ==============================

    Route::get('company-users/get-users', [CompanyUsersController::class, 'index'])->middleware('company.access');

    Route::post('company-users/create',  [CompanyUsersController::class, 'store']);

    Route::get('company-users/get-by-id/{id}', [CompanyUsersController::class, 'show'])->middleware('company.access');

    Route::put('company-users/update/{id}', [CompanyUsersController::class, 'update'])->middleware('company.access');

    Route::patch('company-users/{id}/suspend', [CompanyUsersController::class, 'suspend'])->middleware('company.access');
});

Route::prefix('billing')->middleware(['auth:sanctum'])->group(function () {

    Route::get('dashboard/summary', [DashboardController::class, 'index']);

    // ==================== MÉTODOS DE PAGO ====================
    Route::prefix('payment-methods')->group(function () {
        Route::get('get-all-cards', [PaymentMethodController::class, 'index']);           // Listar tarjetas
        Route::post('add-card', [PaymentMethodController::class, 'store']);          // Agregar tarjeta
        Route::get('get-config', [PaymentMethodController::class, 'config']);    // Config de OpenPay
        Route::get('get-by-id/{cardId}', [PaymentMethodController::class, 'show']);    // Ver tarjeta específica
        Route::delete('delete-card/{cardId}', [PaymentMethodController::class, 'destroy']); // Eliminar tarjeta
    });

    // ==================== SUSCRIPCIONES ====================
    Route::prefix('subscriptions')->group(function () {
        Route::get('get-all-data', [SubscriptionController::class, 'index']);            // Listar suscripciones
        Route::post('create-subscriptions', [SubscriptionController::class, 'store']);           // Crear suscripción
        Route::get('get-by-id/{id}', [SubscriptionController::class, 'show']);         // Ver suscripción
        Route::post('{id}/pause', [SubscriptionController::class, 'pause']); // Pausar suscripción
        Route::post('{id}/resume', [SubscriptionController::class, 'resume']); // Reanudar suscripción
        Route::post('{id}/cancel', [SubscriptionController::class, 'cancel']); // Cancelar suscripción
        Route::post('{id}/renew', [SubscriptionController::class, 'renew']); // Renovar suscripción
    });

    // ==================== INFORMACIÓN DE FACTURACIÓN ====================
    Route::prefix('billing-info')->group(function () {
        Route::get('get-all-data', [BillingInfoController::class, 'index']);             // Ver info de facturación
        Route::post('create-billing-info', [BillingInfoController::class, 'store']);            // Crear/actualizar info
        Route::put('update-by-id/{id}', [BillingInfoController::class, 'update']);        // Actualizar info específica
        Route::get('validate', [BillingInfoController::class, 'validate']);  // Validar si está completa
    });

    // ==================== INFORMACIÓN DE PLANS ====================
    Route::prefix('plans')->group(function () {
        Route::get('get-all-plan', [PlanController::class, 'index']);           // Listar planes
        Route::post('create-plan', [PlanController::class, 'store']);          // Crear plan
        Route::put('update/{id}', [PlanController::class, 'update']);      // Actualizar plan
        Route::delete('delete/{id}', [PlanController::class, 'destroy']);  // Eliminar plan
    });

    // ==================== INFORMACIÓN DE devices company ====================
    Route::prefix('devices')->group(function () {
        // Activar dispositivo (con plan y pago)
        Route::post('activate', [DeviceActivationController::class, 'activate']);

        Route::get('devices-sinasignar', [DeviceActivationController::class, 'available']);


        // Listar dispositivos disponibles
        Route::get('available', [DeviceActivationController::class, 'available']);

        // Preview de dispositivo por IMEI
        Route::get('preview/{imei}', [DeviceActivationController::class, 'preview']);

        // Obtener todos los dispositivos de la empresa
        Route::post('get-all-devices', [DeviceActivationController::class, 'index']);

        Route::post('send-command/{deviceId}/command', [CommandController::class, 'send']);

        /*  Route::post('/activate', [DeviceActivationController::class, 'activate']);
        Route::get('/available', [DeviceActivationController::class, 'available']); */
    });

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

    Route::prefix('config')->group(function () {
        Route::get('/groups', [GroupController::class, 'index']);
        Route::post('/groups', [GroupController::class, 'store']);
        Route::put('/groups/{id}', [GroupController::class, 'update']);
        Route::delete('/groups/{id}', [GroupController::class, 'destroy']);
        
        // Ruta para mover vehículos
        Route::post('/groups/{id}/assign', [GroupController::class, 'assignVehicles']);

        Route::get('/groups/supervisors', [GroupController::class, 'getSupervisors']);
    });


    Route::get('history/route', [HistoryController::class, 'getRoute']);
    Route::get('reports/stops', [ReportController::class, 'getStops']);
    Route::get('reports/mileage', [MileageController::class, 'getMileage']);
    Route::get('reports/drivers/ranking', [DriverScoreController::class, 'getRanking']);
    Route::get('reports/financial/expenses', [FinancialReportController::class, 'getExpenses']);
    Route::post('webhooks/scraping/fines', [ScrapingWebhookController::class, 'receiveFines']);

    Route::post('/reports/financial/expenses', [FinancialReportController::class, 'store']);

    Route::post('vehicles/{id}/insurance', [VehicleController::class, 'updateInsurance']);
    Route::post('vehicles/{id}/maintenance', [VehicleController::class, 'registerMaintenance']);

    Route::get('vehicles/{id}/schedules', [VehicleController::class, 'getMaintenanceSchedules']);
    Route::post('vehicles/{id}/schedules', [VehicleController::class, 'storeMaintenanceSchedule']);

    Route::get('fines', [FineController::class, 'index']);
    Route::post('fines/{id}/pay', [FineController::class, 'markAsPaid']);
    Route::get('fines/history', [FineController::class, 'history']);
    Route::get('fines/export', [FineController::class, 'export']);

    Route::patch('vehicles/{id}/config', [VehicleController::class, 'updateConfig']);

});

// Rutas de Geocercas
Route::prefix('geofences')->middleware('auth:sanctum')->group(function () {
    Route::get('get-all', [GeofenceController::class, 'index']);
    Route::post('create', [GeofenceController::class, 'store']);
    Route::put('update/{id}', [GeofenceController::class, 'update']);
    Route::delete('delete/{id}', [GeofenceController::class, 'destroy']);
});

Route::prefix('drivers')->middleware(['auth:sanctum'])->group(function () {

    Route::get('get-all', [DriverController::class, 'index']);
    Route::get('available', [DriverController::class, 'available']); // Debe ir ANTES de {id}
    Route::get('get-by-id/{id}', [DriverController::class, 'show']);
    Route::post('create-drivers', [DriverController::class, 'store']);
    Route::put('update-drivers/{id}', [DriverController::class, 'update']);
    Route::delete('delete-drivers/{id}', [DriverController::class, 'destroy']);
});

Route::prefix('vehicles')->middleware(['auth:sanctum'])->group(function () {

    Route::get('get-all', [VehicleController::class, 'index']);
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
});

Route::prefix('invoice')->middleware(['auth:sanctum'])->group(function () {

    // ============================================================================
    // PAGOS
    // ============================================================================

    // Listar pagos de la empresa
    Route::get('get-all-payments', [PaymentController::class, 'index']);

    // Listar pagos sin factura
    Route::get('payments/without-invoice', [PaymentController::class, 'withoutInvoice']);

    // Estadísticas de pagos
    Route::get('payments/stats', [PaymentController::class, 'stats']);

    // Detalle de un pago
    Route::get('payments/{id}', [PaymentController::class, 'show']);


    // ============================================================================
    // FACTURAS
    // ============================================================================

    // Listar facturas de la empresa
    Route::get('get-all-invoices', [InvoiceController::class, 'index']);

    // Estadísticas de facturas
    Route::get('invoices/stats', [InvoiceController::class, 'stats']);

    // Solicitar factura para un pago
    Route::post('invoices/request', [InvoiceController::class, 'requestInvoice']);

    // Detalle de una factura
    Route::get('invoices/{id}', [InvoiceController::class, 'show']);

    // Descargar XML
    Route::get('invoices/{id}/xml', [InvoiceController::class, 'downloadXML']);

    // Descargar PDF
    Route::get('invoices/{id}/pdf', [InvoiceController::class, 'downloadPDF']);

    // Cancelar factura
    Route::post('invoices/{id}/cancel', [InvoiceController::class, 'cancelInvoice']);

    // Reenviar factura por correo
    Route::post('invoices/{id}/resend', [InvoiceController::class, 'resendInvoice']);
});

Route::get('/proxy/gas-prices', function () {
    // Tu backend consulta a PetroIntelligence (Laravel no tiene problemas de CORS)
    $response = Http::asForm()->post('https://petrointelligence.com/api/consulta_precios.php?consulta=estado&estado=JAL', [
        'url' => 'https://petrointelligence.com/api/api_precios.html'
    ]);

    return $response->json();
});
