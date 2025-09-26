<?php
/**
 * Template for displaying a single match details page.
 * Matches the design with head-to-head comparison and player stats.
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wp_query;
$match_param = isset($wp_query->query_vars['match']) ? $wp_query->query_vars['match'] : '';
$api_key = get_option('pandascore_tracker_options')['api_key'] ?? '';
$match = null;
$error_message = '';

if (empty($match_param)) {
    $error_message = 'No match ID provided.';
} else if (empty($api_key)) {
    $error_message = 'PandaScore API key not configured. Please check the plugin settings.';
} else {
    try {
        // Validate match ID to prevent potential security issues
        $match_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $match_param);
        $api_url = "https://api.pandascore.co/matches/{$match_id}";

        // Make the API request with proper timeout and SSL verification
        $response = wp_remote_get($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key
            ],
            'timeout' => 15,
            'sslverify' => true
        ]);
        
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code === 200) {
                $body = wp_remote_retrieve_body($response);
                $match = json_decode($body, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $error_message = 'Invalid JSON response from API: ' . json_last_error_msg();
                } else if (!isset($match['id']) || !isset($match['opponents'])) {
                    $error_message = 'Invalid match data structure received from API';
                }
            } else {
                $error_message = sprintf(
                    'API Error (Status %d): %s', 
                    $status_code,
                    strip_tags(wp_remote_retrieve_body($response))
                );
            }
        } else {
            $error_message = 'Failed to connect to PandaScore API: ' . esc_html($response->get_error_message());
        }
    } catch (Exception $e) {
        $error_message = 'Error: ' . esc_html($e->getMessage());
    }
}

if (function_exists('get_header')) {
    get_header();
}
?>

<div class="wrap pandascore-match-details dark-theme">
    <?php if (function_exists('current_user_can') && current_user_can('manage_options')): ?>
        <div class="pandascore-debug">
            <strong>Debug Info:</strong><br>
            Match ID: <?php echo esc_html($match_param); ?><br>
            API URL: <?php echo esc_html($api_url); ?><br>
            <?php if ($error_message): ?>
                Error: <?php echo esc_html($error_message); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($match && isset($match['id'])): ?>
        <div class="match-header">
            <div class="match-time">
                <?php echo esc_html(date('H:i - jS \o\f F Y', strtotime($match['begin_at']))); ?>
            </div>

            <!-- Player Stats Button -->
            <div class="stats-button-container">
                <button class="stats-button" onclick="showAllPlayerStats()">
                    See all player stats
                </button>
            </div>

            <!-- Teams Head to Head Section -->
            <div class="teams-container">
                <?php
                // Helper function to extract team data safely
                function get_team_data($match, $index) {
                    $team = $match['opponents'][$index]['opponent'] ?? null;
                    $score = isset($match['results'][$index]) ? ($match['results'][$index]['score'] ?? 0) : 0;
                    return [
                        'team' => $team,
                        'score' => $score,
                        'name' => $team['name'] ?? 'TBD',
                        'image_url' => $team['image_url'] ?? '',
                        'players' => $team['players'] ?? []
                    ];
                }

                // Get match status and determine if it's live
                $match_status = strtolower($match['status'] ?? '');
                $is_live = in_array($match_status, ['running', 'inprogress', 'in_progress']);
                
                // Extract team data
                $team1_data = get_team_data($match, 0);
                $team2_data = get_team_data($match, 1);
                $team1 = $team1_data['team'];
                $team2 = $team2_data['team'];
                $team1_score = $team1_data['score'];
                $team2_score = $team2_data['score'];

                // Add data attributes for live updates if match is live
                $match_attrs = $is_live ? ' data-match-id="' . esc_attr($match['id']) . '" data-is-live="true"' : '';
                ?>

                <div class="team-section team1">
                    <?php if ($team1): ?>
                        <div class="team-logo">
                            <img src="<?php echo esc_url($team1['image_url']); ?>" 
                                 alt="<?php echo esc_attr($team1['name']); ?>">
                        </div>
                        <div class="team-name"><?php echo esc_html($team1['name']); ?></div>
                        <?php
                        // Display player rosters if available
                        if (!empty($team1['players'])):
                            foreach ($team1['players'] as $player):
                                ?>
                                <div class="player-card">
                                    <div class="player-info">
                                        <img src="<?php echo esc_url($player['image_url'] ?? ''); ?>" 
                                             alt="<?php echo esc_attr($player['name']); ?>" 
                                             class="player-avatar">
                                        <div class="player-name"><?php echo esc_html($player['name']); ?></div>
                                        <div class="most-played">
                                            <?php
                                            if (!empty($player['champions'])):
                                                foreach ($player['champions'] as $champion):
                                                    ?>
                                                    <img src="<?php echo esc_url($champion['image_url']); ?>" 
                                                         alt="<?php echo esc_attr($champion['name']); ?>" 
                                                         class="champion-icon">
                                                    <?php
                                                endforeach;
                                            endif;
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            endforeach;
                        endif;
                        ?>
                    <?php endif; ?>
                </div>

                <div class="versus-section"<?php echo $match_attrs; ?>>
                    <div class="versus-text">VS</div>
                    <div class="score-container">
                        <div class="score" data-team-id="<?php echo esc_attr($team1['id'] ?? ''); ?>"><?php echo intval($team1_score); ?></div>
                        <div class="score-divider">-</div>
                        <div class="score" data-team-id="<?php echo esc_attr($team2['id'] ?? ''); ?>"><?php echo intval($team2_score); ?></div>
                    </div>
                    <div class="match-status <?php echo $is_live ? 'live' : esc_attr($match_status); ?>">
                        <?php if ($is_live): ?>
                            <span class="live-indicator"></span>
                        <?php endif; ?>
                        <?php echo esc_html(ucfirst($match_status)); ?>
                    </div>
                </div>

                <div class="team-section team2">
                    <?php if ($team2): ?>
                        <div class="team-logo">
                            <img src="<?php echo esc_url($team2['image_url']); ?>" 
                                 alt="<?php echo esc_attr($team2['name']); ?>">
                        </div>
                        <div class="team-name"><?php echo esc_html($team2['name']); ?></div>
                        <?php
                        // Display player rosters if available
                        if (!empty($team2['players'])):
                            foreach ($team2['players'] as $player):
                                ?>
                                <div class="player-card">
                                    <div class="player-info">
                                        <img src="<?php echo esc_url($player['image_url'] ?? ''); ?>" 
                                             alt="<?php echo esc_attr($player['name']); ?>" 
                                             class="player-avatar">
                                        <div class="player-name"><?php echo esc_html($player['name']); ?></div>
                                        <div class="most-played">
                                            <?php
                                            if (!empty($player['champions'])):
                                                foreach ($player['champions'] as $champion):
                                                    ?>
                                                    <img src="<?php echo esc_url($champion['image_url']); ?>" 
                                                         alt="<?php echo esc_attr($champion['name']); ?>" 
                                                         class="champion-icon">
                                                    <?php
                                                endforeach;
                                            endif;
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            endforeach;
                        endif;
                        ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Head to Head Section -->
            <div class="head-to-head-section">
                <h2>HEAD TO HEAD</h2>
                <div class="head-to-head-stats">
                    <div class="team-stats team1-stats">
                        <div class="team-name"><?php echo esc_html($team1['name'] ?? ''); ?></div>
                        <div class="wins"><?php echo isset($match['games_won_team1']) ? esc_html($match['games_won_team1']) : '0'; ?> wins</div>
                    </div>
                    <div class="team-stats team2-stats">
                        <div class="team-name"><?php echo esc_html($team2['name'] ?? ''); ?></div>
                        <div class="wins"><?php echo isset($match['games_won_team2']) ? esc_html($match['games_won_team2']) : '0'; ?> wins</div>
                    </div>
                </div>

                <!-- Previous Matches -->
                <?php if (!empty($match['previous_matches'])): ?>
                    <div class="previous-matches">
                        <?php foreach ($match['previous_matches'] as $prev_match): ?>
                            <div class="previous-match">
                                <div class="match-date"><?php echo esc_html(date('d/m/Y', strtotime($prev_match['begin_at']))); ?></div>
                                <div class="match-teams">
                                    <span class="team1"><?php echo esc_html($prev_match['team1_name']); ?></span>
                                    <span class="score"><?php echo esc_html($prev_match['score1']); ?> - <?php echo esc_html($prev_match['score2']); ?></span>
                                    <span class="team2"><?php echo esc_html($prev_match['team2_name']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($match['streams_list'])): ?>
                <div class="streams-section">
                    <h3>Watch Live</h3>
                    <div class="streams-list">
                        <?php foreach ($match['streams_list'] as $stream): ?>
                            <?php if (!empty($stream['raw_url'])): ?>
                                <a href="<?php echo esc_url($stream['raw_url']); ?>" 
                                   target="_blank" 
                                   class="stream-link <?php echo $stream['official'] ? 'official' : ''; ?>">
                                    <?php echo esc_html(strtoupper($stream['language'])); ?>
                                    <?php if ($stream['official']): ?>
                                        <span class="official-tag">Official</span>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($error_message ?: 'Match not found.'); ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Add CSS for the new layout -->
<style>
.dark-theme {
    background-color: #0D0D0D;
    color: #FFFFFF;
    padding: 20px;
}

.match-header {
    max-width: 1200px;
    margin: 0 auto;
}

.match-time {
    text-align: center;
    font-size: 1.2em;
    margin-bottom: 20px;
}

.stats-button-container {
    text-align: center;
    margin-bottom: 30px;
}

.stats-button {
    background-color: transparent;
    color: #FFD700;
    border: 1px solid #FFD700;
    padding: 10px 30px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.stats-button:hover {
    background-color: rgba(255, 215, 0, 0.1);
}

.teams-container {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 40px;
    gap: 20px;
}

.team-section {
    flex: 1;
    max-width: 45%;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    padding: 20px;
}

.team-logo {
    text-align: center;
}

.team-logo img {
    max-width: 150px;
    height: auto;
}

.team-name {
    font-size: 1.5em;
    margin: 15px 0;
    text-align: center;
    font-weight: 600;
}

.versus-section {
    text-align: center;
    align-self: center;
}

.versus-text {
    font-size: 2em;
    margin: 10px 0;
    color: #FFD700;
    font-weight: bold;
}

.score-container {
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 2.5em;
    margin: 10px 0;
    font-weight: bold;
}

.score-divider {
    margin: 0 10px;
    color: #666;
}

.player-card {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 15px;
    margin: 10px 0;
    transition: transform 0.2s ease;
    cursor: pointer;
}

.player-card:hover {
    transform: translateY(-2px);
    background: rgba(255, 255, 255, 0.15);
}

.player-info {
    display: flex;
    align-items: center;
}

.player-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    margin-right: 15px;
    object-fit: cover;
}

.player-name {
    font-size: 1.1em;
    font-weight: 500;
}

.most-played {
    display: flex;
    margin-left: auto;
    gap: 5px;
}

.champion-icon {
    width: 30px;
    height: 30px;
    border-radius: 5px;
    transition: transform 0.2s ease;
}

.champion-icon:hover {
    transform: scale(1.1);
}

.head-to-head-section {
    margin-top: 40px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
}

.head-to-head-section h2 {
    text-align: center;
    color: #FFD700;
    margin-bottom: 20px;
}

.head-to-head-stats {
    display: flex;
    justify-content: space-between;
    margin: 20px 0;
    padding: 20px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 10px;
}

.team-stats {
    text-align: center;
}

.wins {
    color: #FFD700;
    font-size: 1.2em;
    margin-top: 10px;
    font-weight: 600;
}

.previous-matches {
    margin-top: 20px;
}

.previous-match {
    display: flex;
    justify-content: space-between;
    padding: 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    transition: background-color 0.2s ease;
}

.previous-match:hover {
    background: rgba(255, 255, 255, 0.05);
}

.match-date {
    color: #999;
}

.match-teams {
    display: flex;
    gap: 20px;
    align-items: center;
}

.match-teams .score {
    font-weight: bold;
    color: #FFD700;
}

.streams-section {
    margin-top: 30px;
}

.streams-section h3 {
    color: #FFD700;
    margin-bottom: 15px;
}

.streams-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.stream-link {
    background: rgba(255, 255, 255, 0.1);
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    color: #FFFFFF;
    transition: all 0.2s ease;
}

.stream-link:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-1px);
}

.stream-link.official {
    background: rgba(255, 215, 0, 0.2);
}

.official-tag {
    background: #FFD700;
    color: #000000;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    margin-left: 5px;
}

.pandascore-debug {
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    font-family: monospace;
}

/* Responsive Design */
@media (max-width: 768px) {
    .teams-container {
        flex-direction: column;
        align-items: center;
    }
    
    .team-section {
        max-width: 100%;
        margin-bottom: 20px;
    }
    
    .versus-section {
        margin: 20px 0;
    }
    
    .score-container {
        font-size: 2em;
    }
    
    .head-to-head-stats {
        flex-direction: column;
        gap: 20px;
    }
}
</style>

<script>
function showAllPlayerStats() {
    // Add player stats functionality here
    console.log('Showing player stats...');
    // This could trigger a modal or navigate to a stats page
}
</script>

<?php
if (function_exists('get_footer')) {
    get_footer();
}
?>