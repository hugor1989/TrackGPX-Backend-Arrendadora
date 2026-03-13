<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\Account;
use App\Models\AccountRole;
use App\Models\CompanyUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;  // ← Agregar esta línea
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class CompaniesController extends AppBaseController
{
    /**
     * Lista paginada de compañías
     */
    public function index(Request $request)
    {
        $companies = Company::orderBy('id', 'DESC')->paginate(
            $request->get('per_page', 10)
        );

        return $this->success([
            'companies' => CompanyResource::collection($companies),
            'pagination' => [
                'total' => $companies->total(),
                'per_page' => $companies->perPage(),
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
            ]
        ]);
    }

    /**
     * Crear compañía
     */
    public function store(StoreCompanyRequest $request)
    {
        $company = Company::create($request->validated());

        return $this->success(
            new CompanyResource($company),
            "Compañía creada correctamente"
        );
    }

    public function store_NEW(StoreCompanyRequest $request)
    {
        DB::beginTransaction();

        try {
            // 1️⃣ Crear empresa
            $company = Company::create([
                'name'  => $request->name,
                'slug'  => $request->slug ?? Str::slug($request->name),
                'rfc'   => $request->rfc,
                'fiscal_address' => $request->fiscal_address,
                'contact_email'  => $request->contact_email,
                'phone' => $request->phone,
            ]);

            // 2️⃣ Crear cuenta administrador
            $generatedPassword = Str::random(12);

            $adminAccount = Account::create([
                'company_id' => $company->id,
                'name' => "Administrador {$company->name}",
                'email' => $company->slug . '@company.local',
                'password' => bcrypt($generatedPassword),
                'status' => 'active'
            ]);

            // 3️⃣ Asignar rol company_admin
            AccountRole::create([
                'account_id' => $adminAccount->id,
                'role_id' => 3 // ID real del rol company_admin
            ]);

            // 4️⃣ Crear perfil company_user
            CompanyUser::create([
                'account_id' => $adminAccount->id,
                'company_id' => $company->id,
                'name' => "Administrador {$company->name}",
                'phone' => null,
                'position' => 'Administrador',
                'timezone' => 'America/Mexico_City',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Empresa creada correctamente',
                'data' => [
                    'company' => $company,
                    'admin_account' => [
                        'id' => $adminAccount->id,
                        'email' => $adminAccount->email,
                        'generated_password' => $generatedPassword
                    ]
                ]
            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la empresa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver una compañía
     */
    public function show($id)
    {
        $company = Company::findOrFail($id);

        return $this->success(
            new CompanyResource($company)
        );
    }

    /**
     * Obtener la compañía del usuario autenticado
     */

    public function myCompany(Request $request)
    {
        try {
            // 1. Obtener el usuario autenticado (Account)
            $user = $request->user();

            // 2. Cargar la información de la empresa asociada
            // Usamos 'load' para ser eficientes, o accedemos directo a $user->company
            $company = $user->company;

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró información de la empresa'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $company->id,
                    'slug' => $company->slug,
                    'name' => $company->name,
                    'rfc' => $company->rfc,
                    'fiscal_address' => $company->fiscal_address,
                    'contact_email' => $company->contact_email,
                    'phone' => $company->phone,
                    'status' => $company->status,
                    'logo' => $company->logo ? asset('storage/' . $company->logo) : null,
                    'website' => $company->website,
                    'created_at' => $company->created_at,
                    'updated_at' => $company->updated_at,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información de la empresa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar información de la empresa
     */
    public function updateMyCompanie(Request $request)
    {
        try {
            // Obtener empresa del usuario autenticado
            // 1. Obtener el usuario autenticado (Account)
            $user = $request->user();

            // 2. Cargar la información de la empresa asociada
            // Usamos 'load' para ser eficientes, o accedemos directo a $user->company
            $company = $user->company;


            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró información de la empresa'
                ], 404);
            }

            // Validación
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'rfc' => 'nullable|string|max:13',
                'fiscal_address' => 'nullable|string',
                'contact_email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:20',
                'website' => 'nullable|url|max:255',
            ]);

            // Actualizar solo los campos enviados
            $company->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Información actualizada correctamente',
                'data' => [
                    'id' => $company->id,
                    'slug' => $company->slug,
                    'name' => $company->name,
                    'rfc' => $company->rfc,
                    'fiscal_address' => $company->fiscal_address,
                    'contact_email' => $company->contact_email,
                    'phone' => $company->phone,
                    'status' => $company->status,
                    'logo' => $company->logo ? asset('storage/' . $company->logo) : null,
                    'website' => $company->website,
                    'created_at' => $company->created_at,
                    'updated_at' => $company->updated_at,
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar información de la empresa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    * Subir logo de la compañía
    */
    /**
     * Subir o actualizar logo de la empresa
     */
    public function uploadLogo(Request $request)
    {
        try {
            // 1. Obtener el usuario autenticado (Account)  
            $user = $request->user();

            // 2. Cargar la información de la empresa asociada
            // Usamos 'load' para ser eficientes, o accedemos directo a $user->company
            $company = $user->company;

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró información de la empresa'
                ], 404);
            }

            // Validación
            $request->validate([
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 2MB max
            ]);

            // Eliminar logo anterior si existe
            if ($company->logo && Storage::exists('public/' . $company->logo)) {
                Storage::delete('public/' . $company->logo);
            }

            // Guardar nuevo logo
            $path = $request->file('logo')->store('logos', 'public');

            // Actualizar BD
            $company->update(['logo' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Logo actualizado correctamente',
                'data' => [
                    'logo_url' => asset('storage/' . $path)
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Archivo inválido',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir logo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar logo de la empresa
     */
    public function deleteLogo()
    {
        try {
            // 1. Obtener el usuario autenticado (Account)  
            $user = $request->user();

            // 2. Cargar la información de la empresa asociada
            // Usamos 'load' para ser eficientes, o accedemos directo a $user->company
            $company = $user->company;

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró información de la empresa'
                ], 404);
            }

            // Eliminar archivo del storage
            if ($company->logo && Storage::exists('public/' . $company->logo)) {
                Storage::delete('public/' . $company->logo);
            }

            // Actualizar BD (poner null)
            $company->update(['logo' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Logo eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar logo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Actualizar compañía
     */
    public function update(UpdateCompanyRequest $request, $id)
    {
        $company = Company::findOrFail($id);
        $company->update($request->validated());

        return $this->success(
            new CompanyResource($company),
            "Compañía actualizada correctamente"
        );
    }

    /**
     * Suspender compañía (en vez de delete)
     */
    public function suspend($id)
    {
        $company = Company::findOrFail($id);

        $company->update([
            'status' => 'suspended'
        ]);

        return $this->success(null, "Compañía suspendida");
    }
}
