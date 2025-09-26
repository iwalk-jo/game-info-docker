// PandaScore Live Updates for Match Details Page
// This script connects to the PandaScore WebSocket API for a specific match and updates the DOM in real time.
(function(){
  // Get match ID from a data attribute or global JS var (set in PHP template)
  var matchId = window.pandascoreMatchId || null;
  var apiKey = window.pandascoreApiKey || null;
  var errorEl = null;
  function showError(msg) {
    if (!errorEl) errorEl = document.getElementById('pandascore-error-message');
    if (errorEl) errorEl.textContent = msg;
  }
  if (!matchId || !apiKey) {
    showError('Missing match ID or API key. Live updates unavailable.');
    return;
  }

  var wsUrl = 'wss://live.pandascore.co/matches/' + matchId + '?token=' + encodeURIComponent(apiKey);
  var ws;
  var reconnectAttempts = 0;
  var maxReconnects = 5;
  function connectWS() {
    ws = new WebSocket(wsUrl);
    ws.onopen = function() {
      reconnectAttempts = 0;
      showError('');
      console.log('[PandaScore] WebSocket connected for match', matchId);
    };
    ws.onmessage = function(event) {
      var data = {};
      try { data = JSON.parse(event.data); } catch (e) { showError('Received malformed data from server.'); return; }
      // Update live status, scores, and events timeline
      if (data && data.type) {
        if (data.type === 'update' || data.type === 'frame') {
          updateLiveStatus(data.payload || data);
        } else if (data.type === 'event') {
          addEventToTimeline(data.payload || data);
        }
      }
    };
    ws.onerror = function(e) {
      showError('WebSocket error. Live updates may be unavailable.');
      console.warn('[PandaScore] WebSocket error', e);
    };
  ws.onclose = function() {
      if (reconnectAttempts < maxReconnects) {
        reconnectAttempts++;
        showError('Connection lost. Reconnecting... ('+reconnectAttempts+')');
        setTimeout(connectWS, 1000 * reconnectAttempts);
      } else {
        showError('Live updates connection failed. Please refresh the page.');
      }
      console.log('[PandaScore] WebSocket closed for match', matchId);
    };
  }
  connectWS();

  // ...existing code moved into connectWS above...

  function updateLiveStatus(payload) {
    // Example: update score, status, etc.
    var el = document.getElementById('pandascore-live-updates');
    if (!el) return;
    var html = '';
    if (payload.status) html += '<div><strong>Status:</strong> ' + payload.status + '</div>';
    if (payload.results && Array.isArray(payload.results)) {
      html += '<div><strong>Scores:</strong> ' + payload.results.map(function(r){return r.score;}).join(' - ') + '</div>';
    }
    el.innerHTML = html;
  }

  function addEventToTimeline(payload) {
    var el = document.getElementById('pandascore-events-timeline');
    if (!el) return;
    var html = el.innerHTML;
    html += '<div class="pandascore-event">' + (payload.type ? '<strong>' + payload.type + ':</strong> ' : '') + (payload.description || JSON.stringify(payload)) + '</div>';
    el.innerHTML = html;
  }
})();
