document.addEventListener('DOMContentLoaded', function () {
  /**
   * PandaScore Live Tracker - Modern WebSocket Implementation
   * Handles real-time score updates with proper error handling and reconnection logic
   */

  // Configuration
  const CONFIG = {
    MAX_RETRIES: 5,
    BASE_RETRY_DELAY: 2000,
    MAX_RETRY_DELAY: 30000,
    POLL_INTERVAL: 20000,
    NON_RETRY_CODES: new Set([1000, 4001, 4003, 4029])
  }

  // Validate environment
  if (typeof pandaScoreLiveTracker === 'undefined') {
    console.error('[PandaScore] Localized data not found.')
    return
  }

  // Ensure pandaScoreLiveTracker is defined before destructuring
  const { apiKey, wsMatches } = window.pandaScoreLiveTracker || {}

  if (!apiKey || !apiKey.trim()) {
    console.error('[PandaScore] API key is missing or invalid.')
    return
  }

  if (!Array.isArray(wsMatches) || wsMatches.length === 0) {
    console.info('[PandaScore] No live matches to track.')
    return
  }

  // State management
  const connections = new Map()
  const lastResults = new Map()

  /**
   * Utility Functions
   */
  function buildWebSocketUrl(match, useFrames = false) {
    let baseUrl

    if (useFrames && match.frames_url && match.frames_url.length) {
      baseUrl = match.frames_url
    } else if (!useFrames && match.events_url && match.events_url.length) {
      baseUrl = match.events_url
    } else if (match.frames_url && match.frames_url.length) {
      baseUrl = match.frames_url
    } else {
      // Final fallback to generic frames endpoint
      baseUrl = `wss://live.pandascore.co/matches/${match.match_id}`
    }

    const separator = baseUrl.includes('?') ? '&' : '?'
    const timestamp = Date.now()
    return `${baseUrl}${separator}token=${encodeURIComponent(apiKey)}&t=${timestamp}`
  }

  async function fetchMatchResults(matchId) {
    try {
      const url = `https://api.pandascore.co/matches/${matchId}?token=${encodeURIComponent(
        apiKey
      )}`
      const response = await fetch(url, {
        headers: { Accept: 'application/json' }
      })

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const data = await response.json()
      if (data) {
        updateDomWithMatchData(matchId, data)
      }
    } catch (error) {
      console.warn(
        `[PandaScore] Failed to fetch results for match ${matchId}:`,
        error.message
      )
    }
  }

  function updateDomWithMatchData(matchId, matchData) {
    const key = String(matchId)
    const previousData = lastResults.get(key)

    // Skip update if data hasn't changed
    if (
      previousData &&
      JSON.stringify(previousData) === JSON.stringify(matchData)
    ) {
      return
    }

    lastResults.set(key, matchData)

    const matchElement = document.querySelector(
      `.pandascore-match[data-match-id='${matchId}'], .ps-card[data-match-id='${matchId}']`
    )

    if (!matchElement) {
      console.warn(`[PandaScore] Match element not found for ID: ${matchId}`)
      return
    }

    // Update scores and team information
    if (matchData.results && Array.isArray(matchData.results)) {
      updateScores(matchElement, matchData.results)
    }

    // Update team names and logos if available
    if (matchData.opponents && Array.isArray(matchData.opponents)) {
      updateTeamInfo(matchElement, matchData.opponents)
    }

    // Update win/lose states based on current scores
    updateWinLoseStates(matchElement, matchData.results)

    // Update odds with some variation for live matches
    updateOdds(matchElement)
  }

  function updateScores(matchElement, results) {
    results.forEach((result) => {
      const opponentId = result.team_id || result.opponent_id
      if (!opponentId) return

      const score = Number.isInteger(result.score)
        ? result.score
        : parseInt(result.score, 10)
      const scoreElement = matchElement.querySelector(
        `[data-opponent-id='${opponentId}']`
      )

      if (scoreElement) {
        const newScore = Number.isNaN(score) ? '-' : String(score)
        if (scoreElement.textContent !== newScore) {
          scoreElement.textContent = newScore
          scoreElement.classList.add('score-updating')

          // Add visual feedback for score changes with Tailwind classes
          scoreElement.classList.remove('bg-gray-700')
          scoreElement.classList.add('bg-green-500')
          setTimeout(() => {
            scoreElement.classList.remove('bg-green-500')
            scoreElement.classList.add('bg-gray-700')
          }, 1000)
        }
      }
    })
  }

  function updateTeamInfo(matchElement, opponents) {
    opponents.forEach((opponent, index) => {
      const teamName = (opponent.opponent && opponent.opponent.name) || opponent.name || 'NAME'
      const teamLogo = (opponent.opponent && opponent.opponent.image_url) || opponent.image_url || ''

      // Update team name
      const teamNameElements = matchElement.querySelectorAll(
        '.text-sm.font-medium.truncate.flex-1'
      )
      if (teamNameElements[index]) {
        teamNameElements[index].textContent = teamName
      }

      // Update team logo
      const teamLogoElements = matchElement.querySelectorAll(
        '.w-6.h-6.object-contain.flex-shrink-0.mr-2'
      )
      if (teamLogoElements[index] && teamLogo) {
        if (teamLogoElements[index].tagName === 'IMG') {
          teamLogoElements[index].src = teamLogo
          teamLogoElements[index].alt = teamName
        } else {
          // Replace div with img
          const img = document.createElement('img')
          img.className = 'w-6 h-6 object-contain flex-shrink-0 mr-2'
          img.src = teamLogo
          img.alt = teamName
          teamLogoElements[index].parentNode.replaceChild(
            img,
            teamLogoElements[index]
          )
        }
      }
    })
  }

  function updateOdds(matchElement) {
    const oddsElements = matchElement.querySelectorAll('.ps-odds')
    oddsElements.forEach((oddsElement) => {
      // Generate slightly varying odds for live matches to simulate real-time changes
      const baseOdds = parseFloat(oddsElement.textContent) || 2.5
      const variation = (Math.random() - 0.5) * 0.4 // ±0.2 variation
      const newOdds = Math.max(1.1, Math.min(10.0, baseOdds + variation))

      const formattedOdds = newOdds.toFixed(1)
      if (oddsElement.textContent !== formattedOdds) {
        oddsElement.textContent = formattedOdds

        // Add brief highlight for odds changes
        oddsElement.style.transition = 'color 0.3s ease'
        oddsElement.style.color = '#ffa500'
        setTimeout(() => {
          oddsElement.style.color = ''
        }, 800)
      }
    })
  }

  function updateWinLoseStates(matchElement, results) {
    if (!results || results.length < 2) return

    const scores = results.map((r) =>
      Number.isInteger(r.score) ? r.score : parseInt(r.score, 10)
    )
    const team1Win = scores[0] > scores[1]
    const team2Win = scores[1] > scores[0]

    // Update score element classes
    results.forEach((result, index) => {
      const opponentId = result.team_id || result.opponent_id
      const scoreElement = matchElement.querySelector(
        `[data-opponent-id='${opponentId}']`
      )

      if (scoreElement) {
        // Remove existing win/lose classes
        scoreElement.classList.remove('win', 'lose')

        // Add appropriate class
        if (index === 0 && team1Win) {
          scoreElement.classList.add('win')
        } else if (index === 1 && team2Win) {
          scoreElement.classList.add('win')
        } else if ((index === 0 && team2Win) || (index === 1 && team1Win)) {
          scoreElement.classList.add('lose')
        }
      }
    })
  }

  /**
   * Connection Management with Smart Fallback
   */
  function createConnection(match, attempt = 0, useFrames = false) {
    if (attempt >= CONFIG.MAX_RETRIES) {
      console.warn(
        `[PandaScore] Max retries reached for match ${match.match_id}`
      )

      // If we haven't tried polling fallback yet, enable it
      if (!match.use_polling_fallback) {
        console.info(
          `[PandaScore] Enabling polling-only mode for match ${match.match_id}`
        )
        match.use_polling_fallback = true
        startPollingFallback(match)
      }
      return
    }

    const url = buildWebSocketUrl(match, useFrames)
    let socket
    let pollTimer = null

    console.log(
      `[PandaScore] Attempting connection to match ${match.match_id} (${
        useFrames ? 'frames' : 'events'
      } endpoint)`
    )

    try {
      socket = new WebSocket(url)
    } catch (error) {
      console.error(
        `[PandaScore] Failed to create WebSocket for match ${match.match_id}:`,
        error
      )
      scheduleReconnect(match, attempt + 1, useFrames)
      return
    }

    connections.set(match.match_id, socket)

    socket.onopen = function () {
      console.log(`[PandaScore] Connected to match ${match.match_id}`)

      // Send recovery request if available
      if (match.events_url && match.game_ids && match.game_ids.length) {
        const lastGameId = match.game_ids[match.game_ids.length - 1]
        try {
          socket.send(
            JSON.stringify({
              type: 'recover',
              payload: { game_id: lastGameId }
            })
          )
        } catch (error) {
          console.warn(
            `[PandaScore] Failed to send recovery for match ${match.match_id}`
          )
        }
      }

      // Initial sync and start polling
      fetchMatchResults(match.match_id)

      // Use more frequent polling for live matches (every 5 seconds)
      const pollInterval = 5000
      pollTimer = setInterval(
        () => fetchMatchResults(match.match_id),
        pollInterval
      )
    }

    socket.onmessage = function (event) {
      try {
        const message = JSON.parse(event.data)
        if (message && message.type === 'hello') return

        // Trigger result sync for any other message
        fetchMatchResults(match.match_id)
      } catch (error) {
        // Invalid JSON - sync anyway
        fetchMatchResults(match.match_id)
      }
    }

    socket.onerror = function (error) {
      console.error(
        `[PandaScore] WebSocket error for match ${match.match_id}:`,
        error
      )
    }

    socket.onclose = function (event) {
      if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
      }

      connections.delete(match.match_id)

      // Handle specific error codes with smart fallback
      if (event && event.code === 4003) {
        console.warn(
          `[PandaScore] Events endpoint forbidden for match ${match.match_id} (code: 4003)`
        )

        if (!useFrames && match.frames_url) {
          console.info(
            `[PandaScore] Trying frames endpoint for match ${match.match_id}`
          )
          scheduleReconnect(match, 0, true) // Reset attempt count, try frames
          return
        } else {
          console.info(
            `[PandaScore] WebSocket unavailable, using polling for match ${match.match_id}`
          )
          match.use_polling_fallback = true
          startPollingFallback(match)
          return
        }
      }

      // Other non-retryable codes
      if (event && CONFIG.NON_RETRY_CODES.has(event.code)) {
        console.log(
          `[PandaScore] Connection closed for match ${match.match_id} (code: ${event.code})`
        )
        return
      }

      console.warn(
        `[PandaScore] Connection lost for match ${match.match_id}, reconnecting...`
      )
      scheduleReconnect(match, attempt + 1, useFrames)
    }
  }

  function scheduleReconnect(match, attempt, useFrames = false) {
    const delay = Math.min(
      CONFIG.MAX_RETRY_DELAY,
      CONFIG.BASE_RETRY_DELAY * Math.pow(2, attempt - 1)
    )

    setTimeout(() => createConnection(match, attempt, useFrames), delay)
  }

  /**
   * Polling Fallback for when WebSocket is unavailable
   */
  function startPollingFallback(match) {
    console.info(
      `[PandaScore] Starting polling fallback for match ${match.match_id}`
    )

    // Initial fetch
    fetchMatchResults(match.match_id)

    // Set up aggressive polling for live matches (every 3 seconds)
    const pollInterval = setInterval(() => {
      fetchMatchResults(match.match_id)
    }, 3000) // Very frequent updates for live matches

    connections.set(match.match_id, { type: 'polling', timer: pollInterval })
  }


  // Initialize connections for all live matches
  wsMatches.forEach((match) => createConnection(match))
})
