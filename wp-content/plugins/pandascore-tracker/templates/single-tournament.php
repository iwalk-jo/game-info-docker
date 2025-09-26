<?php
/**
 * Template for displaying a single tournament details page.
 *
 * Fetches tournament data from the PandaScore API using the tournament ID or slug from the URL.
 */
global $wp_query;
$tournament_param = isset($wp_query->query_vars['tournament']) ? $wp_query->query_vars['tournament'] : '';
if (!$tournament_param) {
    get_header();
    echo '<main class="pandascore-tournament-details"><h1>Tournament Not Found</h1></main>';
    get_footer();
    return;
}
$api_key = get_option('pandascore_tracker_options')['api_key'] ?? '';
$tournament = null;
$error_message = '';
try {
    if (is_numeric($tournament_param)) {
        $api_url = "https://api.pandascore.co/tournaments/{$tournament_param}?token=" . urlencode($api_key);
        $response = wp_remote_get($api_url, [ 'headers' => [ 'Accept' => 'application/json' ] ]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $tournament = json_decode(wp_remote_retrieve_body($response), true);
        } else {
            $error_message = 'Failed to retrieve tournament data (ID).';
        }
    } else {
        $api_url = "https://api.pandascore.co/tournaments?slug=" . urlencode($tournament_param) . "&token=" . urlencode($api_key);
        $response = wp_remote_get($api_url, [ 'headers' => [ 'Accept' => 'application/json' ] ]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $tournaments = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($tournaments) && count($tournaments) > 0) {
                $tournament = $tournaments[0];
            } else {
                $error_message = 'No tournament found for this slug.';
            }
        } else {
            $error_message = 'Failed to retrieve tournament data (slug).';
        }
    }
    if ($tournament === null || !isset($tournament['id'])) {
        $error_message = 'Tournament not found or unavailable.';
    }
} catch (Exception $e) {
    $error_message = 'Error: ' . $e->getMessage();
}
echo '<main class="pandascore-tournament-details">';
get_header();
echo '<main class="pandascore-tournament-details">';

// Debug info for troubleshooting (remove/comment out in production)
if (function_exists('current_user_can') && current_user_can('manage_options')) {
    echo '<div style="background:#ffe;border:1px solid #cc0;padding:8px;margin-bottom:12px;font-size:13px;">';
    echo '<strong>Debug Info:</strong><br>';
    echo 'Tournament Param: <code>' . esc_html($tournament_param) . '</code><br>';
    echo 'API Key: <code>' . ($api_key ? 'SET' : 'NOT SET') . '</code><br>';
    if (isset($api_url)) echo 'API URL: <code>' . esc_html($api_url) . '</code><br>';
    if ($error_message) echo 'Error: <code>' . esc_html($error_message) . '</code><br>';
    echo '</div>';
}

if ($tournament && isset($tournament['id'])) {
    echo '<h1>' . esc_html($tournament['name'] ?? 'Tournament Details') . '</h1>';
    if (!empty($tournament['league']['image_url'])) {
        echo '<img src="' . esc_url($tournament['league']['image_url']) . '" alt="' . esc_attr($tournament['league']['name'] ?? '') . '" class="pandascore-league-logo">';
    }
    echo '<div class="pandascore-tournament-meta">';
    if (!empty($tournament['league']['name'])) {
        echo '<span class="pandascore-league">League: ' . esc_html($tournament['league']['name']) . '</span>';
    }
    if (!empty($tournament['serie']['name'])) {
        echo '<span class="pandascore-serie">Serie: ' . esc_html($tournament['serie']['name']) . '</span>';
    }
    if (!empty($tournament['videogame']['name'])) {
        echo '<span class="pandascore-videogame">Game: ' . esc_html($tournament['videogame']['name']) . '</span>';
    }
    if (!empty($tournament['region'])) {
        echo '<span class="pandascore-region">Region: ' . esc_html($tournament['region']) . '</span>';
    }
    if (!empty($tournament['prizepool'])) {
        echo '<span class="pandascore-prizepool">Prize Pool: ' . esc_html($tournament['prizepool']) . '</span>';
    }
    if (!empty($tournament['slug'])) {
        echo '<span class="pandascore-tournament-slug">Slug: ' . esc_html($tournament['slug']) . '</span>';
    }
    if (!empty($tournament['id'])) {
        echo '<span class="pandascore-tournament-id">ID: ' . intval($tournament['id']) . '</span>';
    }
    if (!empty($tournament['modified_at'])) {
        echo '<span class="pandascore-tournament-modified">Modified: ' . esc_html($tournament['modified_at']) . '</span>';
    }
    if (!empty($tournament['url'])) {
        echo '<span class="pandascore-tournament-url"><a href="' . esc_url($tournament['url']) . '" target="_blank">Official Page</a></span>';
    }
    echo '</div>';
    // Optionally, list matches or bracket for this tournament
    if (!empty($tournament['matches']) && is_array($tournament['matches'])) {
        echo '<div class="pandascore-tournament-matches"><strong>Matches:</strong> ';
        foreach ($tournament['matches'] as $match) {
            echo '<span class="pandascore-tournament-match">' . esc_html($match['name'] ?? '') . '</span> ';
        }
        echo '</div>';
    }
    if (!empty($tournament['bracket_url'])) {
        echo '<div class="pandascore-tournament-bracket"><a href="' . esc_url($tournament['bracket_url']) . '" target="_blank">View Bracket</a></div>';
    }
} else {
    echo '<h2>' . esc_html($error_message ?: 'Tournament not found or unavailable.') . '</h2>';
}
echo '</main>';
get_footer();
