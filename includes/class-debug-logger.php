<?php

/**
 * Debug Logger class for AI Web Site plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site_Debug_Logger
{
    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Log table name
     */
    private $table_name;

    /**
     * Get single instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_web_site_logs';
    }

    /**
     * Create logs table
     */
    public function create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL,
            component varchar(50) NOT NULL,
            action varchar(50) NOT NULL,
            message text NOT NULL,
            data longtext,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY level (level),
            KEY component (component)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log a message
     * NOTE: Database logging disabled - only error_log for performance
     */
    public function log($level, $component, $action, $message, $data = null)
    {
        // Database logging DISABLED for performance
        // Only use error_log for debugging
        
        // Log to WordPress error_log
        error_log("AI-Web-Site [{$level}] {$component}::{$action} - {$message}" . ($data ? ' | Data: ' . json_encode($data) : ''));
    }

    /**
     * Log info message
     */
    public function info($component, $action, $message, $data = null)
    {
        $this->log('INFO', $component, $action, $message, $data);
    }

    /**
     * Log warning message
     */
    public function warning($component, $action, $message, $data = null)
    {
        $this->log('WARNING', $component, $action, $message, $data);
    }

    /**
     * Log error message
     */
    public function error($component, $action, $message, $data = null)
    {
        $this->log('ERROR', $component, $action, $message, $data);
    }

    /**
     * Log debug message
     */
    public function debug($component, $action, $message, $data = null)
    {
        $this->log('DEBUG', $component, $action, $message, $data);
    }

    /**
     * Get recent logs
     */
    public function get_logs($limit = 50, $level = null, $component = null)
    {
        global $wpdb;

        $where_conditions = array();
        $where_values = array();

        if ($level) {
            $where_conditions[] = 'level = %s';
            $where_values[] = $level;
        }

        if ($component) {
            $where_conditions[] = 'component = %s';
            $where_values[] = $component;
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY timestamp DESC LIMIT %d",
            array_merge($where_values, array($limit))
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Clear old logs (keep last 1000)
     */
    public function cleanup_logs()
    {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$this->table_name} 
             WHERE id NOT IN (
                 SELECT id FROM (
                     SELECT id FROM {$this->table_name} 
                     ORDER BY timestamp DESC 
                     LIMIT 1000
                 ) AS temp
             )"
        );
    }

    /**
     * Get logs as JSON for API
     */
    public function get_logs_json($limit = 50, $level = null, $component = null)
    {
        $logs = $this->get_logs($limit, $level, $component);

        $formatted_logs = array();
        foreach ($logs as $log) {
            $formatted_logs[] = array(
                'id' => $log->id,
                'timestamp' => $log->timestamp,
                'level' => $log->level,
                'component' => $log->component,
                'action' => $log->action,
                'message' => $log->message,
                'data' => $log->data ? json_decode($log->data, true) : null
            );
        }

        return $formatted_logs;
    }
}
