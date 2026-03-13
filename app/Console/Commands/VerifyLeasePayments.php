<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeaseContract;
use App\Models\LeasePayment;
use Carbon\Carbon;
use App\Jobs\ImmobilizeVehicleJob;

class VerifyLeasePayments extends Command
{
    protected $signature   = 'lease:verify-payments';
    protected $description = 'Analiza vencimientos, actualiza estatus y bloquea vehículos sin pago';

    public function handle()
    {
        $today        = Carbon::now();
        $currentMonth = $today->format('Y-m');
        $currentDay   = $today->day;

        $this->info("=== Verificación de Pagos: {$today->toDateString()} ===");

        // ─────────────────────────────────────────────────────────────────────
        // PASO 1: active → past_due
        // Contratos activos que ya pasaron su fecha límite (día de pago + gracia)
        // y NO tienen pago registrado en el mes actual.
        // ─────────────────────────────────────────────────────────────────────
        $activeContracts = LeaseContract::where('status', 'active')
            ->where('auto_immobilize', true)
            ->get();

        $pastDueCount = 0;

        foreach ($activeContracts as $contract) {
            $deadlineDay = $contract->payment_day + $contract->grace_days;

            if ($currentDay <= $deadlineDay) {
                continue; // Aún no vence
            }

            $hasPaid = LeasePayment::where('lease_contract_id', $contract->id)
                ->where('month_paid', $currentMonth)
                ->exists();

            if ($hasPaid) {
                continue; // Ya pagó este mes
            }

            // Sin pago y ya venció → past_due + bloqueo
            $contract->update([
                'status'        => 'past_due',
                'is_immobilized' => true,
            ]);

            ImmobilizeVehicleJob::dispatch(
                $contract->vehicle_id,
                "Bloqueo automático: Pago de {$currentMonth} no detectado",
                'stop'
            );

            $pastDueCount++;
            $this->error("  [VENCIDO]  {$contract->contract_number} — bloqueo enviado.");
        }

        $this->info("  → {$pastDueCount} contrato(s) marcados como past_due.");

        // ─────────────────────────────────────────────────────────────────────
        // PASO 2: past_due → legal_process
        // Contratos vencidos con más de 30 días sin pago pasan a proceso legal.
        // ─────────────────────────────────────────────────────────────────────
        $pastDueContracts = LeaseContract::where('status', 'past_due')->get();

        $legalCount = 0;

        foreach ($pastDueContracts as $contract) {
            // Tomamos la fecha del último pago registrado como referencia.
            // Si nunca ha pagado, usamos la fecha de creación del contrato.
            $lastPayment = LeasePayment::where('lease_contract_id', $contract->id)
                ->orderBy('payment_date', 'desc')
                ->first();

            $referenceDate = $lastPayment
                ? Carbon::parse($lastPayment->payment_date)
                : Carbon::parse($contract->created_at);

            $daysLate = $referenceDate->diffInDays($today);

            if ($daysLate <= 30) {
                continue; // Menos de 30 días, se queda en past_due
            }

            $contract->update(['status' => 'legal_process']);

            $legalCount++;
            $this->warn("  [LEGAL]    {$contract->contract_number} — {$daysLate} días sin pago.");
        }

        $this->info("  → {$legalCount} contrato(s) escalados a legal_process.");

        // ─────────────────────────────────────────────────────────────────────
        // PASO 3: Reactivar contratos past_due que sí pagaron este mes
        // (Por si el pago se registró manualmente después del bloqueo)
        // ─────────────────────────────────────────────────────────────────────
        $recoveredContracts = LeaseContract::where('status', 'past_due')->get();

        $recoveredCount = 0;

        foreach ($recoveredContracts as $contract) {
            $hasPaid = LeasePayment::where('lease_contract_id', $contract->id)
                ->where('month_paid', $currentMonth)
                ->exists();

            if (!$hasPaid) {
                continue;
            }

            $contract->update([
                'status'        => 'active',
                'is_immobilized' => false,
            ]);

            // Desbloquear vehículo si estaba bloqueado
            if ($contract->is_immobilized) {
                ImmobilizeVehicleJob::dispatch(
                    $contract->vehicle_id,
                    "Desbloqueo automático: Pago de {$currentMonth} detectado",
                    'resume'
                );
            }

            $recoveredCount++;
            $this->info("  [PAGADO]   {$contract->contract_number} — reactivado.");
        }

        $this->info("  → {$recoveredCount} contrato(s) reactivados por pago recibido.");
        $this->info("=== Verificación completada. ===");

        return Command::SUCCESS;
    }
}