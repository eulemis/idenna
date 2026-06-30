<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte NNA - SIRP-NNA</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>SIRP-NNA — Registro de Niños, Niñas y Adolescentes</h1>
    <p class="meta">Generado: {{ $generatedAt }} | Total: {{ $records->count() }} registros</p>
    <table>
        <thead>
            <tr>
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
