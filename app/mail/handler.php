<?php
declare(strict_types=1);

require __DIR__ . '/../../_config/mail.php'; // provides cosigo_mailer()

function cosigo_is_cosigo_host(string $host): bool {
  $host = strtolower(trim($host));
  return $host === 'cosigo.io' || str_ends_with($host, '.cosigo.io');
}

function cosigo_safe_text(string $s, int $max = 2000): string {
  $s = trim($s);
  $s = preg_replace("/\r\n?/", "\n", $s);
  if (strlen($s) > $max) $s = substr($s, 0, $max);
  return $s;
}

function cosigo_rate_limit(string $ip, int $maxPerHour = 20): ?string {
  $dir = '/srv/data/contact';
  if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
    // don't hard-fail if mkdir fails; just skip rate limit
    return null;
  }

  $file = $dir . '/rate.json';
  $now = time();
  $hourAgo = $now - 3600;

  $data = [];
  if (is_file($file)) {
    $raw = @file_get_contents($file);
    $tmp = json_decode($raw ?: '[]', true);
    if (is_array($tmp)) $data = $tmp;
  }

  foreach ($data as $k => $arr) {
    if (!is_array($arr)) { unset($data[$k]); continue; }
    $data[$k] = array_values(array_filter($arr, fn($t) => is_int($t) && $t >= $hourAgo));
  }

  $key = $ip ?: 'unknown';
  $data[$key] = $data[$key] ?? [];
  if (count($data[$key]) >= $maxPerHour) return "Too many requests. Try again later.";

  $data[$key][] = $now;
  @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
  return null;
}

/**
 * Returns: ['ok'=>true] OR ['ok'=>false,'error'=>'...']
 */
function cosigo_handle_contact_post(array $post): array {
  // honeypot
  $gotcha = trim((string)($post['_gotcha'] ?? ''));
  if ( !== "") return ['ok' => true];

  $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

  // rate limit
  $rl = cosigo_rate_limit($ip, 20);
  if ($rl) return ['ok' => false, 'error' => $rl];

  // best-effort origin allowlist (optional)
  $origin  = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
  $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
  $srcHost = '';

  if ($origin) {
    $p = parse_url($origin);
    $srcHost = $p['host'] ?? '';
  } elseif ($referer) {
    $p = parse_url($referer);
    $srcHost = $p['host'] ?? '';
  }
  if ($srcHost !== '' && !cosigo_is_cosigo_host($srcHost)) {
    return ['ok' => false, 'error' => 'Forbidden'];
  }

  // fields
  $name    = cosigo_safe_text((string)($post['name'] ?? ''), 120);
  $email   = cosigo_safe_text((string)($post['email'] ?? ''), 180);
  $subject = cosigo_safe_text((string)($post['subject'] ?? ''), 200);
  $message = cosigo_safe_text((string)($post['message'] ?? ''), 8000);

  $site = cosigo_safe_text((string)($post['site'] ?? ''), 120);
  $page = cosigo_safe_text((string)($post['page'] ?? ''), 500);

  if ($name === '' || $email === '' || $message === '') return ['ok' => false, 'error' => 'Missing fields'];
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Invalid email'];

  if ($subject === '') $subject = 'Cosigo inquiry';
  $tag = $site !== '' ? $site : ($srcHost !== '' ? $srcHost : 'unknown');
  $fullSubject = "[Cosigo] {$tag} — {$subject}";

  try {
    $mail = cosigo_mailer();
    $mail->setFrom('sales@cosigo.io', 'Cosigo Contact');
    $mail->addAddress('sales@cosigo.io');
    $mail->addReplyTo($email, $name);

    $mail->Subject = $fullSubject;
    $mail->Body =
      "Site: {$tag}\n" .
      ($page !== '' ? "Page: {$page}\n" : "") .
      "IP: {$ip}\n\n" .
      "Name: {$name}\n" .
      "Email: {$email}\n\n" .
      $message;

    $mail->send();
    return ['ok' => true];

  } catch (Throwable $e) {
    error_log('Mailer exception: ' . $e->getMessage());
    return ['ok' => false, 'error' => 'Send failed'];
  }
}
