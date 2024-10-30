<?php
/**
Plugin Name: Image Auto Sync To OSS
Plugin URI: https://beltxman.com/tag/image-auto-sync-oss
Description: 将文章内非OSS图片自动上传到OSS并替换文章内图片地址，清理本地媒体库(可选)；Pro 版支持一键同步所有历史文章内的图片。
Version: 1.0.1
Author: 行星带
Text Domain: image-auto-sync-oss
Domain Path: /languages
Author URI: https://beltxman.com
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

{Plugin Name} is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

{Plugin Name} is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with {Plugin Name}. If not, see {License URI}.
 */

require 'vendor/autoload.php';
require_once 'core.php';
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define('IASO_VERSION', '1.0.1');
define('IASO_PATH', plugin_dir_path(__FILE__));

// activate
register_activation_hook( __FILE__, 'iaso_activate' );
if (!function_exists('iaso_activate'))
{
    function iaso_activate()
    {
        $tempPath = IASO_PATH . 'temp/';
        if (!is_dir($tempPath)) {
            @mkdir($tempPath);
        }
    }
}
// deactivate
register_deactivation_hook( __FILE__, 'iaso_deactivate' );
if (!function_exists('iaso_deactivate'))
{
    function iaso_deactivate()
    {
        delete_option('iaso_sync_all_history_status');
        delete_transient('iaso_sync_all_history_process');
    }
}

// load languages
function iaso_load_languages() {
    load_plugin_textdomain( 'image-auto-sync-oss', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'iaso_load_languages' );

// load css， js
function iaso_admin_script_load()
{
    wp_register_style('iaso-admin', plugins_url( 'iaso.admin.css', __FILE__ ));
    wp_enqueue_style( 'iaso-admin' );
}
add_action( 'admin_enqueue_scripts', 'iaso_admin_script_load' );

/*
function iaso_front_script_load()
{
    wp_register_style('iaso', plugins_url( 'iaso.css', __FILE__ ));
    wp_enqueue_style( 'iaso' );
}
add_action('wp_enqueue_scripts', 'iaso_front_script_load');
*/

// Add Plugin Settings link to plugin page
function iaso_plugin_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=image_auto_sync_oss">' . __('Setting', 'image-auto-sync-oss') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'iaso_plugin_settings_link');

// add_menu
function iaso_options_page()
{
    add_options_page(
        __('OSS Image Sync Options', 'image-auto-sync-oss'),
        __('OSS Image Sync', 'image-auto-sync-oss'),
        'manage_options',
        'image_auto_sync_oss',
        'iaso_page'
    );
}

add_action('admin_menu', 'iaso_options_page');

// 配置信息
function iaso_setting_init() {

    // 注册一个新配置页 iaso
    register_setting( 'iaso', 'iaso_options' );

    // 在配置页添加一个 section : iaso_section_developers
    add_settings_section(
        'iaso_section',
        // __( 'Oss Options', 'image-auto-sync-oss' ),
        '',
        'iaso_section_cb',
        'iaso'
    );

    add_settings_field(
        'iaso_field_open',
        __( 'Open Image Auto Sync', 'image-auto-sync-oss' ),
        'iaso_field_open_cb',
        'iaso',
        'iaso_section',
        [
            'label_for'         => 'iaso_field_open',
            'class'             => 'iaso_row',
            'iaso_custom_data' => 'custom',
        ]
    );

    add_settings_field(
        'iaso_field_oss_key',
        __( 'OSS AccessKey', 'image-auto-sync-oss' ),
        'iaso_field_oss_key_cb',
        'iaso',
        'iaso_section',
        [
            'label_for'         => 'iaso_field_oss_key',
            'class'             => 'iaso_row',
            'iaso_custom_data' => 'custom',
        ]
    );

    add_settings_field(
        'iaso_field_oss_secret',
        __( 'OSS SecretKey', 'image-auto-sync-oss' ),
        'iaso_field_oss_secret_cb',
        'iaso',
        'iaso_section',
        [
            'label_for'         => 'iaso_field_oss_secret',
            'class'             => 'iaso_row',
            'iaso_custom_data' => 'custom',
        ]
    );

    add_settings_field(
        'iaso_field_oss_bucket',
        __( 'OSS Bucket', 'image-auto-sync-oss' ),
        'iaso_field_oss_bucket_cb',
        'iaso',
        'iaso_section',
        [
            'label_for'         => 'iaso_field_oss_bucket',
            'class'             => 'iaso_row',
            'iaso_custom_data' => 'custom',
        ]
    );

    add_settings_field(
        'iaso_field_oss_endpoint',
        __( 'OSS Endpoint', 'image-auto-sync-oss' ),
        'iaso_field_oss_endpoint_cb',
        'iaso',
        'iaso_section',
        [
            'label_for'         => 'iaso_field_oss_endpoint',
            'class'             => 'iaso_row',
            'iaso_custom_data' => 'custom',
        ]
    );

    add_settings_field(
        'iaso_field_oss_domain',
        __( 'OSS Domain', 'image-auto-sync-oss' ),
        'iaso_field_oss_domain_cb',
        'iaso',
        'iaso_section',
        [
            'label_for'         => 'iaso_field_oss_domain',
            'class'             => 'iaso_row',
            'iaso_custom_data' => 'custom',
        ]
    );

    add_settings_field(
        'iaso_field_oss_subPath',
        __( 'OSS SubPath', 'image-auto-sync-oss' ),
        'iaso_field_oss_subPath_cb',
        'iaso',
        'iaso_section',
        [
            'label_for'         => 'iaso_field_oss_subPath',
            'class'             => 'iaso_row',
            'iaso_custom_data' => 'custom',
        ]
    );

    // iaso_field_open
    // iaso_field_del_local_image

    add_settings_field(
        'iaso_field_del_local_image',
        __( 'Delete Local Image', 'image-auto-sync-oss' ),
        'iaso_field_del_local_image_cb',
        'iaso',
        'iaso_section',
        [
            'label_for'         => 'iaso_field_del_local_image',
            'class'             => 'iaso_row',
            'iaso_custom_data' => 'custom',
        ]
    );

    add_settings_field(
        'iaso_field_open_debug',
        __( 'Open Debug', 'image-auto-sync-oss' ),
        'iaso_field_open_debug_cb',
        'iaso',
        'iaso_section',
        [
            'label_for'         => 'iaso_field_open_debug',
            'class'             => 'iaso_row',
            'iaso_custom_data' => 'custom',
        ]
    );

    add_settings_field(
        'iaso_field_sync_all_history',
        __( 'Sync All History Posts', 'image-auto-sync-oss' ),
        'iaso_field_sync_all_history_cb',
        'iaso',
        'iaso_section',
        [
            'label_for'         => 'iaso_field_sync_all_history',
            'class'             => 'iaso_row',
            'iaso_custom_data' => 'custom',
        ]
    );

}

/**
 * 注册初始化 函数  到  admin_init 钩子
 */
add_action( 'admin_init', 'iaso_setting_init' );

function iaso_page()
{
    // check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class=wrap>
        <form action=options.php method=post>
            <?php
            // output security fields for the registered setting payme
            settings_fields( 'iaso' );
            // output setting sections and their fields
            // (sections are registered for payme, each field is registered to a specific section)
            do_settings_sections( 'iaso' );
            // output save settings button
            submit_button( __('Save Settings', 'image-auto-sync-oss') );
            ?>
        </form>
    </div>
    <?php
}

/**
 * custom option and settings:
 * callback functions
 * @param $args
 */
// developers section cb
// section callbacks can accept an $args parameter, which is an array.
// $args have the following keys defined: title, id, callback.
// the values are defined at the add_settings_section() function.
function iaso_section_cb( $args ) {
    ?>
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <p id=<?php echo esc_attr( $args[ 'id' ] ); ?>><?php esc_html_e( 'Get OSS account (Object Storage Service) before use it.', 'image-auto-sync-oss' ); ?> <a href="https://www.aliyun.com/1111/new?userCode=lfv1cfhb" target="_blank"><?php esc_html_e( 'Get OSS Now', 'image-auto-sync-oss' ); ?></a></p>
    <?php
}

// pill field cb

// field callbacks can accept an $args parameter, which is an array.
// $args is defined at the add_settings_field() function.
// wordpress has magic interaction with the following keys: label_for, class.
// the label_for key value is used for the for attribute of the <label>.
// the class key value is used for the class attribute of the <tr> containing the field.
// you can add custom key value pairs to be used inside your callbacks.
function iaso_field_pill_cb( $args ) {
    // get the value of the setting we've registered with register_setting()
    $options = get_option( 'iaso_options' );
    // output the field
    ?>
    <select id=<?php echo esc_attr( $args[ 'label_for' ] ); ?>
            data-custom=<?php echo esc_attr( $args[ 'iaso_custom_data' ] ); ?>
            name=iaso_options[<?php echo esc_attr( $args[ 'label_for' ] ); ?>]
            title="">
        <option value=red <?php echo isset( $options[ $args[ 'label_for' ] ] ) ? ( selected( $options[ $args[ 'label_for' ] ], 'red', false ) ) : ( '' ); ?>>red pill</option>
        <option value=blue <?php echo isset( $options[ $args[ 'label_for' ] ] ) ? ( selected( $options[ $args[ 'label_for' ] ], 'blue', false ) ) : ( '' ); ?>>blue pill</option>
    </select>
    <p class=description>You take the blue pill and the story ends. You wake in your bed and you believe whatever you want to believe.</p>
    <p class=description>This is a select eg.</p>
    <?php
}

function iaso_field_open_cb($args)
{
    $options = get_option( 'iaso_options' );
    ?>
    <input type="checkbox" name="iaso_options[<?php echo esc_attr( $args[ 'label_for' ] ); ?>]" value="1"
        <?php isset($options[$args['label_for']]) ? checked( $options[$args['label_for']], 1 ) : (''); ?>  title=""/>
    <?php
}

function iaso_field_oss_key_cb($args)
{
    // get the value of the setting we've registered with register_setting()
    $options = get_option( 'iaso_options' );
    // output the field
    ?>
    <input type="text" name="iaso_options[<?php echo esc_attr( $args[ 'label_for' ] ); ?>]" value="<?php echo isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';?>" title="" required="required"/>
    <span style="color:#ff3b08;">*</span>
    <?php
}

function iaso_field_oss_secret_cb($args) {
    $options = get_option( 'iaso_options' );
    ?>
    <input type="text" name="iaso_options[<?php echo esc_attr( $args[ 'label_for' ] ); ?>]" value="<?php echo isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';?>" title="" required="required"/>
    <span style="color:#ff3b08;">*</span>
    <?php
}

function iaso_field_oss_bucket_cb($args) {
    $options = get_option( 'iaso_options' );
    ?>
    <input type="text" name="iaso_options[<?php echo esc_attr( $args[ 'label_for' ] ); ?>]" value="<?php echo isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';?>" title="" required="required"/>
    <span style="color:#ff3b08;">*</span>
    <?php
}

function iaso_field_oss_endpoint_cb($args) {
    $options = get_option( 'iaso_options' );
    ?>
    <input type="text" name="iaso_options[<?php echo esc_attr( $args[ 'label_for' ] ); ?>]" value="<?php echo isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';?>" title="" required="required"/>
    <span style="color:#ff3b08;">*</span>
    <?php
}

function iaso_field_oss_domain_cb($args) {
    $options = get_option( 'iaso_options' );
    ?>
    <input type="text" name="iaso_options[<?php echo esc_attr( $args[ 'label_for' ] ); ?>]" value="<?php echo isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';?>" title="" required="required"/>
    <span style="color:#ff3b08;">*</span>
    <?php
}

function iaso_field_oss_subPath_cb($args) {
    $options = get_option( 'iaso_options' );
    ?>
    <input type="text" name="iaso_options[<?php echo esc_attr( $args[ 'label_for' ] ); ?>]" value="<?php echo isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';?>" title=""/>
    <?php
}

function iaso_field_open_debug_cb( $args ) {
    $options = get_option( 'iaso_options' );
    ?>
    <input type="checkbox" name="iaso_options[<?php echo esc_attr( $args[ 'label_for' ] ); ?>]" value="1"
        <?php isset($options[$args['label_for']]) ? checked( $options[$args['label_for']], 1 ) : (''); ?>  title=""/>
    <p class=description>
        <?php esc_html_e( 'Open debug when you need.', 'image-auto-sync-oss' ); ?>
    </p>
    <?php
}

function iaso_field_del_local_image_cb( $args ) {
    $options = get_option( 'iaso_options' );
    ?>
    <input type="checkbox" name="iaso_options[<?php echo esc_attr( $args[ 'label_for' ] ); ?>]" value="1"
        <?php isset($options[$args['label_for']]) ? checked( $options[$args['label_for']], 1 ) : (''); ?>  title=""/>
    <?php
}

function iaso_field_sync_all_history_cb( $args ) {
    $getPro = 'https://beltxman.com/3542.html';
    ?>
    <a id="sync-now" class="button button-primary" target="_blank" href="<?php echo $getPro; ?>"><?php esc_html_e( 'Get Pro Version', 'image-auto-sync-oss' ); ?></a>
    <?php
}

