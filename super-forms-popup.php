<?php
/**
 * Super Forms Popup
 *
 * @package   Super Forms Popup
 * @author    feeling4design 
 * @copyright 2015 by feeling4design
 *
 * @wordpress-plugin
 * Plugin Name: Super Forms Popup
 * Plugin URI:  http://codecanyon.net/item/super-forms-drag-drop-form-builder/13979866
 * Description: Create fully customizable popups for Super Forms
 * Version:     1.0.0
 * Author:      feeling4design
 * Author URI:  http://codecanyon.net/user/feeling4design
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if( !class_exists( 'SUPER_Popup' ) ) :


    /**
     * Main SUPER_Popup Class
     *
     * @class SUPER_Popup
     * @version 1.0.0
     */
    final class SUPER_Popup {
    
        
        /**
         * @var string
         *
         *  @since      1.0.0
        */
        public $version = '1.0.0';

        
        /**
         * @var SUPER_Popup The single instance of the class
         *
         *  @since      1.0.0
        */
        protected static $_instance = null;

        
        /**
         * Contains an array of registered script handles
         *
         * @var array
         *
         *  @since      1.0.0
        */
        private static $scripts = array();
        
        
        /**
         * Contains an array of localized script handles
         *
         * @var array
         *
         *  @since      1.0.0
        */
        private static $wp_localize_scripts = array();
        
        
        /**
         * Main SUPER_Popup Instance
         *
         * Ensures only one instance of SUPER_Popup is loaded or can be loaded.
         *
         * @static
         * @see SUPER_Popup()
         * @return SUPER_Popup - Main instance
         *
         *  @since      1.0.0
        */
        public static function instance() {
            if(is_null( self::$_instance)){
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        
        /**
         * SUPER_Popup Constructor.
         *
         *  @since      1.0.0
        */
        public function __construct(){
            $this->init_hooks();
            do_action('SUPER_Popup_loaded');
        }
        
        
        /**
         * Define constant if not already set
         *
         * @param  string $name
         * @param  string|bool $value
         *
         *  @since  1.0.0
        */
        private function define($name, $value){
            if(!defined($name)){
                define($name, $value);
            }
        }

        
        /**
         * What type of request is this?
         *
         * string $type ajax, frontend or admin
         * @return bool
         *
         *  @since      1.0.0
        */
        private function is_request($type){
            switch ($type){
                case 'admin' :
                    return is_admin();
                case 'ajax' :
                    return defined( 'DOING_AJAX' );
                case 'cron' :
                    return defined( 'DOING_CRON' );
                case 'frontend' :
                    return (!is_admin() || defined('DOING_AJAX')) && ! defined('DOING_CRON');
            }
        }

        
        /**
         * Hook into actions and filters
         *
         *  @since      1.0.0
        */
        private function init_hooks() {
            
            add_shortcode( 'super-popup', array( $this, 'popup_shortcode_func' ) );

            // Actions since 1.0.0
            add_action( 'wp_ajax_super_set_popup_expire_cookie', array( $this, 'set_popup_expire_cookie' ) ); 
            add_action( 'wp_ajax_nopriv_super_set_popup_expire_cookie', array( $this, 'set_popup_expire_cookie' ) ); 

            if ( $this->is_request( 'frontend' ) ) {
                
                // Filters since 1.0.0
                add_filter( 'super_form_settings_filter', array( $this, 'remove_preloader' ), 50, 2 ); 
                add_filter( 'super_common_js_dynamic_functions_filter', array( $this, 'add_dynamic_function' ), 100, 2 );
                add_filter( 'super_form_styles_filter', array( $this, 'add_popup_styles' ), 10, 2 );

                // Actions since 1.0.0
                add_action( 'super_after_enqueue_element_scripts_action', array( $this, 'load_scripts' ) );
                add_action( 'super_form_before_do_shortcode_filter', array( $this, 'add_form_inside_popup_wrapper'  ), 50, 2 );

            }
            if ( $this->is_request( 'admin' ) ) {
               
                // Filters since 1.0.0 
                add_filter( 'super_settings_after_export_import_filter', array( $this, 'register_popup_settigs' ), 50, 2 ); 
                add_filter( 'super_form_styles_filter', array( $this, 'add_popup_styles' ), 10, 2 );

            }
            if ( $this->is_request( 'ajax' ) ) {

                // Filters since 1.0.0

                // Actions since 1.0.0

            }
        } 


        /**
         * Popup shortcode URL
         *
         *  @since      1.0.0
        */
        public static function popup_shortcode_func( $atts, $content = "" ) {
            return '<a href="#super-popup-'.$atts['id'].'">'.$content.'</a>';
        }


        /**
         * Hook into stylesheets of the form and add styles for the calculator element
         *
         *  @since      1.0.0
        */
        public static function add_dynamic_function( $functions ) {
            
            $functions['after_responsive_form_hook'][] = array(
                'name' => 'init_responsive_popup'
            );

            $functions['before_submit_button_click_hook'][] = array(
                'name' => 'init_check_submit_button_close_popup'
            );

            $functions['after_email_send_hook'][] = array(
                'name' => 'init_set_expiration_cookie_on_submit_popup'
            );




            return $functions;
        }


        /**
         * Remove preloader
         *
         *  @since      1.0.0
        */
        public static function remove_preloader( $settings, $data ) {
            if( (isset($settings['popup_enabled'])) && ($settings['popup_enabled']=='true') ) {
                $settings['form_preload'] = 0;
            }
            return $settings;
        }


        /**
         * Set the popup expire cookie
         *
         *  @since      1.0.0
        */
        public static function set_popup_expire_cookie() {
            $form_id = absint($_POST['form_id']);
            if(!isset($_COOKIE['super_popup_expire_'.$form_id])) {
                $expire = absint($_POST['expire']);
                if( $expire>0 ) {
                    setcookie( 'super_popup_expire_'.$form_id, $form_id, time() + ($expire * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
                }
            }
            die();
        }


        /**
         * Hook into stylesheets of the form and add styles for the popup
         *
         *  @since      1.0.0
        */
        public static function add_popup_styles( $styles, $data ) {
            $v = $data['settings'];   
            if( (isset($v['popup_enabled'])) && ($v['popup_enabled']=='true') ) {
                $v = SUPER_Popup()->form_configuration_data( $v );
                $v = json_decode($v, true);
                
                // Body overflow background color / opacity
                $styles .= '.super-popup-wrapper-'.$data['id'].' {';
                    $styles .= 'background-color: ' . SUPER_Common::hex2rgb( $v['overlay_color'], $v['overlay_opacity'] ) . ';';
                $styles .= '}';

                $s = '.super-popup-'.$data['id'].' ';
                $styles .= $s.'{';
                    // Popup width
                    $styles .= 'width: ' . $v['width'] . 'px;';                    
                    if( $v['background_color']!='' ) {
                        $styles .= 'background-color: ' . $v['background_color'] . ';';
                    }
                    // Popup background image
                    if( $v['background_image']!='' ) {
                        $styles .= 'background-image: url(' . $v['background_image'] . ');';
                        if( $v['background_image_repeat']!='' ) {
                            $styles .= 'background-repeat: ' . $v['background_image_repeat'] . ';';
                        }
                        if( $v['background_image_size']!='' ) {
                            $styles .= 'background-size: ' . $v['background_image_size'] . ';';
                        }
                    }
                    // Popup border / radius
                    if( $v['enable_borders']=='true') {
                        if( $v['border_size']>0 ) {
                            $styles .= 'border-width: ' . $v['border_size'] . 'px;';
                            $styles .= 'border-color: ' . $v['border_color'] . ';';
                            $styles .= 'border-style: solid;';
                        }
                        $border_radius = $v['border_radius_top_left'].'px ';
                        $border_radius .= $v['border_radius_top_right'].'px '; 
                        $border_radius .= $v['border_radius_bottom_right'].'px ';
                        $border_radius .= $v['border_radius_bottom_left'].'px '; 
                        $styles .= 'border-radius: ' . $border_radius . ';';
                        $styles .= '-moz-border-radius: ' . $border_radius . ';';
                        $styles .= '-webkit-border-radius: ' . $border_radius . ';';
                    }
                    // Popup shadow
                    if( $v['enable_shadows']=='true') {
                        $box_shadow = $v['shadow_horizontal_length'].'px ';
                        $box_shadow .= $v['shadow_vertical_length'].'px '; 
                        $box_shadow .= $v['blur_radius'].'px ';
                        $box_shadow .= $v['spread_radius'].'px '; 
                        $box_shadow .= SUPER_Common::hex2rgb( $v['shadow_color'], $v['shadow_opacity'] );
                        $styles .= '-webkit-box-shadow: ' . $box_shadow . ';';
                        $styles .= '-moz-box-shadow: ' . $box_shadow . ';';
                        $styles .= 'box-shadow: ' . $box_shadow . ';';
                    }
                $styles .= '}';

                if( (isset($v['close_btn'])) && ($v['close_btn']=='true') ) {
                    $styles .= $s.'.super-popup-close > .super-popup-close-icon {';
                        $styles .= 'color: ' . $v['close_btn_icon_color'] . ';';
                        $styles .= 'background-color: ' . $v['close_btn_bg_color'] . ';';
                        $styles .= 'font-size: ' . $v['close_btn_icon_size'] . 'px;';
                        $styles .= 'border-width: ' . $v['close_btn_border'] . 'px;';
                        $styles .= 'border-color: ' . $v['close_btn_border_color'] . ';';
                        $styles .= 'border-style: solid;';
                        if( $v['close_btn_padding']!='' ) {
                            $styles .= 'padding: ' . $v['close_btn_padding'] . ';';
                        }
                        if( ($v['close_btn_radius']!='') && ($v['close_btn_radius']>0) ) {
                            $styles .= '-webkit-border-radius: ' . $v['close_btn_radius'] . 'px;';
                            $styles .= '-moz-border-radius: ' . $v['close_btn_radius'] . 'px;';
                            $styles .= 'border-radius: ' . $v['close_btn_radius'] . 'px;';
                        }
                    $styles .= '}';

                    $styles .= $s.'.super-popup-close {';
                        $styles .= 'top: ' . $v['close_btn_top'] . 'px;';
                        $styles .= 'right: ' . $v['close_btn_right'] . 'px;';
                    $styles .= '}';

                    $styles .= $s.'.super-popup-close-label {';
                        if( $v['close_btn_label_padding']!='' ) {
                            $styles .= 'padding: ' . $v['close_btn_label_padding'] . ';';
                        }
                        $styles .= 'color: ' . $v['close_btn_label_color'] . ';';
                        $styles .= 'background-color: ' . $v['close_btn_label_bg_color'] . ';';
                    $styles .= '}';
                }
            }
            return $styles;
        }






        /**
         *  Add the form inside the popup wrapper
         *
         *  @since      1.0.0
        */
        function add_form_inside_popup_wrapper( $result, $data ) {
            $form_id = absint($data['id']);

            $settings = $data["settings"];
            if( (isset($settings['popup_enabled'])) && ($settings['popup_enabled']=='true') ) {

                // Check if expiration is enabled and if cookie exists
                if( ($settings['popup_expire_trigger']!='') && ($settings['popup_expire']>0) ) {
                    if( isset($_COOKIE['super_popup_expire_'.$form_id]) ) {
                        return '';
                    }
                }

                // Generate popup HTML only if popup is enabled
                if( ( ($settings['popup_logged_in']=='true') && (is_user_logged_in()) ) || ( ($settings['popup_not_logged_in']=='true') && (!is_user_logged_in()) ) ) {
                    $form_html = $result;
                    $result = '';
                    $result .= '<div class="super-popup-wrapper-' . $form_id . ' super-popup-wrapper" style="opacity:0;z-index:-2147483648;">';
                        $result .= '<div class="super-popup-' . $form_id . ' super-popup" style="opacity:0;">';
                            
                            // Popup close button
                            if( $settings['popup_close_btn']=='true' ) {
                                $result .= '<span class="super-popup-close">';
                                $result .= '<span class="super-popup-close-label">' . $settings['popup_close_btn_label'] . '</span>';
                                $result .= '<span class="super-popup-close-icon"></span>';
                                $result .= '</span>';    
                            }
                            
                            // Custom popup paddings
                            $styles = '';
                            if( !isset( $settings['popup_enable_padding'] ) ) $settings['popup_enable_padding'] = '';
                            if( $settings['popup_enable_padding']=='true' ) {
                                if( !isset( $settings['popup_padding'] ) ) $settings['popup_padding'] = '';
                                if( $settings['popup_padding']!='' ) {
                                    $styles = ' style="padding:' . $settings['popup_padding'] . ';"';
                                }
                            }

                            // Popup content
                            $result .= '<div class="super-popup-content"' . $styles . '>';
                                $result .= $form_html;
                            $result .= '</div>';
                            
                            $result .= '<textarea name="super-popup-settings">' . $this->form_configuration_data( $settings ) . '</textarea>';
                        $result .= '</div>';
                    $result .= '</div>';
                }else{
                    return '';
                }
            }
            return $result;
            
        }
       

        /**
         * Enqueue scripts
         *
         *  @since      1.0.0
        */
        public static function load_scripts( $data ) {
            if( (isset($data['settings']['popup_enabled'])) && ($data['settings']['popup_enabled']=='true') ) {
                wp_enqueue_style( 'super-popup', plugin_dir_url( __FILE__ ) . 'assets/css/popup.min.css', array(), SUPER_Popup()->version );
                wp_enqueue_script( 'super-css-plugin', plugin_dir_url( __FILE__ ) . 'assets/js/css-plugin.min.js', array( 'jquery', 'super-common' ), SUPER_Popup()->version );
                wp_enqueue_script( 'super-ease-pack', plugin_dir_url( __FILE__ ) . 'assets/js/ease-pack.min.js', array( 'super-css-plugin' ), SUPER_Popup()->version );
                wp_enqueue_script( 'super-tween-lite', plugin_dir_url( __FILE__ ) . 'assets/js/tween-lite.min.js', array( 'super-ease-pack' ), SUPER_Popup()->version );
                wp_enqueue_script( 'super-jquery-gsap', plugin_dir_url( __FILE__ ) . 'assets/js/jquery.gsap.min.js', array( 'super-tween-lite' ), SUPER_Popup()->version );
                wp_enqueue_script( 'super-popup', plugin_dir_url( __FILE__ ) . 'assets/js/popup.min.js', array( 'super-jquery-gsap' ), SUPER_Popup()->version );
                wp_localize_script( 'super-popup', 'super_popup_i18n', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
            }
        }


        /**
         *  Settings Data Array
         * 
         *  @since  1.0.0
         *  @return string configuration settings for javascript
        */
        public function form_configuration_data( $settings ) {
            $popup_settings = array_intersect_key($settings, array_flip(preg_grep('/^popup_/', array_keys($settings))));
            foreach ( $popup_settings as $k => $v ) {
                $old_k = $k;
                $k = str_replace( 'popup_', '', $k );
                if( $k=='background_image' ) {
                    $v = ((trim($v) != '' && intval($v) > 0) ? wp_get_attachment_url(absint($v)) : '');
                }
                unset($popup_settings[$old_k]);
                $popup_settings[$k] = $v;
            }
            return json_encode($popup_settings);
        }


        /**
         * Hook into popup settings 
         *
         *  @since      1.0.0
        */
        function register_popup_settigs( $array, $settings ) {

            $array['popup_settings'] = array(        
                'name' => __( 'Popup Settings', 'super-forms' ),
                'label' => __( 'Popup Settings', 'super-forms' ),
                'fields' => array(
                    'popup_enabled' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_enabled', $settings['settings'], '' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Enable Popup', 'super-forms' ),
                        ),
                    ),
                    'popup_logged_in' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_logged_in', $settings['settings'], 'true' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Show popup to logged in users', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_not_logged_in' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_not_logged_in', $settings['settings'], 'true' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Show popup to none logged in users', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_page_load' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_page_load', $settings['settings'], 'true' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Display popup on page load', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_exit_intent' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_exit_intent', $settings['settings'], '' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Display popup on exit intent', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_leave' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_leave', $settings['settings'], '' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Display popup on page leave/close/exit', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_leave_msg' => array(
                        'name' => __( 'Allert message text (browser requires this)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_leave_msg', $settings['settings'], __( "Wait stay with us! Please take the time to fill out our form!?", "super-forms" ) ),
                        'filter'=>true,
                        'parent' => 'popup_leave',
                        'filter_value' => 'true',
                    ),
                    'popup_enable_scrolling' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_enable_scrolling', $settings['settings'], '' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Display popup after xx% scrolled', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_scrolled' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_scrolled', $settings['settings'], '0' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>100,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_enable_scrolling',
                        'filter_value' => 'true',
                    ),
                    'popup_enable_seconds' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_enable_seconds', $settings['settings'], '' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Display popup after X seconds', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_seconds' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_seconds', $settings['settings'], '0' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>100,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_enable_seconds',
                        'filter_value' => 'true',
                    ),
                    'popup_enable_inactivity' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_enable_inactivity', $settings['settings'], '' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Display popup after X seconds of inactivity', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_inactivity' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_inactivity', $settings['settings'], '0' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>100,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_enable_inactivity',
                        'filter_value' => 'true',
                    ),
                    'popup_enable_schedule' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_enable_schedule', $settings['settings'], '' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Display popup between date range (schedule)', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_from' => array(
                        'name' => __( 'From date', 'super-forms' ),
                        'desc' => __( 'From date (yyyy-mm-dd): Display the popup within specific date range', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_from', $settings['settings'], date('Y-m-d') ),
                        'parent' => 'popup_enable_schedule',
                        'filter_value' => 'true',
                        'filter'=>true,
                    ), 
                    'popup_till' => array(
                        'name' => __( 'Till date', 'super-forms' ),
                        'desc' => __( 'Till date (yyyy-mm-dd): Display the popup within specific date range', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_till', $settings['settings'], date('Y-m-d') ),
                        'parent' => 'popup_enable_schedule',
                        'filter_value' => 'true',
                        'filter'=>true,
                    ),
                    'popup_disable_closing' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_disable_closing', $settings['settings'], '' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Disable popup closing', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_close_btn' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn', $settings['settings'], 'true' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Display close (X) button', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_close_btn_icon_color' => array(
                        'name' => __( 'Close button icon color', 'super-forms' ),
                        'type'=>'color',  
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_icon_color', $settings['settings'], '#fff' ),
                        'filter'=>true,
                        'parent' => 'popup_close_btn',
                        'filter_value' => 'true',  
                    ),
                    'popup_close_btn_bg_color' => array(
                        'name' => __( 'Close button background color (leave blank for none)', 'super-forms' ),
                        'type'=>'color',  
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_bg_color', $settings['settings'], '#00bc65' ),
                        'filter'=>true,
                        'parent' => 'popup_close_btn',
                        'filter_value' => 'true',  
                    ),
                    'popup_close_btn_label' => array(
                        'name' => __( 'Close button label text e.g: Close', 'super-forms' ),
                        'type'=>'text',  
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_label', $settings['settings'], '' ),
                        'filter'=>true,
                        'parent' => 'popup_close_btn',
                        'filter_value' => 'true',  
                    ),
                    'popup_close_btn_label_color' => array(
                        'name' => __( 'Close button label color', 'super-forms' ),
                        'type'=>'color',  
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_label_color', $settings['settings'], '#00bc65' ),
                        'filter'=>true,
                        'parent' => 'popup_close_btn',
                        'filter_value' => 'true',  
                    ),
                    'popup_close_btn_label_bg_color' => array(
                        'name' => __( 'Close button label bg color', 'super-forms' ),
                        'type'=>'color',  
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_label_bg_color', $settings['settings'], '#00bc65' ),
                        'filter'=>true,
                        'parent' => 'popup_close_btn',
                        'filter_value' => 'true',  
                    ),
                    'popup_close_btn_label_padding' => array(
                        'name' => __( 'Close button label paddings e.g: 0px 0px 0px 0px', 'super-forms' ),
                        'label' => __( '(leave blank for default paddings)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_label_padding', $settings['settings'], '' ),
                        'type'=>'text',
                        'filter'=>true,
                        'parent'=>'popup_close_btn',
                        'filter_value'=>'true'
                    ),
                    'popup_close_btn_icon_size' => array(
                        'name' => __( 'Close button icon size in pixels (px)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_icon_size', $settings['settings'], '14' ),
                        'type'=>'slider',
                        'min'=>10,
                        'max'=>50,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_close_btn',
                        'filter_value' => 'true',
                    ),
                    'popup_close_btn_border' => array(
                        'name' => __( 'Close button border size in pixels (px)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_border', $settings['settings'], '0' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>10,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_close_btn',
                        'filter_value' => 'true',
                    ),
                    'popup_close_btn_border_color' => array(
                        'name' => __( 'Close button border color', 'super-forms' ),
                        'type'=>'color',  
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_border_color', $settings['settings'], '' ),
                        'filter'=>true,
                        'parent' => 'popup_close_btn',
                        'filter_value' => 'true',  
                    ),
                    'popup_close_btn_top' => array(
                        'name' => __( 'Position top in pixels (px)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_top', $settings['settings'], '0' ),
                        'type'=>'slider',
                        'min'=>-100,
                        'max'=>100,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_close_btn',
                        'filter_value' => 'true',
                    ),
                    'popup_close_btn_right' => array(
                        'name' => __( 'Position right in pixels (px)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_right', $settings['settings'], '0' ),
                        'type'=>'slider',
                        'min'=>-100,
                        'max'=>100,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_close_btn',
                        'filter_value' => 'true',
                    ),
                    'popup_close_btn_padding' => array(
                        'name' => __( 'Popup close button paddings e.g: 0px 0px 0px 0px', 'super-forms' ),
                        'label' => __( '(leave blank for default paddings)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_padding', $settings['settings'], '' ),
                        'type'=>'text',
                        'filter'=>true,
                        'parent'=>'popup_close_btn',
                        'filter_value'=>'true'
                    ),
                    'popup_close_btn_radius' => array(
                        'name' => __( 'Close button border radius in pixels (px)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_close_btn_radius', $settings['settings'], '0' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>100,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_close_btn',
                        'filter_value' => 'true',
                    ),
                    'popup_enable_padding' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_enable_padding', $settings['settings'], '' ),
                        'type' => 'checkbox', 
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Enable custom popup padding', 'super-forms' ),
                        ),
                        'filter'=>true,
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_padding' => array(
                        'name' => __( 'Popup paddings e.g: 0px 0px 0px 0px', 'super-forms' ),
                        'label' => __( '(leave blank for default paddings)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_padding', $settings['settings'], '' ),
                        'type'=>'text',
                        'filter'=>true,
                        'parent'=>'popup_enable_padding',
                        'filter_value'=>'true'
                    ),
                    'popup_expire_trigger' => array(
                        'name' => __( 'Enable expiration cookie (show popup only once)', 'super-forms' ),
                        'type'=>'select',
                        'default' => SUPER_Settings::get_value( 0, 'popup_expire_trigger', $settings['settings'], '' ),
                        'values'=>array( 
                            '' => __( 'Disabled', 'super-forms' ),
                            'view' => __( 'When popup has been viewed', 'super-forms' ),
                            'close' => __( 'When popup has been closed', 'super-forms' ),
                            'submit' => __( 'When form has been submitted', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                        'filter'=>true,
                    ),
                    'popup_expire' => array(
                        'name' => __( 'Expiration time in days', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_expire', $settings['settings'], '1' ),
                        'type'=>'slider',
                        'min'=>1,
                        'max'=>365,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_expire_trigger',
                        'filter_value' => 'view,close,submit',
                    ),

                    'popup_width' => array(
                        'name' => __( 'Popup width in pixels (px)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_width', $settings['settings'], '700' ),
                        'type'=>'slider',
                        'min'=>360,
                        'max'=>1000,
                        'steps'=>10,
                        'filter'=>true,
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_background_color' => array(
                        'name' => __( 'Popup background color', 'super-forms' ),
                        'type'=>'color',  
                        'default' => SUPER_Settings::get_value( 0, 'popup_background_color', $settings['settings'], '#ffffff' ),
                        'filter'=>true,
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',  
                    ),
                    'popup_overlay_color' => array(
                        'name' => __( 'Body overlay color', 'super-forms' ),
                        'type'=>'color',  
                        'default' => SUPER_Settings::get_value( 0, 'popup_overlay_color', $settings['settings'], '#000000' ),
                        'filter'=>true,
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',  
                    ), 
                    'popup_overlay_opacity' => array(
                        'name' => __( 'Body overlay opacity', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_overlay_opacity', $settings['settings'], '0.5' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>1,
                        'steps'=>0.1,
                        'filter'=>true,
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_background_image' => array(
                        'name' => __( 'Background Image', 'super-forms' ),
                        'type'=>'image',
                        'filter'=>true,
                        'default' => SUPER_Settings::get_value( 0, 'popup_background_image', $settings['settings'], '0' ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true', 
                    ),
                    'popup_background_image_repeat' => array(
                        'name' => __( 'Background Image Repeat', 'super-forms' ),
                        'filter'=>true,
                        'type'=>'select',
                        'filter'=>true,
                        'default' => SUPER_Settings::get_value( 0, 'popup_background_image_repeat', $settings['settings'], 'no_repeat' ),
                        'values'=>array( 
                            'no-repeat' => __( 'No (no-repeat)', 'super-forms' ),
                            'repeat' => __( 'Repeat (repeat)', 'super-forms' ),
                            'repeat-x' => __( 'Repeat X (repeat-x)', 'super-forms' ),
                            'repeat-y' => __( 'Repeat Y (repeat-y)', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_background_image_size' => array(
                        'name' => __( 'Background Image Size', 'super-forms' ),
                        'filter'=>true,
                        'type'=>'select',
                        'default' => SUPER_Settings::get_value( 0, 'popup_background_image_size', $settings['settings'], 'cover' ),
                        'values'=>array( 
                            'inherit' => __( 'Default (inherit)', 'super-forms' ),
                            'contain' => __( 'Contain / Fit (contain)', 'super-forms' ),
                            'cover' => __( 'Cover / Fit (cover)', 'super-forms' ) 
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_slide' => array(
                        'name' => __( 'Popup slide in', 'super-forms' ),
                        'desc' => __( 'Slide in: From Top, Right, Bottom or Left', 'super-forms' ),
                        'filter'=>true,
                        'type'=>'select',
                        'default' => SUPER_Settings::get_value( 0, 'popup_slide', $settings['settings'], 'none' ),
                        'values'=>array(
                            'none' => __( 'None (default)', 'super-forms' ),
                            'from_top' => __( 'From Top', 'super-forms' ),
                            'from_right' => __( 'From Right', 'super-forms' ),
                            'from_bottom' => __( 'From Bottom', 'super-forms' ),
                            'from_left' => __( 'From Left', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ), 
                    'popup_slide_duration' => array(
                        'name' => __( 'Popup Slide In duration in milliseconds', 'super-forms' ),
                        'desc' => __( 'Slide In duration in milliseconds (0 is no fade effect)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_slide_duration', $settings['settings'], '300' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>5000,
                        'steps'=>100,
                        'filter'=>true,
                        'parent' => 'popup_slide',
                        'filter_value' => 'from_top,from_right,from_bottom,from_left',
                    ),
                    'popup_fade_duration' => array(
                        'name' => __( 'Popup FadeIn duration in milliseconds', 'super-forms' ),
                        'desc' => __( 'FadeIn duration in milliseconds (0 is no fade effect)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_fade_duration', $settings['settings'], '300' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>5000,
                        'steps'=>100,
                        'filter'=>true,
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_fade_out_duration' => array(
                        'name' => __( 'Popup FadeOut duration in milliseconds', 'super-forms' ),
                        'desc' => __( 'FadeOut duration in milliseconds (0 is no fade effect)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_fade_out_duration', $settings['settings'], '300' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>5000,
                        'steps'=>100,
                        'filter'=>true,
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_sticky' => array(
                        'name' => __( 'Make popup sticky', 'super-forms' ),
                        'desc' => __( 'Stick to top, right, bottom or left', 'super-forms' ),
                        'filter'=>true,
                        'type'=>'select',
                        'default' => SUPER_Settings::get_value( 0, 'popup_sticky', $settings['settings'], 'default' ),
                        'values'=>array(
                            'default' => __( 'default', 'super-forms' ),
                            'top' => __( 'Top', 'super-forms' ),
                            'right' => __( 'Right', 'super-forms' ),
                            'bottom' => __( 'Bottom', 'super-forms' ),
                            'left' => __( 'Left', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_enable_borders' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_enable_borders', $settings['settings'], '' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Enable Popup Border', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_border_size' => array(
                        'name' => __( 'Border size in pixels (px)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_border_size', $settings['settings'], '0' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>10,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_enable_borders',
                        'filter_value' => 'true',
                    ),
                    'popup_border_color' => array(
                        'name' => __( 'Border color', 'super-forms' ),
                        'type'=>'color',  
                        'default' => SUPER_Settings::get_value( 0, 'popup_border_color', $settings['settings'], '#00bc65' ),
                        'filter'=>true,
                        'parent' => 'popup_enable_borders',
                        'filter_value' => 'true',  
                    ),
                    'popup_border_radius_top_left' => array(
                        'name' => __( 'Border Radius Top Left', 'super-forms' ), 
                        'default' => SUPER_Settings::get_value( 0, 'popup_border_radius_top_left', $settings['settings'], '10' ),
                        'type'=>'slider',
                        'filter'=>true,
                        'min'=>0,
                        'max'=>200,
                        'steps'=>1,
                        'parent' => 'popup_enable_borders',
                        'filter_value' => 'true',
                    ),
                    'popup_border_radius_top_right' => array(
                        'name' => __( 'Border Radius Top Right', 'super-forms' ), 
                        'default' => SUPER_Settings::get_value( 0, 'popup_border_radius_top_right', $settings['settings'], '10' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>200,
                        'filter'=>true,
                        'steps'=>1,
                        'parent' => 'popup_enable_borders',
                        'filter_value' => 'true',
                    ),
                    'popup_border_radius_bottom_left' => array(
                        'name' => __( 'Border Radius Bottom Left', 'super-forms' ), 
                        'default' => SUPER_Settings::get_value( 0, 'popup_border_radius_bottom_left', $settings['settings'], '10' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>200,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_enable_borders',
                        'filter_value' => 'true',
                    ),
                    'popup_border_radius_bottom_right' => array(
                        'name' => __( 'Border Radius Bottom Right', 'super-forms' ), 
                        'default' => SUPER_Settings::get_value( 0, 'popup_border_radius_bottom_right', $settings['settings'], '10' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>200,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_enable_borders',
                        'filter_value' => 'true',
                    ),

                    'popup_enable_shadows' => array(
                        'default' => SUPER_Settings::get_value( 0, 'popup_enable_shadows', $settings['settings'], '' ),
                        'type' => 'checkbox',
                        'filter'=>true,
                        'values' => array(
                            'true' => __( 'Enable Popup Shadows', 'super-forms' ),
                        ),
                        'parent' => 'popup_enabled',
                        'filter_value' => 'true',
                    ),
                    'popup_shadow_horizontal_length' => array(
                        'name' => __( 'Shadow Horizontal Length', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_shadow_horizontal_length', $settings['settings'], '5' ),
                        'type'=>'slider',
                        'min'=>-200,
                        'max'=>200,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_enable_shadows',
                        'filter_value' => 'true',
                    ),
                    'popup_shadow_vertical_length' => array(
                        'name' => __( 'Shadow Vertical Length', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_shadow_vertical_length', $settings['settings'], '5' ),
                        'type'=>'slider',
                        'min'=>-200,
                        'max'=>200,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_enable_shadows',
                        'filter_value' => 'true',
                    ),
                    'popup_blur_radius' => array(
                        'name' => __( 'Shadow Blur Radius', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_blur_radius', $settings['settings'], '15' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>300,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_enable_shadows',
                        'filter_value' => 'true',
                    ),
                    'popup_spread_radius' => array(
                        'name' => __( 'Shadow Spread Radius', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_spread_radius', $settings['settings'], '3' ),
                        'type'=>'slider',
                        'min'=>-200,
                        'max'=>200,
                        'steps'=>1,
                        'filter'=>true,
                        'parent' => 'popup_enable_shadows',
                        'filter_value' => 'true',
                    ),
                    'popup_shadow_color' => array(
                        'name' => __( 'Shadow Color', 'super-forms' ),
                        'type'=>'color',  
                        'default' => SUPER_Settings::get_value( 0, 'popup_shadow_color', $settings['settings'], '#000000' ),
                        'filter'=>true,
                        'parent' => 'popup_enable_shadows',
                        'filter_value' => 'true',
                    ),
                    'popup_shadow_opacity' => array(
                        'name' => __( 'Shadow Opacity', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'popup_shadow_opacity', $settings['settings'], '0.7' ),
                        'type'=>'slider',
                        'min'=>0,
                        'max'=>1,
                        'steps'=>0.05,
                        'filter'=>true,
                        'parent' => 'popup_enable_shadows',
                        'filter_value' => 'true',
                    ),
                 ), 
            ); 
            return $array;
        }


}
endif;

/**
 * Returns the main instance of SUPER_Popup to prevent the need to use globals.
 *
 * @return SUPER_Popup
 */
function SUPER_Popup() {
    return SUPER_Popup::instance();
}

// Global for backwards compatibility.
$GLOBALS['SUPER_Popup'] = SUPER_Popup();