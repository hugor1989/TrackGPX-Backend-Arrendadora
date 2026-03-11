<?php

namespace App\Http\Controllers\Api\Admin\Auth;

use App\Http\Controllers\AppBaseController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TwoFactorAuthController extends AppBaseController
{
    /**
     * Habilita 2FA para el superadmin autenticado y devuelve secret + QR.
     * Requiere autenticación (auth:sanctum) y que el account tenga un registro en super_admins.
     */
    public function enable(Request $request)
    {
        $account = $request->user();
        $super = SuperAdmin::where('account_id', $account->id)->firstOrFail();

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        // Guardar secret cifrado
        $super->two_factor_secret = Crypt::encryptString($secret);
        $super->two_factor_enabled = false;
        $super->save();

        // URL oficial para Google Authenticator
        $otpAuthUrl = $google2fa->getQRCodeUrl(
            'TrackGPX Flotillas',
            $account->email,
            $secret
        );

        return $this->success([
            'secret'       => $secret,
            'otp_auth_url' => $otpAuthUrl
        ], '2FA secret generado.');
    }
    /**
     * Confirma el código TOTP y activa 2FA (debe llamarlo el user autenticado
     * después de escanear el QR).
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required'
        ]);

        $account = $request->user();
        $super = SuperAdmin::where('account_id', $account->id)->firstOrFail();

        if (empty($super->two_factor_secret)) {
            return $this->error('No se encontró el secreto 2FA. Genera primero el QR.', 422);
        }

        $secret = Crypt::decryptString($super->two_factor_secret);
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            return $this->error('Código inválido', 422);
        }

        // Generar recovery codes (guardar como array)
        $recoveryCodes = collect(range(1, 8))
            ->map(fn() => Str::random(12))
            ->toArray();

        $super->two_factor_enabled = true;
        $super->two_factor_recovery_codes = $recoveryCodes;
        $super->save();

        return $this->success([
            'recovery_codes' => $recoveryCodes
        ], '2FA habilitado correctamente');
    }

    /**
     * Desactiva 2FA para el superadmin autenticado.
     */
    public function disable(Request $request)
    {
        $account = $request->user();
        $super = SuperAdmin::where('account_id', $account->id)->firstOrFail();

        $super->two_factor_enabled = false;
        $super->two_factor_secret = null;
        $super->two_factor_recovery_codes = null;
        $super->save();

        return $this->success(null, '2FA desactivado');
    }

    /**
     * Verifica el código TOTP durante el login: recibe email + code (o solo code
     * si ya tienes un 2fa_token) y devuelve token Sanctum si es válido.
     *
     * En tu implementación actual el endpoint espera al menos el email + code.
     */
    public function checkLoginCode(Request $request)
    {
        $request->validate([
            'code'  => 'required'
        ]);

        $account = $request->user();
        $super = SuperAdmin::where('account_id', $account->id)->firstOrFail();

        if (! $super) {
            return $this->error('Usuario no encontrado', 404);
        }

        if (! $super || ! $super->two_factor_enabled) {
            return $this->error('Este usuario no tiene 2FA habilitado', 422);
        }

        if (empty($super->two_factor_secret)) {
            return $this->error('No existe secreto 2FA para este usuario', 422);
        }

        $secret = Crypt::decryptString($super->two_factor_secret);
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            return $this->error('Código incorrecto', 422);
        }

        // Generar token Sanctum sólo si pasó el 2FA
        $token = $account->createToken('auth')->plainTextToken;

        return $this->respond(true, $token, $account, 'Autenticado con 2FA', 200);
    }
}
