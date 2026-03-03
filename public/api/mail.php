<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', '/srv/data/contact/php-error.log');

require_once __DIR__ . '/../../app/mail/handler.php';

function is_cosigo_host(string $host): bool {
  $host = strtolower(trim($host));
  return $host === 'cosigo.io' || str_ends_with($host, '.cosigo.io');
}

function redirect_ok(string $url): bool {
  $url = trim($url);
  if ($url === '') return false;
  if (str_starts_with($url, '/')) return true;

  $p = parse_url($url);
  if (!$p || ($p['scheme'] ?? '') !== 'https') return false;
  $host = $p['host'] ?? '';
  return is_cosigo_host($host);
}

function build_redirect(array $post, string $fallback): string {
  $redir = (string)($post['_redirect'] ?? '');
  if (!redirect_ok($redir)) return $fallback;

  // absolute https://*.cosigo.io/...
  if (!str_starts_with($redir, '/')) return $redir;

  // relative: map to https://<site>/<path>
  $site = strtolower(trim((string)($post['site'] ?? '')));
  if (!is_cosigo_host($site)) return $fallback;

  return "https://{$site}{$redir}";
}

function go(string $url): void {
  header("Location: {$url}", true, 303);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  go('/thanks.html?status=fail');
}

$fallback_ok   = '/thanks.html?status=ok';
$fallback_fail = '/thanks.html?status=fail';

$result = cosigo_handle_contact_post($_POST);

if (!empty($result['ok'])) {
  go(build_redirect($_POST, $fallback_ok));
}

$err = $result['error'] ?? 'unknown';
error_log('Contact form failed: ' . $err);
go(build_redirect($_POST, $fallback_fail));
