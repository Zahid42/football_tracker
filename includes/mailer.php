<?php
/** Tiny SMTP client (no Composer). Settings live in the admin "E-mail" page. */
declare(strict_types=1);

function abs_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

function ft_mail_configured(): bool { return setting('smtp_host') !== '' && setting('smtp_from') !== ''; }

/** Sends an HTML mail built from paragraph strings (already HTML). Returns [bool ok, ?string error]; always logs. */
function ft_mail_send(string $to, string $subject, string $site, array $paras): array
{
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;border:1px solid #ddd;border-radius:12px;overflow:hidden"><div style="background:#0f2a1d;color:#fff;padding:16px 22px;font-weight:700">' . e($site) . '</div><div style="padding:22px;color:#222;line-height:1.55">';
    $text = $site . "\n\n";
    foreach ($paras as $p) { $html .= '<p style="margin:0 0 14px">' . $p . '</p>'; $text .= html_entity_decode(strip_tags(str_replace(['<br>', '</p>'], "\n", $p))) . "\n\n"; }
    $html .= '</div></div>';
    [$ok, $err] = ft_smtp_send($to, $subject, $text, $html);
    try { db()->prepare('INSERT INTO mail_log (to_email, subject, ok, error, created_at) VALUES (?,?,?,?,NOW())')->execute([$to, mb_substr($subject, 0, 200), $ok ? 1 : 0, $err ? mb_substr($err, 0, 250) : null]); } catch (Throwable $e) {}
    return [$ok, $err];
}

function ft_smtp_send(string $to, string $subject, string $text, string $html): array
{
    $host = setting('smtp_host'); $port = (int)(setting('smtp_port') ?: 587); $sec = setting('smtp_secure', 'tls');
    $user = setting('smtp_user'); $pass = setting('smtp_pass'); $from = setting('smtp_from'); $name = setting('smtp_name') ?: setting('site_title', 'Football Tracker');
    if ($host === '' || $from === '') { return [false, 'E-mail is not set up yet (admin > Settings > E-mail).']; }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { return [false, 'Invalid recipient.']; }
    $fp = null;
    try {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client(($sec === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 12, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) { return [false, 'Cannot connect to ' . $host . ':' . $port . ' (' . $es . ')']; }
        stream_set_timeout($fp, 15);
        $rd = function () use ($fp) { $o = ''; while (($l = fgets($fp, 1024)) !== false) { $o .= $l; if (strlen($l) < 4 || $l[3] === ' ') { break; } } return $o; };
        $cmd = function (string $c, array $ok) use ($fp, $rd) { fwrite($fp, $c . "\r\n"); $r = $rd(); if (!in_array((int)substr($r, 0, 3), $ok, true)) { throw new RuntimeException(trim($r)); } return $r; };
        $r = $rd(); if ((int)substr($r, 0, 3) !== 220) { throw new RuntimeException(trim($r)); }
        $me = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
        $cmd('EHLO ' . $me, [250]);
        if ($sec === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { throw new RuntimeException('TLS handshake failed'); }
            $cmd('EHLO ' . $me, [250]);
        }
        if ($user !== '') { $cmd('AUTH LOGIN', [334]); $cmd(base64_encode($user), [334]); $cmd(base64_encode($pass), [235]); }
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $b = 'b' . bin2hex(random_bytes(8));
        $enc = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $msg = 'Date: ' . date('r') . "\r\nFrom: " . $enc($name) . ' <' . $from . ">\r\nTo: <" . $to . ">\r\nSubject: " . $enc($subject) . "\r\nMessage-ID: <" . bin2hex(random_bytes(10)) . '@' . $me . ">\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$b\"\r\n\r\n"
            . "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
            . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$b--\r\n";
        fwrite($fp, $msg . "\r\n.\r\n");
        $r = $rd(); if ((int)substr($r, 0, 3) !== 250) { throw new RuntimeException(trim($r)); }
        @fwrite($fp, "QUIT\r\n"); fclose($fp);
        return [true, null];
    } catch (Throwable $ex) {
        if (is_resource($fp)) { @fclose($fp); }
        return [false, $ex->getMessage() ?: 'The mail server closed the connection.'];
    }
}
