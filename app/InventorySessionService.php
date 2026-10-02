<?php

namespace App;

use App\Models\InventorySession;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventorySessionService
{
    public function today(): string
    {
        return CarbonImmutable::now(config('attendance.timezone'))->toDateString();
    }

    public function open(string $shiftType): InventorySession
    {
        try {
            return DB::transaction(fn (): InventorySession => $this->createSession($shiftType, $this->today()), 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['session' => 'Session tanggal/shift sudah ada atau session lain masih aktif.']);
        }
    }

    public function close(int $sessionId, bool $manualUsageComplete): InventorySession
    {
        return DB::transaction(function () use ($sessionId, $manualUsageComplete): InventorySession {
            $session = InventorySession::whereKey($sessionId)->lockForUpdate()->firstOrFail();
            $this->closeSession($session, $manualUsageComplete);

            return $session;
        }, 3);
    }

    public function handover(int $sessionId, bool $manualUsageComplete): InventorySession
    {
        try {
            return DB::transaction(function () use ($sessionId, $manualUsageComplete): InventorySession {
                $session = InventorySession::whereKey($sessionId)->lockForUpdate()->firstOrFail();
                if ($session->shift_type !== 'morning') {
                    throw ValidationException::withMessages(['session' => 'Handover hanya dari shift pagi ke shift siang.']);
                }
                $this->closeSession($session, $manualUsageComplete);

                return $this->createSession('afternoon', $session->session_date->toDateString());
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['session' => 'Shift berikutnya sudah ada. Handover dibatalkan.']);
        }
    }

    private function createSession(string $shiftType, string $date): InventorySession
    {
        abort_unless(Auth::check(), 403);
        if (! array_key_exists($shiftType, config('attendance.shifts', []))) {
            throw ValidationException::withMessages(['shift_type' => 'Pilih shift yang tersedia.']);
        }
        if (InventorySession::where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['session' => 'Tutup atau serah-terimakan session aktif terlebih dahulu.']);
        }
        if (InventorySession::whereDate('session_date', $date)->where('shift_type', $shiftType)->exists()) {
            throw ValidationException::withMessages(['session' => 'Session tanggal dan shift ini sudah ada.']);
        }
        $session = new InventorySession;
        $session->shift_type = $shiftType;
        $session->session_date = $date;
        $session->opened_by = Auth::id();
        $session->opened_at = now();
        $session->status = 'open';
        $session->save();

        return $session;
    }

    private function closeSession(InventorySession $session, bool $manualUsageComplete): void
    {
        abort_unless(Auth::check(), 403);
        if (! $manualUsageComplete) {
            throw ValidationException::withMessages(['manual_usage_complete' => 'Konfirmasi seluruh pemakaian manual sudah dicatat.']);
        }
        if ($session->status !== 'open') {
            throw ValidationException::withMessages(['session' => 'Session sudah ditutup.']);
        }
        $session->status = 'closed';
        $session->closed_by = Auth::id();
        $session->closed_at = now();
        $session->save();
    }
}
