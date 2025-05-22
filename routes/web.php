<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\FaltasController;
use App\Http\Controllers\OrdenController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmpleadoDashboardController;
use App\Http\Controllers\HoldedController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\VerificarRol;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\GoTimeController;
use App\Services\HoldedService;

Route::resourceVerbs(['create' => 'crear', 'edit' => 'editar']);
Str::singular('ordenes');

Route::get('/', fn() => redirect()->route('dashboard'));

// DASHBOARD CON CONTROL DE ROLES
Route::get('/dashboard', function () {
    $user = Auth::user();

    if ($user?->rol === 'empleado') {
        return redirect()->route('empleado.dashboard');
    }

    if ($user?->rol === 'admin') {
        return app(DashboardController::class)->index(app(HoldedService::class));
    }

    return view('limbo');
})->middleware(['auth', 'verified', VerificarRol::class])->name('dashboard');

// Rutas exclusivas para empleados
Route::middleware(['auth', VerificarRol::class])->group(function () {
    Route::get('/dashboard-empleado', function () {
        return view('empleado.dashboard');
    })->name('empleado.dashboard');

    Route::post('/tareas/{id}/guardar-tiempo', [TareaController::class, 'guardarTiempo'])->name('tareas.guardarTiempo');
    Route::post('/tareas/{tarea}/finalizar', [TareaController::class, 'finalizar']);
    Route::get('/tus-tareas', [EmpleadoDashboardController::class, 'tareas'])->name('empleado.tareas');
});

// Rutas exclusivas para administradores
Route::middleware(['auth', VerificarRol::class, AdminOnly::class])->group(function () {

    // Gestión de usuarios pendientes
    Route::get('/usuarios/pendientes', [UserController::class, 'pendientes'])->name('usuarios.pendientes');
    Route::post('/usuarios/asignar-rol/{user}', [UserController::class, 'asignarRol'])->name('usuarios.asignarRol');

    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Empleados
    Route::resource('empleados', EmpleadoController::class)->except(['show', 'destroy']);

    // Faltas
    Route::get('/faltas', [FaltasController::class, 'index'])->name('asistencias.index');
    Route::post('/faltas', [FaltasController::class, 'store'])->name('faltas.store');
    Route::get('/faltas/grafico/{empleado}', [FaltasController::class, 'grafico'])->name('faltas.grafico');
    Route::get('/faltas/graficas', [FaltasController::class, 'graficasGlobal'])->name('faltas.graficas.global');
    Route::get('/faltas/graficas/datos/{empleado}', [FaltasController::class, 'datosGrafico'])->name('faltas.grafico.datos');
    Route::get('/faltas/graficas/faltas-mensuales/{empleado}', [FaltasController::class, 'faltasAnuales']);
    Route::get('/faltas/crear', [FaltasController::class, 'crearManual'])->name('faltas.crear.manual');
    Route::post('/faltas/crear', [FaltasController::class, 'guardarManual'])->name('faltas.guardar.manual');
    Route::get('/graficas/tareas-por-empleado', [FaltasController::class, 'tareasPorEmpleadoMes']);
    Route::get('/graficas/ordenes-por-empleado', [FaltasController::class, 'ordenesPorEmpleadoMes']);
    Route::get('/faltas/graficas/tareas-mensuales', [FaltasController::class, 'datosGraficoTareasMes'])->name('faltas.grafico.tareas-mensuales');
    Route::get('/faltas/graficas/ordenes-mensuales', [FaltasController::class, 'ordenesMensualesPorEmpleado'])->name('faltas.grafico.ordenes-mensuales');

    // Órdenes
    Route::resource('ordenes', OrdenController::class)->parameters(['ordenes' => 'orden']);
    Route::get('/ordenes/datos/mensuales', [OrdenController::class, 'datosMensuales'])->name('ordenes.datos.mensuales');

    // Tareas
    Route::resource('tareas', TareaController::class)->parameters(['tareas' => 'tarea']);
    Route::get('/tareas', [TareaController::class, 'index'])->name('tareas.index');
    Route::get('/tareas/crear', [TareaController::class, 'create'])->name('tareas.create');
    Route::post('/tareas', [TareaController::class, 'store'])->name('tareas.store');
    Route::patch('/tareas/{tarea}/estado', [TareaController::class, 'cambiarEstado'])->name('tareas.cambiarEstado');
    Route::patch('/tareas/{tarea}/iniciar-cronometro', [TareaController::class, 'iniciarCronometro'])->name('tareas.iniciar');
    Route::patch('/tareas/{tarea}/finalizar-cronometro', [TareaController::class, 'finalizarCronometro'])->name('tareas.finalizar');
    Route::post('/tareas/{tarea}/marcar-en-curso', [TareaController::class, 'marcarEnCurso'])->name('tareas.marcarEnCurso');
    Route::post('/tareas/{tarea}/actualizar-tiempo', [TareaController::class, 'actualizarTiempo'])->name('tareas.actualizarTiempo');
    Route::patch('/registro-entrada/{id}/actualizar-hora', [FaltasController::class, 'actualizarHora'])->name('registroEntrada.actualizarHora');

    Route::get('/holded/buscar-contacto', [HoldedController::class, 'buscarContacto']);

    // Rutas funcionales de Holded
    // Debug: Recibos de venta
    Route::get('/debug-sales-receipts', function (App\Services\HoldedService $holded) {
        return $holded->getSalesReceipts();
    });

    // Debug: Pedidos de venta
    Route::get('/debug-sales-orders', function (App\Services\HoldedService $holded) {
        return $holded->getSalesOrders();
    });

    // Debug: Notas de crédito
    Route::get('/debug-credit-notes', function (App\Services\HoldedService $holded) {
        return $holded->getCreditNotes();
    });

    // Debug: Proformas (opcional)
    Route::get('/debug-proforms', function (App\Services\HoldedService $holded) {
        return $holded->getProforms();
    });

    // Debug: Albaranes (opcional)
    Route::get('/debug-waybills', function (App\Services\HoldedService $holded) {
        return $holded->getWaybills();
    });

    // Debug: Presupuestos (opcional)
    Route::get('/debug-estimates', function (App\Services\HoldedService $holded) {
        return $holded->getEstimates();
    });


    Route::get('/test-holded', fn(HoldedService $holded) => $holded->getInvoices());
    Route::get('/test-ventas', fn(HoldedService $holded) => response()->json(['total_sales_current_year' => $holded->getTotalSalesCurrentYear() . ' €']));
    Route::get('/debug-invoices', fn(HoldedService $holded) => $holded->getInvoices());
    Route::get('/test-gastos', fn(HoldedService $holded) => response()->json(['total_purchases_current_year' => $holded->getTotalPurchasesCurrentYear() . ' €']));
    Route::get('/debug-purchases', fn(HoldedService $holded) => $holded->getPurchaseInvoices());
    Route::get('/debug-treasury', fn(HoldedService $holded) => $holded->getTreasury());
    Route::get('/test-saldo-tesoreria', fn(HoldedService $holded) => response()->json(['total_treasury_balance' => $holded->getTotalTreasuryBalance() . ' €']));
    Route::get('/test-cobros-pendientes-filtrados', fn(HoldedService $holded) => response()->json(['total_pending_collections_year' => $holded->getTotalPendingCollectionsByInvoices() . ' €']));
    Route::get('/test-pagos-pendientes-filtrados', fn(HoldedService $holded) => response()->json(['total_pending_payments_month' => $holded->getTotalPendingPaymentsByPurchases() . ' €']));

    // FICHAJES
    Route::get('/centros-gotime', [GoTimeController::class, 'mostrarCentros']);


    Route::get('/gotime/centros', [GoTimeController::class, 'centros']);
    Route::get('/gotime/empleados', [GoTimeController::class, 'empleados']);
    Route::get('/gotime/fichajes', [GoTimeController::class, 'fichajes']); // ?desde=20250501&hasta=20250520
    Route::get('/gotime/dispositivos', [GoTimeController::class, 'dispositivos']);

    Route::get('/fichajes', [GoTimeController::class, 'vistaFichajes'])->name('fichajes.lista');

    Route::get('/gotime/faltas/{codigo}', [GoTimeController::class, 'faltasEmpleado']);

});

// Rutas de login, registro, etc.
require __DIR__ . '/auth.php';
