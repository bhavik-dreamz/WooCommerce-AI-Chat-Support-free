<?php
/**
 * Plugin Name: WooCommerce AI Chat Support
 * Plugin URI: https://dynamicdreamz.com/
 * Description: AI-powered chat support for WooCommerce with Groq and OpenAI integration
 * Version: 1.0.0
 * Author: Bhavik Patel
 * License: GPL v2 or later
 * Text Domain: wc-ai-chat
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_AI_CHAT_VERSION', '1.0.0');
define('WC_AI_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AI_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include the main AI agent class
require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-woocommerce-ai-agent.php';

/**
 * Main Plugin Class
 */
class WC_AI_Chat_Plugin {
    
    private $ai_agent;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_chat_widget'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('rest_api_init', array($this, 'register_api_endpoints'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->init_ai_agent();
        $this->schedule_cron_jobs();
        
        // Load text domain for translations
        load_plugin_textdomain('wc-ai-chat', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Initialize AI Agent
     */
    private function init_ai_agent() {
        $ai_config = array(
            'openai_key' => get_option('wc_ai_chat_openai_key', ''),
            'groq_key' => get_option('wc_ai_chat_groq_key', ''),
            'provider' => get_option('wc_ai_chat_provider', 'both')
        );
        
        $this->ai_agent = new WooCommerceAIAgent(
            get_option('wc_ai_chat_api_key', ''),
            get_option('wc_ai_chat_api_secret', ''),
            home_url(),
            $ai_config
        );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if (!is_admin()) {
            // CSS for chat widget
            wp_enqueue_style(
                'wc-ai-chat-style',
                WC_AI_CHAT_PLUGIN_URL . 'assets/css/chat-widget.css',
                array(),
                WC_AI_CHAT_VERSION
            );
            
            // JavaScript for chat functionality
            wp_enqueue_script(
                'wc-ai-chat-script',
                WC_AI_CHAT_PLUGIN_URL . 'assets/js/chat-widget.js',
                array('jquery'),
                WC_AI_CHAT_VERSION,
                true
            );
            
            // Localize script with AJAX URL and nonce
            wp_localize_script('wc-ai-chat-script', 'wcAiChat', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'restUrl' => rest_url('wc-ai-chat/v1/'),
                'nonce' => wp_create_nonce('wc_ai_chat_nonce'),
                'currentUserId' => get_current_user_id(),
                'isLoggedIn' => is_user_logged_in(),
                'strings' => array(
                    'welcomeMessage' => __('Hi! How can I help you today?', 'wc-ai-chat'),
                    'typingIndicator' => __('AI is typing...', 'wc-ai-chat'),
                    'errorMessage' => __('Sorry, I encountered an error. Please try again.', 'wc-ai-chat'),
                    'offlineMessage' => __('Chat is currently offline. Please try again later.', 'wc-ai-chat'),
                    'placeholder' => __('Type your message here...', 'wc-ai-chat'),
                    'sendButton' => __('Send', 'wc-ai-chat'),
                    'minimizeButton' => __('Minimize', 'wc-ai-chat'),
                    'closeButton' => __('Close', 'wc-ai-chat')
                ),
                'settings' => array(
                    'enabled' => get_option('wc_ai_chat_enabled', '1'),
                    'theme' => get_option('wc_ai_chat_theme', 'modern'),
                    'position' => get_option('wc_ai_chat_position', 'bottom-right'),
                    'autoOpen' => get_option('wc_ai_chat_auto_open', '0'),
                    'showOnPages' => get_option('wc_ai_chat_show_pages', 'all')
                )
            ));
        }
    }
    
    /**
     * Render chat widget in footer
     */
    public function render_chat_widget() {
        if (!get_option('wc_ai_chat_enabled', '1') || is_admin()) {
            return;
        }
        
        // Check if should show on current page
        $show_on_pages = get_option('wc_ai_chat_show_pages', 'all');
        if ($show_on_pages === 'shop' && !is_shop() && !is_product() && !is_product_category()) {
            return;
        }
        
        $theme = get_option('wc_ai_chat_theme', 'modern');
        $position = get_option('wc_ai_chat_position', 'bottom-right');
        
        ?>
        <div id="wc-ai-chat-widget" class="wc-ai-chat-widget <?php echo esc_attr($theme); ?> <?php echo esc_attr($position); ?>">
            <!-- Chat Toggle Button -->
            <div id="wc-ai-chat-toggle" class="wc-ai-chat-toggle">
                <div class="chat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z" fill="currentColor"/>
                        <path d="M7 9H17V11H7V9ZM7 12H15V14H7V12ZM7 6H17V8H7V6Z" fill="currentColor"/>
                    </svg>
                </div>
                <div class="close-icon" style="display: none;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19 6.41L17.59 5L12 10.59L6.41 5L5 6.41L10.59 12L5 17.59L6.41 19L12 13.41L17.59 19L19 17.59L13.41 12L19 6.41Z" fill="currentColor"/>
                    </svg>
                </div>
                <span class="notification-badge" style="display: none;">1</span>
            </div>
            
            <!-- Chat Window -->
            <div id="wc-ai-chat-window" class="wc-ai-chat-window" style="display: none;">
                <!-- Header -->
                <div class="wc-ai-chat-header">
                    <div class="header-info">
                        <div class="agent-avatar">
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="16" cy="16" r="16" fill="#4F46E5"/>
                                <path d="M16 8C13.79 8 12 9.79 12 12C12 14.21 13.79 16 16 16C18.21 16 20 14.21 20 12C20 9.79 18.21 8 16 8ZM16 24C12.42 24 9.28 22.13 8 19.5C8.03 16.83 13.33 15.4 16 15.4C18.67 15.4 23.97 16.83 24 19.5C22.72 22.13 19.58 24 16 24Z" fill="white"/>
                            </svg>
                        </div>
                        <div class="agent-details">
                            <h4><?php echo esc_html(get_option('wc_ai_chat_agent_name', 'AI Assistant')); ?></h4>
                            <span class="status online"><?php _e('Online', 'wc-ai-chat'); ?></span>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button id="wc-ai-chat-minimize" type="button" title="<?php esc_attr_e('Minimize', 'wc-ai-chat'); ?>">−</button>
                        <button id="wc-ai-chat-close" type="button" title="<?php esc_attr_e('Close', 'wc-ai-chat'); ?>">×</button>
                    </div>
                </div>
                
                <!-- Messages Container -->
                <div id="wc-ai-chat-messages" class="wc-ai-chat-messages">
                    <div class="welcome-message">
                        <div class="message bot-message">
                            <div class="message-content">
                                <p><?php echo esc_html(get_option('wc_ai_chat_welcome_message', 'Hi! How can I help you today?')); ?></p>
                            </div>
                            <div class="message-time"><?php echo current_time('H:i'); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div id="wc-ai-chat-quick-actions" class="wc-ai-chat-quick-actions">
                    <button type="button" class="quick-action" data-message="Track my order">
                        📦 <?php _e('Track Order', 'wc-ai-chat'); ?>
                    </button>
                    <button type="button" class="quick-action" data-message="I need help with returns">
                        🔄 <?php _e('Returns', 'wc-ai-chat'); ?>
                    </button>
                    <button type="button" class="quick-action" data-message="Product recommendations">
                        💡 <?php _e('Recommendations', 'wc-ai-chat'); ?>
                    </button>
                </div>
                
                <!-- Typing Indicator -->
                <div id="wc-ai-chat-typing" class="typing-indicator" style="display: none;">
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span class="typing-text"><?php _e('AI is typing...', 'wc-ai-chat'); ?></span>
                </div>
                
                <!-- Input Form -->
                <form id="wc-ai-chat-form" class="wc-ai-chat-form">
                    <div class="input-container">
                        <textarea 
                            id="wc-ai-chat-input" 
                            placeholder="<?php esc_attr_e('Type your message here...', 'wc-ai-chat'); ?>"
                            rows="1"
                            maxlength="1000"
                        ></textarea>
                        <button type="submit" id="wc-ai-chat-send" disabled>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2 10L18 2L11 10L18 18L2 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                </form>
                
                <!-- Powered By -->
                <div class="wc-ai-chat-powered">
                    <small><?php _e('Powered by AI', 'wc-ai-chat'); ?></small>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Register REST API endpoints
     */
    public function register_api_endpoints() {
        register_rest_route('wc-ai-chat/v1', '/message', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_chat_message'),
            'permission_callback' => '__return_true',
            'args' => array(
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field'
                ),
                'customer_id' => array(
                    'type' => 'integer',
                    'default' => 0
                ),
                'context' => array(
                    'type' => 'object',
                    'default' => array()
                )
            )
        ));
        
        register_rest_route('wc-ai-chat/v1', '/recommendations/(?P<customer_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_recommendations'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Handle chat messages
     */
    public function handle_chat_message($request) {
        try {
            $message = $request->get_param('message');
            $customer_id = $request->get_param('customer_id') ?: $this->get_customer_id_from_session();
            $context = $request->get_param('context') ?: array();
            
            if (!$this->ai_agent) {
                return new WP_Error('agent_not_initialized', 'AI Agent not initialized', array('status' => 500));
            }
            
            // Process message with AI agent
            $response = $this->ai_agent->handleCustomerQuery($customer_id, $message, $context);
            
            // Log conversation for analytics
            $this->log_conversation($customer_id, $message, $response);
            
            return rest_ensure_response(array(
                'success' => true,
                'response' => $response['response'],
                'type' => $response['type'],
                'data' => $response['data'] ?? array(),
                'timestamp' => current_time('mysql')
            ));
            
        } catch (Exception $e) {
            error_log('WC AI Chat Error: ' . $e->getMessage());
            return new WP_Error('chat_error', 'Failed to process message', array('status' => 500));
        }
    }
    
    /**
     * Get product recommendations
     */
    public function get_recommendations($request) {
        try {
            $customer_id = $request->get_param('customer_id') ?: $this->get_customer_id_from_session();
            
            if (!$this->ai_agent) {
                return new WP_Error('agent_not_initialized', 'AI Agent not initialized', array('status' => 500));
            }
            
            $recommendations = $this->ai_agent->getProductRecommendations($customer_id, 5);
            
            return rest_ensure_response(array(
                'success' => true,
                'recommendations' => $recommendations
            ));
            
        } catch (Exception $e) {
            return new WP_Error('recommendations_error', 'Failed to get recommendations', array('status' => 500));
        }
    }
    
    /**
     * Get customer ID from session or user
     */
    private function get_customer_id_from_session() {
        if (is_user_logged_in()) {
            return get_current_user_id();
        }
        
        // For guest users, create a session-based ID
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['wc_ai_guest_id'])) {
            $_SESSION['wc_ai_guest_id'] = 'guest_' . uniqid();
        }
        
        return $_SESSION['wc_ai_guest_id'];
    }
    
    /**
     * Log conversation for analytics
     */
    private function log_conversation($customer_id, $message, $response) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_ai_chat_conversations';
        
        $wpdb->insert(
            $table_name,
            array(
                'customer_id' => $customer_id,
                'message' => $message,
                'response' => wp_json_encode($response),
                'timestamp' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Chat Support', 'wc-ai-chat'),
            __('AI Chat', 'wc-ai-chat'),
            'manage_options',
            'wc-ai-chat',
            array($this, 'admin_page'),
            'dashicons-format-chat',
            30
        );
        
        add_submenu_page(
            'wc-ai-chat',
            __('Settings', 'wc-ai-chat'),
            __('Settings', 'wc-ai-chat'),
            'manage_options',
            'wc-ai-chat-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'wc-ai-chat',
            __('Conversations', 'wc-ai-chat'),
            __('Conversations', 'wc-ai-chat'),
            'manage_options',
            'wc-ai-chat-conversations',
            array($this, 'conversations_page')
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('AI Chat Support Dashboard', 'wc-ai-chat'); ?></h1>
            
            <div class="wc-ai-chat-dashboard">
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3><?php _e('Total Conversations', 'wc-ai-chat'); ?></h3>
                        <span class="stat-number"><?php echo $this->get_total_conversations(); ?></span>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Today\'s Chats', 'wc-ai-chat'); ?></h3>
                        <span class="stat-number"><?php echo $this->get_today_conversations(); ?></span>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Resolution Rate', 'wc-ai-chat'); ?></h3>
                        <span class="stat-number">87%</span>
                    </div>
                </div>
                
                <div class="dashboard-actions">
                    <a href="<?php echo admin_url('admin.php?page=wc-ai-chat-settings'); ?>" class="button button-primary">
                        <?php _e('Configure Settings', 'wc-ai-chat'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wc-ai-chat-conversations'); ?>" class="button">
                        <?php _e('View Conversations', 'wc-ai-chat'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'wc-ai-chat') . '</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('AI Chat Settings', 'wc-ai-chat'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('wc_ai_chat_settings', 'wc_ai_chat_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Chat Widget', 'wc-ai-chat'); ?></th>
                        <td>
                            <input type="checkbox" name="wc_ai_chat_enabled" value="1" <?php checked(get_option('wc_ai_chat_enabled', '1'), '1'); ?> />
                            <p class="description"><?php _e('Enable or disable the chat widget on your website.', 'wc-ai-chat'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('OpenAI API Key', 'wc-ai-chat'); ?></th>
                        <td>
                            <input type="password" name="wc_ai_chat_openai_key" value="<?php echo esc_attr(get_option('wc_ai_chat_openai_key', '')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Enter your OpenAI API key for ChatGPT integration.', 'wc-ai-chat'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Groq API Key', 'wc-ai-chat'); ?></th>
                        <td>
                            <input type="password" name="wc_ai_chat_groq_key" value="<?php echo esc_attr(get_option('wc_ai_chat_groq_key', '')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Enter your Groq API key for faster AI responses.', 'wc-ai-chat'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('AI Provider', 'wc-ai-chat'); ?></th>
                        <td>
                            <select name="wc_ai_chat_provider">
                                <option value="both" <?php selected(get_option('wc_ai_chat_provider', 'both'), 'both'); ?>><?php _e('Both (Groq primary, OpenAI fallback)', 'wc-ai-chat'); ?></option>
                                <option value="groq" <?php selected(get_option('wc_ai_chat_provider', 'both'), 'groq'); ?>><?php _e('Groq Only', 'wc-ai-chat'); ?></option>
                                <option value="openai" <?php selected(get_option('wc_ai_chat_provider', 'both'), 'openai'); ?>><?php _e('OpenAI Only', 'wc-ai-chat'); ?></option>
                            </select>
                            <p class="description"><?php _e('Choose which AI provider to use for responses.', 'wc-ai-chat'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('WooCommerce API Key', 'wc-ai-chat'); ?></th>
                        <td>
                            <input type="text" name="wc_ai_chat_api_key" value="<?php echo esc_attr(get_option('wc_ai_chat_api_key', '')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('WooCommerce API Secret', 'wc-ai-chat'); ?></th>
                        <td>
                            <input type="password" name="wc_ai_chat_api_secret" value="<?php echo esc_attr(get_option('wc_ai_chat_api_secret', '')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Chat Widget Position', 'wc-ai-chat'); ?></th>
                        <td>
                            <select name="wc_ai_chat_position">
                                <option value="bottom-right" <?php selected(get_option('wc_ai_chat_position', 'bottom-right'), 'bottom-right'); ?>><?php _e('Bottom Right', 'wc-ai-chat'); ?></option>
                                <option value="bottom-left" <?php selected(get_option('wc_ai_chat_position', 'bottom-right'), 'bottom-left'); ?>><?php _e('Bottom Left', 'wc-ai-chat'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Welcome Message', 'wc-ai-chat'); ?></th>
                        <td>
                            <textarea name="wc_ai_chat_welcome_message" rows="3" class="regular-text"><?php echo esc_textarea(get_option('wc_ai_chat_welcome_message', 'Hi! How can I help you today?')); ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['wc_ai_chat_nonce'], 'wc_ai_chat_settings')) {
            return;
        }
        
        $settings = array(
            'wc_ai_chat_enabled',
            'wc_ai_chat_openai_key',
            'wc_ai_chat_groq_key',
            'wc_ai_chat_provider',
            'wc_ai_chat_api_key',
            'wc_ai_chat_api_secret',
            'wc_ai_chat_position',
            'wc_ai_chat_welcome_message'
        );
        
        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                update_option($setting, sanitize_text_field($_POST[$setting]));
            }
        }
    }
    
    /**
     * Conversations page
     */
    public function conversations_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_ai_chat_conversations';
        $conversations = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY timestamp DESC LIMIT 50");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Recent Conversations', 'wc-ai-chat'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Customer', 'wc-ai-chat'); ?></th>
                        <th><?php _e('Message', 'wc-ai-chat'); ?></th>
                        <th><?php _e('Response', 'wc-ai-chat'); ?></th>
                        <th><?php _e('Date', 'wc-ai-chat'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conversations as $conversation): ?>
                    <tr>
                        <td><?php echo esc_html($conversation->customer_id); ?></td>
                        <td><?php echo esc_html(wp_trim_words($conversation->message, 10)); ?></td>
                        <td><?php 
                            $response = json_decode($conversation->response, true);
                            echo esc_html(wp_trim_words($response['response'] ?? '', 10)); 
                        ?></td>
                        <td><?php echo esc_html($conversation->timestamp); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Get statistics
     */
    private function get_total_conversations() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_ai_chat_conversations';
        return $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}") ?: 0;
    }
    
    private function get_today_conversations() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_ai_chat_conversations';
        return $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE DATE(timestamp) = CURDATE()") ?: 0;
    }
    
    /**
     * Schedule cron jobs
     */
    private function schedule_cron_jobs() {
        if (!wp_next_scheduled('wc_ai_chat_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wc_ai_chat_cleanup');
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_database_tables();
        $this->set_default_options();
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        wp_clear_scheduled_hook('wc_ai_chat_cleanup');
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Conversations table
        $conversations_table = $wpdb->prefix . 'wc_ai_chat_conversations';
        $sql_conversations = "CREATE TABLE $conversations_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            customer_id varchar(100) NOT NULL,
            message text NOT NULL,
            response longtext NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        // Analytics table
        $analytics_table = $wpdb->prefix . 'wc_ai_chat_analytics';
        $sql_analytics = "CREATE TABLE $analytics_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            customer_id varchar(100),
            data longtext,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_conversations);
        dbDelta($sql_analytics);
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        add_option('wc_ai_chat_enabled', '1');
        add_option('wc_ai_chat_provider', 'both');
        add_option('wc_ai_chat_position', 'bottom-right');
        add_option('wc_ai_chat_theme', 'modern');
        add_option('wc_ai_chat_welcome_message', 'Hi! How can I help you today?');
        add_option('wc_ai_chat_agent_name', 'AI Assistant');
        add_option('wc_ai_chat_auto_open', '0');
        add_option('wc_ai_chat_show_pages', 'all');
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('WooCommerce AI Chat Support requires WooCommerce to be installed and activated.', 'wc-ai-chat'); ?></p>
        </div>
        <?php
    }
}

// Initialize the plugin
new WC_AI_Chat_Plugin();

?>