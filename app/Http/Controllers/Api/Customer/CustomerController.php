<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\CustomerProfile;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    /**
     * Listado de clientes con su score de riesgo actual.
     */
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        // 1. Paginación flexible
        $perPage = $request->get('per_page', 15);
        $perPage = $perPage === 'all' ? 9999 : (int) $perPage;

        // 2. Query base con todas las relaciones que necesitas para la lista
        $query = Account::where('company_id', $companyId)
            ->where('role', 'customer')
            ->with(['riskScore', 'customerProfile', 'leaseContracts.vehicle:id,plate,model,brand']);

        // 3. Aplicar búsqueda
        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // 4. CÁLCULOS PARA TU DASHBOARD (Lo que se ve en las cajitas de arriba)
        // Obtenemos los scores de TODOS los clientes de esta empresa para las gráficas
        $allScores = DB::table('customer_risk_scores')
            ->join('accounts', 'customer_risk_scores.account_id', '=', 'accounts.id')
            ->where('accounts.company_id', $companyId)
            ->select('score', 'points')
            ->get();

        $totalCount = $allScores->count();

        $summary = [
            'total' => $totalCount,
            'promedio_puntos' => round($allScores->avg('points') ?? 0),
            'bajo'  => [
                'count' => $allScores->where('score', 'Bajo')->count(),
                'pct'   => $totalCount > 0 ? round(($allScores->where('score', 'Bajo')->count() / $totalCount) * 100) : 0
            ],
            'medio' => [
                'count' => $allScores->where('score', 'Medio')->count(),
                'pct'   => $totalCount > 0 ? round(($allScores->where('score', 'Medio')->count() / $totalCount) * 100) : 0
            ],
            'alto'  => [
                'count' => $allScores->where('score', 'Alto')->count(),
                'pct'   => $totalCount > 0 ? round(($allScores->where('score', 'Alto')->count() / $totalCount) * 100) : 0
            ],
        ];

        return response()->json([
            'success' => true,
            'data'    => $customers,
            'summary' => $summary // <--- Aquí van los datos para tus barras y cajitas
        ]);
    }
    /**
     * Registro de nuevo cliente (Prospecto).
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            // 1️⃣ Crear la cuenta principal (Account)
            // El rol se fija como 'customer' para diferenciarlo del personal de la financiera
            $account = Account::create([
                'company_id' => $request->user()->company_id,
                'name'       => $request->name,
                'email'      => $request->email,
                'password'   => Hash::make($request->password ?? 'temporal123'), // O generar una aleatoria
                'status'     => 'active',
                'role'       => 'customer'
            ]);

            // 2️⃣ Crear el Perfil del Cliente (Customer Profile)
            // Aquí guardamos los datos que pasaste en tu tabla customer_profiles
            $profile = CustomerProfile::create([
                'account_id'                    => $account->id,
                'rfc'                           => $request->rfc,
                'birth_date'                    => $request->birth_date,
                'gender'                        => $request->gender,
                'phone_primary'                 => $request->phone_primary,
                'phone_secondary'               => $request->phone_secondary,
                'address_home'                  => $request->address_home,
                'address_office'                => $request->address_office,
                'emergency_contact_name'        => $request->emergency_contact_name,
                'emergency_contact_phone'       => $request->emergency_contact_phone,
                'emergency_contact_relationship' => $request->emergency_contact_relationship,
                'job_title'                     => $request->job_title,
                'company_name'                  => $request->company_name,
            ]);

            // 3️⃣ Inicializar el Score de Riesgo (La tabla que creamos antes)
            // Todo cliente nuevo empieza con score neutral para Track GPX
            $account->riskScore()->create([
                'score'  => 'Bajo',
                'points' => 0,
                'reason' => 'Registro inicial de cliente'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cliente registrado exitosamente',
                'data'    => [
                    'account' => $account,
                    'profile' => $profile
                ]
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el cliente',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $company = $this->getAuthenticatedCompany($request);

            $account = Account::where('id', $id)
                ->where('company_id', $company->id)
                ->where('role', 'customer')
                ->firstOrFail();

            // 1️⃣ Actualizar Account (solo nombre, email y status)
            $account->update([
                'name'   => $request->name   ?? $account->name,
                'email'  => $request->email  ?? $account->email,
                'status' => $request->status ?? $account->status,
            ]);

            // 2️⃣ Actualizar o crear Profile
            $account->customerProfile()->updateOrCreate(
                ['account_id' => $account->id],
                [
                    'rfc'                            => $request->rfc,
                    'birth_date'                     => $request->birth_date,
                    'gender'                         => $request->gender,
                    'phone_primary'                  => $request->phone_primary,
                    'phone_secondary'                => $request->phone_secondary,
                    'address_home'                   => $request->address_home,
                    'address_office'                 => $request->address_office,
                    'emergency_contact_name'         => $request->emergency_contact_name,
                    'emergency_contact_phone'        => $request->emergency_contact_phone,
                    'emergency_contact_relationship' => $request->emergency_contact_relationship,
                    'job_title'                      => $request->job_title,
                    'company_name'                   => $request->company_name,
                ]
            );

            DB::commit();

            // Retornar el cliente actualizado con relaciones
            $updated = Account::with(['customerProfile', 'riskScore', 'leaseContracts'])
                ->find($account->id);

            return response()->json([
                'success' => true,
                'message' => 'Cliente actualizado correctamente',
                'data'    => $updated
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el cliente',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detalle del cliente y sus contratos activos.
     */
    /**
     * Detalle del cliente con su expediente completo, contratos y score.
     */
    public function show($id)
    {
        try {
            $companyId = auth()->user()->company_id;

            // Buscamos la cuenta asegurando que pertenezca a la empresa y sea rol cliente
            $customer = Account::where('company_id', $companyId)
                ->where('role', 'customer')
                ->with([
                    'customerProfile',  // Datos detallados (RFC, Dirección, etc.)
                    'riskScore',        // Nivel de riesgo actual
                    'leaseContracts.vehicle.device' // Historial de contratos y GPS instalado
                ])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detalle del cliente obtenido correctamente',
                'data'    => $customer
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'El cliente no existe o no pertenece a su empresa',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el detalle del cliente',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    protected function getAuthenticatedCompany(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        if (method_exists($user, 'company')) {
            return $user->company;
        }

        if (method_exists($user, 'companyUser')) {
            return $user->companyUser?->company;
        }

        if (isset($user->company_id)) {
            return \App\Models\Company::find($user->company_id);
        }

        return null;
    }
}
