document.addEventListener('DOMContentLoaded', function () {
  const filters = document.querySelectorAll('.pandascore-league-filter');
  const matches = document.querySelectorAll('.pandascore-match');
  const specificLeagues = ['LCK', 'LPL', 'LEC', 'LTA', 'LTA South'];
  const MAX_DISPLAY = 8;

  function initializeDefaultState() {
    matches.forEach((match) => {
      const matchLeague = match.querySelector('.pandascore-league-container img');
      if (!matchLeague) {
        const placeholder = match.querySelector('.pandascore-league-placeholder');
        if (placeholder) {
          match.style.display = 'none';
        }
        return;
      }
      const matchLeagueName = matchLeague.alt;
      if (specificLeagues.includes(matchLeagueName)) {
        match.style.display = 'flex';
      } else {
        match.style.display = 'none';
      }
    });
    filters.forEach((f) => f.classList.remove('active'));
  }

  function showMainLeaguesMatches() {
    matches.forEach((match) => {
      const matchLeague = match.querySelector('.pandascore-league-container img');
      if (!matchLeague) {
        match.style.display = 'none';
        return;
      }
      const matchLeagueName = matchLeague.alt;
      if (specificLeagues.includes(matchLeagueName)) {
        match.style.display = 'flex';
      } else {
        match.style.display = 'none';
      }
    });
    enforceDisplayLimit();
    updateContainerVisibility();
  }

  function filterByLeague(selectedLeague) {
    matches.forEach((match) => {
      const matchLeague = match.querySelector('.pandascore-league-container img');
      if (!matchLeague) {
        match.style.display = 'none';
        return;
      }
      const matchLeagueName = matchLeague.alt;
      if (selectedLeague === 'OTHER LEAGUES') {
        if (specificLeagues.includes(matchLeagueName)) {
          match.style.display = 'none';
        } else {
          match.style.display = 'flex';
        }
      } else {
        if (matchLeagueName === selectedLeague) {
          match.style.display = 'flex';
        } else {
          match.style.display = 'none';
        }
      }
    });
    enforceDisplayLimit();
    updateContainerVisibility();
  }

  function updateContainerVisibility() {
    const liveContainer = document.querySelector('.pandascore-live-container');
    const upcomingContainer = document.querySelector('.pandascore-upcoming-container');
    if (liveContainer) {
      const liveMatches = liveContainer.querySelectorAll('.pandascore-match');
      const visibleLiveMatches = Array.from(liveMatches).filter((match) => match.style.display !== 'none');
      liveContainer.style.display = visibleLiveMatches.length > 0 ? 'block' : 'none';
    }
    if (upcomingContainer) {
      const upcomingMatches = upcomingContainer.querySelectorAll('.pandascore-match');
      const visibleUpcomingMatches = Array.from(upcomingMatches).filter((match) => match.style.display !== 'none');
      upcomingContainer.style.display = visibleUpcomingMatches.length > 0 ? 'block' : 'none';
    }
  }

  function enforceDisplayLimit() {
    const applyCap = (selector) => {
      const container = document.querySelector(selector);
      if (!container) return;
      const matches = Array.from(container.querySelectorAll('.pandascore-match'));
      const visible = matches.filter((m) => m.style.display !== 'none');
      visible.forEach((m, idx) => {
        m.style.display = idx < MAX_DISPLAY ? 'flex' : 'none';
      });
    };
    applyCap('.pandascore-live-container');
    applyCap('.pandascore-upcoming-container');
  }

  // Add click event listeners to filters
  filters.forEach((filter) => {
    filter.addEventListener('click', () => {
      const selectedLeague = filter.getAttribute('data-league-name')
      const isCurrentlyActive = filter.classList.contains('active')

      if (isCurrentlyActive) {
        // Toggle OFF: Return to default state (show main 5 leagues)
        filters.forEach((f) => f.classList.remove('active'));
        showMainLeaguesMatches();
      } else {
        // Toggle ON: Filter by selected league
        filters.forEach((f) => f.classList.remove('active'));
        filter.classList.add('active');
        filterByLeague(selectedLeague);
      }
    });
  });

  // Add click-to-detail navigation to each match card
  matches.forEach((match) => {
    const matchId = match.getAttribute('data-match-id');
    if (matchId) {
      match.style.cursor = 'pointer';
      match.addEventListener('click', (e) => {
        if (e.target.closest('button, a')) return;
        window.location.href = window.location.pathname + '?pandascore_match_id=' + encodeURIComponent(matchId);
      });
    }
  });

  initializeDefaultState();
  enforceDisplayLimit();
  updateContainerVisibility();
});
