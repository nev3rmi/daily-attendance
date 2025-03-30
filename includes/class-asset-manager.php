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

        // Make sure jQuery is loaded
        wp_enqueue_script('jquery');
        
        wp_add_inline_style('pbda_admin_style', '
            .pbda-qr-code {
                padding: 15px;
                background: #fff;
                border-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                display: inline-block;
                min-height: 200px;
                min-width: 200px;
            }
            .pbda-qr-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
                padding: 20px 0;
            }
            .pbda-qr-item {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                text-align: center;
            }
            .pbda-qr-item img {
                max-width: 100%;
                height: auto;
            }
        ');
    }

    private function enqueue_common_assets(): void {
        wp_enqueue_style(
            'tooltip',
            PBDA_PLUGIN_URL . 'assets/front/css/tool-tip.min.css',
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
