<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouvelle demande de support</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color:#171311; background:#f7f2ea; margin:0; padding:24px;">

<div style="max-width:560px; margin:0 auto; background:#ffffff; border:1px solid #d9cab3; border-top:4px solid #7a2232; padding:32px;">

    <div style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#c89b47; margin-bottom:8px;">
        &bull; Termini im &middot; Support
    </div>

    <h1 style="margin:0 0 8px 0; font-size:22px; color:#7a2232; font-weight:normal; font-family: Georgia, 'Times New Roman', serif;">
        Nouvelle demande #{{ $ticket->id }}
    </h1>

    <div style="font-size:13px; color:#716059; margin-bottom:24px;">
        Reçue le {{ $ticket->created_at->format('d/m/Y à H:i') }} &middot; source : <strong>{{ $ticket->source_page }}</strong>
    </div>

    <table style="width:100%; font-size:14px; border-collapse:collapse;">
        <tr>
            <td style="padding:6px 0; color:#716059; width:140px;">Prénom</td>
            <td style="padding:6px 0;">{{ $ticket->first_name }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0; color:#716059;">Téléphone</td>
            <td style="padding:6px 0;"><a href="tel:{{ $ticket->phone }}" style="color:#7a2232;">{{ $ticket->phone }}</a></td>
        </tr>
        @if($ticket->email)
        <tr>
            <td style="padding:6px 0; color:#716059;">Email</td>
            <td style="padding:6px 0;"><a href="mailto:{{ $ticket->email }}" style="color:#7a2232;">{{ $ticket->email }}</a></td>
        </tr>
        @endif
        @if($ticket->user_id)
        <tr>
            <td style="padding:6px 0; color:#716059;">Utilisateur</td>
            <td style="padding:6px 0;">#{{ $ticket->user_id }} (connecté)</td>
        </tr>
        @else
        <tr>
            <td style="padding:6px 0; color:#716059;">Utilisateur</td>
            <td style="padding:6px 0; color:#716059;">— invité —</td>
        </tr>
        @endif
        @if(!empty($ticket->source_context))
        <tr>
            <td style="padding:6px 0; color:#716059; vertical-align:top;">Contexte</td>
            <td style="padding:6px 0;">
                @foreach($ticket->source_context as $k => $v)
                    <div><strong>{{ $k }}</strong> : {{ is_scalar($v) ? $v : json_encode($v) }}</div>
                @endforeach
            </td>
        </tr>
        @endif
    </table>

    <hr style="border:0; border-top:1px solid #d9cab3; margin:24px 0;">

    <div style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#716059; margin-bottom:8px;">
        Message
    </div>

    <div style="font-size:14px; line-height:1.6; background:#f7f2ea; padding:16px; border-left:3px solid #7a2232; white-space:pre-wrap;">{{ $ticket->message }}</div>

    @if(!empty($ticket->attachments))
    <hr style="border:0; border-top:1px solid #d9cab3; margin:24px 0;">
    <div style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#716059; margin-bottom:8px;">
        Pièces jointes ({{ count($ticket->attachments) }})
    </div>
    <ul style="font-size:13px; color:#171311; padding-left:20px;">
        @foreach($ticket->attachments as $file)
            <li>{{ $file['original_name'] ?? basename($file['path']) }} &middot; <span style="color:#716059;">{{ round(($file['size_bytes'] ?? 0) / 1024) }} Ko</span></li>
        @endforeach
    </ul>
    @endif

    <hr style="border:0; border-top:1px solid #d9cab3; margin:24px 0;">

    <div style="font-size:12px; color:#716059; text-align:center;">
        Envoyé depuis l'app Termini im
    </div>
</div>

</body>
</html>
