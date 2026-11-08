<?php
// api/callback.php
require_once __DIR__ . '/_bootstrap.php';

$id = trim($_GET['payment_id'] ?? '');
if (!$id) json_err('missing_payment_id');

$p = db_fetch("SELECT id, estado, cus FROM payments WHERE id = ?", [$id]);
if (!$p) json_err('not_found', 404);

json_ok([
    'status' => $p['estado'],
    'cus'    => $p['cus'],
]);
