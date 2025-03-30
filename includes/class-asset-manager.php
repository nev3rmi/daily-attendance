<?php
class AssetManager {
    private $version;
    
    public function __construct() {
        $this->version = defined('WP_DEBUG') && WP_DEBUG ? time() : PBDA_VERSION;
    }

    public function enqueue_frontend_assets(): void {
        $this->enqueue_common_assets();
        wp_enqueue_style(
            'pbda_style',
            PBDA_PLUGIN_URL . 'assets/front/css/style.css',
            [],
            $this->version
        );
    }

    public function enqueue_admin_assets(): void {
        $this->enqueue_common_assets();
        wp_enqueue_style(
            'pbda_admin_style',
            PBDA_PLUGIN_URL . 'assets/admin/css/style.css',
            [],
            $this->version
        );
    }

    private function enqueue_common_assets(): void {
        wp_enqueue_style(
            'tooltip',
            PBDA_PLUGIN_URL . 'assets/tool-tip.min.css',
            [],
            $this->version
        );
        wp_enqueue_style(
            'icofont',
            PBDA_PLUGIN_URL . 'assets/fonts/icofont.min.css',
            [],
            $this->version
        );
    }
}
