<?php
/*
Plugin Name: My Lesson Editor
Plugin URI:  https://example.com/
Description: A custom editor for lesson profiles, accessible via shortcode, with custom category and level dropdowns, and author selection.
Version:     1.4.0 // Updated version for new features
Author:      Muhamad Fikri Haikal
Author URI:  https://caastedu.com/
License:     GPL2
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Global flag to indicate if the shortcode is used on the current page
global $my_lesson_editor_shortcode_used;
$my_lesson_editor_shortcode_used = false;

// Define plugin constants
if ( ! defined( 'MY_LESSON_EDITOR_VERSION' ) ) {
    define( 'MY_LESSON_EDITOR_VERSION', '2.0.0' );
}
if ( ! defined( 'MY_LESSON_EDITOR_PLUGIN_URL' ) ) {
    define( 'MY_LESSON_EDITOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'MY_LESSON_EDITOR_PLUGIN_DIR' ) ) {
    define( 'MY_LESSON_EDITOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Include required classes
require_once MY_LESSON_EDITOR_PLUGIN_DIR . 'includes/class-chapter-parser.php';
require_once MY_LESSON_EDITOR_PLUGIN_DIR . 'includes/class-ai-generator.php';
require_once MY_LESSON_EDITOR_PLUGIN_DIR . 'includes/class-academy-lms-integration.php';
require_once MY_LESSON_EDITOR_PLUGIN_DIR . 'includes/class-academylm-client.php';
require_once MY_LESSON_EDITOR_PLUGIN_DIR . 'includes/delete-handler.php';

// Initialize classes
$my_lesson_editor_chapter_parser = new My_Lesson_Editor_Chapter_Parser();
$my_lesson_editor_ai_generator = new My_Lesson_Editor_AI_Generator();
$my_lesson_editor_academy_lms = new My_Lesson_Editor_Academy_LMS_Integration();

// Define Webhook URLs for individual agent testing (if still used)
if ( ! defined( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_R1' ) ) {
    define( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_R1', 'https://n8n.srv835792.hstgr.cloud/webhook/48d3ec34-bb02-448d-846a-788a3dcfe0ae' );
}
if ( ! defined( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_V3' ) ) {
    define( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_V3', 'https://n8n.srv835792.hstgr.cloud/webhook/78897c47-fca8-44ab-88a7-933ce5ff772b' );
}
if ( ! defined( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_QWEN_2_5' ) ) {
    define( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_QWEN_2_5', 'https://n8n.srv835792.hstgr.cloud/webhook/100d1001-6584-4650-9756-79ee12199af3' );
}

// Define Webhook URL for n8n main automation (override in wp-config.php if needed).
if ( ! defined( 'MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL' ) ) {
    define( 'MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL', MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_R1 );
}


/**
 * Register Custom Post Type for lesson Content
 */
function my_lesson_editor_register_cpt() {
    $labels = array(
        'name'          => _x( 'Lessons', 'Post Type General Name', 'my-lesson-editor' ),
        'singular_name' => _x( 'Lesson', 'Post Type Singular Name', 'my-lesson-editor' ),
        'menu_name'     => __( 'Lessons', 'my-lesson-editor' ),
        'all_items'     => __( 'All lessons', 'my-lesson-editor' ),
        'add_new_item'  => __( 'Add New lesson', 'my-lesson-editor' ),
        'add_new'       => __( 'Add New', 'my-lesson-editor' ),
        'new_item'      => __( 'New lesson', 'my-lesson-editor' ),
        'edit_item'     => __( 'Edit lesson', 'my-lesson-editor' ),
        'update_item'   => __( 'Update lesson', 'my-lesson-editor' ),
        'view_item'     => __( 'View lesson', 'my-lesson-editor' ),
        'search_items'  => __( 'Search lessons', 'my-lesson-editor' ),
        'not_found'     => __( 'Not Found', 'my-lesson-editor' ),
        'not_found_in_trash' => __( 'Not found in Trash', 'my-lesson-editor' ),
    );
    $args = array(
        'label'               => __( 'Lesson', 'my-lesson-editor' ),
        'description'         => __( 'Content associated with lessons', 'my-lesson-editor' ),
        'labels'              => $labels,
        'supports'            => array( 'title', 'editor', 'author', 'custom-fields', 'thumbnail' ),
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 21,
        'menu_icon'           => 'dashicons-lesson',
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => true,
        'can_export'          => true,
        'has_archive'         => true,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'capability_type'     => 'post',
        'rewrite'             => array( 'slug' => 'lesson-profile' ),
        'query_var'           => true,
    );
    register_post_type( 'lesson_profile', $args );
}
add_action( 'init', 'my_lesson_editor_register_cpt' );

/**
 * Register custom taxonomies for lessons (Category and Level)
 */
function my_lesson_editor_register_taxonomies() {
    // Category Taxonomy
    $category_labels = array(
        'name'              => _x( 'Lesson Categories', 'taxonomy general name', 'my-lesson-editor' ),
        'singular_name'     => _x( 'lesson Category', 'taxonomy singular name', 'my-lesson-editor' ),
        'search_items'      => __( 'Search lesson Categories', 'my-lesson-editor' ),
        'all_items'         => __( 'All lesson Categories', 'my-lesson-editor' ),
        'parent_item'       => __( 'Parent lesson Category', 'my-lesson-editor' ),
        'parent_item_colon' => __( 'Parent lesson Category:', 'my-lesson-editor' ),
        'edit_item'         => __( 'Edit lesson Category', 'my-lesson-editor' ),
        'update_item'       => __( 'Update lesson Category', 'my-lesson-editor' ),
        'add_new_item'      => __( 'Add New lesson Category', 'my-lesson-editor' ),
        'new_item_name'     => __( 'New lesson Category Name', 'my-lesson-editor' ),
        'menu_name'         => __( 'Categories', 'my-lesson-editor' ),
    );
    $category_args = array(
        'hierarchical'      => true,
        'labels'            => $category_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'lesson-category' ),
    );
    register_taxonomy( 'lesson_category', array( 'lesson_profile' ), $category_args );

    // Level Taxonomy
    $level_labels = array(
        'name'              => _x( 'Lesson Levels', 'taxonomy general name', 'my-lesson-editor' ),
        'singular_name'     => _x( 'lesson Level', 'taxonomy singular name', 'my-lesson-editor' ),
        'search_items'      => __( 'Search lesson Levels', 'my-lesson-editor' ),
        'all_items'         => __( 'All lesson Levels', 'my-lesson-editor' ),
        'edit_item'         => __( 'Edit lesson Level', 'my-lesson-editor' ),
        'update_item'       => __( 'Update lesson Level', 'my-lesson-editor' ),
        'add_new_item'      => __( 'Add New lesson Level', 'my-lesson-editor' ),
        'new_item_name'     => __( 'New lesson Level Name', 'my-lesson-editor' ),
        'menu_name'         => __( 'Levels', 'my-lesson-editor' ),
    );
    $level_args = array(
        'hierarchical'      => false,
        'labels'            => $level_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'lesson-level' ),
    );
    register_taxonomy( 'lesson_level', array( 'lesson_profile' ), $level_args );

    // Placement Category Taxonomy (New)
    $placement_labels = array(
        'name'                       => _x( 'Placement Categories', 'Taxonomy General Name', 'my-lesson-editor' ),
        'singular_name'              => _x( 'Placement Category', 'Taxonomy Singular Name', 'my-lesson-editor' ),
        'menu_name'                  => __( 'Placement', 'my-lesson-editor' ),
        'all_items'                  => __( 'All Placements', 'my-lesson-editor' ),
        'parent_item'                => __( 'Parent Placement', 'my-lesson-editor' ),
        'parent_item_colon'          => __( 'Parent Placement:', 'my-lesson-editor' ),
        'new_item_name'              => __( 'New Placement Name', 'my-lesson-editor' ),
        'add_new_item'               => __( 'Add New Placement', 'my-lesson-editor' ),
        'edit_item'                  => __( 'Edit Placement', 'my-lesson-editor' ),
        'update_item'                => __( 'Update Placement', 'my-lesson-editor' ),
        'view_item'                  => __( 'View Placement', 'my-lesson-editor' ),
        'separate_items_with_commas' => __( 'Separate placements with commas', 'my-lesson-editor' ),
        'add_or_remove_items'        => __( 'Add or remove placements', 'my-lesson-editor' ),
        'choose_from_most_used'      => __( 'Choose from the most used placements', 'my-lesson-editor' ),
        'popular_items'              => __( 'Popular Placements', 'my-lesson-editor' ),
        'search_items'               => __( 'Search Placements', 'my-lesson-editor' ),
        'not_found'                  => __( 'No Placements Found', 'my-lesson-editor' ),
        'no_terms'                   => __( 'No placements', 'my-lesson-editor' ),
        'items_list'                 => __( 'Placements list', 'my-lesson-editor' ),
        'items_list_navigation'      => __( 'Placements list navigation', 'my-lesson-editor' ),
    );
    $placement_args = array(
        'labels'                     => $placement_labels,
        'hierarchical'               => true, // Can be true or false depending on your need
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_tagcloud'              => false,
        'rewrite'                    => array( 'slug' => 'lesson-placement' ),
        'show_in_rest'               => true,
    );
    register_taxonomy( 'lesson_placement', array( 'lesson_profile' ), $placement_args );
}
add_action( 'init', 'my_lesson_editor_register_taxonomies' );

/**
 * Add default terms for lesson Categories and Levels on plugin activation.
 */
function my_lesson_editor_add_default_terms() {
    $categories = array(
        'Fiction',
        'Non-fiction',
        'Language Arts',
        'STEM',
        'Tests & Assessments',
        'Arts & Design'
    );
    foreach ( $categories as $category_name ) {
        if ( ! term_exists( $category_name, 'lesson_category' ) ) {
            wp_insert_term( $category_name, 'lesson_category' );
        }
    }

    $levels = array(
        'Elementary',
        'Intermediate',
        'Advanced',
        'College'
    );
    foreach ( $levels as $level_name ) {
        if ( ! term_exists( $level_name, 'lesson_level' ) ) {
            wp_insert_term( $level_name, 'lesson_level' );
        }
    }

    // Default terms for Placement Category (New)
    $placements = array(
        'Homepage Featured',
        'Category Page',
        'Sidebar Ads',
        'Blog Post Insert',
        'Landing Page',
        'Home Left Section 1',
        'Home Left Section 2',
        'Home Right List 1',
        'Home Right List 2',
        'Author Footer',
        'Author Right',
        'Article Footer',
        'Article Right',
        'lesson Right',
        'lesson Footer',
        'Video Left',
        'Video Right',
        'Video Footer'
    );
    foreach ( $placements as $placement_name ) {
        if ( ! term_exists( $placement_name, 'lesson_placement' ) ) {
            wp_insert_term( $placement_name, 'lesson_placement' );
        }
    }
}
register_activation_hook( __FILE__, 'my_lesson_editor_add_default_terms' );


/**
 * Enqueue lesson editor scripts/styles.
 *
 * Must run when the shortcode renders: wp_enqueue_scripts runs before the_content,
 * so the old "global shortcode_used" check there never became true and assets never loaded.
 */
function my_lesson_editor_enqueue_frontend_assets() {
    static $mle_assets_enqueued = false;
    if ( $mle_assets_enqueued ) {
        return;
    }
    $mle_assets_enqueued = true;

    wp_enqueue_media();

    if ( current_user_can( 'edit_posts' ) ) {
        wp_enqueue_script( 'wp-tinymce' );
        wp_enqueue_script( 'editor' );
        wp_enqueue_script( 'wplink' );
        wp_enqueue_script( 'wp-plupload' );
        wp_enqueue_script( 'thickbox' );
        wp_enqueue_style( 'thickbox' );
    }

    wp_enqueue_script(
        'mle-shared-uploader',
        MY_LESSON_EDITOR_PLUGIN_URL . 'js/mle-shared-uploader.js',
        array(),
        MY_LESSON_EDITOR_VERSION,
        true
    );

    wp_enqueue_script(
        'my-lesson-editor-script',
        MY_LESSON_EDITOR_PLUGIN_URL . 'js/my-lesson-editor.js',
        array( 'jquery', 'mle-shared-uploader' ),
        MY_LESSON_EDITOR_VERSION,
        true
    );

    wp_enqueue_script(
        'my-lesson-editor-enhanced-script',
        MY_LESSON_EDITOR_PLUGIN_URL . 'js/my-lesson-editor-enhanced.js',
        array( 'jquery', 'my-lesson-editor-script' ),
        MY_LESSON_EDITOR_VERSION,
        true
    );

    wp_enqueue_style(
        'my-lesson-editor-style',
        MY_LESSON_EDITOR_PLUGIN_URL . 'css/my-lesson-editor.css',
        array(),
        MY_LESSON_EDITOR_VERSION
    );

    $mle_local = array(
        'ajaxurl'                  => admin_url( 'admin-ajax.php' ),
        'nonce'                    => wp_create_nonce( 'lesson_editor_nonce' ),
        'alert_no_lesson_selected' => __( 'Please Select a lesson first.', 'my-lesson-editor' ),
        'alert_no_author_selected' => __( 'Please select an author first.', 'my-lesson-editor' ),
        'alert_confirm_delete'     => __( 'Are you sure you want to delete this lesson? This action cannot be undone.', 'my-lesson-editor' ),
        'alert_content_not_found'  => __( 'No content found for the selected lesson.', 'my-lesson-editor' ),
        'delete_confirm_message'   => __( 'Are you sure you want to delete this item?', 'my-lesson-editor' ),
        'delete_success_message'   => __( 'Deleted successfully', 'my-lesson-editor' ),
        'delete_error_message'     => __( 'Failed to delete this item. Please try again.', 'my-lesson-editor' ),
        'n8n_webhook_url'          => defined( 'MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL' ) ? MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL : '',
    );
    wp_localize_script( 'my-lesson-editor-script', 'mylessonEditorAjax', $mle_local );
    wp_localize_script( 'my-lesson-editor-script', 'myLessonPlanEditorAjax', $mle_local );
}

/**
 * Handle form submission for lesson Content Editor.
 */
function my_lesson_editor_handle_submission() {
    if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'my_lesson_editor_submit' ) {
        return;
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_safe_redirect( add_query_arg( 'status', 'error_permissions', wp_get_referer() ) );
        exit;
    }

    // Verify nonce
    if ( ! isset( $_POST['_wpnonce_lesson_content'] ) || ! wp_verify_nonce( $_POST['_wpnonce_lesson_content'], 'lesson_content_submission' ) ) {
        wp_die( 'Security check failed. Please refresh the page and try again.' );
    }

    // Sanitize and retrieve form data
    $selected_lesson_id   = isset( $_POST['selected_lesson_id'] ) ? intval( $_POST['selected_lesson_id'] ) : 0;
    $new_lesson_name      = sanitize_text_field( $_POST['new_lesson_name'] );
    $lesson_subtitle      = sanitize_text_field( $_POST['lesson_subtitle'] );
    $lesson_content       = wp_kses_post( $_POST['lesson_content'] );
    $lesson_cover_id      = isset( $_POST['lesson_cover_id'] ) ? intval( $_POST['lesson_cover_id'] ) : 0;
    $placement_category = isset( $_POST['placement_category'] ) ? sanitize_text_field( $_POST['placement_category'] ) : ''; // New: Placement Category
    $insert_link        = isset( $_POST['insert_link'] ) ? esc_url_raw( $_POST['insert_link'] ) : '';

    // Get selected taxonomy terms (slugs)
    $lesson_category_slug = isset( $_POST['lesson_category'] ) ? sanitize_text_field( $_POST['lesson_category'] ) : '';
    $lesson_level_slug    = isset( $_POST['lesson_level'] ) ? sanitize_text_field( $_POST['lesson_level'] ) : '';
    $selected_author_id = isset( $_POST['selected_author_id'] ) ? intval( $_POST['selected_author_id'] ) : 0;

    $submit_action      = sanitize_text_field( $_POST['submit_lesson_action'] ); // Existing action for Save/Publish/etc.
    $placement_action   = sanitize_text_field( $_POST['placement_action'] ); // New: Action for Placement Submit

    // Determine the lesson title to use for the post
    $post_title = '';
    if ( ! empty( $new_lesson_name ) ) {
        $post_title = $new_lesson_name;
    } elseif ( $selected_lesson_id > 0 ) {
        $existing_lesson = get_post( $selected_lesson_id );
        if ( $existing_lesson ) {
            $post_title = $existing_lesson->post_title;
        }
    }

    $existing_post_id = isset( $_POST['lesson_post_id'] ) ? intval( $_POST['lesson_post_id'] ) : 0;

    // Basic validation for new lesson or updating existing lesson if title changed
    if ( empty( $post_title ) && empty($existing_post_id) ) {
        wp_safe_redirect( add_query_arg( 'status', 'error_empty_title', wp_get_referer() ) );
        exit;
    }

    // Determine post status based on button clicked
    $post_status = 'pending';
    $redirect_status = 'error';

    if ( $submit_action === 'publish' ) {
        if ( current_user_can( 'publish_lesson_profiles' ) || current_user_can( 'publish_posts' ) ) {
            $post_status = 'publish';
            $redirect_status = 'lesson_published';
        } else {
            $post_status = 'pending';
            $redirect_status = 'lesson_pending_review';
        }
    } elseif ( $submit_action === 'save' ) {
        $post_status = 'draft';
        $redirect_status = 'lesson_saved_draft';
    } elseif ( $submit_action === 'unpublish' ) {
        $post_status = 'draft';
        $redirect_status = 'lesson_unpublished';
    } elseif ( $submit_action === 'archive' ) {
        if ( get_post_status_object('archive') ) {
             $post_status = 'archive';
        } else {
            $post_status = 'draft';
        }
        $redirect_status = 'lesson_archived';
    } elseif ( $submit_action === 'delete' ) {
        if ( $existing_post_id ) {
            if ( current_user_can( 'delete_lesson_profiles' ) || current_user_can( 'delete_posts' ) ) {
                if ( wp_delete_post( $existing_post_id, true ) ) {
                    wp_safe_redirect( add_query_arg( 'status', 'lesson_deleted', wp_get_referer() ) );
                    exit;
                } else {
                    error_log( 'Error deleting lesson post: ' . $existing_post_id );
                    wp_safe_redirect( add_query_arg( 'status', 'error_lesson_delete', wp_get_referer() ) );
                    exit;
                }
            } else {
                wp_safe_redirect( add_query_arg( 'status', 'error_permissions', wp_get_referer() ) );
                exit;
            }
        } else {
            wp_safe_redirect( add_query_arg( 'status', 'error_no_content_to_delete', wp_get_referer() ) );
            exit;
        }
    } elseif ( $placement_action === 'publish_placement' ) { // Handle new placement publish
        // Ensure the post exists and the user has permission to publish
        if ($existing_post_id > 0) {
            if ( current_user_can( 'publish_lesson_profiles' ) || current_user_can( 'publish_posts' ) ) {
                // Update post status to publish if not already
                if (get_post_status($existing_post_id) !== 'publish') {
                    wp_update_post(array(
                        'ID'          => $existing_post_id,
                        'post_status' => 'publish',
                    ));
                }
                // Set the placement taxonomy
                if (!empty($placement_category)) {
                    wp_set_post_terms( $existing_post_id, $placement_category, 'lesson_placement' );
                }
                $redirect_status = 'lesson_published_to_placement';
                $post_id = $existing_post_id;
            } else {
                wp_safe_redirect( add_query_arg( 'status', 'error_permissions', wp_get_referer() ) );
                exit;
            }
        } else {
            wp_safe_redirect( add_query_arg( 'status', 'error_no_lesson_selected_for_placement', wp_get_referer() ) );
            exit;
        }
    }


    // Prepare post data for save/update if not already handled by delete/placement publish
    $post_data = array(
        'post_title'    => $post_title,
        'post_content'  => $lesson_content,
        'post_status'   => $post_status,
        'post_type'     => 'lesson_profile',
        'post_author'   => get_current_user_id(),
    );

    $post_id = 0;
    if ( $existing_post_id > 0 ) {
        $post_data['ID'] = $existing_post_id;
        $result = wp_update_post( $post_data, true );
    } else {
        $result = wp_insert_post( $post_data, true );
    }

    if ( is_wp_error( $result ) ) {
        error_log( 'Error creating/updating lesson post: ' . $result->get_error_message() );
        wp_safe_redirect( add_query_arg( 'status', 'error_lesson_db', wp_get_referer() ) );
    } elseif ( $result === 0 ) {
        error_log( 'Failed to create/update lesson post, wp_insert_post/wp_update_post returned 0.' );
        wp_safe_redirect( add_query_arg( 'status', 'error_lesson_db', wp_get_referer() ) );
    } else {
        // Save lesson subtitle as post meta
        update_post_meta( $result, '_lesson_subtitle', $lesson_subtitle );

        // Set featured image (lesson cover)
        if ( ! empty( $lesson_cover_id ) ) {
            set_post_thumbnail( $result, $lesson_cover_id );
        } else {
            delete_post_thumbnail( $result ); // Remove if ID is 0 or empty
        }

        // Save new insert_link as post meta (footer_position is now placement_category)
        update_post_meta( $result, '_insert_link', $insert_link );
        // Set the placement taxonomy directly
        if ( ! empty( $placement_category ) ) {
            wp_set_post_terms( $result, $placement_category, 'lesson_placement' );
        } else {
            wp_set_post_terms( $result, null, 'lesson_placement' ); // Clear if no placement selected
        }


        // Save selected Author ID as post meta for the lesson
        update_post_meta( $result, '_lesson_author_id', $selected_author_id );

        // Set taxonomies
        if ( ! empty( $lesson_category_slug ) ) {
            wp_set_post_terms( $result, $lesson_category_slug, 'lesson_category' );
        } else {
            wp_set_post_terms( $result, null, 'lesson_category' ); // Clear if no category selected
        }

        if ( ! empty( $lesson_level_slug ) ) {
            wp_set_post_terms( $result, $lesson_level_slug, 'lesson_level' );
        } else {
            wp_set_post_terms( $result, null, 'lesson_level' ); // Clear if no level selected
        }

        wp_safe_redirect( add_query_arg( 'status', $redirect_status, wp_get_referer() ) );
    }
    exit;
}
add_action( 'admin_post_my_lesson_editor_submit', 'my_lesson_editor_handle_submission' );
add_action( 'admin_post_nopriv_my_lesson_editor_submit', 'my_lesson_editor_handle_submission' );

/**
 * AJAX handler to get lesson content based on lesson ID.
 */
add_action( 'wp_ajax_my_lesson_editor_get_lesson_content', 'my_lesson_editor_get_lesson_content_ajax' );
function my_lesson_editor_get_lesson_content_ajax() {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to view this content.', 'my-lesson-editor' ) ) );
    }

    check_ajax_referer( 'lesson_editor_nonce', 'nonce' );

    $lesson_id = isset( $_POST['lesson_id'] ) ? intval( $_POST['lesson_id'] ) : 0;

    if ( $lesson_id ) {
        $args = array(
            'p'              => $lesson_id,
            'post_type'      => 'lesson_profile',
            'post_status'    => array( 'publish', 'pending', 'draft', 'archive' ),
            'posts_per_page' => 1,
        );
        $lesson_query = new WP_Query( $args );

        if ( $lesson_query->have_posts() ) {
            $lesson_post = $lesson_query->posts[0];

            $current_categories = wp_get_post_terms( $lesson_post->ID, 'lesson_category', array( 'fields' => 'slugs' ) );
            $current_levels     = wp_get_post_terms( $lesson_post->ID, 'lesson_level', array( 'fields' => 'slugs' ) );
            $current_placement  = wp_get_post_terms( $lesson_post->ID, 'lesson_placement', array( 'fields' => 'slugs' ) ); // New: Get placement

            $associated_author_id = get_post_meta( $lesson_post->ID, '_lesson_author_id', true );

            // Ensure lesson cover URL is fetched correctly
            $lesson_cover_id = get_post_thumbnail_id( $lesson_post->ID );
            $lesson_cover_url = $lesson_cover_id ? wp_get_attachment_url( $lesson_cover_id ) : '';


            wp_send_json_success( array(
                'lesson_post_id'    => $lesson_post->ID,
                'lesson_name'       => esc_html( $lesson_post->post_title ),
                'lesson_subtitle'   => esc_html( get_post_meta( $lesson_post->ID, '_lesson_subtitle', true ) ),
                'lesson_content'    => $lesson_post->post_content,
                'lesson_cover_id'   => $lesson_cover_id,
                'lesson_cover_url'  => $lesson_cover_url,
                'placement_category' => !empty($current_placement) ? $current_placement[0] : '', // New: Send placement
                'insert_link'     => esc_url( get_post_meta( $lesson_post->ID, '_insert_link', true ) ),
                'lesson_categories' => !empty($current_categories) ? $current_categories[0] : '',
                'lesson_levels'     => !empty($current_levels) ? $current_levels[0] : '',
                'associated_author_id' => intval($associated_author_id),
            ) );
        } else {
            wp_send_json_success( array(
                'lesson_post_id'    => 0,
                'lesson_name'       => '',
                'lesson_subtitle'   => '',
                'lesson_content'    => '',
                'lesson_cover_id'   => 0,
                'lesson_cover_url'  => '',
                'placement_category' => '', // New: Clear placement
                'insert_link'     => '',
                'lesson_categories' => '',
                'lesson_levels'     => '',
                'associated_author_id' => 0,
                'message'         => 'No existing content found for this lesson. You can create a new one.',
            ) );
        }
    } else {
        wp_send_json_success( array(
            'lesson_post_id'    => 0,
            'lesson_name'       => '',
            'lesson_subtitle'   => '',
            'lesson_content'    => '',
            'lesson_cover_id'   => 0,
            'lesson_cover_url'  => '',
            'placement_category' => '', // New: Clear placement
            'insert_link'     => '',
            'lesson_categories' => '',
            'lesson_levels'     => '',
            'associated_author_id' => 0,
            'message'         => __( 'Invalid lesson ID, or no lesson selected.', 'my-lesson-editor' )
        ) );
    }
}


/**
 * AJAX handler to get author content based on author ID.
 */
add_action( 'wp_ajax_my_lesson_editor_get_author_content', 'my_lesson_editor_get_author_content_ajax' );
function my_lesson_editor_get_author_content_ajax() {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to view this content.', 'my-lesson-editor' ) ) );
    }

    check_ajax_referer( 'lesson_editor_nonce', 'nonce' );

    $author_id = isset( $_POST['author_id'] ) ? intval( $_POST['author_id'] ) : 0;

    if ( $author_id ) {
        $author_post = get_post( $author_id );
        if ( $author_post && $author_post->post_type === 'author_profile' ) {
            wp_send_json_success( array(
                'author_id'   => $author_post->ID,
                'author_name' => $author_post->post_title,
                'message'     => 'Author selected: ' . $author_post->post_title,
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Author not found.', 'my-lesson-editor' ) ) );
        }
    } else {
        wp_send_json_error( array( 'message' => __( 'No author selected.', 'my-lesson-editor' ) ) );
    }
}


/**
 * Build n8n webhook envelope: { source, body: { ... } } so expressions like $json.body.book_name work.
 * Also builds selected_tasks[] with id, resolved_prompt, mc_qty, short_answer_qty, discussion_questions_count.
 *
 * @param array $automation_in Decoded automation_payload from the browser.
 * @param array $tasks_out     Boolean map lesson_plan, vocabulary, …
 * @param array $file_extras   textbook_filename, textbook_base64, optional trigger_spreadsheet_content.
 * @return array Top-level keys: source (string), body (array) for n8n $json.body.*.
 */
function my_lesson_editor_build_n8n_webhook_envelope( array $automation_in, array $tasks_out, array $file_extras = array() ) {
    $book_name   = isset( $automation_in['book_name'] ) ? sanitize_text_field( $automation_in['book_name'] ) : '';
    $author_name = isset( $automation_in['author_name'] ) ? sanitize_text_field( $automation_in['author_name'] ) : '';
    $chapter     = isset( $automation_in['chapter'] ) ? sanitize_text_field( $automation_in['chapter'] ) : '';

    $difficulty = isset( $automation_in['difficulty_level'] ) ? sanitize_text_field( $automation_in['difficulty_level'] ) : '';
    $allowed    = array( 'beginner', 'intermediate', 'advanced', 'preparatory', 'regular' );
    if ( $difficulty === '' || ! in_array( $difficulty, $allowed, true ) ) {
        $difficulty = 'intermediate';
    }

    $task_prompts = array(
        'lesson_plan' => 'Generate a comprehensive lesson plan for the given book context and chapter. Include learning objectives, instructional sequence, formative checks, and materials notes.',
        'vocabulary'  => 'Generate a vocabulary list with clear definitions and example sentences, aligned to the book and chapter.',
        'flashcards'  => 'Generate flashcard items (term/definition or question/answer) derived from the vocabulary scope.',
        'quiz'        => 'Generate a chapter quiz with the specified numbers of multiple-choice and short-answer questions.',
        'midterm'     => 'Generate a midterm-style assessment appropriate to the difficulty level and book context.',
        'final_exam'  => 'Generate a comprehensive final-exam-style assessment for the book context.',
    );

    $default_qty = array(
        'lesson_plan' => array( 'mc_qty' => null, 'short_answer_qty' => null, 'discussion_questions_count' => null ),
        'vocabulary'  => array( 'mc_qty' => null, 'short_answer_qty' => null, 'discussion_questions_count' => null ),
        'flashcards'  => array( 'mc_qty' => null, 'short_answer_qty' => null, 'discussion_questions_count' => null ),
        'quiz'        => array( 'mc_qty' => 10, 'short_answer_qty' => 4, 'discussion_questions_count' => 0 ),
        'midterm'     => array( 'mc_qty' => 15, 'short_answer_qty' => 5, 'discussion_questions_count' => 3 ),
        'final_exam'  => array( 'mc_qty' => 20, 'short_answer_qty' => 8, 'discussion_questions_count' => 5 ),
    );

    $overrides = array();
    if ( ! empty( $automation_in['task_quantities'] ) && is_array( $automation_in['task_quantities'] ) ) {
        foreach ( $automation_in['task_quantities'] as $tid => $q ) {
            if ( is_array( $q ) ) {
                $overrides[ sanitize_key( $tid ) ] = $q;
            }
        }
    }

    $selected_tasks = array();
    foreach ( $tasks_out as $task_id => $enabled ) {
        if ( ! $enabled ) {
            continue;
        }
        $qty = isset( $default_qty[ $task_id ] ) ? $default_qty[ $task_id ] : array(
            'mc_qty'                       => null,
            'short_answer_qty'             => null,
            'discussion_questions_count' => null,
        );
        if ( isset( $overrides[ $task_id ] ) && is_array( $overrides[ $task_id ] ) ) {
            foreach ( array( 'mc_qty', 'short_answer_qty', 'discussion_questions_count' ) as $qk ) {
                if ( array_key_exists( $qk, $overrides[ $task_id ] ) && null !== $overrides[ $task_id ][ $qk ] && '' !== $overrides[ $task_id ][ $qk ] ) {
                    $qty[ $qk ] = (int) $overrides[ $task_id ][ $qk ];
                }
            }
        }
        $base = isset( $task_prompts[ $task_id ] ) ? $task_prompts[ $task_id ] : 'Generate educational content for this task type.';
        if ( $chapter !== '' && '—' !== $chapter ) {
            $base .= ' Scope: chapter ' . $chapter . '.';
        }
        $selected_tasks[] = array(
            'id'                           => $task_id,
            'resolved_prompt'              => $base,
            'mc_qty'                       => $qty['mc_qty'],
            'short_answer_qty'             => $qty['short_answer_qty'],
            'discussion_questions_count'   => $qty['discussion_questions_count'],
        );
    }

    $body = array(
        'book_name'         => $book_name,
        'author_name'       => $author_name,
        'difficulty_level'  => $difficulty,
        'chapter'           => $chapter,
        'model'             => isset( $automation_in['model'] ) ? sanitize_text_field( $automation_in['model'] ) : 'deepseek_r1',
        'lesson_id'         => isset( $automation_in['lesson_id'] ) ? absint( $automation_in['lesson_id'] ) : 0,
        'lesson_title'      => isset( $automation_in['lesson_title'] ) ? sanitize_text_field( $automation_in['lesson_title'] ) : '',
        'lesson_subtitle'   => isset( $automation_in['lesson_subtitle'] ) ? sanitize_text_field( $automation_in['lesson_subtitle'] ) : '',
        'lesson_category'   => isset( $automation_in['lesson_category'] ) ? sanitize_text_field( $automation_in['lesson_category'] ) : '',
        'tasks'             => $tasks_out,
        'selected_tasks'    => $selected_tasks,
    );

    if ( ! empty( $file_extras ) && is_array( $file_extras ) ) {
        $body = array_merge( $body, $file_extras );
    }

    return array(
        'source' => 'my_lesson_editor_full_automation',
        'body'   => $body,
    );
}

/**
 * Normalize LLM / n8n JSON (title+content or final_title+final_content, nested or string JSON in output).
 *
 * @param array $data Parsed JSON from webhook.
 * @return array Tuple [0 => title string, 1 => content string].
 */
function my_lesson_editor_extract_automation_output( $data ) {
    $title   = '';
    $content = '';
    if ( ! is_array( $data ) ) {
        return array( $title, $content );
    }

    $pick_title = function ( $row ) {
        if ( ! is_array( $row ) ) {
            return '';
        }
        if ( ! empty( $row['final_title'] ) ) {
            return (string) $row['final_title'];
        }
        if ( ! empty( $row['title'] ) ) {
            return (string) $row['title'];
        }
        return '';
    };

    $pick_content = function ( $row ) {
        if ( ! is_array( $row ) ) {
            return '';
        }
        if ( isset( $row['final_content'] ) && $row['final_content'] !== '' ) {
            return (string) $row['final_content'];
        }
        if ( isset( $row['content'] ) && $row['content'] !== '' ) {
            return (string) $row['content'];
        }
        return '';
    };

    $title   = $pick_title( $data );
    $content = $pick_content( $data );

    if ( ( $title === '' || $content === '' ) && ! empty( $data['data'] ) && is_array( $data['data'] ) ) {
        if ( $title === '' ) {
            $title = $pick_title( $data['data'] );
        }
        if ( $content === '' ) {
            $content = $pick_content( $data['data'] );
        }
    }

    if ( ( $title === '' || $content === '' ) && ! empty( $data['output'] ) && is_string( $data['output'] ) ) {
        $inner = json_decode( $data['output'], true );
        if ( is_array( $inner ) ) {
            if ( $title === '' ) {
                $title = $pick_title( $inner );
            }
            if ( $content === '' ) {
                $content = $pick_content( $inner );
            }
        }
    }

    return array( $title, $content );
}

// AJAX handler for full automation process (for lesson editor)
add_action( 'wp_ajax_my_lesson_editor_full_automation', 'my_lesson_editor_full_automation_callback' );
function my_lesson_editor_full_automation_callback() {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to run automation.', 'my-lesson-editor' ) ) );
    }

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'lesson_editor_nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Nonce verification failed. Please reload the page.', 'my-lesson-editor' ) ) );
    }

    $webhook = MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL;
    if ( empty( $webhook ) || ( is_string( $webhook ) && false !== strpos( $webhook, 'YOUR_N8N' ) ) ) {
        $webhook = MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_R1;
    }

    $automation_in = array();
    if ( ! empty( $_POST['automation_payload'] ) ) {
        $decoded = json_decode( wp_unslash( $_POST['automation_payload'] ), true );
        if ( is_array( $decoded ) ) {
            $automation_in = $decoded;
        }
    }

    $task_keys = array( 'lesson_plan', 'vocabulary', 'flashcards', 'quiz', 'midterm', 'final_exam' );
    $tasks_out = array();
    foreach ( $task_keys as $tk ) {
        $tasks_out[ $tk ] = false;
    }
    if ( ! empty( $automation_in['tasks'] ) && is_array( $automation_in['tasks'] ) ) {
        foreach ( $task_keys as $tk ) {
            if ( array_key_exists( $tk, $automation_in['tasks'] ) ) {
                $tasks_out[ $tk ] = filter_var( $automation_in['tasks'][ $tk ], FILTER_VALIDATE_BOOLEAN );
            }
        }
    }

    $has_task = false;
    foreach ( $tasks_out as $v ) {
        if ( $v ) {
            $has_task = true;
            break;
        }
    }
    if ( ! $has_task ) {
        wp_send_json_error( array( 'message' => __( 'Select at least one content type to generate.', 'my-lesson-editor' ) ) );
    }

    $file_extras = array();

    // Optional textbook / book file (same field names as the lesson editor UI).
    $file_fields = array( 'book_upload', 'textbook_file' );
    foreach ( $file_fields as $field ) {
        if ( empty( $_FILES[ $field ]['tmp_name'] ) || ! is_uploaded_file( $_FILES[ $field ]['tmp_name'] ) ) {
            continue;
        }
        $size = isset( $_FILES[ $field ]['size'] ) ? (int) $_FILES[ $field ]['size'] : 0;
        if ( $size <= 0 || $size > 4 * 1024 * 1024 ) {
            wp_send_json_error( array( 'message' => __( 'Uploaded file must be under 4 MB.', 'my-lesson-editor' ) ) );
        }
        $raw = file_get_contents( $_FILES[ $field ]['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false === $raw ) {
            wp_send_json_error( array( 'message' => __( 'Could not read uploaded file.', 'my-lesson-editor' ) ) );
        }
        $file_extras['textbook_filename'] = isset( $_FILES[ $field ]['name'] ) ? sanitize_file_name( wp_unslash( $_FILES[ $field ]['name'] ) ) : 'upload.bin';
        $file_extras['textbook_base64']   = base64_encode( $raw );
        break;
    }

    // Optional legacy CSV trigger (not required).
    if ( ! empty( $_FILES['trigger_spreadsheet']['tmp_name'] ) && is_uploaded_file( $_FILES['trigger_spreadsheet']['tmp_name'] ) ) {
        $csv_raw = file_get_contents( $_FILES['trigger_spreadsheet']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false !== $csv_raw ) {
            $file_extras['trigger_spreadsheet_content'] = base64_encode( $csv_raw );
        }
    }

    $payload = my_lesson_editor_build_n8n_webhook_envelope( $automation_in, $tasks_out, $file_extras );

    $response = wp_remote_post(
        $webhook,
        array(
            'method'  => 'POST',
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 300,
        )
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => $response->get_error_message() ) );
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( $code < 200 || $code >= 300 ) {
        wp_send_json_error(
            array(
                'message' => sprintf(
                    /* translators: 1: HTTP status code, 2: response body excerpt */
                    __( 'n8n returned HTTP %1$d. Body: %2$s', 'my-lesson-editor' ),
                    $code,
                    function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 500 ) : substr( $body, 0, 500 )
                ),
            )
        );
    }

    $book_fallback = ! empty( $payload['body']['book_name'] ) ? $payload['body']['book_name'] : __( 'Generated output', 'my-lesson-editor' );

    if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
        wp_send_json_success(
            array(
                'message'       => __( 'Automation finished (non-JSON response).', 'my-lesson-editor' ),
                'http_code'     => $code,
                'n8n_response'  => $body,
                'final_title'   => $book_fallback,
                'final_content' => $body,
            )
        );
    }

    list( $final_title, $final_content ) = my_lesson_editor_extract_automation_output( $data );

    wp_send_json_success(
        array(
            'message'       => isset( $data['message'] ) ? $data['message'] : __( 'OK', 'my-lesson-editor' ),
            'http_code'     => $code,
            'n8n_response'  => $data,
            'final_title'   => $final_title ? $final_title : $book_fallback,
            'final_content' => $final_content ? $final_content : wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
        )
    );
}

// AJAX handler for saving final generated content as a new draft (for lesson editor)
add_action( 'wp_ajax_my_lesson_editor_save_final_content_as_draft', 'my_lesson_editor_save_final_content_as_draft_callback' );
function my_lesson_editor_save_final_content_as_draft_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'lesson_editor_nonce' ) ) { // Using lesson_editor_nonce now
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    $title = sanitize_text_field( $_POST['title'] );
    $content = wp_kses_post( $_POST['content'] );

    if ( empty( $title ) || empty( $content ) ) {
        wp_send_json_error( 'Title and content cannot be empty.' );
    }

    $new_post_data = array(
        'post_title'    => $title,
        'post_content'  => $content,
        'post_status'   => 'draft',
        'post_type'     => 'lesson_profile',
    );

    $new_post_id = wp_insert_post( $new_post_data, true );

    if ( is_wp_error( $new_post_id ) ) {
        wp_send_json_error( $new_post_id->get_error_message() );
    } else {
        wp_send_json_success( array( 'message' => 'New draft created successfully!', 'post_id' => $new_post_id ) );
    }
}

// AJAX handler for loading content from a specific draft (for "Generated Drafts Content" section in lesson editor)
add_action( 'wp_ajax_my_lesson_editor_load_draft_content', 'my_lesson_editor_load_draft_content_callback' );
function my_lesson_editor_load_draft_content_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'lesson_editor_nonce' ) ) { // Using lesson_editor_nonce now
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    $post_id    = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $draft_type = isset( $_POST['draft_type'] ) ? sanitize_text_field( wp_unslash( $_POST['draft_type'] ) ) : '';

    $post = get_post( $post_id );

    if ( ! $post || $post->post_type !== 'lesson_profile' ) {
        wp_send_json_error( 'Invalid post ID or post type.' );
    }

    $content_to_return = '';
    $title_to_return   = $post->post_title;

    if ( '' === $draft_type || 'main' === $draft_type ) {
        $content_to_return = $post->post_content;
    } elseif ( 'draft1' === $draft_type ) {
        $content_to_return = get_post_meta( $post_id, 'agent_1_draft_content', true );
    } elseif ( 'draft2' === $draft_type ) {
        $content_to_return = get_post_meta( $post_id, 'agent_2_draft_content', true );
    } elseif ( 'draft3' === $draft_type ) {
        $content_to_return = get_post_meta( $post_id, 'agent_3_draft_content', true );
    } else {
        $content_to_return = $post->post_content;
    }

    wp_send_json_success( array(
        'title' => $title_to_return,
        'content' => apply_filters( 'the_content', $content_to_return ),
    ));
}

// AJAX handler for parsing uploaded book files
add_action( 'wp_ajax_my_lesson_editor_parse_book', 'my_lesson_editor_parse_book_callback' );
function my_lesson_editor_parse_book_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'lesson_editor_nonce' ) ) {
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'You do not have permission to upload files.' );
    }

    if ( ! isset( $_FILES['book_file'] ) || empty( $_FILES['book_file']['tmp_name'] ) ) {
        wp_send_json_error( 'No file uploaded.' );
    }

    $book_title = sanitize_text_field( $_POST['book_title'] );
    $file_path = $_FILES['book_file']['tmp_name'];
    
    global $my_lesson_editor_chapter_parser;
    $parsed_data = $my_lesson_editor_chapter_parser->parse_book_file( $file_path, $book_title );

    if ( is_wp_error( $parsed_data ) ) {
        wp_send_json_error( $parsed_data->get_error_message() );
    }

    // Create a book post
    $book_post_data = array(
        'post_title' => $book_title,
        'post_content' => 'Parsed book with ' . $parsed_data['total_chapters'] . ' chapters',
        'post_status' => 'draft',
        'post_type' => 'lesson_profile',
        'post_author' => get_current_user_id()
    );

    $book_id = wp_insert_post( $book_post_data );

    if ( is_wp_error( $book_id ) ) {
        wp_send_json_error( 'Failed to create book post.' );
    }

    // Save chapters to database
    $my_lesson_editor_chapter_parser->save_chapters_to_db( $book_id, $parsed_data['chapters'] );

    // Add book metadata
    update_post_meta( $book_id, '_book_title', $book_title );
    update_post_meta( $book_id, '_total_chapters', $parsed_data['total_chapters'] );
    update_post_meta( $book_id, '_book_type', 'parsed_book' );

    wp_send_json_success( array(
        'book_id' => $book_id,
        'book_title' => $book_title,
        'chapters' => $parsed_data['chapters'],
        'total_chapters' => $parsed_data['total_chapters']
    ));
}

// AJAX handler for AI content generation
add_action( 'wp_ajax_my_lesson_editor_generate_ai_content', 'my_lesson_editor_generate_ai_content_callback' );
function my_lesson_editor_generate_ai_content_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'lesson_editor_nonce' ) ) {
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'You do not have permission to generate content.' );
    }

    $chapter_id = intval( $_POST['chapter_id'] );
    $content_types = array_map( 'sanitize_text_field', $_POST['content_types'] );
    $step_data = array_map( 'sanitize_text_field', $_POST['step_data'] );

    global $my_lesson_editor_ai_generator;
    $generated_content = $my_lesson_editor_ai_generator->generate_content( $chapter_id, $content_types, $step_data );

    if ( is_wp_error( $generated_content ) ) {
        wp_send_json_error( $generated_content->get_error_message() );
    }

    // Save generated content to database
    foreach ( $content_types as $content_type ) {
        if ( isset( $generated_content[ $content_type ] ) ) {
            $my_lesson_editor_ai_generator->save_generated_content( 
                $chapter_id, 
                $content_type, 
                $generated_content[ $content_type ] 
            );
        }
    }

    wp_send_json_success( $generated_content );
}

// AJAX handler for publishing to Academy LMS
add_action( 'wp_ajax_my_lesson_editor_publish_to_academy', 'my_lesson_editor_publish_to_academy_callback' );
function my_lesson_editor_publish_to_academy_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'lesson_editor_nonce' ) ) {
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'You do not have permission to publish content.' );
    }

    $content_type = sanitize_text_field( $_POST['content_type'] );
    $publish_type = sanitize_text_field( $_POST['publish_type'] );
    $chapter_id = intval( $_POST['chapter_id'] );
    $include_video = isset( $_POST['include_video'] ) && $_POST['include_video'] === 'true';
    $include_slides = isset( $_POST['include_slides'] ) && $_POST['include_slides'] === 'true';

    global $my_lesson_editor_academy_lms;
    global $my_lesson_editor_ai_generator;

    // Get generated content
    $generated_content = $my_lesson_editor_ai_generator->get_generated_content( $chapter_id, $content_type );

    if ( empty( $generated_content ) ) {
        wp_send_json_error( 'No generated content found for this chapter.' );
    }

    $content_data = $generated_content[0]->content_data;
    $published_id = null;

    // Publish based on content type
    switch ( $content_type ) {
        case 'lesson_plan':
            $published_id = $my_lesson_editor_academy_lms->publish_lesson_plan( $content_data, $chapter_id );
            break;
        case 'vocabulary':
            $published_id = $my_lesson_editor_academy_lms->publish_vocabulary( $content_data, $chapter_id );
            break;
        case 'flashcards':
            $published_id = $my_lesson_editor_academy_lms->publish_flashcards( $content_data, $chapter_id );
            break;
        case 'quiz':
            $published_id = $my_lesson_editor_academy_lms->publish_quiz( $content_data, $chapter_id );
            break;
        case 'midterm':
            $published_id = $my_lesson_editor_academy_lms->publish_exam( $content_data, 'midterm', $chapter_id );
            break;
        case 'final':
            $published_id = $my_lesson_editor_academy_lms->publish_exam( $content_data, 'final', $chapter_id );
            break;
        default:
            wp_send_json_error( 'Unsupported content type.' );
    }

    if ( is_wp_error( $published_id ) ) {
        wp_send_json_error( $published_id->get_error_message() );
    }

    // Update generated content status to published
    $my_lesson_editor_ai_generator->update_content_status( $generated_content[0]->id, 'published' );

    wp_send_json_success( array(
        'published_id' => $published_id,
        'content_type' => $content_type,
        'publish_type' => $publish_type,
        'message' => 'Content published successfully to Academy LMS.'
    ));
}

// AJAX handler for getting published content
add_action( 'wp_ajax_my_lesson_editor_get_published_content', 'my_lesson_editor_get_published_content_callback' );
function my_lesson_editor_get_published_content_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'lesson_editor_nonce' ) ) {
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'You do not have permission to view published content.' );
    }

    $chapter_id = intval( $_POST['chapter_id'] );

    global $my_lesson_editor_academy_lms;
    $published_content = $my_lesson_editor_academy_lms->get_published_content( $chapter_id );

    wp_send_json_success( $published_content );
}


/**
 * Shortcode to display the lesson Content Editor.
 */
/**
 * AJAX: list AcademyLM courses for the lesson editor dropdown.
 */
add_action( 'wp_ajax_mle_get_courses', 'mle_ajax_get_courses_callback' );
function mle_ajax_get_courses_callback() {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'my-lesson-editor' ) ), 403 );
	}
	check_ajax_referer( 'lesson_editor_nonce', 'nonce' );

	$client  = new MLE_AcademyLM_Client();
	$courses = $client->get_courses();
	wp_send_json_success(
		array(
			'remote'  => $client->is_remote(),
			'courses' => $courses,
		)
	);
}

/**
 * AJAX: extract text from an uploaded desktop file.
 *
 * Field name: "upload_file" (multipart/form-data).
 */
add_action( 'wp_ajax_mle_extract_text_from_upload', 'mle_ajax_extract_text_from_upload_callback' );
function mle_ajax_extract_text_from_upload_callback() {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'my-lesson-editor' ) ), 403 );
	}
	check_ajax_referer( 'lesson_editor_nonce', 'nonce' );

	if ( empty( $_FILES['upload_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['upload_file']['tmp_name'] ) ) {
		wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'my-lesson-editor' ) ) );
	}

	$size = isset( $_FILES['upload_file']['size'] ) ? (int) $_FILES['upload_file']['size'] : 0;
	if ( $size <= 0 || $size > 8 * 1024 * 1024 ) {
		wp_send_json_error( array( 'message' => __( 'File must be greater than 0 and less than 8 MB.', 'my-lesson-editor' ) ) );
	}

	$name   = sanitize_file_name( wp_unslash( $_FILES['upload_file']['name'] ) );
	$client = new MLE_AcademyLM_Client();
	$text   = $client->extract_text( $_FILES['upload_file']['tmp_name'], $name );

	if ( is_wp_error( $text ) ) {
		wp_send_json_error( array( 'message' => $text->get_error_message() ) );
	}

	$preview_html = wpautop( esc_html( wp_check_invalid_utf8( (string) $text, true ) ) );
	wp_send_json_success(
		array(
			'filename' => $name,
			'size'     => $size,
			'text'     => (string) $text,
			'html'     => $preview_html,
		)
	);
}

/**
 * AJAX: submit current main editor content to AcademyLM.
 */
add_action( 'wp_ajax_mle_submit_to_academylm', 'mle_ajax_submit_to_academylm_callback' );
function mle_ajax_submit_to_academylm_callback() {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'my-lesson-editor' ) ), 403 );
	}
	check_ajax_referer( 'lesson_editor_nonce', 'nonce' );

	$title     = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$content   = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
	$course_id = isset( $_POST['course_id'] ) ? sanitize_text_field( wp_unslash( $_POST['course_id'] ) ) : '';

	if ( $title === '' || $content === '' ) {
		wp_send_json_error( array( 'message' => __( 'Title and content are required.', 'my-lesson-editor' ) ) );
	}

	$client = new MLE_AcademyLM_Client();
	$result = $client->create_lesson(
		array(
			'title'     => $title,
			'content'   => $content,
			'course_id' => $course_id,
		)
	);

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'message'   => __( 'Lesson submitted to AcademyLM.', 'my-lesson-editor' ),
			'lesson_id' => isset( $result['lesson_id'] ) ? $result['lesson_id'] : 0,
			'remote'    => ! empty( $result['remote'] ),
		)
	);
}

function my_lesson_editor_shortcode() {
    global $my_lesson_editor_shortcode_used;
    $my_lesson_editor_shortcode_used = true;

    ob_start();

    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        ?>
        <p class="text-center text-red-500">You must be logged in with sufficient permissions to access the lesson Content Editor.</p>
        <?php
        return ob_get_clean();
    }

    // Enqueue here: wp_enqueue_scripts already ran before shortcode output.
    my_lesson_editor_enqueue_frontend_assets();

    // Initialize values for form fields - These will be populated by JS on load/selection
    $lesson_name_val      = '';
    $lesson_subtitle_val  = '';
    $lesson_content_val   = '';
    $lesson_cover_id_val  = 0;
    $lesson_cover_url_val = '';
    $placement_category_val = ''; // New: Initialize for placement
    $insert_link_val    = '';
    $lesson_post_id_val   = 0;
    $lesson_category_val  = '';
    $lesson_level_val     = '';
    $associated_author_id_val = 0;
    $caast_delete_nonce = wp_create_nonce( 'caast_delete_nonce' );


    // Display status messages
    if ( isset( $_GET['status'] ) ) {
        $status_message = '';
        $status_class = 'bg-green-100 border border-green-400 text-green-700';

        switch ( $_GET['status'] ) {
            case 'lesson_published':
                $status_message = 'lesson successfully published!'; break;
            case 'lesson_saved_draft':
                $status_message = 'lesson saved as draft!'; break;
            case 'lesson_pending_review':
                $status_message = 'lesson submitted for review!'; break;
            case 'lesson_unpublished':
                $status_message = 'lesson successfully unpublished (set to draft)!'; break;
            case 'lesson_archived':
                $status_message = 'lesson successfully archived!'; break;
            case 'lesson_deleted':
                $status_message = 'lesson successfully deleted!'; break;
            case 'lesson_added':
                $status_message = 'New lesson added successfully!'; break;
            case 'lesson_published_to_placement': // New status for placement publish
                $status_message = 'lesson successfully published to selected placement!'; break;
            case 'error_empty_title':
                $status_message = 'Error: lesson name cannot be empty. Please fill the lesson name field.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_lesson_db':
                $status_message = 'An error occurred while submitting the lesson. Please try again.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_lesson_delete':
                $status_message = 'An error occurred while deleting the lesson. Please try again.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_no_content_to_delete':
                $status_message = 'No content was selected for deletion.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_permissions':
                $status_message = 'You do not have sufficient permissions to perform this action.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_no_lesson_selected_for_placement': // New error status
                $status_message = 'Please Select a lesson before attempting to publish to a placement.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            default:
                $status_message = 'An unknown status occurred.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700';
        }
        echo '<div class="notice ' . esc_attr($status_class) . ' px-4 py-3 rounded-none relative mb-4 is-dismissible"><p>' . esc_html($status_message) . '</p></div>';
    }

    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;
    $user_avatar = get_avatar_url( $current_user->ID, array( 'size' => 24 ) );

    ?>
    <div class="bg-gray-100 text-[12px] text-gray-600 font-mono flex justify-end gap-9 px-20 py-1 max-w-12xl mx-auto">
        <span>myEMAIL</span>
        <span>myCALENDAR</span>
        <span>myPROJECTS</span>
        <span>myLEARNING</span>
        <span>myWORK</span>
        <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="text-gray-600 hover:text-black">Logout</a>
    </div>

    <main class="w-full mx-auto flex flex-col lg:flex-row gap-2 px-2 py-2 min-h-screen">
        <input type="hidden" id="caast_delete_nonce" value="<?php echo esc_attr( $caast_delete_nonce ); ?>">
        <aside aria-label="Left admin navigation panel" class="w-full lg:w-64 xl:w-72 border border-gray-300 rounded-none p-3 text-xs text-gray-700 font-sans bg-white flex-shrink-0 max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
            <!-- User Profile Section -->
            <div class="flex items-center gap-2 mb-4 p-2 bg-gray-50 rounded-lg">
                <img alt="User avatar placeholder" class="rounded-full w-8 h-8 flex-shrink-0" src="<?php echo esc_url($user_avatar); ?>" />
                <div class="flex-1 min-w-0">
                    <span class="block text-xs font-medium text-gray-900 truncate"><?php echo esc_html($user_email); ?></span>
                    <span class="block text-xs text-gray-500">Content Editor</span>
            </div>
                <i class="fas fa-sync-alt cursor-pointer text-gray-400 text-sm hover:text-gray-600 transition-colors"></i>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="space-y-1">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 px-2">Website Content Editor</h2>
                
                <!-- Content Editor Links -->
                <div class="space-y-1 mb-4">
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/author-endpoint-v2/">
                        <i class="fas fa-user text-gray-500 w-4"></i> 
                        <span>Author Endpoint</span>
                    </a>
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/article-endpoint/">
                        <i class="fas fa-newspaper text-gray-500 w-4"></i> 
                        <span>Article Endpoint</span>
                    </a>
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/book-endpoint/">
                        <i class="fas fa-book-open text-gray-500 w-4"></i> 
                        <span>Book Endpoint</span>
                    </a>
                    <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-3 py-2 text-xs font-medium" href="https://caastedu.com/llesson-plan-editor/">
                        <i class="fas fa-file-alt text-blue-600 w-4"></i> 
                        <span>Lesson Endpoint</span>
                    </a>
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/video-endpoint/">
                        <i class="fab fa-youtube text-gray-500 w-4"></i> 
                        <span>Video Endpoint</span>
                    </a>
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/content-automation/">
                        <i class="fas fa-robot text-gray-500 w-4"></i> 
                        <span>Content Automation</span>
                    </a>
                </div>
                
                <!-- Dashboard Links -->
                <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 px-2">Dashboard</h3>
                    <div class="space-y-1">
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/">
                            <i class="fas fa-th-large text-gray-500 w-4"></i> 
                            <span>Dashboard</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/profile/">
                            <i class="fas fa-user text-gray-500 w-4"></i> 
                            <span>My Profile</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/enrolled-courses/">
                            <i class="fas fa-graduation-cap text-gray-500 w-4"></i> 
                            <span>Enrolled Courses</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/my-lessonings/">
                            <i class="fas fa-calendar-check text-gray-500 w-4"></i> 
                            <span>My Tutor Sessions</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/download-certificate/">
                            <i class="fas fa-download text-gray-500 w-4"></i> 
                            <span>Download Certificates</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/wishlist/">
                            <i class="far fa-heart text-gray-500 w-4"></i> 
                            <span>Wishlist</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/reviews/">
                            <i class="far fa-star text-gray-500 w-4"></i> 
                            <span>Reviews</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/purchase-history/">
                            <i class="fas fa-history text-gray-500 w-4"></i> 
                            <span>Purchase History</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/my-account/">
                            <i class="fas fa-store text-gray-500 w-4"></i> 
                            <span>Store Dashboard</span>
                        </a>
                    </div>
                </div>
                
                <!-- Course Management Links -->
                <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 px-2">Course Management</h3>
                    <div class="space-y-1">
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/courses/">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-graduation-cap text-gray-500 w-4"></i> 
                                <span>Courses</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/lessons/">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-file-alt text-gray-500 w-4"></i> 
                                <span>All Lessons</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/quizzes/">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-question-circle text-gray-500 w-4"></i> 
                                <span>Quizzes</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/meeting/">
                            <div class="flex items-center gap-3">
                                <i class="fab fa-youtube text-gray-500 w-4"></i> 
                                <span>Meetings</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/tutor-lessoning/">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-calendar-check text-gray-500 w-4"></i> 
                                <span>Tutor Sessions</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/assignments/">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-tasks text-gray-500 w-4"></i> 
                                <span>Assignments</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/question-answer/">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-question-circle text-gray-500 w-4"></i> 
                                <span>Q&A</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/announcements/">
                            <i class="fas fa-bullhorn text-gray-500 w-4"></i> 
                            <span>Announcements</span>
                        </a>
                    </div>
                </div>
            </nav>
            
            <!-- Settings Section -->
            <div class="border-t border-gray-200 pt-4 mt-4">
                <a href="https://caastedu.com/dashboard/settings/" class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors">
                    <i class="fas fa-cog text-gray-500 w-4"></i> 
                    <span>Settings</span>
                </a>
            </div>
        </aside>

        <section class="flex-1 flex flex-col lg:flex-row gap-3 w-full max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]" style="min-height:100vh;">
            <!-- Main Content Area -->
            <div class="flex-1 bg-white border border-gray-300 rounded-none p-2 lg:p-6 min-h-[calc(100vh-40px)] flex flex-col w-full">
                <h2 class="text-xl font-semibold mb-4">Lesson Content Editor</h2>

                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data" class="font-sans w-full flex flex-col flex-1 box-shadow:none" id="lesson-editor-form" style="max-width: none !important; box-shadow: none !important; border-radius: 0 !important; margin: 0 !important; padding: 0 !important; background: transparent !important;">
                    <input type="hidden" name="action" value="my_lesson_editor_submit">
                    <?php wp_nonce_field('lesson_content_submission', '_wpnonce_lesson_content'); ?>
                    <input type="hidden" name="lesson_post_id" id="lesson_post_id" value="<?php echo esc_attr( $lesson_post_id_val ); ?>">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end mb-3 w-full">
                        <div class="md:col-span-2">
                            <label for="selected_author_id" class="block text-sm font-medium text-gray-700">Select an author</label>
                            <select name="selected_author_id" id="selected_author_id" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select an author --</option>
                                <?php
                                // Fetch authors from 'author_profile' CPT
                                $authors = get_posts( array(
                                    'post_type'      => 'author_profile', // Assuming 'author_profile' CPT
                                    'posts_per_page' => -1,
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                                    'post_status'    => 'publish', // Only published authors
                                ) );
                                foreach ( $authors as $author ) {
                                    $selected = selected( $associated_author_id_val, $author->ID, false );
                                    echo '<option value="' . esc_attr( $author->ID ) . '" ' . $selected . '>' . esc_html( $author->post_title ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" id="load-author-content-btn" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Submit</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end mb-3 w-full">
                        <div class="md:col-span-2">
                            <label for="selected_lesson_id" class="block text-sm font-medium text-gray-700">Select a lesson</label>
                            <select name="selected_lesson_id" id="selected_lesson_id" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select a lesson --</option>
                                <?php
                                $lessons = get_posts( array(
                                    'post_type'      => 'lesson_profile',
                                    'posts_per_page' => -1,
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                                    'post_status'    => array('publish', 'draft', 'pending', 'archive'),
                                ) );
                                foreach ( $lessons as $lesson ) {
                                    echo '<option value="' . esc_attr( $lesson->ID ) . '">' . esc_html( $lesson->post_title ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" id="load-lesson-content-btn" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Submit</button>
                        </div>
                    </div>

                    <!-- AcademyLM Course + Source/Action Block (Upload | Edit | Submit) -->
                    <div class="border border-gray-200 rounded-none p-3 mb-4 w-full bg-gray-50" data-mle-uploader="lesson">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end mb-3 w-full">
                            <div class="md:col-span-2">
                                <div class="flex items-center justify-between mb-1">
                                    <label for="select_academylm_course" class="text-sm font-medium text-gray-700"><?php esc_html_e( 'Select a Course (AcademyLM)', 'my-lesson-editor' ); ?></label>
                                    <a href="https://caastedu.com/wp-admin/admin.php?page=academy-courses" target="_blank" rel="noopener noreferrer" class="text-xs text-indigo-600 hover:text-indigo-800 hover:underline font-medium ml-2 whitespace-nowrap" style="line-height:1.4;"><?php esc_html_e( 'Course Setting', 'my-lesson-editor' ); ?> &#x2197;</a>
                                </div>
                                <select id="select_academylm_course" name="select_academylm_course" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value=""><?php esc_html_e( 'Loading courses…', 'my-lesson-editor' ); ?></option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="button" id="refresh-academylm-courses" class="button button-secondary bg-white text-gray-800 border border-gray-300 px-4 py-2 rounded-none hover:bg-gray-100 w-full"><?php esc_html_e( 'Refresh', 'my-lesson-editor' ); ?></button>
                            </div>
                        </div>

                        <div class="flex flex-col mb-3 w-full">
                            <label for="mle_source_book_name" class="block text-sm font-medium text-gray-700"><?php esc_html_e( 'Book Name', 'my-lesson-editor' ); ?></label>
                            <input type="text" id="mle_source_book_name" name="mle_source_book_name" placeholder="<?php esc_attr_e( 'Mirrors the AI Book Name or upload filename', 'my-lesson-editor' ); ?>" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-2 w-full">
                            <button type="button" data-mle-action="upload" class="button button-secondary bg-white text-gray-800 border border-gray-300 px-4 py-2 rounded-none hover:bg-gray-100 w-full inline-flex items-center justify-center gap-2">
                                <span class="mle-btn-label"><?php esc_html_e( 'Upload', 'my-lesson-editor' ); ?></span>
                                <span class="mle-btn-spinner hidden inline-block w-3 h-3 border-2 border-gray-400 border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                            </button>
                            <button type="button" data-mle-action="edit" class="button button-secondary bg-white text-gray-800 border border-gray-300 px-4 py-2 rounded-none hover:bg-gray-100 w-full inline-flex items-center justify-center gap-2">
                                <span class="mle-btn-label"><?php esc_html_e( 'Edit', 'my-lesson-editor' ); ?></span>
                                <span class="mle-btn-spinner hidden inline-block w-3 h-3 border-2 border-gray-400 border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                            </button>
                            <button type="button" data-mle-action="submit" class="button button-primary bg-blue-600 text-white px-4 py-2 rounded-none hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 w-full inline-flex items-center justify-center gap-2">
                                <span class="mle-btn-label"><?php esc_html_e( 'Submit', 'my-lesson-editor' ); ?></span>
                                <span class="mle-btn-spinner hidden inline-block w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                            </button>
                        </div>

                        <input type="file" data-mle-file="hidden" class="hidden" accept=".pdf,.txt,.md,.csv,.doc,.docx" />
                        <p data-mle-status class="text-xs text-gray-600 mt-2 min-h-[1rem]" role="status" aria-live="polite"></p>
                    </div>
                    <!-- /AcademyLM block -->

                    <div class="flex flex-col mb-3 w-full">
                        <label for="new_lesson_name" class="block text-sm font-medium text-gray-700">Add a lesson</label>
                        <input type="text" name="new_lesson_name" id="new_lesson_name" placeholder="Enter lesson name" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_attr($lesson_name_val); ?>" />
                    </div>

                    <div class="flex flex-col mb-3 w-full">
                        <label for="lesson_subtitle" class="block text-sm font-medium text-gray-700">lesson Subtitle:</label>
                        <input type="text" name="lesson_subtitle" id="lesson_subtitle" placeholder="lesson subtitle text" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_attr($lesson_subtitle_val); ?>" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end mb-3 w-full">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Upload lesson cover image</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="text" id="lesson_cover_url" name="lesson_cover_url" class="flex-grow border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Browse lesson cover" readonly value="<?php echo esc_url($lesson_cover_url_val); ?>" />
                                <input type="hidden" id="lesson_cover_id" name="lesson_cover_id" value="<?php echo esc_attr($lesson_cover_id_val); ?>" />
                            </div>
                            <div id="lesson-cover-preview" class="mt-2 text-center" style="<?php echo empty($lesson_cover_url_val) ? 'display:none;' : ''; ?>">
                                <img src="<?php echo esc_url($lesson_cover_url_val); ?>" alt="lesson cover Preview" style="max-width: 150px; height: auto; display: inline-block; margin-bottom: 5px;" />
                                <button type="button" class="remove-lesson-cover text-red-500 hover:text-red-700 text-xs mt-1 rounded-none block mx-auto">Remove Image</button>
                            </div>
                        </div>
                        <div class="flex items-end">
                            <button type="button" class="button button-secondary browse-lesson-cover bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Browse</button>
                        </div>
                    </div>

                    <div class="mb-3 w-full">
                        <label for="lesson_content" class="block text-sm font-medium text-gray-700">Body Content:</label>
                        <?php
                        if ( current_user_can( 'edit_posts' ) ) {
                            wp_editor( $lesson_content_val, 'lesson_content', array(
                                'textarea_name' => 'lesson_content',
                                'textarea_rows' => 12,
                                'teeny'         => false,
                                'media_buttons' => false,
                                'tinymce'       => array(
                                    'height' => 300,
                                    'toolbar1' => 'undo redo | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link | forecolor backcolor',
                                    'toolbar2' => 'print preview | table | charmap emoticons | code fullscreen',
                                ),
                                'editor_class' => 'mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm',
                            ) );
                        } else {
                            echo '<textarea name="lesson_content" id="lesson_content" rows="12" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">' . esc_textarea($lesson_content_val) . '</textarea>';
                        }
                        ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 w-full">
                        <div>
                            <label for="lesson_category" class="block text-sm font-medium text-gray-700">Select a category</label>
                            <select name="lesson_category" id="lesson_category" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select a category --</option>
                                <?php
                                $categories = get_terms( array(
                                    'taxonomy'   => 'lesson_category',
                                    'hide_empty' => false,
                                ) );
                                foreach ( $categories as $category ) {
                                    $selected = ( $lesson_category_val === $category->slug ) ? 'selected' : '';
                                    echo '<option value="' . esc_attr( $category->slug ) . '" ' . $selected . '>' . esc_html( $category->name ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label for="lesson_level" class="block text-gray-700 text-sm font-bold mb-2">
                                lesson Level:
                            </label>
                            <select name="lesson_level" id="lesson_level" class="w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">-- Select a Level --</option>
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-4 w-full">
                        <div class="md:col-span-2 flex flex-col">
                            <label for="submit_lesson_action_select" class="block text-sm font-medium text-gray-700">Action</label>
                            <select name="submit_lesson_action" id="submit_lesson_action_select" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="save">Save Draft</option>
                                <option value="publish">Publish Ready</option>
                                <option value="unpublish">Unpublish</option>
                                <option value="archive">Archive</option>
                                <option value="delete">Delete</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" name="submit_lesson_action_btn" value="submit" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Submit</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-4 w-full">
                        <div class="flex flex-col md:col-span-2">
                            <label for="placement_category" class="block text-sm font-medium text-gray-700">Placement Category</label>
                            <select name="placement_category" id="placement_category" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select Placement --</option>
                                <?php
                                $placements = get_terms( array(
                                    'taxonomy'   => 'lesson_placement',
                                    'hide_empty' => false,
                                ) );
                                foreach ( $placements as $placement ) {
                                    $selected = ( $placement_category_val === $placement->slug ) ? 'selected' : '';
                                    echo '<option value="' . esc_attr( $placement->slug ) . '" ' . $selected . '>' . esc_html( $placement->name ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <!--<div class="flex flex-col">-->
                        <!--    <label for="insert_link" class="block text-sm font-medium text-gray-700">Insert Link</label>-->
                        <!--    <input type="url" name="insert_link" id="insert_link" placeholder="https://example.com" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_url($insert_link_val); ?>" />-->
                        <!--</div>-->
                        <div class="flex items-end">
                            <button type="submit" name="placement_action" value="publish_placement" class="button button-primary bg-blue-600 text-white px-4 py-2 rounded-none hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 w-full">Publish</button>
                        </div>
                    </div>
                </form>
            </div>
        <style>
            @media (max-width: 1024px) {
                section.flex-1.flex, section .flex-1 {
                    min-height: unset !important;
                }
            }
            section.flex-1.flex {
                min-height: 100vh;
                width: 100vw;
                max-width: 100vw;
            }
            section.flex-1.flex .flex-1 {
                min-width:0;
                flex-shrink:1;
                flex-grow:1;
                width: 100%;
                display: flex;
                flex-direction: column;
            }
            #lesson-editor-form {
                flex: 1 1 auto;
                min-height: 0;
                max-width: 100vw;
            }
            @media (max-width: 640px) {
                section.flex-1.flex .flex-1 {
                    padding: 0.5rem !important;
                }
                #lesson-editor-form {
                    padding: 0 !important;
                }
            }
        </style>
        <!-- Responsiveness to avoid blank spaces on browser zoom -->
        <script>
        (function() {
            function fixVH() {
                // Ensure full vertical fill even if zoomed.
                document.querySelectorAll('section.flex-1.flex').forEach(function(section) {
                    section.style.minHeight = window.innerHeight + 'px';
                });
            }
            window.addEventListener('resize', fixVH);
            window.addEventListener('zoom', fixVH); // Just in case; not widely supported.
            fixVH();
        })();
        </script>





            <!-- AI Content Generator Sidebar -->
            <aside class="w-full lg:w-1/4 bg-white border border-gray-300 rounded-lg p-4 lg:p-6 shadow-md max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                <h2 class="text-xl font-semibold text-gray-800 mb-4"><?php esc_html_e( 'AI Content Generator', 'my-lesson-editor' ); ?></h2>

                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3"><?php esc_html_e( 'Reference and context', 'my-lesson-editor' ); ?></h3>
                    <div class="mb-4">
                        <label for="book_title" class="block text-gray-700 text-sm font-bold mb-2"><?php esc_html_e( 'Book / textbook title', 'my-lesson-editor' ); ?></label>
                        <input type="text" id="book_title" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2" placeholder="<?php esc_attr_e( 'Optional — uses lesson title if empty', 'my-lesson-editor' ); ?>">
                    </div>
                    <div class="mb-4">
                        <label for="ai_chapter" class="block text-gray-700 text-sm font-bold mb-2"><?php esc_html_e( 'Chapter number or name', 'my-lesson-editor' ); ?></label>
                        <input type="text" id="ai_chapter" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2" placeholder="<?php esc_attr_e( 'e.g. Chapter 1 (optional)', 'my-lesson-editor' ); ?>">
                    </div>
                    <div class="mb-4">
                        <label for="llm_model_select" class="block text-gray-700 text-sm font-bold mb-2"><?php esc_html_e( 'AI model', 'my-lesson-editor' ); ?></label>
                        <select id="llm_model_select" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2">
                            <option value="deepseek_r1" selected><?php esc_html_e( 'DeepSeek R1', 'my-lesson-editor' ); ?></option>
                            <option value="gpt_4o"><?php esc_html_e( 'Kimi K2', 'my-lesson-editor' ); ?></option>
                            <option value="claude_3_5_sonnet"><?php esc_html_e( 'Qwen 3', 'my-lesson-editor' ); ?></option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="book_upload" class="block text-gray-700 text-sm font-bold mb-2"><?php esc_html_e( 'Upload book file (optional)', 'my-lesson-editor' ); ?></label>
                        <input type="file" id="book_upload" name="book_upload" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2" accept=".pdf,.txt,.doc,.docx">
                        <p class="text-gray-500 text-xs mt-1"><?php esc_html_e( 'PDF, TXT, DOC, DOCX — max 4 MB. Not required.', 'my-lesson-editor' ); ?></p>
                    </div>
                    <div id="book_upload_status" class="mb-4 text-xs text-gray-600"></div>
                </div>

                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4"><?php esc_html_e( 'Select Content Types to Generate', 'my-lesson-editor' ); ?></h3>
                    <div class="space-y-3">
                        <div class="content-type-card bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <label class="flex items-start space-x-3 cursor-pointer group">
                                <input type="checkbox" id="task_lesson_plan" class="content-type-checkbox mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" value="lesson_plan" data-task-key="lesson_plan">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-gray-900 group-hover:text-blue-700"><?php esc_html_e( 'Lesson plan by chapter', 'my-lesson-editor' ); ?></span>
                                    <p class="text-xs text-gray-600 mt-1"><?php esc_html_e( 'Lesson plans aligned to chapters', 'my-lesson-editor' ); ?></p>
                                </div>
                            </label>
                        </div>
                        <div class="content-type-card bg-green-50 border border-green-200 rounded-lg p-3">
                            <label class="flex items-start space-x-3 cursor-pointer group">
                                <input type="checkbox" id="task_vocabulary" class="content-type-checkbox mt-1 h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500" value="vocabulary" data-task-key="vocabulary">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-gray-900 group-hover:text-green-700"><?php esc_html_e( 'Vocabulary list', 'my-lesson-editor' ); ?></span>
                                    <p class="text-xs text-gray-600 mt-1"><?php esc_html_e( 'Key terms per chapter', 'my-lesson-editor' ); ?></p>
                                </div>
                            </label>
                        </div>
                        <div class="content-type-card bg-purple-50 border border-purple-200 rounded-lg p-3">
                            <label class="flex items-start space-x-3 cursor-pointer group">
                                <input type="checkbox" id="task_flashcards" class="content-type-checkbox mt-1 h-4 w-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500" value="flashcards" data-task-key="flashcards">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-gray-900 group-hover:text-purple-700"><?php esc_html_e( 'Flashcards', 'my-lesson-editor' ); ?></span>
                                    <p class="text-xs text-gray-600 mt-1"><?php esc_html_e( 'From vocabulary', 'my-lesson-editor' ); ?></p>
                                </div>
                            </label>
                        </div>
                        <div class="content-type-card bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <label class="flex items-start space-x-3 cursor-pointer group">
                                <input type="checkbox" id="task_quiz" class="content-type-checkbox mt-1 h-4 w-4 text-yellow-600 border-gray-300 rounded focus:ring-yellow-500" value="quiz" data-task-key="quiz">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-gray-900 group-hover:text-yellow-700"><?php esc_html_e( 'Quiz by chapter', 'my-lesson-editor' ); ?></span>
                                    <p class="text-xs text-gray-600 mt-1"><?php esc_html_e( 'Chapter quizzes', 'my-lesson-editor' ); ?></p>
                                </div>
                            </label>
                        </div>
                        <div class="content-type-card bg-red-50 border border-red-200 rounded-lg p-3">
                            <label class="flex items-start space-x-3 cursor-pointer group">
                                <input type="checkbox" id="task_midterm" class="content-type-checkbox mt-1 h-4 w-4 text-red-600 border-gray-300 rounded focus:ring-red-500" value="midterm" data-task-key="midterm">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-gray-900 group-hover:text-red-700"><?php esc_html_e( 'Midterm exam', 'my-lesson-editor' ); ?></span>
                                    <p class="text-xs text-gray-600 mt-1"><?php esc_html_e( 'Midterm-style assessment', 'my-lesson-editor' ); ?></p>
                                </div>
                            </label>
                        </div>
                        <div class="content-type-card bg-indigo-50 border border-indigo-200 rounded-lg p-3">
                            <label class="flex items-start space-x-3 cursor-pointer group">
                                <input type="checkbox" id="task_final_exam" class="content-type-checkbox mt-1 h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" value="final" data-task-key="final_exam">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-gray-900 group-hover:text-indigo-700"><?php esc_html_e( 'Final exam', 'my-lesson-editor' ); ?></span>
                                    <p class="text-xs text-gray-600 mt-1"><?php esc_html_e( 'Final assessment', 'my-lesson-editor' ); ?></p>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div class="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700"><?php esc_html_e( 'Selected content types', 'my-lesson-editor' ); ?></span>
                            <span id="selected-count" class="text-sm text-gray-600">0 <?php esc_html_e( 'selected', 'my-lesson-editor' ); ?></span>
                        </div>
                        <div id="selected-types" class="mt-2 text-xs text-gray-600"></div>
                    </div>
                </div>

                <div id="chapters_list" class="mb-6" style="display:none;"></div>
                <div id="generated_content_container" class="mb-6" style="display:none;"></div>

                <div class="mt-6 border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3"><?php esc_html_e( 'n8n automation', 'my-lesson-editor' ); ?></h3>
                    <p class="text-xs text-gray-600 mb-3"><?php esc_html_e( 'No manual prompt required for this button. Pick content types, then start.', 'my-lesson-editor' ); ?></p>
                    <button type="button" id="start_full_automation" class="button button-primary bg-blue-600 text-white px-4 py-2 rounded-none hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 w-full">
                        <span class="mle-generate-label"><?php esc_html_e( 'Start Automation Process', 'my-lesson-editor' ); ?></span>
                    </button>
                    <div id="full_automation_status" class="mt-3 p-3 rounded text-xs text-left min-h-[2.5rem] bg-gray-50 border border-gray-200 text-gray-700"></div>
                </div>

                <div class="mt-6 border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3"><?php esc_html_e( 'Output preview', 'my-lesson-editor' ); ?></h3>
                    <label for="final_generated_title" class="block text-gray-700 text-sm font-bold mb-2"><?php esc_html_e( 'Title', 'my-lesson-editor' ); ?></label>
                    <input type="text" id="final_generated_title" class="form-input mt-1 mb-3 block w-full border border-gray-300 rounded-none shadow-sm p-2" placeholder="<?php esc_attr_e( 'Filled after automation', 'my-lesson-editor' ); ?>">
                    <label for="final_generated_content" class="block text-gray-700 text-sm font-bold mb-2"><?php esc_html_e( 'Content', 'my-lesson-editor' ); ?></label>
                    <textarea id="final_generated_content" rows="12" class="output-content-textarea shadow appearance-none border border-gray-300 rounded-none w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm" placeholder="<?php esc_attr_e( 'n8n response / generated body', 'my-lesson-editor' ); ?>"></textarea>
                    <button type="button" id="upload_final_content_to_editor" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 w-full mt-3"><?php esc_html_e( 'Load into main editor', 'my-lesson-editor' ); ?></button>
                    <button type="button" id="save_final_content_as_draft" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 w-full mt-2"><?php esc_html_e( 'Save as new lesson draft', 'my-lesson-editor' ); ?></button>
                </div>

                <details class="mt-6 border-t border-gray-200 pt-4">
                    <summary class="text-sm font-semibold text-gray-800 cursor-pointer"><?php esc_html_e( 'Optional: manual LLM prompt', 'my-lesson-editor' ); ?></summary>
                    <div class="mt-3">
                        <label for="llm_prompt_title" class="block text-gray-700 text-sm font-bold mb-2"><?php esc_html_e( 'Prompt title', 'my-lesson-editor' ); ?></label>
                        <input type="text" id="llm_prompt_title" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2">
                        <label for="llm_prompt_content" class="block text-gray-700 text-sm font-bold mb-2 mt-3"><?php esc_html_e( 'Prompt body', 'my-lesson-editor' ); ?></label>
                        <textarea id="llm_prompt_content" class="output-content-textarea border border-gray-300 rounded-none w-full py-2 px-3 text-gray-700 text-sm" rows="6"></textarea>
                        <button type="button" id="send_prompt_to_llm" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 w-full mt-2"><?php esc_html_e( 'Send prompt to LLM (separate)', 'my-lesson-editor' ); ?></button>
                        <div id="llm_response" class="response-status-message mt-3 p-3 hidden text-center font-semibold rounded text-xs"></div>
                    </div>
                </details>
            </aside>
        
        
        
        
        <aside class="w-full lg:w-1/5 bg-white border border-gray-300 rounded-lg p-4 lg:p-6 shadow-md max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 mt-6">Generated Drafts Content</h2>
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="select_title_ce" class="block text-gray-700 text-sm font-bold mb-2">
                        Select Title:
                    </label>
                    <select id="select_title_ce" class="w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Select Title --</option>
                        <?php
                        // Populate with existing lesson_profile posts (drafts and published)
                        $args = array(
                            'post_type'      => 'lesson_profile',
                            'posts_per_page' => -1,
                            'post_status'    => array( 'publish', 'draft' ),
                            'orderby'        => 'title',
                            'order'          => 'ASC',
                        );
                        $lesson_posts = new WP_Query( $args );
                        if ( $lesson_posts->have_posts() ) {
                            while ( $lesson_posts->have_posts() ) {
                                $lesson_posts->the_post();
                                echo '<option value="' . esc_attr( get_the_ID() ) . '">' . esc_html( get_the_title() ) . ' (' . esc_html(get_post_status()) . ')</option>';
                            }
                            wp_reset_postdata();
                        }
                        ?>
                    </select>
                </div>
                <div class="flex-1 hidden" id="draft_selection_container">
                    <label for="select_draft_ce" class="block text-gray-700 text-sm font-bold mb-2">
                        Select Draft:
                    </label>
                    <select id="select_draft_ce" class="w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Select Draft --</option>
                        <option value="main"><?php esc_html_e( 'Main lesson body', 'my-lesson-editor' ); ?></option>
                        <option value="draft1">Draft 1 (Agent 1)</option>
                        <option value="draft2">Draft 2 (Agent 2)</option>
                        <option value="draft3">Draft 3 (Agent 3)</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button id="load_content_to_editor_from_draft" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Load Content to Editor</button>
                </div>
            </div>
        </aside>
        </section>

    </main>
    <?php
    return ob_get_clean();
}
add_shortcode( 'my_lesson_editor', 'my_lesson_editor_shortcode' );

// Add rewrite rules
function my_lesson_editor_rewrite_rule() {
    add_rewrite_rule(
        '^lesson-profile/([0-9]+)/?$',
        'index.php?post_type=lesson_profile&p=$matches[1]',
        'top'
    );
}
add_action( 'init', 'my_lesson_editor_rewrite_rule' );

// Flush rewrite rules on plugin activation
function my_lesson_editor_activate() {
    my_lesson_editor_register_cpt();
    my_lesson_editor_register_taxonomies();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'my_lesson_editor_activate' );

// Flush rewrite rules on plugin deactivation
function my_lesson_editor_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'my_lesson_editor_deactivate' );

// ================= DOWNLOAD HANDLER FOR LESSON PLAN/VOCABULARY ===============
// (letakkan setelah semua WP AJAX handler)
add_action('wp_ajax_mle_download_item', 'mle_handle_download_item'); // Hanya untuk user login, bukan nopriv!

function mle_handle_download_item() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized', 'Unauthorized', ['response' => 403]);
    }
    $item_id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $type    = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    $nonce   = isset($_POST['nonce']) ? $_POST['nonce'] : '';

    if (!$item_id || !$nonce) {
        wp_die('Invalid request', 'Bad Request', ['response' => 400]);
    }
    if (!wp_verify_nonce($nonce, 'mle_download_' . $item_id)) {
        wp_die('Nonce check failed', 'Forbidden', ['response' => 403]);
    }

    $post = get_post($item_id);
    if (!$post || !in_array($post->post_type, ['lesson_profile', 'vocabulary'])) {
        wp_die('Item not found', 'Not Found', ['response' => 404]);
    }

    $title   = $post->post_title;
    $content = apply_filters('the_content', $post->post_content);
    $author  = get_the_author_meta('display_name', $post->post_author);
    $date    = get_the_date('Y-m-d', $post);
    $label   = $post->post_type === 'vocabulary' ? 'vocabulary' : 'lesson-plan';

    $filename = $label . '-' . sanitize_title($title) . '.pdf';
    $use_pdf = class_exists('Dompdf\\Dompdf');
    if ($use_pdf) {
        // --- PDF GENERATION: Dompdf ---
        if (!class_exists('Dompdf\\Dompdf')) {
            if (file_exists(MY_LESSON_EDITOR_PLUGIN_DIR . 'vendor/autoload.php')) {
                require_once(MY_LESSON_EDITOR_PLUGIN_DIR . 'vendor/autoload.php');
            }
        }
        $dompdf = new Dompdf\Dompdf();
        $html = '<h2>' . esc_html($title) . '</h2>'
              . '<p><b>Type:</b> ' . esc_html($label) . '</p>'
              . '<p><b>Author:</b> ' . esc_html($author) . '</p>'
              . '<p><b>Date:</b> ' . esc_html($date) . '</p><hr>'
              . $content;
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    } else {
        // --- TXT fallback ---
        $filename = $label . '-' . sanitize_title($title) . '.txt';
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $title . "\n" .
             "Type: $label\n" .
             "Author: $author\n" .
             "Date: $date\n\n" .
             strip_tags($content);
        exit;
    }
}