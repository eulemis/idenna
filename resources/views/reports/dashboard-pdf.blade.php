<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Panel ejecutivo SIRP-NNA</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; color: #103B73; margin: 0 0 4px; }
        .meta { color: #666; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; }
        .grid { width: 100%; }
        .grid td { width: 50%; vertical-align: top; border: none; padding: 0 6px 0 0; }
        .kpi { display: inline-block; margin-right: 16px; }
        .kpi strong { font-size: 14px; color: #103B73; }
    </style>
</head>
<body>
    <h1>Panel ejecutivo — SIRP-NNA</h1>
    <p class="meta">Generado: {{ $generatedAt }}</p>

    <p>
        <span class="kpi">Total NNA: <strong>{{ $stats['kpis']['total'] ?? 0 }}</strong></span>
        <span class="kpi">Hoy: <strong>{{ $stats['kpis']['today'] ?? 0 }}</strong></span>
        <span class="kpi">Borrador: <strong>{{ $stats['kpis']['draft'] ?? 0 }}</strong></span>
        <span class="kpi">Sync: <strong>{{ $stats['kpis']['synced'] ?? 0 }}</strong></span>
    </p>

    <table class="grid"><tr>
        <td>
            <h2>Por estado</h2>
            <table>
                <tr><th>Estado</th><th>Total</th></tr>
                @foreach($stats['by_estado'] ?? [] as $row)
                    <tr><td>{{ $row['name'] }}</td><td>{{ $row['total'] }}</td></tr>
                @endforeach
            </table>
        </td>
        <td>
            <h2>Por género</h2>
            <table>
                <tr><th>Género</th><th>Total</th></tr>
                @foreach($stats['by_gender'] ?? [] as $row)
                    <tr><td>{{ $row['name'] }}</td><td>{{ $row['total'] }}</td></tr>
                @endforeach
            </table>
        </td>
    </tr></table>

    <table class="grid"><tr>
        <td>
            <h2>Por grupo de edad</h2>
            <table>
                <tr><th>Grupo</th><th>Total</th></tr>
                @foreach($stats['by_age_group'] ?? [] as $row)
                    <tr><td>{{ $row['group'] }}</td><td>{{ $row['total'] }}</td></tr>
                @endforeach
            </table>
        </td>
        <td>
            <h2>Por lugar</h2>
            <table>
                <tr><th>Lugar</th><th>Total</th></tr>
                @foreach($stats['by_lugar'] ?? [] as $row)
                    <tr><td>{{ $row['name'] }}</td><td>{{ $row['total'] }}</td></tr>
                @endforeach
            </table>
        </td>
    </tr></table>

    <h2>Productividad por registrador (top 10)</h2>
    <table>
        <tr><th>Registrador</th><th>Total</th></tr>
        @foreach($stats['productivity_by_user'] ?? [] as $row)
            <tr><td>{{ $row['name'] }}</td><td>{{ $row['total'] }}</td></tr>
        @endforeach
    </table>
</body>
</html>
