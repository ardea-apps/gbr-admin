<?php
// Sends the "submission accepted" email for the GBR admin page.
// The Resend key lives in config.php on the server (never in the repo).

$ALLOWED_ORIGIN = 'https://apps.garcez.net';
$GH_OWNER = 'ngarcez-burgee';
$GH_REPO  = 'gbr-data';

header('Access-Control-Allow-Origin: ' . $ALLOWED_ORIGIN);
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"error":"method"}'; exit; }

function fail($code, $msg) { http_response_code($code); echo json_encode(['error' => $msg]); exit; }

$config = require __DIR__ . '/config.php';
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) fail(400, 'bad request');

$token = (string)($in['token'] ?? '');
$email = trim((string)($in['email'] ?? ''));
$first = trim(preg_replace('/[\r\n]+/', ' ', (string)($in['first'] ?? '')));
$club  = trim(preg_replace('/[\r\n]+/', ' ', (string)($in['club'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) fail(400, 'bad email');
if ($club === '' || strlen($club) > 200 || strlen($first) > 100) fail(400, 'bad fields');
if ($token === '') fail(401, 'no token');

// Authenticate: the caller must hold a GitHub token with write access to the data repo.
$ch = curl_init("https://api.github.com/repos/$GH_OWNER/$GH_REPO");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 15,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_MAXREDIRS => 3,
  CURLOPT_HTTPHEADER => ["Authorization: token $token", 'User-Agent: gbr-mailer', 'Accept: application/vnd.github+json'],
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);
if ($res === false) fail(502, 'cannot reach GitHub: ' . $cerr);
if ($code !== 200) fail(403, "GitHub check failed (status $code)");
$repo = json_decode((string)$res, true);
if (isset($repo['permissions']) && empty($repo['permissions']['push'])) fail(403, 'token has no write access');

$greeting = $first !== '' ? "Ahoy $first," : 'Ahoy,';
$body = "$greeting

Thank you for submitting the $club burgee to the Global Burgee Register.

We are pleased to confirm that your submission has been received and that the Register has been updated. Your club is now part of our growing directory of yacht and sailing clubs from around the world.

We truly appreciate your collaboration in helping us build the most comprehensive burgee registry available. Contributions like yours are what make this resource valuable for the entire sailing community.

If you have any questions or need to make any changes to the listing, please don't hesitate to reach out.

Fair winds,

The Global Burgee Register Team
https://burgeeregister.org";

$ch = curl_init('https://api.resend.com/emails');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 20,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $config['resend_key'], 'Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode([
    'from' => 'Global Burgee Register <' . $config['from_email'] . '>',
    'to' => [$email],
    'subject' => 'Your Burgee Submission Has Been Accepted — Global Burgee Register',
    'text' => $body,
  ]),
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code >= 200 && $code < 300) { echo '{"ok":true}'; exit; }
fail(502, 'email provider rejected the request');
