<?php
/**
 * Initialize Vercel Environment
 * 
 * Include this file in your index.php or bootstrap file
 * to enable Vercel-specific configurations
 */

// Load Vercel configuration
require_once __DIR__ . '/config/vercel.php';

// Load Vercel session handler
require_once __DIR__ . '/config/vercel-sessions.php';

// Initialize sessions after database connection
// In your bootstrap file, after creating database connection:
/*
$database = new Database();
$db = $database->getConnection();
initializeVercelSessions($db);
*/

// Add Vercel-specific error handling
if (IS_VERCEL) {
    // Custom error handler for Vercel
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        $error_message = sprintf(
            "[%s] %s in %s:%d",
            date('Y-m-d H:i:s'),
            $errstr,
            $errfile,
            $errline
        );
        
        error_log($error_message);
        
        if (IS_DEVELOPMENT) {
            echo $error_message;
        } elseif (IS_PRODUCTION) {
            http_response_code(500);
            echo "An error occurred. Please try again later.";
        }
        
        return true;
    });
    
    // Custom exception handler for Vercel
    set_exception_handler(function($exception) {
        $error_message = sprintf(
            "[%s] Exception: %s in %s:%d",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        
        error_log($error_message);
        error_log($exception->getTraceAsString());
        
        if (IS_DEVELOPMENT) {
            throw $exception;
        } elseif (IS_PRODUCTION) {
            http_response_code(500);
            echo "An error occurred. Please try again later.";
        }
    });
}

?>
