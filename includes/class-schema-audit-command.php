<?php
/**
 * WP-CLI command: wp schema-audit run
 *
 * @package Schema_Audit
 */

class Schema_Audit_Command
{
    /**
     * Audits JSON-LD structured data in published post content.
     *
     * ## OPTIONS
     *
     * [--post_type=<types>]
     * : Comma-separated post types to audit. Default: post,page.
     *
     * [--status=<status>]
     * : Post status to audit. Default: publish.
     *
     * [--limit=<n>]
     * : Maximum number of posts to audit. Default: 0 (all).
     *
     * [--report=<file>]
     * : Write the findings to a file on disk.
     *
     * ## EXAMPLES
     *
     *     wp schema-audit run
     *     wp schema-audit run --post_type=post,page,event --report=audit.txt
     *
     * Exit code 1 when any error is found, so this drops straight into CI.
     *
     * @param array       $args       Positional args.
     * @param array       $assoc_args Associative args.
     * @return void
     */
    public function run($args, $assoc_args)
    {
        $post_type = isset($assoc_args['post_type'])
            ? array_map('trim', explode(',', $assoc_args['post_type']))
            : array('post', 'page');
        $status = isset($assoc_args['status']) ? $assoc_args['status'] : 'publish';
        $limit  = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 0;
        $report = isset($assoc_args['report']) ? $assoc_args['report'] : null;

        $scanned  = 0;
        $errTotal = 0;
        $lines    = array();
        $paged    = 1;

        do {
            $query = new WP_Query(array(
                'post_type'      => $post_type,
                'post_status'    => $status,
                'posts_per_page' => 100,
                'paged'          => $paged,
                'fields'         => 'ids',
            ));

            if (empty($query->posts)) {
                break;
            }

            foreach ($query->posts as $post_id) {
                if ($limit > 0 && $scanned >= $limit) {
                    break 2;
                }

                $content = get_post_field('post_content', $post_id);
                if (!is_string($content) || $content === '') {
                    $scanned++;
                    continue;
                }
                $scanned++;

                foreach (Schema_Audit_Engine::audit_html($content) as $block) {
                    $errTotal += count($block['errors']);

                    $header = sprintf(
                        'post #%d "%s" [JSON-LD block %d]',
                        $post_id,
                        get_the_title($post_id),
                        $block['block']
                    );
                    WP_CLI::line($header);
                    $lines[] = $header;

                    foreach ($block['errors'] as $error) {
                        WP_CLI::line('  ' . $error);
                        $lines[] = '  ' . $error;
                    }
                    WP_CLI::line('');
                }
            }

            if ($limit > 0 && $scanned >= $limit) {
                break;
            }
            $paged++;
        } while ($paged <= (int) $query->max_num_pages);

        if ($errTotal === 0) {
            WP_CLI::success("Schema audit clean across {$scanned} post(s).");
            return;
        }

        WP_CLI::line("=== SCHEMA AUDIT: {$errTotal} error(s) across {$scanned} post(s) ===");

        if ($report !== null) {
            file_put_contents($report, implode("\n", $lines));
            WP_CLI::line("Report written to {$report}");
        }

        WP_CLI::halt(1);
    }
}
