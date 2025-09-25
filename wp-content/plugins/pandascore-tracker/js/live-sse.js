(function(){
  function patchScores(root, data){
    (data||[]).filter(m=>m.status==='running').forEach(m => {
      const card = root.querySelector('.pandascore-match[data-match-id="'+m.id+'"]');
      if (!card) return;
      const scores = m.scores || [];
      const scoreA = scores[0]||0, scoreB = scores[1]||0;
      const nodes = card.querySelectorAll('.pandascore-score');
      if (nodes[0]) nodes[0].textContent = scoreA;
      if (nodes[1]) nodes[1].textContent = scoreB;
    });
  }

  async function fetchMatches(endpoint, params){
    const url = new URL(endpoint, window.location.origin);
    Object.entries(params).forEach(([k,v])=>{ if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v); });
    const res = await fetch(url.toString(), { cache: 'no-store' });
    if (!res.ok) throw new Error('Fetch failed');
    return res.json();
  }

  function startPollingFallback(root){
    const endpoint = root.getAttribute('data-endpoint');
    const game = root.getAttribute('data-game')||'lol';
    const per_page = parseInt(root.getAttribute('data-per-page')||'50',10) || 50;
    const interval = parseInt(root.getAttribute('data-poll-interval')||'12000',10) || 12000;
    if (!endpoint) return;
    async function poll(){
      try{
        const json = await fetchMatches(endpoint, { game, type:'live', per_page });
        patchScores(root, json.data||[]);
      }catch(e){/* ignore */}
    }
    poll();
    return setInterval(poll, interval);
  }

  function init(root){
    const sseUrl = root.getAttribute('data-sse-url');
    const type = root.getAttribute('data-type')||'upcoming';
    if (!sseUrl || (type!=='live' && type!=='mixed')) return;

    let fallbackTimer = null;
    let es;
    try {
      es = new EventSource(sseUrl);
    } catch(e) {
      fallbackTimer = startPollingFallback(root);
      return;
    }

    es.addEventListener('update', function(ev){
      try {
        const payload = JSON.parse(ev.data);
        patchScores(root, payload.data||[]);
      } catch(e) { /* ignore */ }
    });

    es.addEventListener('error', function(){
      if (es.readyState === EventSource.CLOSED && !fallbackTimer) {
        fallbackTimer = startPollingFallback(root);
      }
    });

    es.addEventListener('end', function(){
      // server ended stream; browser will attempt reconnect; fallback handled in error
    });
  }

  function boot(){
    document.querySelectorAll('.pandascore-tracker').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
