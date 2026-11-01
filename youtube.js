/* Harbufeed YouTube source adapter · v0.2.0 */
(() => {
  const base = "./api/";
  const get = async path => {
    const response = await fetch(base + path, {credentials:"same-origin",cache:"no-store"});
    const json = await response.json();
    if (!response.ok) throw new Error(json.error || "YouTube is unavailable");
    return json;
  };
  let csrf = "", connected = false, subs = [], videos = [], channel = null;
  const excludedKey = "harbufeed-excluded-channels";
  const cacheKey = "harbufeed-youtube-cache-v2";
  const ttl = 15 * 60 * 1000;
  const readCache = () => { try { return JSON.parse(localStorage.getItem(cacheKey) || "null"); } catch { return null; } };
  const writeCache = () => { try { localStorage.setItem(cacheKey, JSON.stringify({at:Date.now(),subs,videos,channel})); } catch {} };
  const exclusions = () => { try { return JSON.parse(localStorage.getItem(excludedKey)||"[]"); } catch { return []; } };
  const safe = s => String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
  const bar = document.createElement("section");
  bar.className = "source";
  bar.style.margin = "0 0 24px";
  const heading = document.querySelector(".heading");
  heading.parentNode.insertBefore(bar, heading);
  function show(message="") {
    bar.innerHTML = connected
      ? '<strong>YouTube connected'+(channel?' · '+safe(channel.title):"")+'</strong> <button class="save" id="hf-manage">Manage channels ('+subs.length+')</button> <button class="save" id="hf-disconnect">Disconnect</button>'
      : '<strong>Harbufeed · YouTube</strong> <a class="button" href="./api/auth/login.php">Connect with Google</a>';
    if (message) { const note=document.createElement("p");note.textContent=message;bar.appendChild(note); }
    document.getElementById("hf-manage")?.addEventListener("click",manage);
    document.getElementById("hf-disconnect")?.addEventListener("click",disconnect);
  }
  async function disconnect() {
    try {
      const r=await fetch(base+"auth/logout.php",{method:"POST",credentials:"same-origin",headers:{"X-CSRF-Token":csrf}});
      if(!r.ok)throw Error("Disconnect failed");
      localStorage.removeItem(cacheKey);
      location.reload();
    } catch(e) { show(e.message); }
  }
  function manage() {
    const old=document.getElementById("hf-channel-list"); if(old){old.remove();return;}
    const panel=document.createElement("div");panel.id="hf-channel-list";
    panel.style.cssText="margin-top:14px;max-height:300px;overflow:auto";
    panel.innerHTML="<p>Select subscriptions to include in your feed (first 20 enabled are loaded).</p>"+
      subs.map(c=>'<label style="display:block;margin:10px 0"><input type="checkbox" data-hf-channel="'+safe(c.id)+'" '+(exclusions().includes(c.id)?"":"checked")+'> '+safe(c.title)+'</label>').join("");
    bar.appendChild(panel);
    panel.querySelectorAll("[data-hf-channel]").forEach(input=>input.addEventListener("change",async()=>{
      let list=exclusions();const id=input.dataset.hfChannel;
      list=input.checked?list.filter(x=>x!==id):[...new Set([...list,id])];
      localStorage.setItem(excludedKey,JSON.stringify(list));
      localStorage.removeItem(cacheKey);
      await loadVideos();
    }));
  }
  async function loadVideos() {
    const selected=subs.filter(c=>!exclusions().includes(c.id)).slice(0,20);
    const values=await Promise.allSettled(selected.map(c=>get("youtube/videos.php?channelId="+encodeURIComponent(c.id))));
    videos=values.flatMap(x=>x.status==="fulfilled"?x.value.items:[]);
    window.harbufeedSetLive(videos,subs);
    writeCache();
    show(values.some(x=>x.status==="rejected")?"Some channels could not be loaded.":"");
  }
  async function init() {
    try {
      const status=await get("auth/status.php");csrf=status.csrf;connected=status.connected;
      if(!connected){show();return;}
      const cached = readCache();
      if (cached && Array.isArray(cached.subs) && Array.isArray(cached.videos)) {
        subs=cached.subs; videos=cached.videos; channel=cached.channel;
        window.harbufeedSetLive(videos,subs);
        if (Date.now()-cached.at < ttl) { show("Showing cached feed"); return; }
        show("Showing cached feed · updating");
      } else show("Loading YouTube subscriptions...");
      const profile=await get("youtube/profile.php");channel=profile.channel;
      subs=[];
      let next="";let pageCount=0;
      do {
        const page=await get("youtube/subscriptions.php"+(next?"?pageToken="+encodeURIComponent(next):""));
        subs.push(...page.items);next=page.nextPageToken||"";pageCount++;
      }while(next && pageCount<30);
      await loadVideos();
    } catch(e) {show("Unable to refresh YouTube: "+e.message); if(videos.length) window.harbufeedSetLive(videos,subs);}
  }
  window.harbufeedToggleChannel=async(id,checked)=>{let list=exclusions();list=checked?list.filter(x=>x!==id):[...new Set([...list,id])];localStorage.setItem(excludedKey,JSON.stringify(list));localStorage.removeItem(cacheKey);await loadVideos();};
  show();init();
})();