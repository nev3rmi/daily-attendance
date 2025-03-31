<?php
trait PB_Settings_Fields {
    /**
     * Generate field output 
     */
    public function field_generator($option) {
        $id = isset($option['id']) ? $option['id'] : "";
        if (empty($id)) return;

        do_action("pb_settings_before_$id", $option);

        if (isset($option['type']) && !empty($field_type = $option['type'])) {
            if (method_exists($this, "generate_$field_type") && is_callable(array(
                    $this,
                    "generate_$field_type"
                ))) {
                call_user_func(array($this, "generate_$field_type"), $option);
            }
        }

        if (isset($option['disabled']) && $option['disabled'] && !empty($this->disabled_notice)) {
            printf('<span class="disabled-notice" style="background: #ffe390eb;margin-left: 10px;padding: 5px 12px;font-size: 12px;border-radius: 3px;color: #717171;">%s</span>', $this->disabled_notice);
        }
    }

    function generate_gallery($option) {
        // ...existing gallery generation code...
    }

    function generate_media($option) {
        // ...existing media generation code...
    }

    function generate_range($option) {
        // ...existing range generation code...
    }

    function generate_select2($option) {
        // ...existing select2 generation code...
    }

    function generate_datepicker($option) {
        // ...existing datepicker generation code...
    }

    function generate_timepicker($option) {
        // ...existing timepicker generation code...
    }

    function generate_wp_editor($option) {
        // ...existing wp_editor generation code...
    }

    function generate_colorpicker($option) {
        // ...existing colorpicker generation code...
    }

    function generate_text($option) {
        // ...existing text generation code...
    }

    function generate_number($option) {
        // ...existing number generation code...
    }

    function generate_textarea($option) {
        // ...existing textarea generation code...
    }

    function generate_select($option) {
        // ...existing select generation code...
    }

    function generate_checkbox($option) {
        // ...existing checkbox generation code...
    }

    function generate_radio($option) {
        // ...existing radio generation code...
    }

    function generate_image_select($option) {
        // ...existing image_select generation code...
    }

    function section_callback($section) {
        // ...existing section callback code...
    }
}
