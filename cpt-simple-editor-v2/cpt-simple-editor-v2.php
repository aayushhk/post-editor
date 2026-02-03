<?php
/**
 * Plugin Name: CPT Simple Editor V2
 * Description: One-page frontend editor for CPT + ACF fields.
 * Version: 2.0
 */

if (!defined('ABSPATH')) exit;

add_shortcode('cpt_edit_form', function () {

    if (!is_singular()) return '';
    $post_id = get_queried_object_id();
    if (!$post_id || !current_user_can('edit_post', $post_id)) return '';

    // HANDLE SUBMIT
    if (
        isset($_POST['cpt_editor_nonce']) &&
        wp_verify_nonce($_POST['cpt_editor_nonce'], 'cpt_editor')
    ) {

        // Update title
        if (isset($_POST['post_title'])) {
            wp_update_post([
                'ID' => $post_id,
                'post_title' => sanitize_text_field($_POST['post_title'])
            ]);
        }

        // Update tags
        if (isset($_POST['post_tags'])) {
            $tags = array_map('sanitize_text_field', explode(',', $_POST['post_tags']));
            wp_set_post_tags($post_id, $tags, false);
        }

        // Update ACF fields
        if (function_exists('update_field') && isset($_POST['acf'])) {
            foreach ($_POST['acf'] as $key => $value) {
                update_field($key, $value, $post_id);
            }
        }

        wp_safe_redirect(get_permalink($post_id));
        exit;
    }

    // FETCH DATA
    $title = get_the_title($post_id);
    $tags  = wp_get_post_tags($post_id, ['fields' => 'names']);
    $acf_fields = [];

    if (function_exists('acf_get_field_groups')) {
        $groups = acf_get_field_groups(['post_id' => $post_id]);
        foreach ($groups as $group) {
            $fields = acf_get_fields($group['key']);
            if ($fields) $acf_fields = array_merge($acf_fields, $fields);
        }
    }

    ob_start();
    ?>

    <form method="post" class="cpt-editor">
        <?php wp_nonce_field('cpt_editor', 'cpt_editor_nonce'); ?>

        <h2>Edit Post</h2>

        <!-- TITLE -->
        <label>Title</label>
        <input type="text" name="post_title" value="<?php echo esc_attr($title); ?>">

        <!-- TAGS -->
        <label>Tags</label>
        <input
            type="text"
            name="post_tags"
            value="<?php echo esc_attr(implode(',', $tags)); ?>"
            placeholder="Comma separated"
        >

        <!-- ACF FIELDS -->
        <?php foreach ($acf_fields as $field):
            $value = get_field($field['key'], $post_id);
            $name  = "acf[{$field['key']}]";
        ?>
            <div class="acf-field">
                <label><?php echo esc_html($field['label']); ?></label>

                <?php switch ($field['type']):

                    case 'text':
                    case 'email':
                    case 'url':
                    case 'number': ?>
                        <input type="<?php echo esc_attr($field['type']); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>">
                        <?php break;

                    case 'textarea': ?>
                        <textarea name="<?php echo esc_attr($name); ?>"><?php echo esc_textarea($value); ?></textarea>
                        <?php break;

                    case 'true_false': ?>
                        <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($value, 1); ?>>
                        <?php break;

                    case 'select':
                    case 'radio': ?>
                        <select name="<?php echo esc_attr($name); ?>">
                            <?php foreach ($field['choices'] as $k => $label): ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($value, $k); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php break;

                    case 'checkbox': ?>
                        <?php foreach ($field['choices'] as $k => $label): ?>
                            <label class="inline">
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr($name); ?>[]"
                                    value="<?php echo esc_attr($k); ?>"
                                    <?php checked(is_array($value) && in_array($k, $value)); ?>
                                >
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach;
                        break;

                    case 'color_picker': ?>
                        <input type="color" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>">
                        <?php break;

                    case 'date_picker': ?>
                        <input type="date" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>">
                        <?php break;

                    default: ?>
                        <em>Field type "<?php echo esc_html($field['type']); ?>" supported for future.</em>

                <?php endswitch; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit">Update</button>
    </form>

    <style>
        .cpt-editor {
            max-width: 720px;
            background: #fff;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,.05);
            font-family: system-ui;
        }
        .cpt-editor label {
            font-weight: 600;
            margin-top: 20px;
            display: block;
        }
        .cpt-editor input,
        .cpt-editor textarea,
        .cpt-editor select {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .acf-field {
            margin-top: 20px;
        }
        .inline {
            display: inline-block;
            margin-right: 12px;
        }
        button {
            margin-top: 30px;
            padding: 12px 20px;
            border-radius: 10px;
            background: #4f46e5;
            color: #fff;
            border: none;
            font-size: 15px;
            cursor: pointer;
        }
    </style>

    <?php
    return ob_get_clean();
});
