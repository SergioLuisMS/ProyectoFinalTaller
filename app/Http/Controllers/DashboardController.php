<?php

namespace App\Http\Controllers;

use App\Services\HoldedService;

class DashboardController extends Controller
{
    public function index(HoldedService $holdedService)
    {
        return view('dashboard', [
            'totalSalesYear' => $holdedService->getTotalSalesCurrentYear(),
            'totalPurchasesYear' => $holdedService->getTotalPurchasesCurrentYear(),
            'profitYear' => $holdedService->getProfitCurrentYear(),
            'treasuryBalance' => $holdedService->getTotalTreasuryBalance(),
            'pendingCollectionsYear' => $holdedService->getTotalPendingCollectionsByInvoices(),
            'pendingPaymentsMonth' => $holdedService->getTotalPendingPaymentsByPurchases(),
        ]);
    }
}
