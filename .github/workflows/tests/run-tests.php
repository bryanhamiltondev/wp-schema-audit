<?php
/**
 * Standalone CI self-test for the Schema Audit engine.
 *
 * Usage:
 *   php tests/run-tests.php pass  - exits 0 only if the good fixture is clean.
 *   php tests/run-tests.php fail  - exits 0 only if the broken fixture is rejected.
 *
 * The "fail" direction is asserted as strictly as the "pass" direction: the
 * broken fixture lists the exact findings the engine MUST produce, so a bug
 * that makes the auditor blind (passing everything) or trigger-happy
 * (failing everything) cannot slip through CI.
 *
 * @package Schema_Audit
 */

require_once __DIR__ . '/../includes/class-schema-audit-engine.php';

if ($argc !== 2 || !in_array($argv[1], array('pass', 'fail'), true)) {
    fwrite(STDERR, "Usage: php tests/run-tests.php pass|fail\n");
    exit(2);
}
$mode = $argv[1];

$fixture = $mode === 'pass'
    ? __DIR__ . '/fixtures/good.html'
    : __DIR__ . '/fixtures/broken.html';

$html = file_get_contents($fixture);
if ($html === false) {
    fwrite(STDERR, "Cannot read fixture: {$fixture}\n");
    exit(2);
}

$findings = Schema_Audit_Engine::audit_html($html);
$errors   = array();
foreach ($findings as $finding) {
    foreach ($finding['errors'] as $error) {
        $errors[] = sprintf('[block %d] %s', $finding['block'], $error);
    }
}

if ($mode === 'pass') {
    if (empty($errors)) {
        echo "PASS: good fixture is clean\n";
        exit(0);
    }
    echo "FAIL: good fixture produced findings it should not report:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

// Fail mode: every expected finding must actually appear.
$expected = array(
    '[MusicEvent] missing required property "startDate"',
    '[MusicEvent] missing required property "performer"',
    '[Organization] missing required property "url"',
    '[FAQPage] missing required property "mainEntity"',
    'references unresolved @id "#dj-missing"',
    '[JSON] unparsable JSON-LD block',
);

$missing = array();
foreach ($expected as $needle) {
    $found = false;
    foreach ($errors as $error) {
        if (strpos($error, $needle) !== false) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $missing[] = $needle;
    }
}

if (empty($missing)) {
    printf(
        "PASS: broken fixture correctly rejected (%d finding(s), %d asserted)\n",
        count($errors),
        count($expected)
    );
    exit(0);
}

echo "FAIL: broken fixture was not fully rejected - the auditor may be going blind:\n";
foreach ($errors as $error) {
    echo "  found:   {$error}\n";
}
foreach ($missing as $needle) {
    echo "  missing: {$needle}\n";
}
exit(1);
