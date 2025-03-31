<?php
trait PB_Settings_Tabs {
    /**
     * Tab/Page related functionality
     */
    private function get_pages() {
        if (isset($this->data['pages'])) {
            $pages = $this->data['pages'];
        } else {
            return array();
        }

        $pages_sorted = array();
        $increment = 0;

        foreach ($pages as $page_key => $page) {
            $increment += 5;
            $priority = isset($page['priority']) ? $page['priority'] : $increment;
            $pages_sorted[$page_key] = $priority;
        }
        array_multisort($pages_sorted, SORT_ASC, $pages);

        return $pages;
    }

    private function get_settings_fields() {
        // ... Keep existing method code ...
    }

    function display_function() {
        // ... Keep existing method code ...
    }

    // Keep all tab/page related methods with full code
}
