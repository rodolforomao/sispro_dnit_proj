<?php
// Tag vigente da publicação em produção (10.100.11.235/sispro).
if (!defined('SISPRO_VERSION')) {
    define('SISPRO_VERSION', '0.00.007');
}

if (!function_exists('sispro_asset')) {
    function sispro_asset($url)
    {
        $sep = (strpos($url, '?') === false) ? '?' : '&';
        return htmlspecialchars($url . $sep . 'v=' . rawurlencode(SISPRO_VERSION), ENT_QUOTES, 'UTF-8');
    }
}
