<?php
/**
 * Template for displaying a single league details page.
 *
 * Fetches league data from the PandaScore API using the league ID or slug from the URL.
 */
global $wp_query;
$league_param = isset($wp_query->query_vars['league']) ? $wp_query->query_vars['league'] : '';
if (!$league_param) {
    get_header();
    echo '<main class="pandascore-league-details"><h1>League Not Found</h1></main>';
    get_footer();
    return;
}
$api_key = get_option('pandascore_tracker_options')['api_key'] ?? '';
$league = null;
$error_message = '';
try {
    if (is_numeric($league_param)) {
        $api_url = "https://api.pandascore.co/leagues/{$league_param}?token=" . urlencode($api_key);
        $response = wp_remote_get($api_url, [ 'headers' => [ 'Accept' => 'application/json' ] ]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $league = json_decode(wp_remote_retrieve_body($response), true);
        } else {
            $error_message = 'Failed to retrieve league data (ID).';
        }
    } else {
        $api_url = "https://api.pandascore.co/leagues?slug=" . urlencode($league_param) . "&token=" . urlencode($api_key);
        $response = wp_remote_get($api_url, [ 'headers' => [ 'Accept' => 'application/json' ] ]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $leagues = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($leagues) && count($leagues) > 0) {
                $league = $leagues[0];
            } else {
                $error_message = 'No league found for this slug.';
            }
        } else {
            $error_message = 'Failed to retrieve league data (slug).';
        }
    }
    if ($league === null || !isset($league['id'])) {
        $error_message = 'League not found or unavailable.';
    }
} catch (Exception $e) {
    $error_message = 'Error: ' . $e->getMessage();
}
echo '<main class="pandascore-league-details">';
get_header();
echo '<main class="pandascore-league-details">';

// Debug info for troubleshooting (remove/comment out in production)
if (function_exists('current_user_can') && current_user_can('manage_options')) {
    echo '<div style="background:#ffe;border:1px solid #cc0;padding:8px;margin-bottom:12px;font-size:13px;">';
    echo '<strong>Debug Info:</strong><br>';
    echo 'League Param: <code>' . esc_html($league_param) . '</code><br>';
    echo 'API Key: <code>' . ($api_key ? 'SET' : 'NOT SET') . '</code><br>';
    if (isset($api_url)) echo 'API URL: <code>' . esc_html($api_url) . '</code><br>';
    if ($error_message) echo 'Error: <code>' . esc_html($error_message) . '</code><br>';
    echo '</div>';
}

if ($league && isset($league['id'])) {
    echo '<h1>' . esc_html($league['name'] ?? 'League Details') . '</h1>';
    if (!empty($league['image_url'])) {
        echo '<img src="' . esc_url($league['image_url']) . '" alt="' . esc_attr($league['name'] ?? '') . '" class="pandascore-league-logo">';
    }
    echo '<div class="pandascore-league-meta">';
    if (!empty($league['videogame']['name'])) {
        echo '<span class="pandascore-videogame">Game: ' . esc_html($league['videogame']['name']) . '</span>';
    }
    if (!empty($league['region'])) {
        echo '<span class="pandascore-region">Region: ' . esc_html($league['region']) . '</span>';
    }
    if (!empty($league['slug'])) {
        echo '<span class="pandascore-league-slug">Slug: ' . esc_html($league['slug']) . '</span>';
    }
    if (!empty($league['id'])) {
        echo '<span class="pandascore-league-id">ID: ' . intval($league['id']) . '</span>';
    }
    if (!empty($league['modified_at'])) {
        echo '<span class="pandascore-league-modified">Modified: ' . esc_html($league['modified_at']) . '</span>';
    }
    if (!empty($league['url'])) {
        echo '<span class="pandascore-league-url"><a href="' . esc_url($league['url']) . '" target="_blank">Official Page</a></span>';
    }
    echo '</div>';
    // Optionally, list tournaments or series for this league
    if (!empty($league['series']) && is_array($league['series'])) {
        echo '<div class="pandascore-league-series"><strong>Series:</strong> ';
        foreach ($league['series'] as $serie) {
            echo '<span class="pandascore-league-serie">' . esc_html($serie['name'] ?? '') . '</span> ';
        }
        echo '</div>';
    }
    if (!empty($league['tournaments']) && is_array($league['tournaments'])) {
        echo '<div class="pandascore-league-tournaments"><strong>Tournaments:</strong> ';
        foreach ($league['tournaments'] as $tournament) {
            echo '<span class="pandascore-league-tournament">' . esc_html($tournament['name'] ?? '') . '</span> ';
        }
        echo '</div>';
    }
} else {
    echo '<h2>' . esc_html($error_message ?: 'League not found or unavailable.') . '</h2>';
}
echo '</main>';
get_footer();
