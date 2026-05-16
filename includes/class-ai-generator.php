<?php
/**
 * AI Content Generator Class for Lesson Plan Editor
 * Handles 3-step AI generation workflow for different content types
 */

if (!defined('ABSPATH')) {
    exit;
}

class My_Lesson_Editor_AI_Generator {
    
    private $n8n_webhook_url;
    private $content_types = array(
        'lesson_plan' => 'Lesson Plan',
        'vocabulary' => 'Vocabulary List',
        'flashcards' => 'Flashcards',
        'quiz' => 'Quiz',
        'midterm' => 'Midterm Exam',
        'final' => 'Final Exam'
    );
    
    public function __construct() {
        $this->n8n_webhook_url = defined('MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL') 
            ? MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL 
            : '';
    }
    
    /**
     * Generate AI content based on 3-step workflow
     */
    public function generate_content($chapter_id, $content_types, $step_data) {
        if (empty($this->n8n_webhook_url)) {
            return new WP_Error('no_webhook', 'N8N webhook URL not configured');
        }
        
        $payload = array(
            'chapter_id' => $chapter_id,
            'content_types' => $content_types,
            'step_data' => $step_data,
            'source' => 'lesson_plan_ai_generator',
            'timestamp' => current_time('mysql')
        );
        
        $response = wp_remote_post($this->n8n_webhook_url, array(
            'method' => 'POST',
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($payload),
            'timeout' => 300
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_response', 'Invalid JSON response from AI service');
        }
        
        return $data;
    }
    
    /**
     * Save generated content to database
     */
    public function save_generated_content($chapter_id, $content_type, $content_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lesson_plan_generated_content';
        
        // Create table if it doesn't exist
        $this->create_generated_content_table();
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'chapter_id' => $chapter_id,
                'content_type' => $content_type,
                'content_data' => json_encode($content_data),
                'status' => 'draft',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to save generated content');
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get generated content for a chapter
     */
    public function get_generated_content($chapter_id, $content_type = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lesson_plan_generated_content';
        
        $where_clause = "WHERE chapter_id = %d";
        $params = array($chapter_id);
        
        if ($content_type) {
            $where_clause .= " AND content_type = %s";
            $params[] = $content_type;
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC",
            $params
        ));
        
        // Decode JSON content data
        foreach ($results as $result) {
            $result->content_data = json_decode($result->content_data, true);
        }
        
        return $results;
    }
    
    /**
     * Update generated content status
     */
    public function update_content_status($content_id, $status, $content_data = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lesson_plan_generated_content';
        
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if ($content_data) {
            $update_data['content_data'] = json_encode($content_data);
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $content_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Create generated content table
     */
    private function create_generated_content_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lesson_plan_generated_content';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            chapter_id bigint(20) NOT NULL,
            content_type varchar(50) NOT NULL,
            content_data longtext NOT NULL,
            status varchar(20) DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY chapter_id (chapter_id),
            KEY content_type (content_type),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get available content types
     */
    public function get_content_types() {
        return $this->content_types;
    }
    
    /**
     * Validate step data for 3-step workflow
     */
    public function validate_step_data($step_number, $step_data) {
        switch ($step_number) {
            case 1:
                // Step 1: Learning Objectives and Goals
                return isset($step_data['learning_objectives']) && 
                       isset($step_data['target_audience']) && 
                       isset($step_data['difficulty_level']);
                       
            case 2:
                // Step 2: Content Structure and Format
                return isset($step_data['content_format']) && 
                       isset($step_data['assessment_type']) && 
                       isset($step_data['interactive_elements']);
                       
            case 3:
                // Step 3: Customization and Preferences
                return isset($step_data['language']) && 
                       isset($step_data['time_allocation']) && 
                       isset($step_data['additional_requirements']);
                       
            default:
                return false;
        }
    }
    
    /**
     * Generate step prompts based on content type
     */
    public function get_step_prompts($content_type, $step_number) {
        $prompts = array(
            'lesson_plan' => array(
                1 => 'Define learning objectives and target audience for this lesson plan',
                2 => 'Specify content structure, format, and assessment methods',
                3 => 'Set language preferences, time allocation, and additional requirements'
            ),
            'vocabulary' => array(
                1 => 'Identify key vocabulary terms and their difficulty levels',
                2 => 'Define vocabulary presentation format and learning activities',
                3 => 'Set language preferences and additional vocabulary requirements'
            ),
            'flashcards' => array(
                1 => 'Define flashcard content and learning objectives',
                2 => 'Specify flashcard format, difficulty, and interactive elements',
                3 => 'Set language preferences and additional flashcard features'
            ),
            'quiz' => array(
                1 => 'Define quiz objectives, question types, and difficulty levels',
                2 => 'Specify quiz format, scoring system, and feedback methods',
                3 => 'Set language preferences and additional quiz requirements'
            ),
            'midterm' => array(
                1 => 'Define midterm exam objectives and comprehensive coverage',
                2 => 'Specify exam format, question distribution, and time limits',
                3 => 'Set language preferences and additional exam requirements'
            ),
            'final' => array(
                1 => 'Define final exam objectives and comprehensive assessment',
                2 => 'Specify exam format, question distribution, and grading criteria',
                3 => 'Set language preferences and additional exam requirements'
            )
        );
        
        return isset($prompts[$content_type][$step_number]) 
            ? $prompts[$content_type][$step_number] 
            : 'Complete the required information for this step';
    }
}
