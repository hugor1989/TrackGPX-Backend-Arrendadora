<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyUserRequest;
use App\Http\Requests\UpdateCompanyUserRequest;
use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\AccountRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyUsersController extends Controller
{
    // ============================================================
    // 1. LISTAR USUARIOS DE UNA COMPAÑÍA
    // ============================================================
    /* public function index(Request $request, $companyId)
    {
        $users = CompanyUser::with(['account:id,email,status'])
            ->where('company_id', $companyId)
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    } */
    public function index(Request $request)
    {
        // 1. Obtener el usuario autenticado (Account)
        $currentUser = $request->user();

        // 2. Verificar que tenga una empresa asignada
        if (!$currentUser->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Este usuario no tiene una empresa asociada.'
            ], 403);
        }

        // 3. Usar el company_id del usuario logueado para filtrar
        $users = CompanyUser::with(['account:id,email,status,role']) // Traemos datos clave de la cuenta
            ->where('company_id', $currentUser->company_id)
            ->orderBy('created_at', 'desc') // Opcional: ordenar por más recientes
            ->paginate(10);

        return response()->json([
            'success' => true,
            // Si usas paginate(), Laravel devuelve 'data', 'links', 'meta' automáticamente.
            // A veces es mejor devolver el objeto paginado completo o solo 'data' según tu front.
            'data' => $users->items(),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ]
        ]);
    }

    // ============================================================
    // 2. CREAR USUARIO INTERNO (account + company_user + rol)
    // ============================================================
    public function store(StoreCompanyUserRequest $request)
    {
        DB::beginTransaction();

        Log::info('ROLE REQUEST', ['role' => $request->role]);

        try {
            // 1️⃣ Crear cuenta madre
            $account = Account::create([
                'company_id' => $request->company_id,
                'name'       => $request->name,
                'email'      => $request->email,
                'password'   => Hash::make($request->password),
                'status'     => 'active',
                'role'       => $request->role
            ]);

            // 2️⃣ Crear el registro company_user
            $user = CompanyUser::create([
                'account_id' => $account->id,
                'company_id' => $request->company_id,
                'name'       => $request->name,
                'phone'      => $request->phone,
                'position'   => $request->position,
                'timezone'   => $request->timezone ?? 'America/Mexico_City',
            ]);

            // 3️⃣ Asignar rol
            if ($request->role) {
                $role = Role::where('name', $request->role)->firstOrFail();

                AccountRole::create([
                    'account_id' => $account->id,
                    'role_id'    => $role->id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'data' => [
                    'account' => $account,
                    'company_user' => $user
                ]
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // 3. OBTENER UN USUARIO
    // ============================================================
    public function show($id)
    {
        $user = CompanyUser::with(['account', 'account.roles'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    // ============================================================
    // 4. ACTUALIZAR UN USUARIO
    // ============================================================
    public function update(UpdateCompanyUserRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $user = CompanyUser::findOrFail($id);
            $account = $user->account;

            // 1️⃣ Actualizar datos de account
            if ($request->has('email')) {
                $account->email = $request->email;
            }
            if ($request->has('name')) {
                $account->name = $request->name;
            }
            if ($request->password) {
                $account->password = Hash::make($request->password);
            }
            $account->save();

            // 2️⃣ Actualizar datos de company_user
            $user->update($request->only(['name', 'phone', 'position', 'timezone']));

            // 3️⃣ Actualizar rol si lo enviaron
            if ($request->role) {
                $role = Role::where('name', $request->role)->firstOrFail();

                AccountRole::where('account_id', $account->id)->delete();

                AccountRole::create([
                    'account_id' => $account->id,
                    'role_id'    => $role->id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // 5. SUSPENDER USUARIO
    // ============================================================
    public function suspend($id)
    {
        $user = CompanyUser::findOrFail($id);
        $user->account->update(['status' => 'inactive']);

        return response()->json([
            'success' => true,
            'message' => 'Usuario suspendido correctamente'
        ]);
    }
}
