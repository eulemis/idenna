            <tr>
                <td>{{ $nna->registration_code ?? $nna->uuid ?? '—' }}</td>
                <td>{{ $nna->first_name }}</td>
                <td>{{ $nna->last_name }}</td>
                <td>{{ $nna->age_years ?? '—' }}</td>
                <td>{{ $nna->status?->label() ?? $nna->status ?? '—' }}</td>
                <td>{{ $nna->registered_at?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
