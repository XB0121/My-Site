<?php
/**
 * Plugin Name: My Site 网站编辑
 * Description: 管理导航、首页 Hero、项目、关于我和联络方式。
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

function vibe_site_defaults() {
    return json_decode(file_get_contents(__DIR__ . '/vibe-site/defaults.json'), true);
}
function vibe_site_settings() {
    return array_merge(vibe_site_defaults(), (array) get_option('vibe_site_settings', array()));
}
function vibe_site_sanitize($input) {
    $values = vibe_site_settings();
    foreach (vibe_site_defaults() as $key => $default) {
        if (!isset($input[$key])) continue;
        if (substr($key, -9) === '_image_id') $values[$key] = absint($input[$key]);
        elseif ($key === 'contact_email') $values[$key] = sanitize_email($input[$key]);
        elseif ($key === 'contact_social_url') $values[$key] = esc_url_raw($input[$key], array('https', 'http'));
        else $values[$key] = sanitize_textarea_field($input[$key]);
    }
    return $values;
}
add_action('admin_init', function() {
    register_setting('vibe_site', 'vibe_site_settings', array('type' => 'array', 'sanitize_callback' => 'vibe_site_sanitize'));
});
add_action('init', function() {
    register_post_type('vibe_project', array(
        'labels' => array('name' => '项目', 'singular_name' => '项目', 'add_new_item' => '添加项目', 'edit_item' => '编辑项目', 'all_items' => '全部项目', 'add_new' => '添加项目'),
        'public' => true, 'show_in_rest' => true, 'rest_base' => 'projects',
        'menu_icon' => 'dashicons-portfolio', 'menu_position' => 21,
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields'),
        'rewrite' => false,
    ));
    add_post_type_support('vibe_project', 'thumbnail');
    add_theme_support('post-thumbnails');
});
add_action('add_meta_boxes_vibe_project', function() {
    add_meta_box('vibe-project-details', '项目资料', function($post) {
        wp_nonce_field('vibe_project_details', 'vibe_project_nonce');
        foreach (array('category' => '项目分类（例如：网站设计）', 'year' => '年份', 'url' => '项目外部网址（选填）') as $key => $label) {
            echo '<p><label>' . esc_html($label) . '<br><input style="width:100%" type="text" name="vibe_project_' . esc_attr($key) . '" value="' . esc_attr(get_post_meta($post->ID, '_vibe_' . $key, true)) . '"></label></p>';
        }
        echo '<p>项目封面在右侧「特色图片」设置；简短介绍填写「摘要」。排序使用「页面属性 → 顺序」，数值越小越靠前。</p>';
    });
});
add_action('save_post_vibe_project', function($id) {
    if (wp_is_post_autosave($id) || wp_is_post_revision($id) || !current_user_can('edit_post', $id)) return;
    if (!isset($_POST['vibe_project_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vibe_project_nonce'])), 'vibe_project_details')) return;
    foreach (array('category', 'year', 'url') as $key) {
        $value = isset($_POST['vibe_project_' . $key]) ? wp_unslash($_POST['vibe_project_' . $key]) : '';
        update_post_meta($id, '_vibe_' . $key, $key === 'url' ? esc_url_raw($value, array('http', 'https')) : sanitize_text_field($value));
    }
});
function vibe_site_image($id, $alt = '') {
    if (!$id || !wp_attachment_is_image($id)) return null;
    return array('id' => (int) $id, 'url' => wp_get_attachment_image_url($id, 'large'), 'alt' => $alt ?: get_post_meta($id, '_wp_attachment_image_alt', true));
}
add_action('rest_api_init', function() {
    register_rest_route('vibe-site/v1', '/content', array(
        'methods' => 'GET', 'permission_callback' => '__return_true',
        'callback' => function() {
            $settings = vibe_site_settings();
            $settings['hero_image'] = vibe_site_image($settings['hero_image_id'], $settings['hero_image_alt']);
            $settings['about_image'] = vibe_site_image($settings['about_image_id'], $settings['about_image_alt']);
            $projects = array_map(function($post) {
                return array('id' => $post->ID, 'title' => get_the_title($post), 'excerpt' => get_the_excerpt($post), 'content' => apply_filters('the_content', $post->post_content), 'image' => vibe_site_image(get_post_thumbnail_id($post)), 'category' => get_post_meta($post->ID, '_vibe_category', true), 'year' => get_post_meta($post->ID, '_vibe_year', true), 'url' => get_post_meta($post->ID, '_vibe_url', true));
            }, get_posts(array('post_type' => 'vibe_project', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => array('menu_order' => 'ASC', 'date' => 'DESC'))));
            $response = rest_ensure_response(array('settings' => $settings, 'projects' => $projects));
            $response->header('Cache-Control', 'no-store');
            return $response;
        }
    ));
});
function vibe_site_tabs() {
    return array('brand' => '导航与品牌', 'hero' => '首页 Hero', 'projects' => '项目区', 'about' => '关于我', 'contact' => '联络方式');
}
add_action('admin_menu', function() {
    add_menu_page('网站编辑', '网站编辑', 'manage_options', 'vibe-site', 'vibe_site_admin', 'dashicons-layout', 20);
    foreach (vibe_site_tabs() as $tab => $label) {
        add_submenu_page('vibe-site', $label, $label, 'manage_options', 'vibe-site-' . $tab, 'vibe_site_admin');
    }
});
add_action('admin_enqueue_scripts', function() {
    if (!isset($_GET['page']) || strpos(sanitize_key($_GET['page']), 'vibe-site') !== 0) return;
    wp_enqueue_media();
    wp_enqueue_script('vibe-site-editor', plugins_url('vibe-site/admin.js', __FILE__), array('jquery', 'media-views'), '1.0.0', true);
});
function vibe_site_field($key, $label, $textarea = false) {
    $settings = vibe_site_settings();
    echo '<tr><th scope="row"><label for="vibe-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
    if ($textarea) echo '<textarea class="large-text" rows="4" id="vibe-' . esc_attr($key) . '" name="vibe_site_settings[' . esc_attr($key) . ']">' . esc_textarea($settings[$key]) . '</textarea>';
    else echo '<input class="regular-text" style="width:min(100%,600px)" id="vibe-' . esc_attr($key) . '" name="vibe_site_settings[' . esc_attr($key) . ']" value="' . esc_attr($settings[$key]) . '">';
    echo '</td></tr>';
}
function vibe_site_media_field($prefix, $label) {
    $settings = vibe_site_settings();
    $image = vibe_site_image($settings[$prefix . '_image_id']);
    echo '<tr><th>' . esc_html($label) . '</th><td><input type="hidden" id="vibe-' . esc_attr($prefix) . '-image" name="vibe_site_settings[' . esc_attr($prefix) . '_image_id]" value="' . absint($settings[$prefix . '_image_id']) . '">';
    echo '<p><img id="vibe-' . esc_attr($prefix) . '-preview" style="max-width:280px;max-height:220px;' . ($image ? '' : 'display:none;') . '" src="' . esc_url($image ? $image['url'] : '') . '" alt=""></p>';
    echo '<button type="button" class="button vibe-media" data-prefix="' . esc_attr($prefix) . '">选择 / 上传图片</button> <button type="button" class="button vibe-media-remove" data-prefix="' . esc_attr($prefix) . '">移除图片</button><p class="description">不设置图片时显示默认的抽象视觉。上传的图片会随网站发布，无需单独图床。</p></td></tr>';
}
function vibe_site_admin() {
    if (!current_user_can('manage_options')) return;
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'vibe-site';
    $tab = str_replace('vibe-site-', '', $page);
    if (!array_key_exists($tab, vibe_site_tabs())) $tab = 'brand';
    echo '<div class="wrap" style="max-width:1000px"><h1>网站编辑</h1><p>修改下面的内容后保存，本地预览会自动更新。更新线上网站时，请双击项目文件夹里的「发布网站.cmd」。</p>';
    echo '<p><a class="button" target="_blank" rel="noopener" href="http://127.0.0.1:4321/">打开本地预览 ↗</a> <a class="button" target="_blank" rel="noopener" href="https://xb0121.github.io/My-Site/">查看线上网站 ↗</a> <a class="button" href="' . esc_url(admin_url('edit.php?post_type=vibe_project')) . '">管理项目</a></p>';
    settings_errors();
    echo '<nav class="nav-tab-wrapper">';
    foreach (vibe_site_tabs() as $id => $label) echo '<a class="nav-tab ' . ($id === $tab ? 'nav-tab-active' : '') . '" href="' . esc_url(admin_url('admin.php?page=vibe-site-' . $id)) . '">' . esc_html($label) . '</a>';
    echo '</nav><form method="post" action="options.php" style="background:#fff;padding:18px 26px;border:1px solid #ddd;border-top:0">';
    settings_fields('vibe_site');
    echo '<h2>' . esc_html(vibe_site_tabs()[$tab]) . '</h2><table class="form-table">';
    if ($tab === 'brand') {
        foreach (array('site_name' => '网站名称', 'nav_home' => '主页按钮', 'nav_projects' => '项目按钮', 'nav_about' => '关于我按钮', 'nav_contact' => '联络方式按钮', 'footer_text' => '页脚文案') as $key => $label) vibe_site_field($key, $label);
    } elseif ($tab === 'hero') {
        foreach (array('hero_eyebrow' => '标题上方的小字', 'hero_title' => '主标题 · 第一行', 'hero_accent' => '主标题 · 强调行', 'hero_button' => '项目按钮文字', 'hero_secondary' => '关于我按钮文字') as $key => $label) vibe_site_field($key, $label);
        vibe_site_field('hero_description', '介绍文案', true);
        vibe_site_media_field('hero', '主视觉图片');
        vibe_site_field('hero_image_alt', '图片说明');
    } elseif ($tab === 'projects') {
        vibe_site_field('projects_title', '项目区标题');
        vibe_site_field('projects_description', '项目区介绍', true);
    } elseif ($tab === 'about') {
        vibe_site_field('about_eyebrow', '标题上方的小字');
        vibe_site_field('about_title', '关于我标题');
        vibe_site_field('about_text', '自我介绍', true);
        vibe_site_media_field('about', '个人图片');
        vibe_site_field('about_image_alt', '图片说明');
    } else {
        vibe_site_field('contact_title', '联络区标题', true);
        vibe_site_field('contact_text', '联络区说明', true);
        foreach (array('contact_email' => '电子邮箱（选填）', 'contact_wechat' => '微信号（选填）', 'contact_social_label' => '社交链接名称', 'contact_social_url' => '社交链接网址') as $key => $label) vibe_site_field($key, $label);
        echo '<tr><td colspan="2">留空的联系方式不会显示在网站上。</td></tr>';
    }
    echo '</table>';
    submit_button('保存更改', 'primary', 'vibe_save');
    echo '</form></div>';
}
