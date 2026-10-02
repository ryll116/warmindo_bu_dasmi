<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryMovementRequest;
use App\Http\Requests\Admin\InventorySessionRequest;
use App\InventoryService;
use App\InventorySessionService;
use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Models\StockMovement;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(InventorySessionService $sessions): View
    {
        return view('admin.inventory.index', [
            'items' => InventoryItem::orderBy('item_name')->get(),
            'activeSession' => InventorySession::where('status', 'open')->first(),
            'sessions' => InventorySession::orderByDesc('id')->limit(15)->get(),
            'movements' => StockMovement::with(['item', 'session', 'creator'])->orderByDesc('id')->paginate(30),
            'operationalDate' => $sessions->today(),
            'referenceKey' => 'manual:'.Str::uuid(),
        ]);
    }

    public function open(InventorySessionRequest $request, InventorySessionService $service): RedirectResponse
    {
        try {
            $service->open($request->validated('shift_type'));
        } catch (QueryException $exception) {
            return $this->databaseFailure($exception);
        }

        return to_route('admin.inventory.index')->with('success', 'Session inventory dibuka.');
    }

    public function close(InventorySessionRequest $request, InventorySession $session, InventorySessionService $service): RedirectResponse
    {
        try {
            $service->close($session->id, $request->boolean('manual_usage_complete'));
        } catch (QueryException $exception) {
            return $this->databaseFailure($exception);
        }

        return to_route('admin.inventory.index')->with('success', 'Session inventory ditutup.');
    }

    public function handover(InventorySessionRequest $request, InventorySession $session, InventorySessionService $service): RedirectResponse
    {
        try {
            $service->handover($session->id, $request->boolean('manual_usage_complete'));
        } catch (QueryException $exception) {
            return $this->databaseFailure($exception);
        }

        return to_route('admin.inventory.index')->with('success', 'Shift pagi ditutup dan shift siang dibuka. Saldo diteruskan.');
    }

    public function movement(InventoryMovementRequest $request, InventoryService $service): RedirectResponse
    {
        $data = $request->validated();
        $item = (int) $data['inventory_item_id'];
        $session = (int) $data['inventory_session_id'];
        $quantity = $data['quantity'] ?? '0';
        $notes = $data['notes'] ?? null;
        $key = $data['reference_key'] ?? null;

        try {
            match ($data['movement_type']) {
                'stock_in' => $service->stockIn($item, $session, $quantity, $notes, $key),
                'manual_usage' => $service->manualUsage($item, $session, $quantity, $notes, $key),
                'waste' => $service->waste($item, $session, $quantity, $notes, $key),
                'adjustment' => $service->adjust($item, $session, $quantity, $notes ?? '', $key),
                'opening' => $service->recordOpeningCount($item, $session, $data['physical_stock'], $notes),
                'closing' => $service->recordClosingCount($item, $session, $data['physical_stock'], $notes),
            };
        } catch (QueryException $exception) {
            return $this->databaseFailure($exception);
        }

        return to_route('admin.inventory.index')->with('success', 'Movement inventory tercatat.');
    }

    private function databaseFailure(QueryException $exception): RedirectResponse
    {
        report($exception);

        return back()->withInput()->with('error', 'Inventory belum dapat disimpan. Periksa ledger sebelum mencoba kembali.');
    }
}
