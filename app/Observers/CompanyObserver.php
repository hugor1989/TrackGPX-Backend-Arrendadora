<?php

namespace App\Observers;

use App\Models\Company;
use App\Services\OpenPayService;
use Illuminate\Support\Facades\Log;

class CompanyObserver
{
    protected OpenPayService $openPayService;

    public function __construct(OpenPayService $openPayService)
    {
        $this->openPayService = $openPayService;
    }

    /**
     * Handle the Company "created" event.
     * 
     * Se ejecuta automáticamente después de crear una empresa
     */
    public function created(Company $company): void
    {
        // Solo crear customer si no existe ya
        if (!empty($company->openpay_customer_id)) {
            return;
        }

        try {
            // Preparar datos limpios
            $customerData = [
                'name' => $company->name,
                'email' => $company->contact_email,
                'external_id' => "company_{$company->id}",
            ];

            // Solo agregar teléfono si existe
            if (!empty($company->phone)) {
                $customerData['phone'] = $company->phone;
            }

            // Solo agregar dirección si existe
           /*  if (!empty($company->fiscal_address)) {
                $customerData['address'] = $company->fiscal_address;
            } */

            // Crear customer en OpenPay
            $result = $this->openPayService->createCustomer($customerData);

            if ($result['success']) {
                // Guardar el customer_id en la empresa
                $company->update([
                    'openpay_customer_id' => $result['customer_id'],
                ]);

                Log::info("OpenPay customer creado para empresa", [
                    'company_id' => $company->id,
                    'openpay_customer_id' => $result['customer_id'],
                ]);
            } else {
                Log::error("Error al crear OpenPay customer", [
                    'company_id' => $company->id,
                    'error' => $result['error'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Excepción al crear OpenPay customer", [
                'company_id' => $company->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Company "updated" event.
     * 
     * Sincronizar cambios con OpenPay cuando se actualiza la empresa
     */
    public function updated(Company $company): void
    {
        // Solo sincronizar si ya tiene customer_id
        if (empty($company->openpay_customer_id)) {
            return;
        }

        // Verificar si cambió algún campo relevante
        $relevantFields = ['name', 'contact_email', 'phone', 'rfc', 'fiscal_address'];
        $hasChanges = false;

        foreach ($relevantFields as $field) {
            if ($company->isDirty($field)) {
                $hasChanges = true;
                break;
            }
        }

        if (!$hasChanges) {
            return;
        }

        try {
            // Actualizar en OpenPay
            $updateData = [
                'name' => $company->name,
                'email' => $company->contact_email,
                'phone_number' => $company->phone,
            ];

            $result = $this->openPayService->updateCustomer(
                $company->openpay_customer_id,
                $updateData
            );

            if ($result['success']) {
                Log::info("OpenPay customer actualizado", [
                    'company_id' => $company->id,
                    'openpay_customer_id' => $company->openpay_customer_id,
                ]);
            } else {
                Log::error("Error al actualizar OpenPay customer", [
                    'company_id' => $company->id,
                    'error' => $result['error'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Excepción al actualizar OpenPay customer", [
                'company_id' => $company->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Company "deleted" event.
     * 
     * OPCIONAL: Eliminar customer de OpenPay cuando se elimina la empresa
     * (Comentado por defecto para no perder historial de pagos)
     */
    public function deleted(Company $company): void
    {
        // if (!empty($company->openpay_customer_id)) {
        //     try {
        //         $result = $this->openPayService->deleteCustomer($company->openpay_customer_id);
        //         
        //         if ($result['success']) {
        //             Log::info("OpenPay customer eliminado", [
        //                 'company_id' => $company->id,
        //                 'openpay_customer_id' => $company->openpay_customer_id,
        //             ]);
        //         }
        //     } catch (\Exception $e) {
        //         Log::error("Error al eliminar OpenPay customer", [
        //             'company_id' => $company->id,
        //             'exception' => $e->getMessage(),
        //         ]);
        //     }
        // }
    }
}
