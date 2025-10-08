<?php
/**
 * AI Web Site - Server Diagnostic Tool
 * Verifică configurația PHP și limitele serverului
 *
 * Accesează: https://ai-web.site/wp-content/plugins/ai-web-site-plugin/server-diagnostic.php
 */

// Securitate: verifică dacă este accesat direct
if (!defined('ABSPATH')) {
    // Nu e WordPress, dar permitem accesul pentru diagnostic
}

// Header pentru afișare frumoasă
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Web Site - Server Diagnostic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            color: #333;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .header p { opacity: 0.9; font-size: 16px; }
        .content { padding: 30px; }
        .section {
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .section-title {
            background: #f5f5f5;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 18px;
            color: #333;
            border-bottom: 2px solid #667eea;
        }
        .section-content { padding: 20px; }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            font-weight: 600;
            color: #555;
            flex: 1;
        }
        .info-value {
            flex: 2;
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        .status-good { color: #10b981; font-weight: 600; }
        .status-warning { color: #f59e0b; font-weight: 600; }
        .status-bad { color: #ef4444; font-weight: 600; }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .recommendation {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin-top: 15px;
            border-radius: 4px;
        }
        .recommendation strong { color: #92400e; }
        .footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 AI Web Site - Server Diagnostic</h1>
            <p>Verificare configurație PHP și limite server</p>
        </div>

        <div class="content">
            <!-- PHP Version & SAPI -->
            <div class="section">
                <div class="section-title">📦 Informații PHP</div>
                <div class="section-content">
                    <div class="info-row">
                        <div class="info-label">Versiune PHP</div>
                        <div class="info-value"><?php echo PHP_VERSION; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Server API (SAPI)</div>
                        <div class="info-value">
                            <?php
                            $sapi = php_sapi_name();
$is_fpm = (strpos($sapi, 'fpm') !== false || strpos($sapi, 'cgi') !== false);
echo $sapi;
if ($is_fpm) {
    echo ' <span class="badge badge-warning">FPM/CGI - .htaccess NU funcționează</span>';
} else {
    echo ' <span class="badge badge-success">mod_php - .htaccess funcționează</span>';
}
?>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Loaded Configuration File</div>
                        <div class="info-value"><?php echo php_ini_loaded_file() ?: 'N/A'; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Additional .ini files</div>
                        <div class="info-value"><?php echo php_ini_scanned_files() ?: 'None'; ?></div>
                    </div>
                </div>
            </div>

            <!-- Memory & Limits -->
            <div class="section">
                <div class="section-title">💾 Limite de Memorie și Execuție</div>
                <div class="section-content">
                    <?php
                    $memory_limit = ini_get('memory_limit');
$memory_bytes = return_bytes($memory_limit);
$memory_ok = $memory_bytes >= 512 * 1024 * 1024; // 512MB

$max_execution = ini_get('max_execution_time');
$execution_ok = $max_execution >= 300 || $max_execution == 0;

$post_max = ini_get('post_max_size');
$upload_max = ini_get('upload_max_filesize');
?>
                    <div class="info-row">
                        <div class="info-label">memory_limit</div>
                        <div class="info-value">
                            <?php echo $memory_limit; ?>
                            <?php if ($memory_ok): ?>
                                <span class="badge badge-success">OK pentru 1.6MB</span>
                            <?php else: ?>
                                <span class="badge badge-danger">PREA MIC!</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">max_execution_time</div>
                        <div class="info-value">
                            <?php echo $max_execution == 0 ? 'Unlimited' : $max_execution . 's'; ?>
                            <?php if ($execution_ok): ?>
                                <span class="badge badge-success">OK</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Poate fi prea mic</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">post_max_size</div>
                        <div class="info-value"><?php echo $post_max; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">upload_max_filesize</div>
                        <div class="info-value"><?php echo $upload_max; ?></div>
                    </div>

                    <?php if (!$memory_ok): ?>
                    <div class="recommendation">
                        <strong>⚠️ RECOMANDARE:</strong> Crește memory_limit la minimum 512M pentru a suporta răspunsuri JSON de 1.6MB!
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Output Buffering -->
            <div class="section">
                <div class="section-title">📤 Output Buffering & Compression</div>
                <div class="section-content">
                    <?php
$output_buffering = ini_get('output_buffering');
$buffering_enabled = ($output_buffering && $output_buffering != 'Off' && $output_buffering != '0');

$zlib_compression = ini_get('zlib.output_compression');
$compression_enabled = ($zlib_compression && $zlib_compression != 'Off' && $zlib_compression != '0');
?>
                    <div class="info-row">
                        <div class="info-label">output_buffering</div>
                        <div class="info-value">
                            <?php echo $output_buffering ?: 'Off'; ?>
                            <?php if ($buffering_enabled): ?>
                                <span class="badge badge-danger">ACTIVAT - Poate cauza probleme!</span>
                            <?php else: ?>
                                <span class="badge badge-success">Dezactivat</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">zlib.output_compression</div>
                        <div class="info-value">
                            <?php echo $zlib_compression ?: 'Off'; ?>
                            <?php if ($compression_enabled): ?>
                                <span class="badge badge-warning">Activat</span>
                            <?php else: ?>
                                <span class="badge badge-success">Dezactivat</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">implicit_flush</div>
                        <div class="info-value"><?php echo ini_get('implicit_flush') ? 'On' : 'Off'; ?></div>
                    </div>

                    <?php if ($buffering_enabled): ?>
                    <div class="recommendation">
                        <strong>⚠️ PROBLEMA IDENTIFICATĂ:</strong> output_buffering este activat! Acest lucru poate cauza pagină albă pentru răspunsuri mari (1.6MB). Dezactivează în .user.ini sau php.ini.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Disabled Functions -->
            <div class="section">
                <div class="section-title">🔒 Funcții Dezactivate</div>
                <div class="section-content">
                    <?php
$disabled = ini_get('disable_functions');
$disabled_array = $disabled ? explode(',', $disabled) : [];
$critical_disabled = [];
$critical_functions = ['ini_set', 'apache_setenv', 'set_time_limit'];

foreach ($critical_functions as $func) {
    if (in_array(trim($func), array_map('trim', $disabled_array))) {
        $critical_disabled[] = $func;
    }
}
?>
                    <div class="info-row">
                        <div class="info-label">disable_functions</div>
                        <div class="info-value">
                            <?php if (empty($disabled)): ?>
                                <span class="badge badge-success">Nimic dezactivat</span>
                            <?php else: ?>
                                <span class="badge badge-warning"><?php echo count($disabled_array); ?> funcții</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($critical_disabled)): ?>
                    <div class="info-row">
                        <div class="info-label">Funcții critice blocate</div>
                        <div class="info-value">
                            <span class="badge badge-danger"><?php echo implode(', ', $critical_disabled); ?></span>
                        </div>
                    </div>
                    <div class="recommendation">
                        <strong>⚠️ PROBLEMĂ:</strong> Funcțiile <?php echo implode(', ', $critical_disabled); ?> sunt blocate! Codul nu poate modifica limitele PHP în runtime.
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($disabled) && empty($critical_disabled)): ?>
                    <div class="info-row">
                        <div class="info-label">Lista completă</div>
                        <div class="info-value" style="text-align: left; font-size: 12px; word-break: break-all;">
                            <?php echo $disabled; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- FastCGI/FPM Settings -->
            <?php if (function_exists('apache_get_modules')): ?>
            <div class="section">
                <div class="section-title">🔧 Module Apache</div>
                <div class="section-content">
                    <?php
$modules = apache_get_modules();
                $important_modules = ['mod_fcgid', 'mod_deflate', 'mod_gzip', 'mod_headers', 'mod_rewrite'];
                ?>
                    <?php foreach ($important_modules as $mod): ?>
                    <div class="info-row">
                        <div class="info-label"><?php echo $mod; ?></div>
                        <div class="info-value">
                            <?php if (in_array($mod, $modules)): ?>
                                <span class="badge badge-success">Activat</span>
                            <?php else: ?>
                                <span class="badge">Dezactivat</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recomandări Finale -->
            <div class="section">
                <div class="section-title">💡 Recomandări pentru Rezolvare</div>
                <div class="section-content">
                    <?php
                $recommendations = [];

if (!$memory_ok) {
    $recommendations[] = "Crește <code>memory_limit = 512M</code> în .user.ini sau php.ini";
}

if ($buffering_enabled) {
    $recommendations[] = "Dezactivează <code>output_buffering = Off</code> în .user.ini sau php.ini";
}

if (!empty($critical_disabled)) {
    $recommendations[] = "Contactează hosting-ul să deblocheze: " . implode(', ', $critical_disabled);
}

if ($is_fpm) {
    $recommendations[] = "Server folosește PHP-FPM/CGI - folosește .user.ini sau php.ini (NU .htaccess)";
}

if (empty($recommendations)) {
    echo '<div style="color: #10b981; font-weight: 600;">✅ Configurația pare OK! Dacă tot ai probleme, verifică Apache/Nginx logs.</div>';
} else {
    echo '<ol style="padding-left: 20px;">';
    foreach ($recommendations as $rec) {
        echo '<li style="margin: 10px 0;">' . $rec . '</li>';
    }
    echo '</ol>';
}
?>
                </div>
            </div>

            <!-- Test API Response -->
            <div class="section">
                <div class="section-title">🧪 Test Simulat (5MB Array)</div>
                <div class="section-content">
                    <?php
// Test: creează un array mare și încearcă să-l returneze ca JSON
$test_size = 5 * 1024 * 1024; // 5MB
$test_array = array_fill(0, 100000, str_repeat('x', 50));
$json_test = json_encode($test_array);
$json_size = strlen($json_test);

if ($json_test !== false) {
    echo '<div class="info-row">';
    echo '<div class="info-label">JSON Encoding (5MB array)</div>';
    echo '<div class="info-value"><span class="badge badge-success">SUCCES - ' . number_format($json_size / 1024 / 1024, 2) . ' MB</span></div>';
    echo '</div>';
} else {
    echo '<div class="info-row">';
    echo '<div class="info-label">JSON Encoding (5MB array)</div>';
    echo '<div class="info-value"><span class="badge badge-danger">EȘUAT - ' . json_last_error_msg() . '</span></div>';
    echo '</div>';
}

// Curăță memoria
unset($test_array, $json_test);
?>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>🔍 AI Web Site - Server Diagnostic Tool v1.0</p>
            <p style="margin-top: 5px; font-size: 12px;">Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Helper function: Convert PHP ini values to bytes
 */
function return_bytes($val)
{
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $val = (int)$val;

    switch ($last) {
        case 'g': $val *= 1024;
            // no break
        case 'm': $val *= 1024;
            // no break
        case 'k': $val *= 1024;
    }

    return $val;
}
?>

