<?php
/*
Plugin Name: PandaScore Tracker
Description: Fetches and displays PandaScore game scores via shortcode.
Version: 1.2 (League Filter Added)
Author: Deejay Dev
Text Domain: pandascore-tracker
*/

if (!defined('ABSPATH')) {
    exit;
}

class PandaScore_Tracker_Plugin {
    private $option_key = 'pandascore_tracker_options';
    private $live_match_ids = [];

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('pandascore_tracker', [$this, 'shortcode_handler']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets() {
        wp_register_style('pandascore-tracker-style', plugins_url('css/index.css', __FILE__), [], '1.2');
        wp_register_script('pandascore-live-tracker-js', plugins_url('js/live-tracker.js', __FILE__), [], '1.2', true);
        wp_register_script('pandascore-timezone-js', plugins_url('js/timezone-converter.js', __FILE__), [], '1.0', true);
        wp_register_script('pandascore-league-filter-js', plugins_url('js/league-filter.js', __FILE__), [], '1.0', true);
    }

    public function admin_menu() {
        add_options_page('PandaScore Tracker', 'PandaScore Tracker', 'manage_options', 'pandascore-tracker', [$this, 'settings_page']);
    }

    public function register_settings() {
        register_setting($this->option_key, $this->option_key);
        add_settings_section('pandascore_main', 'PandaScore Settings', null, 'pandascore-tracker');
        add_settings_field('api_key', 'API Key', [$this, 'field_api_key'], 'pandascore-tracker', 'pandascore_main');
    }

    public function field_api_key() {
        $opts = get_option($this->option_key);
        $val = isset($opts['api_key']) ? esc_attr($opts['api_key']) : '';
        echo '<input type="text" name="' . $this->option_key . '[api_key]" value="' . $val . '" class="pandascore-api-key-input">';
    }

    public function settings_page() {
        wp_enqueue_style('pandascore-tracker-style');
        ?>
        <div class="wrap">
            <h1>PandaScore Tracker</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_key);
                do_settings_sections('pandascore-tracker');
                submit_button();
                ?>
            </form>
            <h3>Shortcode Usage</h3>
            <p><strong>Basic usage:</strong> <code>[pandascore_tracker]</code>
        </p>
            <p><strong>Live matches:</strong> <code>[pandascore_tracker type="live"]</code></p>
            <p><strong>Mixed (live + upcoming):</strong> <code>[pandascore_tracker type="mixed" game="lol"]</code></p>
            <h4>Parameters:</h4>
            <ul>
                <li><strong>game:</strong> Game type (valorant, lol, csgo, dota2, etc.)</li>
                <li><strong>type:</strong> Match type - "upcoming" (default), "live", or "mixed"</li>
            </ul>
        </div>
        <?php
    }

    private function get_api_key() {
        $opts = get_option($this->option_key);
        return isset($opts['api_key']) ? trim($opts['api_key']) : '';
    }

    // 🔹 NEW: Render league filters row
    private function render_league_filters() {
        // Define the specific leagues we want to show (consolidated LTA)
        $leagues = ['LCK', 'LPL', 'LEC', 'LTA'];

        $html = '<div class="pandascore-league-filters">';

        // Add specific league buttons with local images
        foreach ($leagues as $league_name) {
                       // Convert league name to filename format
            $filename = str_replace(' ', '-', strtoupper($league_name)) . '-logo.png';
            $image_url = plugins_url('images/' . $filename, __FILE__);

            $html .= '<div class="pandascore-league-filter" data-league-name="' . esc_attr($league_name) . '" title="' . esc_attr($league_name) . '">';
            $html .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($league_name) . '">';
            $html .= '</div>';
        }

        // Add "OTHER LEAGUES" button using the same pattern
        $other_leagues_filename = 'OTHERS-LEAGUES-logo.png';
        $other_leagues_image = plugins_url('images/' . $other_leagues_filename, __FILE__);
        $html .= '<div class="pandascore-league-filter" data-league-name="OTHER LEAGUES" title="OTHER LEAGUES">';
        $html .= '<img src="' . esc_url($other_leagues_image) . '" alt="OTHER LEAGUES">';
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    private function make_api_call($game, $limit, $endpoint) {
        $api_key = $this->get_api_key();
        if (!$api_key) return new WP_Error('no_api_key', 'PandaScore API key not set');

        $query_args = ['page[size]' => intval($limit)];

        // For the league filtering to work properly with "OTHER LEAGUES",
        // we fetch all LoL matches and let JavaScript handle the filtering
        // This ensures we have all matches available for client-side filtering

        $url = add_query_arg($query_args, "https://api.pandascore.co/{$game}/matches/{$endpoint}");
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $api_key]
        ]);

        if (is_wp_error($response)) return $response;
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('api_error', 'PandaScore API returned code ' . wp_remote_retrieve_response_code($response));
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) return new WP_Error('json_error', 'Invalid JSON from API');
        return $data;
    }

    private function get_team_logo_html($logo_url, $team_name, $acronym) {
        if ($logo_url) {
            return '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($team_name) . '" class="pandascore-team-logo">';
        }
        $fallback_letter = strtoupper(($acronym && $acronym !== 'TBD' && $acronym !== 'N/A') ? $acronym[0] : (($team_name && $team_name !== 'TBD' && $team_name !== 'N/A') ? $team_name[0] : '?'));
        return '<div class="pandascore-team-logo-placeholder" title="Unknown Team">' . esc_html($fallback_letter) . '</div>';
    }

    private function render_team($logo_url, $name, $acronym, $score = null, $opponent_id = null) {
        $html = '<div class="pandascore-team' . ($score !== null ? ' with-score' : '') . '">';
        $html .= '<div class="pandascore-team-info">';
        $html .= $this->get_team_logo_html($logo_url, $name, $acronym);
        $html .= '<span class="pandascore-team-name" title="' . esc_attr($name) . '">' . esc_html($acronym) . '</span>';
        $html .= '</div>';
        if ($score !== null) {
            $html .= '<div class="pandascore-score" data-opponent-id="' . esc_attr($opponent_id ?? '') . '">' . intval($score) . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    private function render_match($match, $is_live = false) {
        $opponents = ['TBD', 'TBD'];
        $acronyms = ['TBD', 'TBD'];
        $logos = ['', ''];
        $scores = [0, 0];
        $opponent_ids = [null, null];

        if (isset($match['opponents']) && is_array($match['opponents'])) {
            foreach ($match['opponents'] as $i => $o) {
                if ($i < 2) {
                    $opponents[$i] = isset($o['opponent']['name']) ? esc_html($o['opponent']['name']) : 'TBD';
                    $acronyms[$i] = !empty($o['opponent']['acronym']) ? esc_html($o['opponent']['acronym']) : $opponents[$i];
                    $logos[$i] = $o['opponent']['image_url'] ?? '';
                    $opponent_ids[$i] = $o['opponent']['id'] ?? null;
                }
            }
        }

        if (isset($match['results']) && is_array($match['results'])) {
            foreach ($match['results'] as $i => $r) {
                if ($i < 2) $scores[$i] = intval($r['score'] ?? 0);
            }
        }

        $league_name = esc_html($match['league']['name'] ?? '');
        $league_logo = esc_url($match['league']['image_url'] ?? '');
        $league_id = esc_attr($match['league']['id'] ?? '');
        $scheduled_at = $match['scheduled_at'] ?? '';
        $is_upcoming = !$is_live && $scheduled_at;

        // 🔹 Added data-league-id for filtering
        $html = '<div class="pandascore-match" data-league-id="' . $league_id . '" data-match-id="' . esc_attr($match['id'] ?? '') . ($is_upcoming ? '" data-scheduled-at="' . esc_attr($scheduled_at) : '') . '">';
        $html .= '<div class="pandascore-league-container">';
        $html .= $league_logo ? '<div class="pandascore-league-logo"><img src="' . $league_logo . '" alt="' . $league_name . '" title="' . $league_name . '"></div>'
                             : '<div class="pandascore-league-placeholder" title="' . $league_name . '">' . ($league_name ? $league_name[0] : 'L') . '</div>';
        $html .= '</div>';

        $html .= '<div class="pandascore-match-content' . ($is_live ? ' live-layout' : '') . '">';
        $html .= '<div class="pandascore-teams-container">';
        $html .= $this->render_team($logos[0], $opponents[0], $acronyms[0], $is_live ? $scores[0] : null, $opponent_ids[0]);
        $html .= $this->render_team($logos[1], $opponents[1], $acronyms[1], $is_live ? $scores[1] : null, $opponent_ids[1]);
        $html .= '</div>';

        if ($is_upcoming) {
            $html .= '<div class="pandascore-time-container"><div class="pandascore-time-badge"><div class="pandascore-time">Loading...</div><div class="pandascore-time-day">Loading...</div></div></div>';
        }
        $html .= '</div></div>';
        return $html;
    }

    private function render_matches($game, $limit, $is_live) {
        $matches = $this->make_api_call($game, $limit, $is_live ? 'running' : 'upcoming');
        if (is_wp_error($matches)) {
            return '<div class="pandascore-error">Error: ' . esc_html($matches->get_error_message()) . '</div>';
        }
        if (empty($matches)) {
            return $is_live ? '' : '<div class="pandascore-no-matches">No upcoming matches found.</div>';
        }

        $html = '<div class="pandascore-section-header">' . ($is_live ? '<span class="pandascore-live-indicator"></span>LIVE' : 'UPCOMING') . '</div>';
        $html .= '<div class="pandascore-matches-container">';
        foreach ($matches as $match) {
            if ($is_live && isset($match['id'])) {
                $this->live_match_ids[] = $match['id'];
            }
            $html .= $this->render_match($match, $is_live);
        }
        $html .= '</div>';
        return $html;
    }

    public function shortcode_handler($atts) {
        $atts = shortcode_atts(['game' => 'lol', 'limit' => 100, 'align' => 'center', 'type' => 'mixed'], $atts, 'pandascore_tracker');
        wp_enqueue_style('pandascore-tracker-style');
        wp_enqueue_script('pandascore-timezone-js');
        wp_enqueue_script('pandascore-league-filter-js'); 

        $this->live_match_ids = [];
        $html = '<div class="pandascore-tracker align-' . esc_attr($atts['align']) . '">';

        // 🔹 Render league filters first
        $html .= $this->render_league_filters();

        // 🔹 Create grouped containers for live and upcoming matches
        $html .= '<div class="pandascore-matches-wrapper">';
        if (in_array($atts['type'], ['live', 'mixed'])) {
            $live_content = $this->render_matches($atts['game'], $atts['limit'], true);
            if (!empty($live_content)) {
                $html .= '<div class="pandascore-live-container">';
                $html .= $live_content;
                $html .= '</div>';
            }
        }
        if (in_array($atts['type'], ['upcoming', 'mixed'])) {
            $upcoming_content = $this->render_matches($atts['game'], $atts['limit'], false);
            if (!empty($upcoming_content)) {
                $html .= '<div class="pandascore-upcoming-container">';
                $html .= $upcoming_content;
                $html .= '</div>';
            }
        }
        $html .= '</div>';

        if ($this->live_match_ids) {
            wp_enqueue_script('pandascore-live-tracker-js');
            wp_localize_script('pandascore-live-tracker-js', 'pandaScoreLiveTracker', [
                'apiKey' => $this->get_api_key(),
                'matchIds' => array_unique($this->live_match_ids),
                'websocketUrl' => 'wss://websocket.pandascore.co/ws'
            ]);
        }

        $html .= '</div>';
        return $html;
    }
}

new PandaScore_Tracker_Plugin();