<?php
/**
 * Schema Audit Engine.
 *
 * Framework-agnostic JSON-LD auditing logic shared by the WP-CLI command,
 * the Tools page, and the standalone CI test runner. No WordPress functions
 * are called here, so the class is testable in CI without bootstrapping WP.
 *
 * Two behaviors, both born from production use on The DJ Calendar:
 *
 * 1. Required-property validation per schema.org type.
 * 2. Two-pass @id resolution: first index every node carrying an @id
 *    (definitions), then walk every remaining property value - so a
 *    reference only "resolves" if the node it points to is actually
 *    defined in the same graph, not merely mentioned.
 *
 * @package Schema_Audit
 */

class Schema_Audit_Engine
{
    /**
     * Required properties per known schema.org type.
     *
     * @var array<string, string[]>
     */
    const KNOWN_TYPES = array(
        'MusicEvent'     => array('name', 'startDate', 'location', 'performer'),
        'MusicGroup'     => array('name'),
        'Place'          => array('name'),
        'Organization'   => array('name', 'url'),
        'WebPage'        => array('name'),
        'FAQPage'        => array('mainEntity'),
        'BreadcrumbList' => array('itemListElement'),
        'Person'         => array('name'),
    );

    /**
     * Audit every JSON-LD block in an HTML document.
     *
     * @param string $html Raw HTML (e.g. post_content).
     * @return array[] Findings: { block: int, errors: string[] } per failing block.
     */
    public static function audit_html($html)
    {
        $findings = array();

        foreach (self::extract_jsonld((string) $html) as $i => $nodes) {
            $errors = self::audit_graph($nodes);
            if (!empty($errors)) {
                $findings[] = array('block' => $i + 1, 'errors' => $errors);
            }
        }

        return $findings;
    }

    /**
     * Extract every JSON-LD block from an HTML document.
     *
     * @return array[] Each element is a flat list of graph nodes.
     */
    public static function extract_jsonld($html)
    {
        $blocks = array();

        preg_match_all(
            '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
            $html,
            $matches
        );

        foreach ($matches[1] as $raw) {
            $decoded = json_decode(trim($raw), true);
            if (!is_array($decoded)) {
                $blocks[] = array(array('__parse_error__' => 'unparsable JSON-LD block'));
                continue;
            }
            if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                $blocks[] = $decoded['@graph'];
            } else {
                $blocks[] = array($decoded);
            }
        }

        return $blocks;
    }

    /**
     * Audit one graph: required properties per type + @id resolution.
     *
     * @return string[] Error lines (empty = clean).
     */
    public static function audit_graph(array $nodes)
    {
        $errors    = array();
        $nodesById = array();

        // First pass: index every node carrying an @id (these are definitions).
        foreach ($nodes as $node) {
            if (is_array($node) && isset($node['@id']) && is_string($node['@id'])) {
                $nodesById[$node['@id']] = true;
            }
        }

        // Second pass: validate each node.
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            if (isset($node['__parse_error__'])) {
                $errors[] = '[JSON] ' . $node['__parse_error__'];
                continue;
            }

            $type = isset($node['@type']) ? $node['@type'] : null;
            if ($type === null) {
                continue;
            }
            $types     = is_array($type) ? $type : array($type);
            $typeLabel = implode('/', $types);

            // Required properties.
            foreach ($types as $t) {
                if (!isset(self::KNOWN_TYPES[$t])) {
                    continue;
                }
                foreach (self::KNOWN_TYPES[$t] as $required) {
                    if (!isset($node[$required]) || $node[$required] === '' || $node[$required] === array()) {
                        $id = isset($node['@id']) ? ' on ' . $node['@id'] : '';
                        $errors[] = '[' . $t . '] missing required property "' . $required . '"' . $id;
                    }
                }
            }

            // Reference resolution: every nested "@id" value must point to a
            // node defined somewhere in this graph. The node's own top-level
            // @id is a definition, not a reference, so it is excluded.
            $copy = $node;
            unset($copy['@id']);
            array_walk_recursive($copy, function ($value, $key) use (&$errors, $nodesById, $typeLabel) {
                if (!is_string($value) || $key === '@context') {
                    return;
                }
                if (preg_match('/^(?:https?:\/\/[^\s#]+)?#[A-Za-z0-9_-]+$/', $value)
                    && !isset($nodesById[$value])
                ) {
                    $errors[] = '[' . $typeLabel . '] property "' . $key
                        . '" references unresolved @id "' . $value . '"';
                }
            });
        }

        return $errors;
    }
}
