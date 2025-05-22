<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GoTimeCloudService;
use DateTime;
use DateTimeZone;
use GuzzleHttp\Client;
use Carbon\CarbonPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GoTimeController extends Controller
{
    protected $api;

    private function headers($token)
    {
        $now = new DateTime('now', new DateTimeZone('Europe/Madrid'));

        return [
            'X-Token' => $token,
            'DATE' => $now->format('Ymd'),
            'TIME' => $now->format('His'),
            'UTC' => '+2'
        ];
    }

    public function __construct(GoTimeCloudService $api)
    {
        $this->api = $api;
    }

    public function centros()
    {
        $token = $this->api->obtenerToken();
        if (!$token) return response()->json(['error' => 'Token inválido'], 401);

        return response()->json($this->api->obtenerCentros($token));
    }

    public function empleados()
    {
        $token = $this->api->obtenerToken();
        if (!$token) return response()->json(['error' => 'Token inválido'], 401);

        return response()->json($this->api->obtenerEmpleados($token));
    }

    public function fichajes(Request $request)
    {
        $token = $this->api->obtenerToken();
        if (!$token) return response()->json(['error' => 'Token inválido'], 401);

        $desde = $request->input('desde', now()->format('Ymd'));
        $hasta = $request->input('hasta', now()->format('Ymd'));

        return response()->json($this->api->obtenerFichajes($token, $desde, $hasta));
    }

    public function dispositivos()
    {
        $token = $this->api->obtenerToken();
        if (!$token) return response()->json(['error' => 'Token inválido'], 401);

        return response()->json($this->api->obtenerDispositivos($token));
    }

    public function vistaFichajes(GoTimeCloudService $api, Request $request)
    {
        $token = $api->obtenerToken();
        if (!$token) {
            return redirect()->back()->with('error', 'Error al autenticar con GoTime');
        }

        $desde = $request->input('desde') ?? now()->startOfMonth()->format('Ymd');
        $hasta = $request->input('hasta') ?? now()->format('Ymd');

        $fichajes = $api->obtenerFichajes($token, $desde, $hasta)['data'] ?? [];
        $empleados = $api->obtenerEmpleados($token)['data'] ?? [];

        $mapaEmpleados = collect($empleados)->mapWithKeys(function ($emp) {
            return [$emp['code'] => $emp['name'] . ' ' . ($emp['surnames'] ?? '')];
        });

        $fichajesOrdenados = collect($fichajes)
            ->sortBy(fn($item) => $item['date'] . $item['time'])
            ->groupBy(fn($item) => $item['employee'] . '_' . $item['date']);

        $fichajesProcesados = collect();


        foreach ($fichajesOrdenados as $grupo) {
            $contador = 0;
            foreach ($grupo as $fichaje) {
                $fichaje['empleado'] = $mapaEmpleados[$fichaje['employee']] ?? 'Desconocido';
                $fichaje['evento_nombre'] = $contador % 2 === 0 ? 'Entrada' : 'Salida';
                $fichaje['evento_tipo'] = $contador % 2 === 0 ? 0 : 1;
                $fichajesProcesados->push($fichaje);
                $contador++;
            }
        }

        $fichajes = $fichajesProcesados->sortByDesc(fn($f) => $f['date'] . $f['time'])->values();


        // =============================
        // CÁLCULO DE FALTAS POR S004 (Ausencia injustificada)
        // =============================

        $anio = now()->year;
        $token = $api->obtenerToken();
        $faltasPorEmpleado = [];

        foreach ($empleados as $emp) {
            $codigo = $emp['code'];
            $nombreCompleto = strtolower(trim($emp['name'] . ' ' . ($emp['surnames'] ?? '')));

            // Excluir algunos nombres
            $excluirNombres = ['conchi', 'yanira', 'david', 'julio fuentes'];
            if (in_array($nombreCompleto, $excluirNombres)) {
                continue;
            }

            $incidencias = $api->obtenerFaltasEmpleado($token, $codigo, $anio);

            // Cada incidencia ya está filtrada por S004, sumamos sus ocurrencias
            $faltasS004 = count($incidencias);


            $faltasPorEmpleado[] = [
                'nombre' => ucwords($nombreCompleto),
                'faltas' => $faltasS004,
            ];
        }




        $asistencia = $this->calcularMinutosNoAsistidosYMoras($fichajesOrdenados, $mapaEmpleados, $desde, $hasta);


        $empleados = array_keys($asistencia['minutosNoAsistidos']);

        $diasConRetraso = [];
        $diasConSalida = [];

        foreach ($empleados as $nombre) {
            $diasConRetraso[] = $asistencia['diasConRetraso'][$nombre] ?? 0;
            $diasConSalida[] = $asistencia['diasConSalidaTemprana'][$nombre] ?? 0;
        }

        return view('fichajes.index', [
            'fichajes' => $fichajes,
            'faltasPorEmpleado' => $faltasPorEmpleado,
            'asistencia' => $asistencia,
            'diasConRetraso' => $diasConRetraso,
            'diasConSalida' => $diasConSalida,
            'empleadosNombres' => $empleados,
        ]);
    }

    public function obtenerFichajes($token, $desde, $hasta)
    {
        $client = new Client();
        $url = 'https://tallertsa.gotimecloud.com/api/v1/punches';
        $limit = 200;
        $offset = 0;
        $todos = [];

        while (true) {
            $response = $client->get($url, [
                'headers' => $this->headers($token),
                'query' => [
                    'dateFrom' => $desde,
                    'dateTo' => $hasta,
                    'employee' => 'all',
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $fichajes = $data['data'] ?? [];
            $todos = array_merge($todos, $fichajes);

            if (count($fichajes) < $limit) {
                break;
            }

            $offset += $limit;
        }

        return ['data' => $todos];
    }

    public function faltasEmpleado($codigo)
    {
        $token = $this->api->obtenerToken();
        if (!$token) {
            return response()->json(['error' => 'Token inválido'], 401);
        }

        $faltas = $this->api->obtenerFaltasEmpleado($token, $codigo, now()->year, now()->month);
        return response()->json($faltas);
    }

    private function calcularMinutosNoAsistidosYMoras($fichajesOrdenados, $mapaEmpleados, $desde, $hasta)
    {
        $minutosNoAsistidosPorEmpleado = [];
        $diasConRetrasoPorEmpleado = [];
        $diasConSalidaTempranaPorEmpleado = [];

        foreach ($fichajesOrdenados as $clave => $grupo) {
            if ($grupo->count() < 2) {
                continue; // ignorar días con fichaje incompleto (solo entrada o solo salida)
            }

            [$codigoEmpleado, $fecha] = explode('_', $clave);
            $fechaCarbon = \Carbon\Carbon::createFromFormat('Ymd', $fecha);

            // Filtrar por rango de fechas
            if ($fechaCarbon->lt(\Carbon\Carbon::parse($desde)) || $fechaCarbon->gt(\Carbon\Carbon::parse($hasta))) {
                continue;
            }

            $empleadoNombre = $mapaEmpleados[$codigoEmpleado] ?? 'Desconocido';

            $primerFichaje = $grupo->first();
            $ultimoFichaje = $grupo->last();

            $horaEntrada = \Carbon\Carbon::createFromFormat('His', $primerFichaje['time']);
            $horaSalida  = \Carbon\Carbon::createFromFormat('His', $ultimoFichaje['time']);

            $horaEsperadaEntrada = \Carbon\Carbon::createFromFormat('H:i', '08:00');
            $horaEsperadaSalida  = \Carbon\Carbon::createFromFormat('H:i', '16:00');

            $minutosRetraso = $horaEntrada->greaterThan($horaEsperadaEntrada)
                ? $horaEsperadaEntrada->diffInMinutes($horaEntrada)
                : 0;

            $minutosSalidaTemprana = $horaSalida->lessThan($horaEsperadaSalida)
                ? $horaSalida->diffInMinutes($horaEsperadaSalida)
                : 0;

            $minutosNoAsistidos = $minutosRetraso + $minutosSalidaTemprana;

            $minutosNoAsistidosPorEmpleado[$empleadoNombre] = ($minutosNoAsistidosPorEmpleado[$empleadoNombre] ?? 0) + $minutosNoAsistidos;

            if ($minutosRetraso > 0) {
                $diasConRetrasoPorEmpleado[$empleadoNombre] = ($diasConRetrasoPorEmpleado[$empleadoNombre] ?? 0) + 1;
            }
            if ($minutosSalidaTemprana > 0) {
                $diasConSalidaTempranaPorEmpleado[$empleadoNombre] = ($diasConSalidaTempranaPorEmpleado[$empleadoNombre] ?? 0) + 1;
            }
        }

        return [
            'minutosNoAsistidos' => $minutosNoAsistidosPorEmpleado,
            'diasConRetraso' => $diasConRetrasoPorEmpleado,
            'diasConSalidaTemprana' => $diasConSalidaTempranaPorEmpleado,
        ];
    }
}
