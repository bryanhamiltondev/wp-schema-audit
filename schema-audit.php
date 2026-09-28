<?php
/**
 * Plugin Name:       Schema Audit
 * Plugin URI:        https://github.com/bryanhamiltondev/wp-schema-audit
 * Description:       Audits JSON-LD structured data across your content: required properties per schema.org type, @id reference resolution, and parse detection. Ships a WP-CLI command and a Tools page. Zero dependencies.
 * Version:           1.0.0
 * Author:            Bryan Hamilton
 * Author URI:        https://github.com/bryanhamiltondev
 * License:           MIT
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Text Domain:       schema-audit
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-schema-audit-engine.php';
require_once __DIR__ . '/includes/class-schema-audit-command.php';

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('schema-audit', 'Schema_Audit_Command');
}

/**
 * Audit a batch of published content and return a structured report.
 *
 * @param int   $limit     Maximum posts to audit.
 * @param array $post_types Post types to include.
 * @return array { posts: int, errors: int, findings: array[] }
 */
function schema_audit_run_site($limit = 100, $post_types = array('post', 'page'))
{
    $results = array('posts' => 0, 'errors' => 0, 'findings' => array());

    $query = new WP_Query(array(
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'posts_per_page' => (int) $limit,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ));

    foreach ($query->posts as $post_id) {
        $content = get_post_field('post_content', $post_id);
        if (!is_string($content) || $content === '') {
            continue;
        }

        $results['posts']++;

        foreach (Schema_Audit_Engine::audit_html($content) as $block) {
            $results['errors'] += count($block['errors']);
            $results['findings'][] = array(
                'post'  => get_the_title($post_id) . ' (#' . $post_id . ')',
                'block' => $block['block'],
                'errors' => $block['errors'],
            );
        }
    }

    wp_reset_postdata();

    return $results;
}

/**
 * Tools page: run the audit on demand and render the report.
 */
function schema_audit_render_tools_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $ran     = false;
    $results = null;

    if (isset($_POST['schema_audit_nonce'])
        && wp_verify_nonce(sanitize_key($_POST['schema_audit_nonce']), 'schema_audit_run')
    ) {
        $ran     = true;
        $results = schema_audit_run_site(100);
    }
    ?>
    <div class="wrap">
        <h1>Schema Audit</h1>
        <p>Audits JSON-LD structured data in published content: required properties
        per schema.org type, internal <code>@id</code> resolution, and unparsable blocks.</p>

        <form method="post">
            <?php wp_nonce_field('schema_audit_run', 'schema_audit_nonce'); ?>
            <p>
                <button type="submit" class="button button-primary">Run audit (last 100 posts)</button>
            </p>
        </form>

        <?php if ($ran && $results !== null) : ?>
            <h2>Report</h2>
            <p><strong><?php echo esc_html($results['errors']); ?></strong> error(s)
            across <strong><?php echo esc_html($results['posts']); ?></strong> audited post(s).</p>

            <?php if (empty($results['findings'])) : ?>
                <p><em>Clean. Every JSON-LD block passed required-property and @id checks.</em></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>Block</th>
                            <th>Errors</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['findings'] as $finding) : ?>
                            <tr>
                                <td><?php echo esc_html($finding['post']); ?></td>
                                <td><?php echo esc_html($finding['block']); ?></td>
                                <td>
                                    <code>
                                    <?php
                                    echo esc_html(implode(' | ', $finding['errors']));
                                    ?>
                                    </code>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

add_action('admin_menu', function () {
    add_tools_page(
        'Schema Audit',
        'Schema Audit',
        'manage_options',
        'schema-audit',
        'schema_audit_render_tools_page'
    );
});
