<?php
/**
 * Academy LMS Integration Class for Lesson Plan Editor
 * Handles publishing content to Academy LMS components
 */

if (!defined('ABSPATH')) {
    exit;
}

class My_Lesson_Editor_Academy_LMS_Integration {
    
    private $academy_tables = array(
        'courses' => 'academy_courses',
        'lessons' => 'academy_lessons',
        'quizzes' => 'academy_quizzes',
        'questions' => 'academy_questions',
        'flashcards' => 'academy_flashcards'
    );
    
    public function __construct() {
        // Initialize Academy LMS integration
        add_action('init', array($this, 'check_academy_lms_active'));
    }
    
    /**
     * Check if Academy LMS is active
     */
    public function check_academy_lms_active() {
        if (!class_exists('AcademyLMS')) {
            add_action('admin_notices', array($this, 'academy_lms_inactive_notice'));
        }
    }
    
    /**
     * Show notice if Academy LMS is not active
     */
    public function academy_lms_inactive_notice() {
        echo '<div class="notice notice-warning"><p>';
        echo 'Academy LMS plugin is required for full Lesson Plan Editor functionality. ';
        echo '<a href="' . admin_url('plugins.php') . '">Please install and activate Academy LMS.</a>';
        echo '</p></div>';
    }
    
    /**
     * Publish lesson plan to Academy LMS
     */
    public function publish_lesson_plan($lesson_data, $chapter_id = null) {
        global $wpdb;
        
        // Create Academy LMS lesson
        $lesson_post_data = array(
            'post_title' => $lesson_data['title'],
            'post_content' => $lesson_data['content'],
            'post_status' => 'publish',
            'post_type' => 'academy_lesson',
            'post_author' => get_current_user_id()
        );
        
        $lesson_id = wp_insert_post($lesson_post_data);
        
        if (is_wp_error($lesson_id)) {
            return $lesson_id;
        }
        
        // Add lesson metadata
        if (isset($lesson_data['duration'])) {
            update_post_meta($lesson_id, '_academy_lesson_duration', $lesson_data['duration']);
        }
        
        if (isset($lesson_data['video_url'])) {
            update_post_meta($lesson_id, '_academy_lesson_video_url', $lesson_data['video_url']);
        }
        
        if ($chapter_id) {
            update_post_meta($lesson_id, '_lesson_plan_chapter_id', $chapter_id);
        }
        
        // Add to Academy LMS lessons table if it exists
        $this->add_to_academy_lessons_table($lesson_id, $lesson_data);
        
        return $lesson_id;
    }
    
    /**
     * Publish vocabulary list to Academy LMS
     */
    public function publish_vocabulary($vocabulary_data, $chapter_id = null) {
        global $wpdb;
        
        // Create vocabulary post
        $vocab_post_data = array(
            'post_title' => $vocabulary_data['title'],
            'post_content' => $this->format_vocabulary_content($vocabulary_data['terms']),
            'post_status' => 'publish',
            'post_type' => 'academy_lesson',
            'post_author' => get_current_user_id()
        );
        
        $vocab_id = wp_insert_post($vocab_post_data);
        
        if (is_wp_error($vocab_id)) {
            return $vocab_id;
        }
        
        // Add vocabulary metadata
        update_post_meta($vocab_id, '_lesson_type', 'vocabulary');
        update_post_meta($vocab_id, '_vocabulary_terms', $vocabulary_data['terms']);
        
        if ($chapter_id) {
            update_post_meta($vocab_id, '_lesson_plan_chapter_id', $chapter_id);
        }
        
        return $vocab_id;
    }
    
    /**
     * Publish flashcards to Academy LMS
     */
    public function publish_flashcards($flashcard_data, $chapter_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->academy_tables['flashcards'];
        
        // Check if flashcards table exists
        if (!$this->table_exists($table_name)) {
            $this->create_flashcards_table();
        }
        
        $flashcard_ids = array();
        
        foreach ($flashcard_data['cards'] as $card) {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'front_text' => $card['front'],
                    'back_text' => $card['back'],
                    'difficulty_level' => isset($card['difficulty']) ? $card['difficulty'] : 'medium',
                    'category' => isset($card['category']) ? $card['category'] : '',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result !== false) {
                $flashcard_ids[] = $wpdb->insert_id;
            }
        }
        
        // Create flashcard set post
        $set_post_data = array(
            'post_title' => $flashcard_data['title'],
            'post_content' => 'Flashcard set with ' . count($flashcard_ids) . ' cards',
            'post_status' => 'publish',
            'post_type' => 'academy_flashcard_set',
            'post_author' => get_current_user_id()
        );
        
        $set_id = wp_insert_post($set_post_data);
        
        if (!is_wp_error($set_id)) {
            update_post_meta($set_id, '_flashcard_ids', $flashcard_ids);
            update_post_meta($set_id, '_lesson_type', 'flashcards');
            
            if ($chapter_id) {
                update_post_meta($set_id, '_lesson_plan_chapter_id', $chapter_id);
            }
        }
        
        return $set_id;
    }
    
    /**
     * Publish quiz to Academy LMS
     */
    public function publish_quiz($quiz_data, $chapter_id = null) {
        global $wpdb;
        
        // Create quiz post
        $quiz_post_data = array(
            'post_title' => $quiz_data['title'],
            'post_content' => $quiz_data['description'],
            'post_status' => 'publish',
            'post_type' => 'academy_quiz',
            'post_author' => get_current_user_id()
        );
        
        $quiz_id = wp_insert_post($quiz_post_data);
        
        if (is_wp_error($quiz_id)) {
            return $quiz_id;
        }
        
        // Add quiz metadata
        update_post_meta($quiz_id, '_quiz_duration', $quiz_data['duration']);
        update_post_meta($quiz_id, '_quiz_attempts', $quiz_data['attempts']);
        update_post_meta($quiz_id, '_quiz_pass_mark', $quiz_data['pass_mark']);
        
        if ($chapter_id) {
            update_post_meta($quiz_id, '_lesson_plan_chapter_id', $chapter_id);
        }
        
        // Add questions to Academy LMS questions table
        $this->add_quiz_questions($quiz_id, $quiz_data['questions']);
        
        return $quiz_id;
    }
    
    /**
     * Add questions to Academy LMS questions table
     */
    private function add_quiz_questions($quiz_id, $questions) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->academy_tables['questions'];
        
        // Check if questions table exists
        if (!$this->table_exists($table_name)) {
            $this->create_questions_table();
        }
        
        foreach ($questions as $question) {
            $wpdb->insert(
                $table_name,
                array(
                    'quiz_id' => $quiz_id,
                    'question_text' => $question['question'],
                    'question_type' => $question['type'],
                    'options' => json_encode($question['options']),
                    'correct_answer' => $question['correct_answer'],
                    'points' => isset($question['points']) ? $question['points'] : 1,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
            );
        }
    }
    
    /**
     * Publish exam (midterm/final) to Academy LMS
     */
    public function publish_exam($exam_data, $exam_type = 'midterm', $chapter_id = null) {
        global $wpdb;
        
        // Create exam post
        $exam_post_data = array(
            'post_title' => $exam_data['title'],
            'post_content' => $exam_data['description'],
            'post_status' => 'publish',
            'post_type' => 'academy_exam',
            'post_author' => get_current_user_id()
        );
        
        $exam_id = wp_insert_post($exam_post_data);
        
        if (is_wp_error($exam_id)) {
            return $exam_id;
        }
        
        // Add exam metadata
        update_post_meta($exam_id, '_exam_type', $exam_type);
        update_post_meta($exam_id, '_exam_duration', $exam_data['duration']);
        update_post_meta($exam_id, '_exam_attempts', $exam_data['attempts']);
        update_post_meta($exam_id, '_exam_pass_mark', $exam_data['pass_mark']);
        
        if ($chapter_id) {
            update_post_meta($exam_id, '_lesson_plan_chapter_id', $chapter_id);
        }
        
        // Add exam questions
        $this->add_quiz_questions($exam_id, $exam_data['questions']);
        
        return $exam_id;
    }
    
    /**
     * Format vocabulary content for display
     */
    private function format_vocabulary_content($terms) {
        $content = '<div class="vocabulary-list">';
        $content .= '<h3>Vocabulary Terms</h3>';
        $content .= '<ul>';
        
        foreach ($terms as $term) {
            $content .= '<li>';
            $content .= '<strong>' . esc_html($term['word']) . '</strong>';
            $content .= ' - ' . esc_html($term['definition']);
            if (isset($term['example'])) {
                $content .= '<br><em>Example: ' . esc_html($term['example']) . '</em>';
            }
            $content .= '</li>';
        }
        
        $content .= '</ul>';
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Add lesson to Academy LMS lessons table
     */
    private function add_to_academy_lessons_table($lesson_id, $lesson_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->academy_tables['lessons'];
        
        if ($this->table_exists($table_name)) {
            $wpdb->insert(
                $table_name,
                array(
                    'lesson_id' => $lesson_id,
                    'lesson_title' => $lesson_data['title'],
                    'lesson_content' => $lesson_data['content'],
                    'lesson_duration' => isset($lesson_data['duration']) ? $lesson_data['duration'] : 0,
                    'lesson_order' => 0,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%d', '%d', '%s')
            );
        }
    }
    
    /**
     * Create flashcards table
     */
    private function create_flashcards_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->academy_tables['flashcards'];
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            front_text text NOT NULL,
            back_text text NOT NULL,
            difficulty_level varchar(20) DEFAULT 'medium',
            category varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY difficulty_level (difficulty_level),
            KEY category (category)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create questions table
     */
    private function create_questions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->academy_tables['questions'];
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) NOT NULL,
            question_text text NOT NULL,
            question_type varchar(50) NOT NULL,
            options longtext,
            correct_answer text NOT NULL,
            points int(11) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id),
            KEY question_type (question_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Check if table exists
     */
    private function table_exists($table_name) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        return $result === $table_name;
    }
    
    /**
     * Get published content for a chapter
     */
    public function get_published_content($chapter_id) {
        $published_content = array();
        
        // Get published lessons
        $lessons = get_posts(array(
            'post_type' => array('academy_lesson', 'academy_quiz', 'academy_exam'),
            'meta_query' => array(
                array(
                    'key' => '_lesson_plan_chapter_id',
                    'value' => $chapter_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1
        ));
        
        foreach ($lessons as $lesson) {
            $published_content[] = array(
                'id' => $lesson->ID,
                'title' => $lesson->post_title,
                'type' => $lesson->post_type,
                'status' => $lesson->post_status,
                'published_date' => $lesson->post_date
            );
        }
        
        return $published_content;
    }
}
