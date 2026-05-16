<?php
/**
 * Chapter Parser Class for Lesson Plan Editor
 * Handles parsing of uploaded books/textbooks into chapters
 */

if (!defined('ABSPATH')) {
    exit;
}

class My_Lesson_Editor_Chapter_Parser {
    
    private $upload_dir;
    private $allowed_extensions = array('pdf', 'txt', 'doc', 'docx');
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'] . '/lesson-plan-uploads/';
        
        // Create directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
    }
    
    /**
     * Parse uploaded book file into chapters
     */
    public function parse_book_file($file_path, $book_title = '') {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $this->allowed_extensions)) {
            return new WP_Error('invalid_file_type', 'Unsupported file type');
        }
        
        switch ($extension) {
            case 'pdf':
                return $this->parse_pdf($file_path, $book_title);
            case 'txt':
                return $this->parse_text($file_path, $book_title);
            case 'doc':
            case 'docx':
                return $this->parse_document($file_path, $book_title);
            default:
                return new WP_Error('unsupported_format', 'File format not supported');
        }
    }
    
    /**
     * Parse PDF files (requires additional library)
     */
    private function parse_pdf($file_path, $book_title) {
        // Note: This requires a PDF parsing library like Smalot\PdfParser
        // For now, return a placeholder structure
        return array(
            'book_title' => $book_title,
            'chapters' => array(
                array(
                    'chapter_number' => 1,
                    'chapter_title' => 'Chapter 1: Introduction',
                    'content' => 'PDF parsing requires additional library implementation.',
                    'word_count' => 0,
                    'estimated_time' => '30 minutes'
                )
            ),
            'total_chapters' => 1
        );
    }
    
    /**
     * Parse plain text files
     */
    private function parse_text($file_path, $book_title) {
        $content = file_get_contents($file_path);
        $chapters = $this->extract_chapters_from_text($content);
        
        return array(
            'book_title' => $book_title,
            'chapters' => $chapters,
            'total_chapters' => count($chapters)
        );
    }
    
    /**
     * Parse Word documents
     */
    private function parse_document($file_path, $book_title) {
        // Note: This requires additional library for Word document parsing
        // For now, return a placeholder structure
        return array(
            'book_title' => $book_title,
            'chapters' => array(
                array(
                    'chapter_number' => 1,
                    'chapter_title' => 'Chapter 1: Introduction',
                    'content' => 'Word document parsing requires additional library implementation.',
                    'word_count' => 0,
                    'estimated_time' => '30 minutes'
                )
            ),
            'total_chapters' => 1
        );
    }
    
    /**
     * Extract chapters from text content
     */
    private function extract_chapters_from_text($content) {
        $chapters = array();
        
        // Common chapter patterns
        $patterns = array(
            '/Chapter\s+(\d+)[:\s]+(.+?)(?=Chapter\s+\d+|$)/is',
            '/CHAPTER\s+(\d+)[:\s]+(.+?)(?=CHAPTER\s+\d+|$)/is',
            '/\d+\.\s+(.+?)(?=\d+\.\s+|$)/is'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $index => $match) {
                    $chapter_content = trim($match[0]);
                    $word_count = str_word_count($chapter_content);
                    $estimated_time = ceil($word_count / 200); // Assuming 200 words per minute
                    
                    $chapters[] = array(
                        'chapter_number' => $index + 1,
                        'chapter_title' => isset($match[2]) ? trim($match[2]) : "Chapter " . ($index + 1),
                        'content' => $chapter_content,
                        'word_count' => $word_count,
                        'estimated_time' => $estimated_time . ' minutes'
                    );
                }
                break; // Use first successful pattern
            }
        }
        
        // If no chapters found, treat entire content as one chapter
        if (empty($chapters)) {
            $word_count = str_word_count($content);
            $estimated_time = ceil($word_count / 200);
            
            $chapters[] = array(
                'chapter_number' => 1,
                'chapter_title' => 'Complete Content',
                'content' => $content,
                'word_count' => $word_count,
                'estimated_time' => $estimated_time . ' minutes'
            );
        }
        
        return $chapters;
    }
    
    /**
     * Save parsed chapters to database
     */
    public function save_chapters_to_db($book_id, $chapters) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lesson_plan_chapters';
        
        // Create table if it doesn't exist
        $this->create_chapters_table();
        
        // Delete existing chapters for this book
        $wpdb->delete($table_name, array('book_id' => $book_id));
        
        // Insert new chapters
        foreach ($chapters as $chapter) {
            $wpdb->insert(
                $table_name,
                array(
                    'book_id' => $book_id,
                    'chapter_number' => $chapter['chapter_number'],
                    'chapter_title' => $chapter['chapter_title'],
                    'content' => $chapter['content'],
                    'word_count' => $chapter['word_count'],
                    'estimated_time' => $chapter['estimated_time'],
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s', '%d', '%s', '%s')
            );
        }
        
        return true;
    }
    
    /**
     * Create chapters table
     */
    private function create_chapters_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lesson_plan_chapters';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            book_id bigint(20) NOT NULL,
            chapter_number int(11) NOT NULL,
            chapter_title varchar(255) NOT NULL,
            content longtext NOT NULL,
            word_count int(11) DEFAULT 0,
            estimated_time varchar(50) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY book_id (book_id),
            KEY chapter_number (chapter_number)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get chapters for a specific book
     */
    public function get_chapters($book_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lesson_plan_chapters';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE book_id = %d ORDER BY chapter_number ASC",
            $book_id
        ));
        
        return $results;
    }
}
