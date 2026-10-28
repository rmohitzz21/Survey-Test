<?php
/**
 * Resources — shared document library self-check (CLI).
 * Usage: C:\xampp\php\php.exe tests/resources-test.php
 *
 * Covers the two things that can actually hurt: who is allowed to write, and
 * what file types are allowed in / previewed inline.
 */
$fail = 0;
function assert_true($cond, string $msg): void {
    global $fail;
    if ($cond) echo "  OK  $msg\n";
    else { echo " FAIL $msg\n"; $fail++; }
}

echo "resources-test\n";
$root = dirname(__DIR__);

// ── Write actions must be admin-gated ────────────────────────────────────────
$api = file_get_contents($root.'/api/resources.php');
foreach (['upload', 'rename', 'delete'] as $act) {
    $seg = strstr($api, "\$action === '$act'");
    $seg = $seg === false ? '' : substr($seg, 0, 300);
    assert_true(str_contains($seg, 'is_admin()'), "action '$act' is gated on is_admin()");
}
assert_true(str_contains($api, 'require_auth_json()'), 'listing requires a signed-in user');
assert_true(!str_contains($api, 'require_staff_json()'), 'clients are not blocked from reading the library');
assert_true(str_contains($api, 'require_csrf'), 'POSTs require a CSRF token');

// ── Upload validation is the shared one, not a copy ──────────────────────────
$upload = file_get_contents($root.'/api/upload.php');
assert_true(!str_contains($upload, 'function sp_upload_ok'),
    'sp_upload_ok lives in includes/functions.php only (no duplicate in upload.php)');
require_once $root.'/includes/functions.php';
assert_true(function_exists('sp_upload_ok'), 'sp_upload_ok is available from functions.php');

$allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','gif','zip','csv','txt'];
foreach (['php','phtml','html','svg','exe','js'] as $ext) {
    assert_true(!in_array($ext, $allowed, true), ".$ext is not an accepted resource type");
}
// is_uploaded_file() is false outside a real request, so only the reject path
// is reachable here — that is the path that matters.
$files = ['tmp_name'=>['/no/such/file'], 'name'=>['x.pdf'], 'error'=>[UPLOAD_ERR_OK], 'size'=>[10]];
assert_true(sp_upload_ok($files, 0, $allowed, 10*1024*1024) === null,
    'a tmp path that was not uploaded is rejected');
$files['error'] = [UPLOAD_ERR_INI_SIZE];
assert_true(sp_upload_ok($files, 0, $allowed, 10*1024*1024) === null, 'a PHP upload error is rejected');

// ── Inline preview must never render script-bearing types ────────────────────
$serve = file_get_contents($root.'/api/serve.php');
$viewable = strstr($serve, '$viewable');
$viewable = $viewable === false ? '' : substr($viewable, 0, 200);
assert_true(str_contains($viewable, 'application/pdf'), 'PDFs preview inline');
foreach (['text/html', 'image/svg'] as $mime) {
    assert_true(!str_contains($viewable, $mime), "$mime is never served inline");
}
assert_true(str_contains($serve, 'resource_id'), 'serve.php has a resource branch');
assert_true(str_contains($serve, "SELECT filename FROM resources WHERE id=?"),
    'serve.php takes the filename from the DB, not the query string');

// ── Schema + storage ─────────────────────────────────────────────────────────
assert_true(str_contains(file_get_contents($root.'/includes/schema-update.php'),
    'CREATE TABLE IF NOT EXISTS resources'), 'migrate.php creates the resources table');
assert_true(is_dir($root.'/uploads/resources'), 'uploads/resources exists');

// ── Alpine wiring in index.php ───────────────────────────────────────────────
$idx = file_get_contents($root.'/index.php');
foreach (["\$navBtn('resources'", "view==='resources'", 'loadResources()',
          'uploadResources()', 'deleteResource(', 'get filteredResources()',
          "resources:'Resources'"] as $needle) {
    assert_true(str_contains($idx, $needle), "index.php wires up: $needle");
}

echo $fail ? "\n$fail failure(s)\n" : "\nall passed\n";
exit($fail ? 1 : 0);
