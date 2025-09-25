(function(){
  async function fetchMatches(endpoint, params){
    const url = new URL(endpoint, window.location.origin);
    Object.entries(params).forEach(([k,v])=>{ if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v); });
    const res = await fetch(url.toString(), { cache: 'no-store' });
    if (!res.ok) throw new Error('Fetch failed');
    return res.json();
  }
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
  function init(root){
    const endpoint = root.getAttribute('data-endpoint');
    const game = root.getAttribute('data-game')||'lol';
    const type = root.getAttribute('data-type')||'upcoming';
    const per_page = parseInt(root.getAttribute('data-per-page')||'50',10) || 50;
    const interval = parseInt(root.getAttribute('data-poll-interval')||'12000',10) || 12000;
    if (!endpoint || (type!=='live' && type!=='mixed')) return;
    async function poll(){
      try{
        const json = await fetchMatches(endpoint, { game, type:'live', per_page });
        patchScores(root, json.data||[]);
      }catch(e){/* ignore */}
    }
    poll();
    setInterval(poll, interval);
  }
  function boot(){
    document.querySelectorAll('.pandascore-tracker').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
