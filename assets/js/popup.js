/**
 * EWEB - MC Video Popup
 * Version: 1.1.3
 * 
 * DESCRIPCIÓN:
 * Sistema de popup de video de YouTube con reproducción automática.
 * Incluye sistema de persistencia para no mostrar repetidamente.
 * 
 * FUNCIONALIDADES PRINCIPALES:
 * - Autoplay de video de YouTube (silenciado por defecto)
 * - Selección automática de idioma (ES/EN)
 * - Sistema de persistencia (localStorage + cookies)
 * - Control de visibilidad mediante clase CSS
 * - Cierre con botón X (opcionalmente abre URL)
 * - Debugging completo en consola
 * 
 * PARÁMETROS URL:
 * - ?mcvp=1 : Fuerza mostrar el popup (útil para pruebas)
 * - ?mcvp=0 : Suprime el popup completamente
 * 
 * CONSOLA DEBUG:
 * Todos los mensajes empiezan con [MCVP] para fácil filtrado
 */

(()=>{
    // Clave para localStorage y cookies
    const KEY = 'mcvp_last_time';
  
    /**
     * Helper para debug - solo muestra logs si debugMode está activo
     * @param {...any} args - Argumentos para console.log
     */
    function debug(...args) {
      if (window.MCVideoPopupCFG?.debugMode) {
        console.log(...args);
      }
    }
  
    /**
     * Verifica si la URL tiene el parámetro ?mcvp=1 para forzar mostrar
     * @returns {boolean} true si debe forzar mostrar
     */
    function urlHasForce(){
      try{ return new URLSearchParams(location.search).get('mcvp') === '1'; }catch(_){ return false; }
    }
    
    /**
     * Verifica si la URL tiene el parámetro ?mcvp=0 para suprimir
     * @returns {boolean} true si debe suprimir
     */
    function urlHasSuppress(){
      try{ return new URLSearchParams(location.search).get('mcvp') === '0'; }catch(_){ return false; }
    }
  
    /**
     * Selecciona la URL del video según el idioma del usuario
     * @param {HTMLElement} el - Elemento con data-video-es y data-video-en
     * @returns {string} URL del video seleccionado
     * 
     * LÓGICA:
     * 1. Si modo='site': usa idioma del sitio (html lang)
     * 2. Si modo='browser': usa idioma del navegador
     * 3. Prioriza español si detecta 'es', sino inglés
     * 4. Si no hay video en el idioma detectado, usa el que esté disponible
     */
    function pickVideoURL(el){
      const mode = (window.MCVideoPopupCFG?.languageMode || 'browser');
      const nav = (navigator.language || navigator.userLanguage || '').toLowerCase();
      const htmlLang = (document.documentElement.lang || '').toLowerCase();
      const useES = (mode === 'site') ? htmlLang.startsWith('es') : nav.startsWith('es');
      const es = el.getAttribute('data-video-es') || '';
      const en = el.getAttribute('data-video-en') || '';
      if (useES && es) return es;
      if (en) return en;
      return es || en;
    }
  
    /**
     * Extrae el ID del video de una URL de YouTube
     * @param {string} url - URL de YouTube
     * @returns {string} ID del video (ej: 'dQw4w9WgXcQ')
     * 
     * FORMATOS SOPORTADOS:
     * - https://www.youtube.com/watch?v=ABC123
     * - https://youtu.be/ABC123
     * - https://www.youtube.com/embed/ABC123
     */
    function ytIdFromUrl(url){
      if(!url) return '';
      try{
        const u = new URL(url);
        if(u.hostname.includes('youtu.be')) return u.pathname.replace('/','');
        if(u.searchParams.get('v')) return u.searchParams.get('v');
        const m = url.match(/embed\/([a-zA-Z0-9_-]{6,})/);
        return m? m[1] : '';
      }catch(e){ return ''; }
    }
  
    /**
     * Lee el timestamp de cuándo se cerró el popup por última vez
     * @returns {number} Timestamp en milisegundos (0 si nunca se ha cerrado)
     * 
     * ESTRATEGIA DUAL:
     * 1. Intenta leer de localStorage (preferido)
     * 2. Si falla, intenta leer de cookies (fallback)
     * 3. Si ambos fallan, retorna 0 (nunca mostrado)
     */
    function readStoredTimestamp(){
      let raw = '';
      try{ raw = localStorage.getItem(KEY) || ''; }catch(_){}
      if(!raw){
        const match = document.cookie.match(new RegExp('(?:^|; )' + KEY + '=([^;]+)'));
        raw = match ? decodeURIComponent(match[1]) : '';
      }
      const parsed = parseInt(raw, 10);
      return Number.isFinite(parsed) ? parsed : 0;
    }
  
    function writeStoredTimestamp(value){
      const str = String(value);
      try{ 
        localStorage.setItem(KEY, str); 
        debug('[MCVP] Saved to localStorage:', str);
      }catch(e){ 
        console.warn('[MCVP] localStorage failed:', e);
      }
      const maxAge = 365 * 24 * 3600;
      document.cookie = KEY + '=' + encodeURIComponent(str) + '; path=/; max-age=' + maxAge;
      debug('[MCVP] Saved to cookie:', str);
    }
  
    function shouldShow(){
      const cfg = window.MCVideoPopupCFG || {showOnce:true, hours:24};
      if(urlHasSuppress()) {
        debug('[MCVP] Suppressed by ?mcvp=0');
        return false;
      }
      if(urlHasForce()) {
        debug('[MCVP] Forced by ?mcvp=1');
        return true;
      }
      if(!cfg.showOnce) {
        debug('[MCVP] showOnce disabled, always show');
        return true;
      }
      try{
        const last = readStoredTimestamp();
        debug('[MCVP] Last shown timestamp:', last, new Date(last));
        if(!last) {
          debug('[MCVP] Never shown before');
          return true;
        }
        const ms = cfg.hours * 3600 * 1000;
        const elapsed = Date.now() - last;
        const shouldShow = elapsed > ms;
        debug('[MCVP] Hours configured:', cfg.hours, 'Elapsed hours:', (elapsed / 3600000).toFixed(2), 'Should show:', shouldShow);
        return shouldShow;
      }catch(e){ 
        console.error('[MCVP] Error in shouldShow:', e);
        return true; 
      }
    }
  
    function markShown(){
      const now = Date.now();
      debug('[MCVP] Marking as shown at:', new Date(now));
      try{ writeStoredTimestamp(now); }catch(e){ console.error('[MCVP] Failed to mark shown:', e); }
    }
  
    function createYT(playerId, videoId){
      const cfg = window.MCVideoPopupCFG || {start:0, mute:true, controls:true};
      debug('[MCVP] Creating YouTube player with config:', cfg);
      return new YT.Player(playerId, {
        videoId,
        playerVars: {
          autoplay: 1,
          controls: cfg.controls ? 1 : 0,
          mute: cfg.mute ? 1 : 0,
          rel: 0,
          playsinline: 1,
          start: cfg.start || 0,
          enablejsapi: 1,
          origin: window.location.origin,
          modestbranding: 1,
          iv_load_policy: 3
        },
        events: {
          onReady: (ev)=>{ 
            debug('[MCVP] YouTube player ready');
            try{ ev.target.playVideo(); }catch(e){ console.error('[MCVP] Autoplay failed:', e); } 
          },
          onError: (ev)=>{
            console.error('[MCVP] YouTube player error:', ev.data);
          }
        }
      });
    }
  
    function ensureYTAPI(cb){
      if(window.YT && window.YT.Player){ 
        debug('[MCVP] YouTube API already loaded');
        cb(); 
        return; 
      }
      debug('[MCVP] Loading YouTube API...');
      const s = document.createElement('script');
      s.src = 'https://www.youtube.com/iframe_api';
      document.head.appendChild(s);
      window.onYouTubeIframeAPIReady = ()=> {
        debug('[MCVP] YouTube API ready callback fired');
        cb();
      };
    }
  
    function openTargetNewTab(url){
      try{ window.open(url, '_blank', 'noopener'); }catch(_){ location.assign(url); }
    }
  
    document.addEventListener('DOMContentLoaded', ()=>{
      debug('[MCVP] DOM loaded, initializing...');
      debug('[MCVP] Config:', window.MCVideoPopupCFG);
      
      const wrap = document.querySelector('.mcvp-wrap');
      if(!wrap) {
        console.warn('[MCVP] Popup wrapper not found in DOM');
        return;
      }
      
      if(!shouldShow()) {
        debug('[MCVP] Popup will not be shown');
        return;
      }
  
      // Mostrar popup y bloquear scroll
      debug('[MCVP] Showing popup and locking scroll...');
      wrap.classList.add('mcvp-active');
      document.documentElement.classList.add('mcvp-lock');
      document.body.classList.add('mcvp-lock');
  
      const target = wrap.getAttribute('data-target') || 'https://marschallenge.space';
      const url = pickVideoURL(wrap);
      debug('[MCVP] Selected video URL:', url);
      
      const vid = ytIdFromUrl(url);
      debug('[MCVP] Extracted video ID:', vid);
      
      if(!vid) {
        console.error('[MCVP] Failed to extract video ID from URL:', url);
        console.error('[MCVP] Please check that the video URL is a valid YouTube link');
        // Still show popup but with error message
        const slot = document.getElementById('mcvp-player-slot');
        if(slot) {
          slot.innerHTML = '<div style="color:white;padding:2rem;text-align:center;">Error: Invalid video URL</div>';
        }
        return;
      }
      
      const slotId = 'mcvp-player-slot';
  
      ensureYTAPI(()=>{
        try {
          const player = createYT(slotId, vid);
          debug('[MCVP] Player created successfully');
    
          const toplink = document.querySelector('.mcvp-toplink');
          if(toplink && window.MCVideoPopupCFG?.toplinkEnabled){
            toplink.classList.add('is-on');
            debug('[MCVP] Top link enabled');
          }
    
          // Botón unmute
          const unbtn = document.querySelector('.mcvp-unmute');
          if(unbtn){
            unbtn.addEventListener('click', (ev)=>{
              ev.preventDefault();
              ev.stopPropagation();
              debug('[MCVP] Unmuting video...');
              try{ 
                player.unMute(); 
                player.setVolume(100);
                player.playVideo(); // Force play to prevent pausing
              }catch(e){ console.error('[MCVP] Unmute failed:', e); }
              unbtn.style.display = 'none'; // Hide instead of remove to prevent click fall-through
            });
          }
    
          // Cerrar SOLO con X
          const x = document.querySelector('.mcvp-close');
          if(x){
            x.addEventListener('click', ()=>{
              debug('[MCVP] Close button clicked');
              
              if(window.MCVideoPopupCFG?.openOnClose){ 
                debug('[MCVP] Opening target URL in new tab:', target);
                openTargetNewTab(target); 
              }
              
              markShown();
              
              debug('[MCVP] Removing scroll lock and popup...');
              wrap.classList.remove('mcvp-active');
              document.documentElement.classList.remove('mcvp-lock');
              document.body.classList.remove('mcvp-lock');
              wrap.remove();
              
              debug('[MCVP] Popup closed successfully');
            });
          }
    
          // Deshabilitar cierre por click en overlay
          const ov = document.querySelector('.mcvp-overlay');
          if(ov){ 
            ov.addEventListener('click', (e)=> { 
              e.preventDefault(); 
              debug('[MCVP] Overlay clicked (popup will not close)');
            }); 
          }
        } catch(error) {
          console.error('[MCVP] Error creating YouTube player:', error);
        }
      });
    });
  })();
