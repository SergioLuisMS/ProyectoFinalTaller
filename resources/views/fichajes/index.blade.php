@extends('layouts.base')

@section('content')

<div class="mb-6">
    <form method="GET" action="{{ route('fichajes.lista') }}" class="flex flex-wrap items-end gap-4 bg-white p-4 rounded shadow border border-gray-200">
        <div>
            <label for="desde" class="block text-sm font-semibold text-gray-700">Desde</label>
            <input type="date" id="desde" name="desde" class="border rounded px-3 py-1 text-sm" value="{{ request('desde') ?? now()->startOfMonth()->format('Y-m-d') }}">
        </div>
        <div>
            <label for="hasta" class="block text-sm font-semibold text-gray-700">Hasta</label>
            <input type="date" id="hasta" name="hasta" class="border rounded px-3 py-1 text-sm" value="{{ request('hasta') ?? now()->format('Y-m-d') }}">
        </div>
        <div>
            <button type="submit" class="bg-[#317080] text-white px-4 py-2 rounded hover:bg-[#285a63] transition">
                Aplicar filtro
            </button>
        </div>
    </form>
</div>


<div class="grid grid-cols-1 gap-6 mb-10">
    <h2 class="text-2xl font-bold text-gray-800">
        Resumen de asistencia del
        {{ \Carbon\Carbon::parse(request('desde') ?? now()->startOfMonth())->format('d/m/Y') }}
        al
        {{ \Carbon\Carbon::parse(request('hasta') ?? now())->format('d/m/Y') }}
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($asistencia['minutosNoAsistidos'] as $nombre => $minutos)
        <div class="bg-white shadow-md rounded-xl p-4 border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-700 mb-1">{{ $nombre }}</h3>
            <p class="text-sm text-gray-600">
                <strong>{{ round($minutos) }}</strong> minutos no asistidos<br>
                <strong>{{ $asistencia['diasConRetraso'][$nombre] ?? 0 }}</strong> días con entrada tardía<br>
                <strong>{{ $asistencia['diasConSalidaTemprana'][$nombre] ?? 0 }}</strong> días con salida anticipada
            </p>
        </div>
        @endforeach
    </div>
</div>

<hr class="my-6 border-gray-300">

<div class="mb-12">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Días con incidencias de entrada o salida</h3>
    <div class="overflow-x-auto rounded-lg border bg-white p-4 shadow">
        <canvas id="graficaDiasIncidencias" style="min-width: 1000px; max-height: 400px;"></canvas>
    </div>
</div>

<div class="mb-12">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Minutos no asistidos por empleado</h3>
    <div class="overflow-x-auto rounded-lg border bg-white p-4 shadow">
        <canvas id="graficaBarrasMinutos" style="min-width: 1000px; max-height: 400px;"></canvas>
    </div>
</div>

<div class="mb-12">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Faltas por empleado en {{ now()->year }}</h3>
    <div class="overflow-x-auto rounded-lg border bg-white p-4 shadow">
        <canvas id="graficaFaltas"></canvas>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Gráfico de faltas
    new Chart(document.getElementById('graficaFaltas').getContext('2d'), {
        type: 'bar',
        data: {
            labels: @json(array_column($faltasPorEmpleado, 'nombre')),
            datasets: [{
                label: 'Faltas acumuladas',
                data: @json(array_column($faltasPorEmpleado, 'faltas')),
                backgroundColor: '#d23e5d'
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                },
                y: {
                    ticks: {
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });

    // Gráfico de minutos no asistidos
    new Chart(document.getElementById('graficaBarrasMinutos').getContext('2d'), {
        type: 'bar',
        data: {
            labels: @json(array_keys($asistencia['minutosNoAsistidos'])),
            datasets: [{
                label: 'Minutos no asistidos',
                data: @json(array_map('round', array_values($asistencia['minutosNoAsistidos']))),
                backgroundColor: '#317080'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.raw} minutos`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        font: {
                            size: 12
                        }
                    }
                },
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Gráfico de días con entrada tardía y salida anticipada
    new Chart(document.getElementById('graficaDiasIncidencias').getContext('2d'), {
        type: 'bar',
        data: {
            labels: @json($empleadosNombres),
            datasets: [{
                    label: 'Días con entrada tardía',
                    data: @json($diasConRetraso),
                    backgroundColor: '#f59e0b'
                },
                {
                    label: 'Días con salida anticipada',
                    data: @json($diasConSalida),
                    backgroundColor: '#7ebdb3'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true,
                    ticks: {
                        font: {
                            size: 12
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    stacked: true
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.raw} días`;
                        }
                    }
                }
            }
        }
    });
</script>
@endpush


@endsection
