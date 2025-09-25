# Live Tracker: Caching and Polling Logic

This document explains the caching and polling mechanisms used in the `live-tracker.js` file of the PandaScore Tracker plugin.

---

## Overview

The live tracker is responsible for real-time updates of match scores and details using a combination of WebSocket connections and HTTP polling as a fallback. This ensures users always see the latest data, even if the live feed is interrupted.

---

## Caching Logic

- **Purpose:**
  - Prevent unnecessary DOM updates and reduce flicker.
  - Avoid redundant API calls for unchanged data.
- **Implementation:**
  - Uses a `lastResults` Map to store the most recent data for each match by match ID.
  - Before updating the DOM, the script compares the new data with the cached data. If there is no change, the update is skipped.
  - This logic is found in the `updateDomWithMatchData` function.

---

## Polling Logic

- **Purpose:**
  - Provide a fallback for real-time updates if WebSocket connections fail or are unavailable.
  - Ensure live data is refreshed at regular intervals.
- **Implementation:**
  - When a WebSocket connection cannot be established or is lost, the script switches to polling mode for that match.
  - Polling is performed using `setInterval`, fetching match data every 3-5 seconds (configurable).
  - The polling interval is aggressive for live matches to keep data fresh.
  - Polling is stopped if a WebSocket connection is re-established or the match is no longer live.
  - This logic is managed by the `startPollingFallback` and `stopPollingFallback` functions.

---

## WebSocket and Polling Fallback Flow

1. **Attempt WebSocket connection** for each live match.
2. **On connection failure or closure** (with certain error codes), switch to polling mode.
3. **Continue polling** until a WebSocket connection is restored or the match ends.
4. **Cache results** to avoid unnecessary DOM updates and API calls.

---

## Best Practices
- Use caching to minimize DOM and network activity.
- Always provide a fallback (polling) for real-time features.
- Clean up intervals and connections to avoid memory leaks.
- Compare new and old data before updating the UI.

---

For more details, see the code in `js/live-tracker.js` and the main plugin documentation in `.qodo`.
