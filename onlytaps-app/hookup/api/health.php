<?php
/**
 * Vercel Health Check API Endpoint
 * 
 * This endpoint helps verify Vercel deployment is working
 * and checks database connectivity
 * 
 * Endpoint: /api/health.php
 * Usage: curl https://your-app.vercel.app/api/health.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'environment' => getenv('APP_ENV') ?: 'unknown',
    'php_version' => PHP_VERSION,
    'platform' => 'vercel',
];

try {
    // Check if environment variables are set
    $required_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
    $missing_vars = [];
    
    foreach ($required_vars as $var) {
        if (!getenv($var)) {
            $missing_vars[] = $var;
        }
    }
    
    if (!empty($missing_vars)) {
        $response['status'] = 'warning';
        $response['missing_env_vars'] = $missing_vars;
        $response['database'] = 'not_configured';
    } else {
        // Test database connection
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            $result = $db->query('SELECT 1');
            if ($result) {
                $response['database'] = 'connected';
                $response['database_version'] = $db->query('SELECT VERSION()')->fetchColumn();
            } else {
                $response['database'] = 'query_failed';
                $response['status'] = 'warning';
            }
        } else {
            $response['database'] = 'disconnected';
            $response['status'] = 'error';
        }
    }
} catch (Exception $e) {
    $response['database'] = 'error';
    $response['database_error'] = $e->getMessage();
    $response['status'] = 'error';
}

// Set HTTP status code based on response status
$http_code = ($response['status'] === 'ok') ? 200 : (($response['status'] === 'warning') ? 206 : 500);
http_response_code($http_code);

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
