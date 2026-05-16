<?php
/**
 * Delete handler for CAAST dashboard items.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_ajax_caast_delete_item', 'caast_handle_delete_item' );

/**
 * Handle AJAX delete requests for dashboard items.
 */
function caast_handle_delete_item() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error(
            array( 'message' => __( 'You must be logged in to delete items.', 'my-lesson-editor' ) ),
            403
        );
    }

    if ( ! current_user_can( 'administrator' ) && ! current_user_can( 'content_developer' ) ) {
        wp_send_json_error(
            array( 'message' => __( 'You do not have permission to delete items.', 'my-lesson-editor' ) ),
            403
        );
    }

    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

    if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'caast_delete_nonce' ) ) {
        wp_send_json_error(
            array( 'message' => __( 'Security check failed. Please refresh the page.', 'my-lesson-editor' ) ),
            403
        );
    }

    $item_id      = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
    $content_type = isset( $_POST['content_type'] ) ? sanitize_key( wp_unslash( $_POST['content_type'] ) ) : '';

    if ( ! $item_id || empty( $content_type ) ) {
        wp_send_json_error(
            array( 'message' => __( 'Invalid delete request.', 'my-lesson-editor' ) ),
            400
        );
    }

    $post_mapped_types   = array( 'author', 'book', 'article' );
    $custom_content_types = array( 'lesson_plan', 'vocabulary', 'flashcards', 'quiz', 'midterm', 'final' );

    if ( in_array( $content_type, $post_mapped_types, true ) ) {
        caast_delete_post_item( $item_id );
    } elseif ( in_array( $content_type, $custom_content_types, true ) ) {
        caast_delete_generated_content( $item_id, $content_type );
    } else {
        wp_send_json_error(
            array( 'message' => __( 'Unsupported content type.', 'my-lesson-editor' ) ),
            400
        );
    }

    wp_send_json_success(
        array(
            'message'   => __( 'Item deleted', 'my-lesson-editor' ),
            'new_nonce' => wp_create_nonce( 'caast_delete_nonce' ),
        )
    );
}

/**
 * Delete WordPress post-based items.
 *
 * @param int $post_id Post ID to delete.
 */
function caast_delete_post_item( $post_id ) {
    $post = get_post( $post_id );

    if ( ! $post ) {
        wp_send_json_error(
            array( 'message' => __( 'Item not found.', 'my-lesson-editor' ) ),
            404
        );
    }

    if ( ! current_user_can( 'administrator' ) ) {
        $current_user_id = get_current_user_id();
        if ( (int) $post->post_author !== (int) $current_user_id ) {
            wp_send_json_error(
                array( 'message' => __( 'You cannot delete items created by other users.', 'my-lesson-editor' ) ),
                403
            );
        }
    }

    global $wpdb;

    // Remove metadata explicitly before deleting the post.
    $wpdb->delete( $wpdb->postmeta, array( 'post_id' => $post_id ), array( '%d' ) );

    // Delete attachments to avoid orphans.
    $attachments = get_posts(
        array(
            'post_type'      => 'attachment',
            'post_parent'    => $post_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        )
    );

    if ( ! empty( $attachments ) ) {
        foreach ( $attachments as $attachment_id ) {
            wp_delete_attachment( $attachment_id, true );
        }
    }

    $deleted = wp_delete_post( $post_id, true );

    if ( ! $deleted ) {
        wp_send_json_error(
            array( 'message' => __( 'Failed to delete the requested item.', 'my-lesson-editor' ) ),
            500
        );
    }
}

/**
 * Delete generated content stored in custom tables.
 *
 * @param int    $item_id      Row ID.
 * @param string $content_type Content type key.
 */
function caast_delete_generated_content( $item_id, $content_type ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'lesson_plan_generated_content';

    $record = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE id = %d AND content_type = %s",
            $item_id,
            $content_type
        )
    );

    if ( ! $record ) {
        wp_send_json_error(
            array( 'message' => __( 'Content not found or already deleted.', 'my-lesson-editor' ) ),
            404
        );
    }

    $deleted = $wpdb->delete(
        $table_name,
        array(
            'id' => $item_id,
        ),
        array( '%d' )
    );

    if ( false === $deleted ) {
        wp_send_json_error(
            array( 'message' => __( 'Unable to delete generated content record.', 'my-lesson-editor' ) ),
            500
        );
    }
}

