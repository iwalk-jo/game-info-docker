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
        add_action('rest_api_init', [$this, 'register_rest']);
        add_shortcode('pandascore_tracker', [$this, 'shortcode_handler']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets() {
        wp_register_style('pandascore-tracker-style', plugins_url('css/index.css', __FILE__), [], '1.2');
        wp_register_script('pandascore-live-tracker-js', plugins_url('js/live-tracker.js', __FILE__), [], '1.2', true);
        wp_register_script('pandascore-timezone-js', plugins_url('js/timezone-converter.js', __FILE__), [], '1.0', true);
        wp_register_script('pandascore-league-filter-js', plugins_url('js/league-filter.js', __FILE__), [], '1.0', true);
        wp_register_script('pandascore-live-sse-js', plugins_url('js/live-sse.js', __FILE__), [], '0.2.0', true);
        wp_register_script('pandascore-live-polling-js', plugins_url('js/live-polling.js', __FILE__), [], '0.2.0', true);
    }

    public function admin_menu() {
        add_options_page('PandaScore Tracker', 'PandaScore Tracker', 'manage_options', 'pandascore-tracker', [$this, 'settings_page']);
    }

    public function register_settings() {
        register_setting($this->option_key, $this->option_key);
        add_settings_section('pandascore_main', 'PandaScore Settings', null, 'pandascore-tracker');
        add_settings_field('api_key', 'API Key', [$this, 'field_api_key'], 'pandascore-tracker', 'pandascore_main');
        add_settings_field('default_game', 'Default Game', [$this, 'field_default_game'], 'pandascore-tracker', 'pandascore_main');
        add_settings_field('default_type', 'Default Type', [$this, 'field_default_type'], 'pandascore-tracker', 'pandascore_main');
        add_settings_field('default_per_page', 'Default Per Page', [$this, 'field_default_per_page'], 'pandascore-tracker', 'pandascore_main');
        add_settings_field('hide_leagues', 'Hide Leagues (CSV)', [$this, 'field_hide_leagues'], 'pandascore-tracker', 'pandascore_main');
        add_settings_field('enable_fast_polling', 'Fast Live Polling (5s)', [$this, 'field_enable_fast_polling'], 'pandascore-tracker', 'pandascore_main');
        add_settings_field('enable_sse', 'Enable SSE Relay (secure)', [$this, 'field_enable_sse'], 'pandascore-tracker', 'pandascore_main');
        add_settings_field('relay_url', 'External Relay URL', [$this, 'field_relay_url'], 'pandascore-tracker', 'pandascore_main');
        add_settings_field('expose_websocket', 'Expose API Key for WebSocket (NOT RECOMMENDED)', [$this, 'field_expose_websocket'], 'pandascore-tracker', 'pandascore_main');
    }

    public function field_api_key() {
        $opts = get_option($this->option_key);
        $val = isset($opts['api_key']) ? esc_attr($opts['api_key']) : '';
        echo '<input type="text" name="' . $this->option_key . '[api_key]" value="' . $val . '" class="pandascore-api-key-input">';
        echo '<p class="description">Never expose this in the client. Use SSE relay or polling for realtime.</p>';
    }

    public function field_default_game() {
        $opts = get_option($this->option_key);
        $val = isset($opts['default_game']) ? esc_attr($opts['default_game']) : 'lol';
        echo '<input type="text" name="' . $this->option_key . '[default_game]" value="' . $val . '" placeholder="lol">';
    }
    public function field_default_type() {
        $opts = get_option($this->option_key);
        $val = isset($opts['default_type']) ? esc_attr($opts['default_type']) : 'upcoming';
        echo '<select name="' . $this->option_key . '[default_type]">';
        foreach (['upcoming','live','mixed'] as $t) {
            echo '<option value="'.$t.'"'.selected($val,$t,false).'>'.ucfirst($t).'</option>';
        }
        echo '</select>';
    }
    public function field_default_per_page() {
        $opts = get_option($this->option_key);
        $val = isset($opts['default_per_page']) ? intval($opts['default_per_page']) : 50;
        echo '<input type="number" min="1" max="100" name="' . $this->option_key . '[default_per_page]" value="' . $val . '">';
    }
    public function field_hide_leagues() {
        $opts = get_option($this->option_key);
        $val = isset($opts['hide_leagues']) ? esc_attr($opts['hide_leagues']) : '';
        echo '<input type="text" name="' . $this->option_key . '[hide_leagues]" value="' . $val . '" placeholder="LCK,LEC">';
    }
    public function field_enable_fast_polling() {
        $opts = get_option($this->option_key);
        $val = !empty($opts['enable_fast_polling']);
        echo '<label><input type="checkbox" name="' . $this->option_key . '[enable_fast_polling]" value="1" '.checked($val,true,false).'> Poll live scores every 5s (default 12s)</label>';
    }
    public function field_enable_sse() {
        $opts = get_option($this->option_key);
        $val = !empty($opts['enable_sse']);
        echo '<label><input type="checkbox" name="' . $this->option_key . '[enable_sse]" value="1" '.checked($val,true,false).'> Enable server-sent events relay (no client token)</label>';
    }
    public function field_relay_url() {
        $opts = get_option($this->option_key);
        $val = isset($opts['relay_url']) ? esc_url($opts['relay_url']) : '';
        echo '<input type="url" style="width:420px" name="' . $this->option_key . '[relay_url]" value="' . $val . '" placeholder="https://relay.example.com/sse">';
    }
    public function field_expose_websocket() {
        $opts = get_option($this->option_key);
        $val = !empty($opts['expose_websocket']);
        echo '<label style="color:#b91c1c"><input type="checkbox" name="' . $this->option_key . '[expose_websocket]" value="1" '.checked($val,true,false).'> Expose API key to client for WS (NOT recommended)</label>';
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
                <li><strong>per_page:</strong> Items per page (1–100)</li>
                <li><strong>page:</strong> Page number (1+)</li>
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

    // REST + SSE
    public function register_rest() {
        register_rest_route('pandascore/v1', '/matches', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_matches'],
            'permission_callback' => '__return_true',
            'args' => [
                'game' => ['type'=>'string','required'=>false,'default'=>'lol'],
                'type' => ['type'=>'string','required'=>false,'default'=>'upcoming'],
                'page' => ['type'=>'integer','required'=>false,'default'=>1],
                'per_page' => ['type'=>'integer','required'=>false,'default'=>50],
                'league' => ['type'=>'string','required'=>false],
                'from' => ['type'=>'string','required'=>false],
                'to' => ['type'=>'string','required'=>false],
                'team' => ['type'=>'string','required'=>false],
            ],
        ]);
        register_rest_route('pandascore/v1', '/live-stream', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_live_stream'],
            'permission_callback' => '__return_true',
            'args' => [
                'game' => ['type'=>'string','required'=>false,'default'=>'lol'],
                'per_page' => ['type'=>'integer','required'=>false,'default'=>50],
                'duration' => ['type'=>'integer','required'=>false,'default'=>60],
            ],
        ]);
    }

    private function api_request($path, $query = []) {
        $api_key = $this->get_api_key();
        if (!$api_key) return new WP_Error('no_api_key', 'PandaScore API key not set');
        $url = 'https://api.pandascore.co/'.$path;
        if (!empty($query)) $url = add_query_arg($query, $url);
        $resp = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [ 'Accept'=>'application/json', 'Authorization' => 'Bearer '.$api_key ],
        ]);
        if (is_wp_error($resp)) return $resp;
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) return new WP_Error('api_error', 'Upstream error: '.$code);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($json)) return new WP_Error('json_error','Invalid JSON');
        $headers = wp_remote_retrieve_headers($resp);
        return ['json'=>$json,'headers'=>$headers,'status'=>$code];
    }

    private function extract_pagination_meta($headers) {
        $h = is_object($headers) && method_exists($headers,'getAll') ? $headers->getAll() : (is_array($headers)?$headers:[]);
        $norm = [];
        foreach ($h as $k=>$v) $norm[strtolower($k)] = is_array($v)?implode(',', $v):$v;
        $meta = ['page'=>null,'per_page'=>null,'total'=>null,'has_next'=>false,'has_prev'=>false,'last_page'=>null];
        if (!empty($norm['x-page'])) $meta['page'] = intval($norm['x-page']);
        if (!empty($norm['x-per-page'])) $meta['per_page'] = intval($norm['x-per-page']);
        if (!empty($norm['x-total'])) $meta['total'] = intval($norm['x-total']);
        if (!empty($norm['link'])) {
            $link = $norm['link'];
            $meta['has_next'] = (strpos($link,'rel="next"')!==false);
            $meta['has_prev'] = (strpos($link,'rel="previous"')!==false)||(strpos($link,'rel="prev"')!==false);
            if (preg_match('/<([^>]+)>;\s*rel="last"/i', $link, $m)) {
                $parts = wp_parse_url($m[1]);
                if (!empty($parts['query'])) { parse_str($parts['query'],$q); $meta['last_page'] = isset($q['page'])?intval($q['page']):(isset($q['page[number]'])?intval($q['page[number]']):null); }
            }
        }
        return $meta;
    }

    private function normalize_matches($arr) {
        return array_map(function($m){
            $league = isset($m['league']) && is_array($m['league']) ? $m['league'] : [];
            $res = isset($m['results']) && is_array($m['results']) ? $m['results'] : [];
            $scores = [];
            foreach ($res as $r) $scores[] = intval($r['score'] ?? 0);
            $opps = [];
            foreach (($m['opponents'] ?? []) as $o) {
                $opp = $o['opponent'] ?? [];
                $opps[] = [
                    'id' => $opp['id'] ?? null,
                    'name' => $opp['name'] ?? 'TBD',
                    'acronym' => $opp['acronym'] ?? ($opp['name'] ?? 'TBD'),
                    'image_url' => $opp['image_url'] ?? '',
                ];
            }
            return [
                'id' => $m['id'] ?? null,
                'status' => $m['status'] ?? 'unknown',
                'scheduled_at' => $m['scheduled_at'] ?? null,
                'begin_at' => $m['begin_at'] ?? null,
                'league' => [
                    'id' => $league['id'] ?? null,
                    'name' => $league['name'] ?? '',
                    'image_url' => $league['image_url'] ?? '',
                ],
                'opponents' => $opps,
                'scores' => $scores,
                'raw' => $m,
            ];
        }, $arr);
    }

    private function fetch_lives_info() {
        $resp = $this->api_request('lives');
        if (is_wp_error($resp)) return ['endpoints'=>[]];
        $out = [];
        foreach (($resp['json'] ?? []) as $entry) {
            foreach (($entry['endpoints'] ?? []) as $ep) {
                $mid = isset($ep['match_id']) ? intval($ep['match_id']) : null; if (!$mid) continue;
                if (!isset($out[$mid])) $out[$mid] = [];
                $out[$mid][] = ['type'=>$ep['type'] ?? '', 'url'=>$ep['url'] ?? '', 'open'=>!empty($ep['open'])];
            }
        }
        return ['endpoints'=>$out];
    }

    public function rest_get_matches(WP_REST_Request $req) {
        $game = sanitize_text_field($req->get_param('game') ?: 'lol');
        $type = sanitize_text_field($req->get_param('type') ?: 'upcoming');
        $page = max(1, intval($req->get_param('page') ?: 1));
        $per_page = max(1, min(100, intval($req->get_param('per_page') ?: 50)));
        $league = sanitize_text_field($req->get_param('league') ?: '');
        $from = sanitize_text_field($req->get_param('from') ?: '');
        $to = sanitize_text_field($req->get_param('to') ?: '');
        $team = sanitize_text_field($req->get_param('team') ?: '');

        $endpoint = ($type === 'live') ? 'running' : (($type === 'upcoming') ? 'upcoming' : null);
        if ($endpoint === null) {
            // mixed: fetch both
            $running = $this->api_request("{$game}/matches/running", ['page[size]'=>$per_page,'page[number]'=>$page]);
            $upcoming = $this->api_request("{$game}/matches/upcoming", ['page[size]'=>$per_page,'page[number]'=>$page]);
            if (is_wp_error($running)) return $running;
            if (is_wp_error($upcoming)) return $upcoming;
            $data = array_merge($this->normalize_matches($running['json']), $this->normalize_matches($upcoming['json']));
            $meta = ['running'=>$this->extract_pagination_meta($running['headers']), 'upcoming'=>$this->extract_pagination_meta($upcoming['headers'])];
        } else {
            $resp = $this->api_request("{$game}/matches/{$endpoint}", ['page[size]'=>$per_page,'page[number]'=>$page]);
            if (is_wp_error($resp)) return $resp;
            $data = $this->normalize_matches($resp['json']);
            $meta = $this->extract_pagination_meta($resp['headers']);
        }

        // filters
        if ($from || $to) {
            $fromTs = $from ? strtotime($from) : null; $toTs = $to ? strtotime($to) : null;
            $data = array_values(array_filter($data, function($m) use($fromTs,$toTs){
                $dt = $m['scheduled_at'] ?: $m['begin_at']; if (!$dt) return false; $ts = strtotime($dt); if ($ts===false) return false;
                if ($fromTs && $ts < $fromTs) return false; if ($toTs && $ts > $toTs) return false; return true; }));
        }
        if ($team) {
            $needle = strtoupper($team);
            $data = array_values(array_filter($data, function($m) use($needle){
                foreach (($m['opponents']??[]) as $o) { $n = strtoupper($o['name']??''); $a = strtoupper($o['acronym']??''); if ((strpos($n,$needle)!==false)||(strpos($a,$needle)!==false)) return true; }
                return false; }));
        }
        if ($league) {
            $data = array_values(array_filter($data, function($m) use($league){
                $n = strtoupper($m['league']['name']??''); if ($league==='OTHER LEAGUES') return !in_array($n,['LCK','LPL','LEC','LTA'],true); return $n===strtoupper($league);
            }));
        }

        $ws = [];
        if ($type==='live' || $type==='mixed') { $l = $this->fetch_lives_info(); $ws = $l['endpoints'] ?? []; }

        return rest_ensure_response(['page'=>$page,'per_page'=>$per_page,'type'=>$type,'game'=>$game,'data'=>$data,'meta'=>$meta,'ws'=>$ws]);
    }

    public function rest_live_stream(WP_REST_Request $req) {
        if (headers_sent()) return new WP_Error('headers_sent','Cannot start SSE; headers already sent');
        if (function_exists('ignore_user_abort')) ignore_user_abort(true);
        @set_time_limit(0);
        $opts = get_option($this->option_key);
        $game = sanitize_text_field($req->get_param('game') ?: ($opts['default_game'] ?? 'lol'));
        $per_page = max(1, min(100, intval($req->get_param('per_page') ?: ($opts['default_per_page'] ?? 50))));
        $interval_ms = !empty($opts['enable_fast_polling']) ? 5000 : 12000;
        $duration = max(5, intval($req->get_param('duration') ?: 60));
        $ticks = (int) floor(($duration * 1000) / $interval_ms);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache'); header('Connection: keep-alive'); header('X-Accel-Buffering: no');
        while (ob_get_level()>0) { @ob_end_flush(); } @ob_implicit_flush(1);

        for ($i=0; $i<=$ticks; $i++) {
            $resp = $this->api_request("{$game}/matches/running", ['page[size]'=>$per_page,'page[number]'=>1]);
            if (is_wp_error($resp)) { echo "event: error\n"; echo 'data: '.json_encode(['message'=>$resp->get_error_message()])."\n\n"; }
            else { $data = $this->normalize_matches($resp['json']); echo "event: update\n"; echo 'data: '.json_encode(['game'=>$game,'data'=>$data,'ts'=>time()])."\n\n"; }
            @flush(); @ob_flush(); usleep($interval_ms*1000);
        }
        echo "event: end\n"; echo 'data: '.json_encode(['ts'=>time()])."\n\n"; exit;
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
        // Apply hidden leagues (SSR)
        $opts = get_option($this->option_key);
        if (!is_wp_error($matches) && !empty($opts['hide_leagues'])) {
            $hide = array_filter(array_map('trim', explode(',', strtoupper($opts['hide_leagues']))));
            if ($hide) {
                $matches = array_values(array_filter($matches, function($m) use($hide){
                    $name = isset($m['league']['name']) ? strtoupper($m['league']['name']) : '';
                    return $name && !in_array($name, $hide, true);
                }));
            }
        }
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
        $opts = get_option($this->option_key);
        $defaults = [
            'game' => isset($opts['default_game']) ? $opts['default_game'] : 'lol',
            'limit' => isset($opts['default_per_page']) ? intval($opts['default_per_page']) : 50,
            'align' => 'center',
            'type' => isset($opts['default_type']) ? $opts['default_type'] : 'upcoming',
            'per_page' => 0,
            'page' => 1,
        ];
        $atts = shortcode_atts($defaults, $atts, 'pandascore_tracker');
        $per_page = intval($atts['per_page'])>0 ? intval($atts['per_page']) : intval($atts['limit']);
        $page = max(1, intval($atts['page']));
        $poll_interval = !empty($opts['enable_fast_polling']) ? 5000 : 12000;

        wp_enqueue_style('pandascore-tracker-style');
        wp_enqueue_script('pandascore-timezone-js');
        wp_enqueue_script('pandascore-league-filter-js');

        $this->live_match_ids = [];

        $poll_url = add_query_arg(['game'=>$atts['game'],'type'=>'live','per_page'=>$per_page,'page'=>1], rest_url('pandascore/v1/matches'));
        $sse_url = '';
        if (!empty($opts['enable_sse'])) { $sse_url = add_query_arg(['game'=>$atts['game'],'per_page'=>$per_page], rest_url('pandascore/v1/live-stream')); }
        elseif (!empty($opts['relay_url'])) { $sse_url = esc_url($opts['relay_url']); }

        if ($atts['type']==='live' || $atts['type']==='mixed') {
            if (!empty($opts['enable_sse'])) wp_enqueue_script('pandascore-live-sse-js'); else wp_enqueue_script('pandascore-live-polling-js');
        }

        $html = '<div class="pandascore-tracker align-' . esc_attr($atts['align']) . '" data-game="'.esc_attr($atts['game']).'" data-type="'.esc_attr($atts['type']).'" data-per-page="'.esc_attr($per_page).'" data-poll-interval="'.esc_attr($poll_interval).'" data-endpoint="'.esc_url($poll_url).'"'.($sse_url?' data-sse-url="'.esc_url($sse_url).'"':'').'>';

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
            $opts = get_option($this->option_key);
            if (!empty($opts['expose_websocket'])) {
                wp_enqueue_script('pandascore-live-tracker-js');
                wp_localize_script('pandascore-live-tracker-js', 'pandaScoreLiveTracker', [
                    'apiKey' => $this->get_api_key(),
                    'matchIds' => array_unique($this->live_match_ids),
                ]);
            }
        }

        $html .= '</div>';
        return $html;
    }
}

new PandaScore_Tracker_Plugin();