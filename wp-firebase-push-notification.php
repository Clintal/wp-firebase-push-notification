<?php
/*
Plugin Name:Wordpress Firebase Push Notification
Description:Wordpress Firebase Push Notification
Version:1
Author:sony7596, miraclewebssoft, reach.baljit
Author URI:http://www.miraclewebsoft.com
License:GPL2
License URI:https://www.gnu.org/licenses/gpl-2.0.html
*/
if (!defined('ABSPATH')) {
    exit;
}

if (!defined("FCM_VERSION_CURRENT")) define("FCM_VERSION_CURRENT", '1');
if (!defined("FCM_URL")) define("FCM_URL", plugin_dir_url( __FILE__ ) );
if (!defined("FCM_PLUGIN_DIR")) define("FCM_PLUGIN_DIR", plugin_dir_path(__FILE__));
if (!defined("FCM_PLUGIN_NM")) define("FCM_PLUGIN_NM", 'Wordpress Firebase Push Notification');
if (!defined("FCM_TD")) define("FCM_TD", 'fcm_td');


Class Firebase_Push_Notification
{
    public $pre_name = 'fcm';

    public function __construct()
    {
        // Installation and uninstallation hooks
        register_activation_hook(__FILE__, array($this, $this->pre_name . '_activate'));
        register_deactivation_hook(__FILE__, array($this, $this->pre_name . '_deactivate'));
        add_action('admin_menu', array($this, $this->pre_name . '_setup_admin_menu'));
        add_action("admin_init", array($this, $this->pre_name . '_backend_plugin_js_scripts_filter_table'));
        add_action("admin_init", array($this, $this->pre_name . '_backend_plugin_css_scripts_filter_table'));
        add_action('admin_init', array($this, $this->pre_name . '_settings'));
        add_action('publish_post', array($this, $this->pre_name . '_on_post_publish'), 10, 2);
        //add_action('init', array($this, $this->pre_name . '_custom_post_type'));

    }

    public function fcm_setup_admin_menu()
    {
        add_submenu_page('options-general.php', __('Firebase Push Notification', FCM_TD), FCM_PLUGIN_NM, 'manage_options', 'fcm_slug', array($this, 'fcm_admin_page'));

        add_submenu_page(null            // -> Set to null - will hide menu link
            , __('Test Notification', FCM_TD)// -> Page Title
            , 'Test Notification'    // -> Title that would otherwise appear in the menu
            , 'administrator' // -> Capability level
            , 'test_notification'   // -> Still accessible via admin.php?page=menu_handle
            , array($this, 'fcm_test_notification') // -> To render the page
        );
    }

    public function fcm_admin_page()
    {
        include(plugin_dir_path(__FILE__) . 'views/dashboard.php');
    }

    function fcm_backend_plugin_js_scripts_filter_table()
    {
        wp_enqueue_script("jquery");
        wp_enqueue_script("fcm.js", FCM_URL . "assets/js/fcm.js");
    }

    function fcm_backend_plugin_css_scripts_filter_table()
    {
        wp_enqueue_style("fcm.css", FCM_URL . "assets/css/fcm.css");
    }

    public function fcm_activate()
    {

    }

    public function fcm_deactivate()
    {
    }


    function fcm_settings()
    {    //register our settings
        register_setting('fcm_group', 'stf_fcm_api');
        register_setting('fcm_group', 'fcm_option');
        register_setting('fcm_group', 'fcm_topic');
        register_setting('fcm_group', 'fcm_disable');
        register_setting('fcm_group', 'fcm_update_disable');
        register_setting('fcm_group', 'fcm_page_disable');
        register_setting('fcm_group', 'fcm_update_page_disable');

    }

    function fcm_custom_post_type()
    {
        register_post_type('device_tokens',
            [
                'labels'      => [
                    'name'          => __('Device Tokens'),
                    'singular_name' => __('Device Token'),
                ],
                'public'      => true,
                'has_archive' => true,
            ]
        );
    }

    // https://wordpress.stackexchange.com/questions/247447/how-can-i-tell-if-a-post-has-been-published-at-least-once
    function fcm_on_post_publish($post_id, $post) {
        // $from = get_bloginfo('name');
        // $content = 'There are new post notification from '.$from;
        $content = $post->post_title;

        if (get_option('stf_fcm_api') && get_option('fcm_topic')) {
            $published_at_least_once = get_post_meta( $post_id, 'is_published', true );

            if (!$published_at_least_once && get_option('fcm_disable') != 1) {
                $published_at_least_once = true;
                $this->fcm_notification($content, (string) $post_id);
            }

            update_post_meta( $post_id, 'is_published', $published_at_least_once );
        }
    }

    function fcm_test_notification(){
        $content = 'Test Notification from FCM Plugin';

        $result = $this->fcm_notification($content, '0');

        echo '<div class="row">';
        echo '<div><h2>Debug Information</h2>';

        echo '<pre>';
        printf($result);
        echo '</pre>';

        echo '<p><a href="'. admin_url('admin.php').'?page=test_notification">Retry</a></p>';
        echo '<p><a href="'. admin_url('admin.php').'?page=fcm_slug">Home</a></p>';

        echo '</div>';
    }

    function fcm_notification($content, $post_id){
        $topic =  "'".get_option('fcm_topic')."' in topics";
        $apiKey = get_option('stf_fcm_api');
        $url = 'https://fcm.googleapis.com/fcm/send';
        $headers = array(
            'Authorization: key=' . $apiKey,
            'Content-Type: application/json'
        );
        $notification_data = array(
            // when application open then post field 'data' parameter work so 'message' and 'body' key should have same text or value
            'message'           => $content,
            'post_id'           => $post_id
        );

        $notification = array(
            // when application close then post field 'notification' parameter work
            'body'  => $content,
            'sound' => 'default'
        );

        $post = array(
            'condition'         => $topic,
            'notification'      => $notification,
            "content_available" => true,
            'priority'          => 'high',
            'data'              => $notification_data
        );
        //echo '<pre>';
        //var_dump($post);
        // Initialize curl handle
        $ch = curl_init();

        // Set URL to GCM endpoint
        curl_setopt($ch, CURLOPT_URL, $url);

        // Set request method to POST
        curl_setopt($ch, CURLOPT_POST, true);

        // Set our custom headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Get the response back as string instead of printing it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Set JSON post data
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));

        // Actually send the push
        $result = curl_exec($ch);

        // Close curl handle
        curl_close($ch);

        // Debug GCM response

        $result_de = json_decode($result);

        return $result;

        //var_dump($result); die;

    }


}

$Firebase_Push_Notification_OBJ = new Firebase_Push_Notification();
