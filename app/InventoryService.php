<?php

namespace App;

use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Models\StockMovement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function stockIn(int $itemId, int $sessionId, string $quantity, ?string $notes = null, ?string $referenceKey = null): StockMovement
    {
        return $this->mutate($itemId, $sessionId, 'stock_in', $this->positive($quantity), null, $notes, $referenceKey);
    }

    public function manualUsage(int $itemId, int $sessionId, string $quantity, ?string $notes = null, ?string $referenceKey = null): StockMovement
    {
        return $this->mutate($itemId, $sessionId, 'manual_usage', -$this->positive($quantity), null, $notes, $referenceKey);
    }

    public function waste(int $itemId, int $sessionId, string $quantity, ?string $notes = null, ?string $referenceKey = null): StockMovement
    {
        return $this->mutate($itemId, $sessionId, 'waste', -$this->positive($quantity), null, $notes, $referenceKey);
    }

    public function adjust(int $itemId, int $sessionId, string $quantity, string $notes, ?string $referenceKey = null): StockMovement
    {
        if (trim($notes) === '') {
            throw ValidationException::withMessages(['notes' => 'Alasan adjustment wajib diisi.']);
        }
        $units = InventoryQuantity::parse($quantity);
        if ($units === 0) {
            throw ValidationException::withMessages(['quantity' => 'Adjustment harus mengubah saldo.']);
        }

        return $this->mutate($itemId, $sessionId, 'adjustment', $units, null, $notes, $referenceKey);
    }

    public function recordOpeningCount(int $itemId, int $sessionId, string $physicalStock, ?string $notes = null): StockMovement
    {
        return $this->mutate($itemId, $sessionId, 'opening', 0, $this->physical($physicalStock), $notes, "count:{$sessionId}:{$itemId}:opening");
    }

    public function recordClosingCount(int $itemId, int $sessionId, string $physicalStock, ?string $notes = null): StockMovement
    {
        return $this->mutate($itemId, $sessionId, 'closing', 0, $this->physical($physicalStock), $notes, "count:{$sessionId}:{$itemId}:closing");
    }

    private function positive(string $quantity): int
    {
        $units = InventoryQuantity::parse($quantity);
        if ($units <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah masuk, pemakaian, atau waste harus lebih dari nol.']);
        }

        return $units;
    }

    private function physical(string $quantity): string
    {
        $units = InventoryQuantity::parse($quantity);
        if ($units < 0) {
            throw ValidationException::withMessages(['physical_stock' => 'Stok fisik tidak boleh negatif.']);
        }

        return InventoryQuantity::format($units);
    }

    private function mutate(int $itemId, int $sessionId, string $type, int $units, ?string $physical, ?string $notes, ?string $referenceKey): StockMovement
    {
        if ($referenceKey !== null && (! preg_match('/\A[a-zA-Z0-9:_-]{1,150}\z/', $referenceKey))) {
            throw ValidationException::withMessages(['reference_key' => 'Reference key harus 1-150 karakter huruf, angka, titik dua, strip, atau underscore.']);
        }
        $notes = $notes === null ? null : trim($notes);
        $actorId = Auth::id();
        $quantity = InventoryQuantity::format($units);

        try {
            return DB::transaction(function () use ($itemId, $sessionId, $type, $units, $physical, $notes, $referenceKey, $actorId, $quantity): StockMovement {
                $session = InventorySession::whereKey($sessionId)->lockForUpdate()->firstOrFail();
                $item = InventoryItem::whereKey($itemId)->lockForUpdate()->firstOrFail();
                if ($referenceKey !== null) {
                    $existing = StockMovement::where('reference_key', $referenceKey)->lockForUpdate()->first();
                    if ($existing) {
                        return $this->replay($existing, $itemId, $sessionId, $type, $quantity, $physical, $notes, $actorId);
                    }
                }
                if ($session->status !== 'open') {
                    throw ValidationException::withMessages(['session' => 'Movement baru hanya dapat dicatat pada session aktif.']);
                }
                if (! $item->is_active) {
                    throw ValidationException::withMessages(['inventory_item_id' => 'Item inventory tidak aktif.']);
                }

                $before = InventoryQuantity::parse($item->current_stock);
                $after = $before + $units;
                if ($after < 0 || $after > InventoryQuantity::MAX) {
                    throw ValidationException::withMessages(['quantity' => 'Saldo tidak boleh negatif atau melebihi kapasitas stok.']);
                }

                if ($units !== 0) {
                    $item->current_stock = InventoryQuantity::format($after);
                    $item->save();
                }
                $movement = new StockMovement;
                $movement->inventory_item_id = $itemId;
                $movement->inventory_session_id = $sessionId;
                $movement->movement_type = $type;
                $movement->quantity = $quantity;
                $movement->stock_before = InventoryQuantity::format($before);
                $movement->stock_after = InventoryQuantity::format($after);
                $movement->physical_stock = $physical;
                $movement->notes = $notes;
                $movement->reference_key = $referenceKey;
                $movement->created_by = $actorId;
                $movement->save();

                return $movement;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $referenceKey === null ? null : StockMovement::where('reference_key', $referenceKey)->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->replay($existing, $itemId, $sessionId, $type, $quantity, $physical, $notes, $actorId);
        }
    }

    private function replay(StockMovement $existing, int $itemId, int $sessionId, string $type, string $quantity, ?string $physical, ?string $notes, ?int $actorId): StockMovement
    {
        if ($existing->inventory_item_id !== $itemId || $existing->inventory_session_id !== $sessionId
            || $existing->movement_type !== $type || $existing->quantity !== $quantity
            || $existing->physical_stock !== $physical || $existing->notes !== $notes || $existing->created_by !== $actorId) {
            throw ValidationException::withMessages(['reference_key' => 'Reference key sudah dipakai untuk operasi yang berbeda.']);
        }

        return $existing;
    }
}
