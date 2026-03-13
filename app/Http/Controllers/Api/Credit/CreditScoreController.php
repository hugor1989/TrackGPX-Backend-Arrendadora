<?php

namespace App\Http\Controllers\Api\Credit;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerRiskScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditScoreController extends Controller
{
    /**
     * Resumen estadístico para las gráficas del Front
     */
    public function getSummary(Request $request)
    {
        $companyId = $request->user()->company_id;

        $stats = DB::table('customer_risk_scores')
            ->join('accounts', 'customer_risk_scores.account_id', '=', 'accounts.id')
            ->where('accounts.company_id', $companyId)
            ->select('score', DB::raw('count(*) as total'))
            ->groupBy('score')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Resumen de score obtenido',
            'data' => [
                'alto'   => $stats->where('score', 'Alto')->first()->total ?? 0,
                'medio'  => $stats->where('score', 'Medio')->first()->total ?? 0,
                'bajo'   => $stats->where('score', 'Bajo')->first()->total ?? 0,
                'promedio_puntos' => DB::table('customer_risk_scores')
                    ->join('accounts', 'customer_risk_scores.account_id', '=', 'accounts.id')
                    ->where('accounts.company_id', $companyId)
                    ->avg('points') ?? 0
            ]
        ]);
    }

    /**
     * Ranking de clientes: los más riesgosos primero
     */
    public function getRankings(Request $request)
    {
        $companyId = $request->user()->company_id;

        $rankings = Account::where('company_id', $companyId)
            ->where('role', 'customer')
            ->with(['riskScore', 'customerProfile'])
            ->join('customer_risk_scores', 'accounts.id', '=', 'customer_risk_scores.account_id')
            ->orderBy('customer_risk_scores.points', 'desc') // Más puntos = más riesgo en este modelo
            ->select('accounts.*')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $rankings
        ]);
    }

    /**
     * Lógica proactiva: Recalcular puntos de un cliente
     * Esta función se llamaría desde un Job o cuando se detecta un evento (pago tardío, etc.)
     */
    public function recalculate($accountId)
    {
        $account = Account::with(['leaseContracts'])->findOrFail($accountId);
        $points = 0;
        $reason = "Recalculo de rutina";

        // Regla 1: Contratos vencidos (Past Due)
        $pastDueCount = $account->leaseContracts->where('status', 'past_due')->count();
        if ($pastDueCount > 0) {
            $points += ($pastDueCount * 50);
            $reason = "Detectados {$pastDueCount} contratos con mora.";
        }

        // Regla 2: Sin contrato activo (Neutral)
        if ($account->leaseContracts->count() === 0) {
            $points = 0;
            $reason = "Cliente sin historial activo.";
        }

        // Determinar etiqueta de texto
        $scoreLabel = 'Bajo';
        if ($points >= 100) $scoreLabel = 'Alto';
        elseif ($points >= 40) $scoreLabel = 'Medio';

        $score = CustomerRiskScore::updateOrCreate(
            ['account_id' => $accountId],
            [
                'points' => $points,
                'score'  => $scoreLabel,
                'reason' => $reason
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Score actualizado',
            'data' => $score
        ]);
    }
}