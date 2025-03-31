<?php
trait PB_Settings_Post_Types {
    /**
     * Register post type
     */
    public function register_post_type($post_type, array $args = []) {
        if (post_type_exists($post_type)) {
            return;
        }

        $singular = $args['singular'] ?? '';
        $plural = $args['plural'] ?? '';
        $labels = $args['labels'] ?? [];

        // ...existing post type registration code...
    }

    /**
     * Register taxonomy
     */
    public function register_taxonomy($tax_name, $obj_name, array $args = []) {
        if (taxonomy_exists($tax_name)) {
            return;
        }

        // ...existing taxonomy registration code...
    }
}
