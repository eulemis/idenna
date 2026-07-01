<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte NNA - SIRP-NNA</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { font-size: 14px; margin-bottom: 4px; }
        .meta { color: #666; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 3px 5px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>SIRP-NNA — Registro de Niños, Niñas y Adolescentes</h1>
    <p class="meta">Generado: {{ $generatedAt }} | Total: {{ $total ?? $records->count() }} registros</p>
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombres</th>
                <th>Apellidos</th>
                <th>Edad</th>
                <th>Estado</th>
                <th>Fecha registro</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $r)
            <tr>
                <td>{{ $r->registration_code ?? $r->uuid ?? '—' }}</td>
                <td>{{ $r->first_name }}</td>
                <td>{{ $r->last_name }}</td>
                <td>{{ $r->age_years ?? '—' }}</td>
                <td>{{ $r->status?->label() ?? $r->status }}</td>
                <td>{{ $r->registered_at?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
