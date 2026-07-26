<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anda Disebut dalam Diskusi</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; line-height: 1.6; color: #333; }
        .container { width: 90%; max-width: 600px; margin: 20px auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; }
        .header { background-color: #4a90e2; color: #ffffff; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .quote { background-color: #f9f9f9; padding: 15px; border-left: 3px solid #4a90e2; border-radius: 4px; margin: 15px 0; }
        .button { display: inline-block; background-color: #4a90e2; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { background-color: #f4f4f4; color: #777; padding: 20px; text-align: center; font-size: 12px; border-top: 1px solid #e0e0e0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin:0;font-size:20px;">{{ $setting->company_name ?? 'Notula AI' }}</h1>
        </div>
        <div class="content">
            <p>Halo, <strong>{{ $mentionedUser->name }}</strong>,</p>
            <p><strong>{{ $comment->user->name ?? 'Seseorang' }}</strong> menyebut Anda dalam diskusi rapat <strong>{{ $comment->meeting->title }}</strong>:</p>
            <div class="quote">{{ $comment->body }}</div>
            <p style="text-align:center; margin-top: 25px;">
                <a href="{{ url('/meetings/' . $comment->meeting_id) }}" class="button">Lihat Diskusi</a>
            </p>
        </div>
        <div class="footer">
            <p>Email ini dikirim otomatis oleh {{ $setting->company_name ?? 'Notula AI' }}.</p>
        </div>
    </div>
</body>
</html>
