<!doctype html>
<html lang="zh-CN">
  <body style="margin:0;background:#f5f5f4;color:#1c1917;font-family:Arial,sans-serif;">
    <div style="max-width:560px;margin:0 auto;padding:32px 20px;">
      <div style="background:#ffffff;border:1px solid #e7e5e4;border-radius:24px;padding:32px;">
        <p style="margin:0 0 12px;font-size:13px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#e11d48;">Short Video</p>
        <h1 style="margin:0;font-size:28px;line-height:1.2;color:#0c0a09;">{{ $headline }}</h1>
        <p style="margin:16px 0 0;font-size:15px;line-height:1.8;color:#57534e;">{{ $description }}</p>
        <div style="margin:24px 0 0;padding:18px 20px;border-radius:20px;background:#f5f5f4;text-align:center;">
          <span style="display:block;font-size:32px;font-weight:700;letter-spacing:0.32em;color:#0c0a09;">{{ $code }}</span>
        </div>
        <p style="margin:24px 0 0;font-size:14px;line-height:1.8;color:#57534e;">
          验证码 10 分钟内有效，失效时间为 {{ $expiresAt->timezone(config('app.timezone'))->format('H:i') }}。
        </p>
        <p style="margin:12px 0 0;font-size:14px;line-height:1.8;color:#57534e;">
          如果这不是你的操作，可以直接忽略这封邮件。
        </p>
      </div>
    </div>
  </body>
</html>
