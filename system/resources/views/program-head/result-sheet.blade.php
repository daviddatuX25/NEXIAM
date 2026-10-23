<section>
    <h1>Academic review of assessment results</h1>
    <p>Academic review copy · {{ $program['code'] }} — {{ $program['name'] }}</p>
    <p>Result issuance: {{ ($record['result_released'] ?? false) ? 'Released' : 'Not released' }}</p>
    <h2>{{ $record['name'] }}</h2>
    <p>Application reference: {{ $record['reference_code'] }}</p>
    <table style="width:100%;border-collapse:collapse;margin-top:20px">
        <thead>
            <tr>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #777">Aptitude area</th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #777">Academic review score</th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #777">Scale</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($record['score_profile']['series'] ?? [] as $score)
                <tr>
                    <td style="padding:8px;border-bottom:1px solid #ddd">{{ $score['area_name'] ?? $score['area_code'] }}</td>
                    <td style="padding:8px;border-bottom:1px solid #ddd">{{ $score['score'] ?? 'Unavailable' }}</td>
                    <td style="padding:8px;border-bottom:1px solid #ddd">{{ str_replace('_', ' ', $score['scale'] ?? 'converted score') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="padding:12px">No current scored results are available for this record.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p style="margin-top:24px">This copy is limited to the authorized program review. It is not an admission-clearance or enrollment document.</p>
</section>
