<?php
trait PB_Settings_Menu {
    /**
     * Menu related functionality
     */
    private function get_menu_type() {
        if (isset($this->data['menu_type'])) {
            return $this->data['menu_type'];
        } else {
            return "main";
        }
    }

    private function get_menu_position() {
        if (isset($this->data['position'])) {
            return $this->data['position'];
        } else {
            return 60;
        }
    }

    // ... Keep all existing menu methods with full code ...

    private function get_menu_icon() {
        // ...existing method code...
    }

    function get_menu_slug() {
        // ...existing method code...
    }

    private function get_capability() {
        // ...existing method code...
    }

    private function get_menu_page_title() {
        // ...existing method code...
    }

    // Keep all other menu-related methods with full code
}
