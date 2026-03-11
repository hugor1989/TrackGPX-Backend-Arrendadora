<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    /**
     * LOGIN (con soporte 2FA si está activo)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        $account = Account::where('email', $request->email)->first();

        if (!$account || !Hash::check($request->password, $account->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales incorrectas.'],
            ]);
        }

        // ¿Es super admin? Verificar si tiene 2FA encendido
        $superAdmin = SuperAdmin::where('account_id', $account->id)->first();

        if ($superAdmin && $superAdmin->two_factor_enabled) {
            // Retornamos un estado "2FA requerido"

            $tempToken = $account->createToken('temp-2fa-token', ['2fa'])->plainTextToken;

            return response()->json([
                'requires_2fa' => true,
                'temp_token' => $tempToken,
                'message' => 'Se requiere código 2FA para continuar',
            ]);
        }

        // Crear token de acceso normal
        $token = $account->createToken('auth')->plainTextToken;

        return response()->json([
                'success' => true,
                'token' => $token,
                'message' => 'Dispositivo activado exitosamente',
                'data' => [
                    'user'  => $account,
                ],
            ], 200);
       
    }

    /**
     * CONFIRMAR 2FA DESPUÉS DEL LOGIN
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required'
        ]);

        $account = Account::where('email', $request->email)->firstOrFail();
        $superAdmin = SuperAdmin::where('account_id', $account->id)->firstOrFail();

        if (!$superAdmin->two_factor_enabled) {
            return response()->json([
                'message' => 'Este usuario no tiene 2FA habilitado'
            ], 422);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($superAdmin->two_factor_secret, $request->code);

        if (!$valid) {
            return response()->json([
                'message' => 'Código incorrecto'
            ], 422);
        }

        // Crear token si pasa el 2FA
        $token = $account->createToken('auth')->plainTextToken;

        return response()->json([
            'user'  => $account,
            'token' => $token,
        ]);
    }

    /**
     * LOGOUT
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user(); // Account
        $company = $user->company; // Relación account → company (nullable)
        $companyUser = $user->companyUser; // Relación 1:1 con company_users (nullable)

        // Obtener roles
        $roles = $user->roles()->pluck('name');

        // Obtener permisos (sumados por todos sus roles)
        $permissions = $user->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'company_id'    => $user->company_id,
                'status'        => $user->status,
                'last_login'    => $user->last_login,
                'roles'         => $roles,
                'permissions'   => $permissions,

                // Empresa
                'company' => $company ? [
                    'id'    => $company->id,
                    'name'  => $company->name,
                    'slug'  => $company->slug,
                ] : null,

                // Si es usuario interno de empresa
                'company_user' => $companyUser ? [
                    'id'        => $companyUser->id,
                    'name'      => $companyUser->name,
                    'phone'     => $companyUser->phone,
                    'position'  => $companyUser->position,
                    'timezone'  => $companyUser->timezone,
                ] : null,
            ]
        ]);
    }
}
