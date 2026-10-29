(() => {
    'use strict';
    function init(scope=document){scope.querySelectorAll('[data-lpm-carousel]:not([data-ready])').forEach(g=>{
        g.dataset.ready='1';const slides=[...g.querySelectorAll('.lpm-slide')],controls=g.querySelector('.lpm-controls');if(!slides.length||!controls)return;
        controls.hidden=false;let index=0,timer=null,userPaused=false,hover=false,focused=false;const reduced=matchMedia('(prefers-reduced-motion: reduce)'),pause=g.querySelector('[data-pause]'),position=g.querySelector('[data-position]');
        function show(n){index=(n+slides.length)%slides.length;slides.forEach((s,i)=>{s.hidden=i!==index;});position.textContent=(index+1)+' / '+slides.length;}
        function schedule(){clearInterval(timer);timer=null;const active=g.dataset.autoplay==='1'&&!userPaused&&!hover&&!focused&&!reduced.matches&&!document.hidden;
            position.setAttribute('aria-live',active?'off':'polite');if(pause)pause.textContent=userPaused?'Putar':'Pause';
            if(active)timer=setInterval(()=>show(index+1),Math.max(5,Number(g.dataset.interval))*1000);
        }
        function manual(n){userPaused=true;show(n);schedule();}
        g.querySelector('[data-prev]').onclick=()=>manual(index-1);g.querySelector('[data-next]').onclick=()=>manual(index+1);
        if(pause)pause.onclick=()=>{userPaused=!userPaused;schedule();};
        g.addEventListener('keydown',e=>{if(['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName))return;if(e.key==='ArrowRight'){e.preventDefault();manual(index+1);}if(e.key==='ArrowLeft'){e.preventDefault();manual(index-1);}});
        g.addEventListener('mouseenter',()=>{hover=true;schedule();});g.addEventListener('mouseleave',()=>{hover=false;schedule();});
        g.addEventListener('focusin',()=>{focused=true;schedule();});g.addEventListener('focusout',()=>{setTimeout(()=>{focused=g.contains(document.activeElement);schedule();},0);});
        let start=null;g.addEventListener('touchstart',e=>{start=e.touches.length===1?[e.touches[0].clientX,e.touches[0].clientY]:null;},{passive:true});
        g.addEventListener('touchend',e=>{if(!start||!e.changedTouches.length)return;const dx=e.changedTouches[0].clientX-start[0],dy=e.changedTouches[0].clientY-start[1];if(Math.abs(dx)>50&&Math.abs(dx)>Math.abs(dy))manual(index+(dx<0?1:-1));start=null;},{passive:true});
        reduced.addEventListener('change',schedule);document.addEventListener('visibilitychange',schedule);show(0);schedule();
    });}
    window.LPMCarousel={init};if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>init());else init();
})();
