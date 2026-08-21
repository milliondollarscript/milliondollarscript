<?php
/**
 * Public advertiser-list rendering and bounded search.
 *
 * @package MillionDollarScript\V3\Pages
 */

namespace MillionDollarScript\V3\Pages\Concerns;

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Support\Template;

if (!defined('ABSPATH')) {
    exit;
}

trait RendersAdvertiserLists {

    private function advertiser_list($grid_id, array $args = []) {
        $grid_repo = new GridRepository();
        $all_grids = !absint($grid_id);
        $grid = $all_grids ? null : $grid_repo->find($grid_id);
        if ($grid && 'active' !== (string) $grid->get('status')) {
            $grid = null;
        }

        $list = $this->advertiser_list_options($args, $all_grids);
        $search = $list['search_enabled'] ? $this->advertiser_list_search() : '';
        $per_page = (int) \MillionDollarScript\Core\Hooks::apply(
            'million-dollar-script/advertiser/list/per/page',
            24,
            ['grid_id' => absint($grid_id), 'all_grids' => $all_grids]
        );
        $per_page = max(1, min(100, $per_page));
        $results = (new PlacementRepository())->public_advertiser_page([
            'grid_id' => $all_grids ? 0 : absint($grid_id),
            'page' => $this->advertiser_list_page(),
            'per_page' => $per_page,
            'search' => $search,
        ]);

        return Template::render('frontend/pages/advertiser-list.php', [
            'all_grids' => $all_grids,
            'clear_url' => $this->advertiser_list_url(1, ''),
            'columns' => $list['columns'],
            'form_action' => $this->advertiser_list_form_action(),
            'grid' => $grid,
            'has_active_grids' => $grid_repo->active_count() > 0,
            'hidden_inputs' => $this->advertiser_list_hidden_inputs(),
            'items' => $this->advertiser_list_items($results['items'], $list['columns']),
            'layout' => $list['layout'],
            'page' => $results['page'],
            'pagination' => $this->advertiser_list_pagination($results['page'], $results['total_pages'], $search),
            'per_page' => $results['per_page'],
            'search' => $search,
            'search_enabled' => $list['search_enabled'],
            'styles' => $list['styles'],
            'theme_class' => $this->theme_class(),
            'total' => $results['total'],
            'total_pages' => $results['total_pages'],
        ]);
    }

    private function advertiser_list_options(array $args, $all_grids = false) {
        $allowed_columns = $this->advertiser_list_columns();
        $columns = $this->advertiser_list_visible_columns($args['list_columns'] ?? '', $allowed_columns);
        if ($all_grids && isset($allowed_columns['grid']) && !in_array('grid', $columns, true)) {
            $columns[] = 'grid';
        }

        $layout = sanitize_key((string) ($args['list_layout'] ?? 'list'));
        $layouts = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/advertiser/list/layouts', ['list', 'cards', 'accordion']);
        $layouts = array_values(array_filter(array_map('sanitize_key', is_array($layouts) ? $layouts : [])));
        if (!in_array($layout, $layouts, true)) {
            $layout = 'list';
        }

        $styles = [];
        $accent = $this->hex_color($args['list_accent_color'] ?? '');
        $background = $this->hex_color($args['list_background_color'] ?? '');
        if ($accent) {
            $styles[] = '--mds3-advertiser-accent:' . esc_attr($accent);
        }
        if ($background) {
            $styles[] = '--mds3-advertiser-surface:' . esc_attr($background);
        }

        return [
            'columns' => $columns,
            'layout' => $layout,
            'search_enabled' => $this->truthy_shortcode_value($args['list_search'] ?? 'yes'),
            'styles' => $styles,
        ];
    }

    private function advertiser_list_columns() {
        $columns = [
            'image' => __('Image', 'million-dollar-script'),
            'title' => __('Advertiser', 'million-dollar-script'),
            'url' => __('Website', 'million-dollar-script'),
            'popup' => __('Description', 'million-dollar-script'),
            'alt' => __('Alt text', 'million-dollar-script'),
            'grid' => __('Grid', 'million-dollar-script'),
        ];
        $columns = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/advertiser/list/columns', $columns);
        if (!is_array($columns)) {
            return [];
        }

        $sanitized = [];
        foreach ($columns as $key => $label) {
            $key = sanitize_key((string) $key);
            if ($key) {
                $sanitized[$key] = sanitize_text_field((string) $label);
            }
        }

        return $sanitized;
    }

    private function advertiser_list_visible_columns($raw_columns, array $allowed_columns) {
        $defaults = array_values(array_intersect(['image', 'title', 'url'], array_keys($allowed_columns)));
        $raw_columns = trim((string) $raw_columns);
        if ('' === $raw_columns) {
            return $defaults;
        }

        if ('all' === strtolower($raw_columns)) {
            return array_keys($allowed_columns);
        }

        $columns = [];
        foreach (preg_split('/[\s,|]+/', $raw_columns) as $column) {
            $column = sanitize_key((string) $column);
            if ($column && isset($allowed_columns[$column]) && !in_array($column, $columns, true)) {
                $columns[] = $column;
            }
        }

        return $columns ?: $defaults;
    }

    private function advertiser_list_items(array $placements, array $columns) {
        $items = [];
        foreach ($placements as $placement) {
            $url = esc_url_raw((string) ($placement['link_url'] ?? ''));
            $host = $url ? wp_parse_url($url, PHP_URL_HOST) : '';
            $alt_text = sanitize_text_field((string) ($placement['alt_text'] ?? ''));
            /* translators: %d: advertiser placement ID. */
            $title = $alt_text ?: ($host ?: sprintf(__('Advertiser #%d', 'million-dollar-script'), absint($placement['id'] ?? 0)));
            $popup_text = wp_strip_all_tags((string) ($placement['popup_text'] ?? ''), true);
            $popup_text = '' !== $popup_text ? wp_trim_words($popup_text, 40, '...') : '';
            $grid_title = sanitize_text_field((string) ($placement['grid_title'] ?? ''));
            $image = !empty($placement['attachment_id'])
                ? wp_get_attachment_image(absint($placement['attachment_id']), 'thumbnail', false, ['loading' => 'lazy'])
                : '';
            $values = [
                'image' => $image,
                'title' => $title,
                'url' => $host ?: $url,
                'alt' => $alt_text,
                'popup' => $popup_text,
                'grid' => $grid_title,
            ];
            $values = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/advertiser/list/item/values', $values, $placement, $columns);
            $values = is_array($values) ? $values : [];

            $sanitized_values = [];
            foreach ($values as $key => $value) {
                $key = sanitize_key((string) $key);
                if ($key) {
                    $sanitized_values[$key] = 'image' === $key ? wp_kses_post((string) $value) : sanitize_text_field(wp_strip_all_tags((string) $value, true));
                }
            }

            $items[] = [
                'id' => absint($placement['id'] ?? 0),
                'url' => $url,
                'values' => $sanitized_values,
            ];
        }

        return $items;
    }

    private function advertiser_list_search() {
        $search = isset($_GET['mds3_advertiser_search']) ? sanitize_text_field(wp_unslash($_GET['mds3_advertiser_search'])) : '';

        return trim(substr($search, 0, 100));
    }

    private function advertiser_list_page() {
        return max(1, absint($_GET['mds3_advertiser_p'] ?? 1));
    }

    private function advertiser_list_pagination($page, $total_pages, $search) {
        if ($total_pages <= 1) {
            return [];
        }

        $items = [];
        $page = max(1, min(absint($page), absint($total_pages)));
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);

        if ($page > 1) {
            $items[] = ['label' => __('Previous', 'million-dollar-script'), 'url' => $this->advertiser_list_url($page - 1, $search), 'current' => false, 'type' => 'previous'];
        }
        if ($start > 1) {
            $items[] = ['label' => '1', 'url' => $this->advertiser_list_url(1, $search), 'current' => 1 === $page, 'type' => 'page'];
            if ($start > 2) {
                $items[] = ['label' => '&hellip;', 'url' => '', 'current' => false, 'type' => 'gap'];
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $items[] = ['label' => (string) $i, 'url' => $this->advertiser_list_url($i, $search), 'current' => $i === $page, 'type' => 'page'];
        }
        if ($end < $total_pages) {
            if ($end < $total_pages - 1) {
                $items[] = ['label' => '&hellip;', 'url' => '', 'current' => false, 'type' => 'gap'];
            }
            $items[] = ['label' => (string) $total_pages, 'url' => $this->advertiser_list_url($total_pages, $search), 'current' => $total_pages === $page, 'type' => 'page'];
        }
        if ($page < $total_pages) {
            $items[] = ['label' => __('Next', 'million-dollar-script'), 'url' => $this->advertiser_list_url($page + 1, $search), 'current' => false, 'type' => 'next'];
        }

        return $items;
    }

    private function advertiser_list_url($page, $search) {
        $url = remove_query_arg(['mds3_advertiser_p', 'mds3_advertiser_search'], $this->current_request_url());
        $query = [];
        if ('' !== trim((string) $search)) {
            $query['mds3_advertiser_search'] = trim((string) $search);
        }
        if (absint($page) > 1) {
            $query['mds3_advertiser_p'] = absint($page);
        }

        return $query ? add_query_arg($query, $url) : $url;
    }

    private function advertiser_list_form_action() {
        $url = $this->current_request_url();
        foreach (array_keys($_GET) as $key) {
            $url = remove_query_arg(sanitize_key((string) $key), $url);
        }

        return $url;
    }

    private function advertiser_list_hidden_inputs() {
        $inputs = [];
        foreach ($_GET as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $key = sanitize_key((string) $key);
            if ($key && !in_array($key, ['mds3_advertiser_p', 'mds3_advertiser_search'], true)) {
                $inputs[$key] = sanitize_text_field(wp_unslash($value));
            }
        }

        return $inputs;
    }

    private function truthy_shortcode_value($value) {
        return !in_array(strtolower(trim((string) $value)), ['0', 'false', 'no', 'off'], true);
    }
}
