<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Relevé de notes - {{ $student->matricule }}</title>
    <style>
        @page { margin: 28px 32px; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1f2933;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #1f4e79;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .school-name {
            font-size: 16px;
            font-weight: bold;
            color: #1f4e79;
        }

        .doc-title {
            font-size: 13px;
            font-weight: bold;
            text-align: right;
            color: #1f4e79;
        }

        .doc-subtitle {
            font-size: 10px;
            text-align: right;
            color: #52606d;
        }

        .info-table {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 3px 6px;
            font-size: 10.5px;
            vertical-align: top;
        }

        .info-label {
            color: #52606d;
            width: 110px;
        }

        .info-value {
            font-weight: bold;
        }

        .ue-block {
            margin-bottom: 12px;
        }

        .ue-title {
            background-color: #1f4e79;
            color: #ffffff;
            font-weight: bold;
            font-size: 11px;
            padding: 5px 8px;
        }

        table.notes-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }

        table.notes-table th {
            background-color: #dbe6f3;
            color: #1f4e79;
            font-size: 9.5px;
            text-align: center;
            padding: 5px 4px;
            border: 1px solid #b9cde3;
        }

        table.notes-table td {
            font-size: 10px;
            text-align: center;
            padding: 5px 4px;
            border: 1px solid #dfe7f0;
        }

        table.notes-table td.subject-name {
            text-align: left;
            font-weight: bold;
        }

        .ue-footer td {
            font-weight: bold;
            background-color: #f0f4f9;
        }

        .status-pending { color: #9aa5b1; font-style: italic; }
        .status-absent { color: #d64545; font-weight: bold; }
        .validated-yes { color: #1a7f37; font-weight: bold; }
        .validated-no  { color: #d64545; font-weight: bold; }

        .summary-table {
            width: 100%;
            margin-top: 16px;
            border-collapse: collapse;
        }

        .summary-table td {
            border: 1px solid #b9cde3;
            padding: 8px 10px;
            font-size: 11px;
        }

        .summary-label {
            background-color: #dbe6f3;
            color: #1f4e79;
            font-weight: bold;
            width: 55%;
        }

        .summary-value {
            font-weight: bold;
            font-size: 13px;
            text-align: center;
        }

        .footer-note {
            margin-top: 24px;
            font-size: 9px;
            color: #7b8794;
            text-align: center;
        }

        .signature-table {
            width: 100%;
            margin-top: 40px;
        }

        .signature-table td {
            width: 50%;
            font-size: 10px;
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #9aa5b1;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div class="school-name">NoteTrack</div>
                <div style="font-size: 9.5px; color: #52606d;">Établissement d'enseignement supérieur</div>
            </td>
            <td style="width: 40%;">
                <div class="doc-title">RELEVÉ DE NOTES</div>
                <div class="doc-subtitle">
                    {{ $semester_id ? 'Bulletin semestriel' : 'Bulletin annuel' }}
                    @if($school_year)
                        &mdash; Année {{ $school_year->label }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="info-label">Étudiant</td>
            <td class="info-value">{{ $student->first_name }} {{ $student->last_name }}</td>
            <td class="info-label">Matricule</td>
            <td class="info-value">{{ $student->matricule }}</td>
        </tr>
        <tr>
            <td class="info-label">Classe</td>
            <td class="info-value">{{ $classe->label ?? 'N/A' }}</td>
            <td class="info-label">Promotion</td>
            <td class="info-value">{{ $promotion->label ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">Année scolaire</td>
            <td class="info-value">{{ $school_year->label ?? 'N/A' }}</td>
            <td class="info-label">Édité le</td>
            <td class="info-value">{{ $generated_at->format('d/m/Y à H:i') }}</td>
        </tr>
    </table>

    @forelse($ues as $ue)
        <div class="ue-block">
            <div class="ue-title">{{ $ue['code'] }} &mdash; {{ $ue['label'] }}</div>

            <table class="notes-table">
                <thead>
                    <tr>
                        <th style="width: 26%; text-align: left;">Matière</th>
                        <th style="width: 16%;">Enseignant</th>
                        <th style="width: 8%;">Coef.</th>
                        <th style="width: 8%;">Crédits</th>
                        <th style="width: 10%;">Test</th>
                        <th style="width: 10%;">Examen</th>
                        <th style="width: 10%;">Rattrapage</th>
                        <th style="width: 6%;">Moy.</th>
                        <th style="width: 6%;">Validé</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ue['subjects'] as $subject)
                        <tr>
                            <td class="subject-name">{{ $subject['name'] }}</td>
                            <td>{{ $subject['teacher'] ?? '-' }}</td>
                            <td>{{ $subject['coefficient'] }}</td>
                            <td>{{ $subject['credits'] }}</td>
                            @foreach(['test', 'exam', 'makeup'] as $key)
                                @php($note = $subject[$key])
                                <td>
                                    @if($note === null)
                                        -
                                    @elseif($note['status'] === 'PENDING')
                                        <span class="status-pending">en attente</span>
                                    @else
                                        {{ number_format($note['value'], 2) }}
                                    @endif
                                </td>
                            @endforeach
                            <td><strong>{{ $subject['average'] !== null ? number_format($subject['average'], 2) : '-' }}</strong></td>
                            <td>
                                @if($subject['validated'] === true)
                                    <span class="validated-yes">Oui</span>
                                @elseif($subject['validated'] === false)
                                    <span class="validated-no">Non</span>
                                @else
                                    <span class="status-pending">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="ue-footer">
                        <td colspan="6" style="text-align: right;">Moyenne UE ({{ $ue['total_credits'] }} crédits)</td>
                        <td colspan="1">{{ $ue['ue_average'] !== null ? number_format($ue['ue_average'], 2) : '-' }}</td>
                        <td colspan="2">
                            @if($ue['ue_validated'] === true)
                                <span class="validated-yes">Validée</span>
                            @elseif($ue['ue_validated'] === false)
                                <span class="validated-no">Non validée</span>
                            @else
                                <span class="status-pending">-</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @empty
        <p>Aucune matière disponible pour cet étudiant sur la période sélectionnée.</p>
    @endforelse

    <table class="summary-table">
        <tr>
            <td class="summary-label">Moyenne générale</td>
            <td class="summary-value">{{ $general_average !== null ? number_format($general_average, 2) . ' / 20' : 'Non calculable' }}</td>
        </tr>
        <tr>
            <td class="summary-label">Mention</td>
            <td class="summary-value">{{ $mention }}</td>
        </tr>
    </table>

    <table class="signature-table">
        <tr>
            <td>Le responsable pédagogique</td>
            <td>Cachet de l'établissement</td>
        </tr>
    </table>

    <div class="footer-note">
        Document généré automatiquement par NoteTrack le {{ $generated_at->format('d/m/Y à H:i') }}.
        Les notes en attente de publication n'entrent pas dans le calcul des moyennes.
    </div>

</body>
</html>
