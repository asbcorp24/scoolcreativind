import * as THREE from '/assets/vendor/three/three.module.min.js';
import { GLTFLoader } from '/assets/vendor/three/GLTFLoader.js';
import { OrbitControls } from '/assets/vendor/three/OrbitControls.js';
import { MeshoptDecoder } from '/assets/vendor/three/meshopt_decoder.module.js';

document.addEventListener('DOMContentLoaded',()=>{
  const glow=document.getElementById('cursorGlow');
  window.addEventListener('pointermove',e=>{
    if(glow){glow.style.left=e.clientX+'px';glow.style.top=e.clientY+'px'}
  });

  const io=new IntersectionObserver(entries=>entries.forEach(e=>{
    if(e.isIntersecting)e.target.classList.add('visible')
  }),{threshold:.12});
  document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

  initAccessibility();
  initAdminSidebar();
  initMobileMoreMenu();
  initPWA();
  initGlobalMusicPlayer();
  initPageTransitions();
  initTiltCards();
  initScrollTitles();
  initAudioPlayers();
  initMediaPagination();
  initMediaSliders();
  initPhotoLightbox();
  initModelViewers();
  initHero();
  document.querySelectorAll('.pano-shell[data-panorama]').forEach(initPanorama);
});

function initPageTransitions(){
  const layer=document.getElementById('pageTransition');
  if(!layer)return;
  requestAnimationFrame(()=>layer.classList.add('ready'));

  document.addEventListener('click',e=>{
    const a=e.target.closest('a[href]');
    if(!a||e.defaultPrevented||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey)return;
    if(a.target==='_blank'||a.hasAttribute('download'))return;
    const href=a.getAttribute('href');
    if(!href||href.startsWith('#')||href.startsWith('mailto:')||href.startsWith('tel:')||href.startsWith('javascript:'))return;

    let url;
    try{url=new URL(a.href,location.href)}catch{return}
    if(url.origin!==location.origin)return;

    if(url.pathname===location.pathname && url.search===location.search && url.hash){
      return;
    }

    e.preventDefault();
    layer.classList.remove('ready');
    layer.classList.add('leaving');
    setTimeout(()=>location.href=url.href,360);
  });

  window.addEventListener('pageshow',()=>{
    layer.classList.remove('leaving');
    layer.classList.add('ready');
  });
}

function initHero(){
  const canvas=document.getElementById('heroCanvas');
  if(!canvas||!THREE)return;

  const scene=new THREE.Scene();
  const camera=new THREE.PerspectiveCamera(50,innerWidth/innerHeight,.1,100);
  const renderer=new THREE.WebGLRenderer({canvas,alpha:true,antialias:true});
  renderer.setPixelRatio(Math.min(devicePixelRatio,1.7));
  renderer.setSize(innerWidth,innerHeight);
  camera.position.z=7;

  const group=new THREE.Group();
  scene.add(group);

  const material=new THREE.MeshBasicMaterial({
    color:0x8a5cff,wireframe:true,transparent:true,opacity:.36
  });
  const mesh=new THREE.Mesh(new THREE.IcosahedronGeometry(2.1,2),material);
  group.add(mesh);

  const ringMat=new THREE.MeshBasicMaterial({
    color:0x00e5ff,wireframe:true,transparent:true,opacity:.22
  });

  for(let i=0;i<3;i++){
    const ring=new THREE.Mesh(
      new THREE.TorusGeometry(2.7+i*.45,.012,8,120),
      ringMat
    );
    ring.rotation.x=Math.PI/2.5+i*.35;
    ring.rotation.y=i*.55;
    group.add(ring);
  }

  const pts=[];
  for(let i=0;i<180;i++){
    pts.push((Math.random()-.5)*14,(Math.random()-.5)*10,(Math.random()-.5)*7);
  }
  const pGeo=new THREE.BufferGeometry();
  pGeo.setAttribute('position',new THREE.Float32BufferAttribute(pts,3));
  scene.add(new THREE.Points(
    pGeo,
    new THREE.PointsMaterial({color:0xffffff,size:.025,transparent:true,opacity:.45})
  ));

  let px=0,py=0;
  window.addEventListener('pointermove',e=>{
    px=(e.clientX/innerWidth-.5)*.5;
    py=(e.clientY/innerHeight-.5)*.35;
  });

  function draw(){
    requestAnimationFrame(draw);
    group.rotation.y+=.0025;
    group.rotation.x+=(py-group.rotation.x)*.018;
    group.rotation.y+=(px-group.rotation.y)*.012;
    group.position.x=innerWidth>900?2.1:0;
    renderer.render(scene,camera);
  }
  draw();

  window.addEventListener('resize',()=>{
    camera.aspect=innerWidth/innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(innerWidth,innerHeight);
  });
}

function initPanorama(el){
  const url=el.dataset.panorama;
  if(!url||!THREE)return;

  const scene=new THREE.Scene();
  const camera=new THREE.PerspectiveCamera(70,el.clientWidth/el.clientHeight,.1,1000);
  const isMobile=window.matchMedia('(pointer:coarse)').matches || window.innerWidth<768;
  const renderer=new THREE.WebGLRenderer({antialias:!isMobile,powerPreference:'high-performance'});
  renderer.setPixelRatio(Math.min(devicePixelRatio,isMobile?1:1.5));
  renderer.setSize(el.clientWidth,el.clientHeight);
  renderer.domElement.style.touchAction='none';
  el.style.touchAction='none';
  el.appendChild(renderer.domElement);

  const geo=new THREE.SphereGeometry(500,isMobile?40:60,isMobile?28:40);
  geo.scale(-1,1,1);

  new THREE.TextureLoader().load(
    url,
    tex=>{
      tex.colorSpace=THREE.SRGBColorSpace;
      scene.add(new THREE.Mesh(geo,new THREE.MeshBasicMaterial({map:tex})));
      const ph=el.querySelector('.pano-placeholder');
      if(ph)ph.style.display='none';
    },
    undefined,
    ()=>{
      const ph=el.querySelector('.pano-placeholder');
      if(ph){
        ph.innerHTML='<strong>360°</strong><span>Не удалось загрузить панораму</span>';
      }
    }
  );

  let hotspots=[];
  try{
    hotspots=JSON.parse(el.dataset.hotspots||'[]');
    if(!Array.isArray(hotspots))hotspots=[];
  }catch{hotspots=[]}

  const hotspotViews=hotspots.map(item=>{
    const button=document.createElement('button');
    button.type='button';
    button.className='pano-hotspot';
    button.innerHTML='<span class="pano-hotspot-dot"></span><span class="pano-hotspot-label"></span>';
    button.querySelector('.pano-hotspot-label').textContent=item.label||'Перейти';
    if(item.target_url){
      button.addEventListener('click',ev=>{
        ev.stopPropagation();
        location.href=item.target_url;
      });
    }
    el.appendChild(button);
    return {item,button,world:new THREE.Vector3()};
  });

  let lon=0,lat=0,down=false,sx=0,sy=0,sl=0,st=0;

  el.addEventListener('pointerdown',e=>{
    if(e.target.closest('.pano-hotspot'))return;
    e.preventDefault();
    down=true;sx=e.clientX;sy=e.clientY;sl=lon;st=lat;
    el.setPointerCapture?.(e.pointerId);
    el.classList.add('is-dragging');
  },{passive:false});

  el.addEventListener('pointerup',()=>{
    down=false;
    el.classList.remove('is-dragging');
  });

  el.addEventListener('pointercancel',()=>{
    down=false;
    el.classList.remove('is-dragging');
  });

  el.addEventListener('pointermove',e=>{
    if(down){
      e.preventDefault();
      lon=sl+(sx-e.clientX)*.12;
      lat=st+(e.clientY-sy)*.12;
    }
  },{passive:false});

  if(!window.PointerEvent){
    let touching=false,tsx=0,tsy=0,tsl=0,tst=0;

    el.addEventListener('touchstart',e=>{
      if(e.target.closest('.pano-hotspot') || !e.touches.length)return;
      const t=e.touches[0];
      touching=true;tsx=t.clientX;tsy=t.clientY;tsl=lon;tst=lat;
      el.classList.add('is-dragging');
      e.preventDefault();
    },{passive:false});

    el.addEventListener('touchmove',e=>{
      if(!touching || !e.touches.length)return;
      const t=e.touches[0];
      lon=tsl+(tsx-t.clientX)*.12;
      lat=tst+(t.clientY-tsy)*.12;
      e.preventDefault();
    },{passive:false});

    const endTouch=()=>{
      touching=false;
      el.classList.remove('is-dragging');
    };
    el.addEventListener('touchend',endTouch,{passive:true});
    el.addEventListener('touchcancel',endTouch,{passive:true});
  }

  el.addEventListener('wheel',e=>{
    camera.fov=Math.max(35,Math.min(90,camera.fov+e.deltaY*.03));
    camera.updateProjectionMatrix();
    e.preventDefault();
  },{passive:false});

  const cameraDir=new THREE.Vector3();

  function updateHotspots(){
    camera.getWorldDirection(cameraDir);

    hotspotViews.forEach(h=>{
      const yaw=Number(h.item.yaw||0);
      const pitch=Number(h.item.pitch||0);
      const phi=THREE.MathUtils.degToRad(90-pitch);
      const theta=THREE.MathUtils.degToRad(yaw);

      h.world.set(
        490*Math.sin(phi)*Math.cos(theta),
        490*Math.cos(phi),
        490*Math.sin(phi)*Math.sin(theta)
      );

      const visible=cameraDir.dot(h.world.clone().normalize())>0.08;
      const projected=h.world.clone().project(camera);

      if(!visible||projected.z>1){
        h.button.style.opacity='0';
        h.button.style.pointerEvents='none';
        return;
      }

      const x=(projected.x*.5+.5)*el.clientWidth;
      const y=(-projected.y*.5+.5)*el.clientHeight;
      h.button.style.transform='translate(-50%,-50%) translate('+x+'px,'+y+'px)';
      h.button.style.opacity='1';
      h.button.style.pointerEvents='auto';
    });
  }

  function draw(){
    requestAnimationFrame(draw);
    lat=Math.max(-85,Math.min(85,lat));
    const phi=THREE.MathUtils.degToRad(90-lat);
    const theta=THREE.MathUtils.degToRad(lon);
    camera.lookAt(
      500*Math.sin(phi)*Math.cos(theta),
      500*Math.cos(phi),
      500*Math.sin(phi)*Math.sin(theta)
    );
    renderer.render(scene,camera);
    updateHotspots();
  }
  draw();

  new ResizeObserver(()=>{
    camera.aspect=el.clientWidth/el.clientHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(el.clientWidth,el.clientHeight);
  }).observe(el);
}


function initTiltCards(){
  document.querySelectorAll('.studio-card,.tilt-card,.project-card,.team-card,.equipment-card,.cabinet-link').forEach(card=>{
    card.addEventListener('pointermove',e=>{
      if(window.matchMedia('(pointer:coarse)').matches)return;
      const r=card.getBoundingClientRect();
      const x=(e.clientX-r.left)/r.width-.5;
      const y=(e.clientY-r.top)/r.height-.5;
      card.style.transform='perspective(900px) rotateX('+(-y*5)+'deg) rotateY('+(x*7)+'deg) translateY(-3px)';
    });
    card.addEventListener('pointerleave',()=>{
      card.style.transform='';
    });
  });
}


function initAudioPlayers(){
  const tracks=[...document.querySelectorAll('[data-audio-track]')];
  if(!tracks.length)return;

  let current=null;

  tracks.forEach(track=>{
    const audio=track.querySelector('[data-audio]');
    const button=track.querySelector('[data-audio-button]');
    const fill=track.querySelector('[data-audio-progress]');
    const time=track.querySelector('[data-audio-time]');
    const bar=track.querySelector('.audio-progress');

    const fmt=s=>{
      if(!Number.isFinite(s))return '00:00';
      const m=Math.floor(s/60),sec=Math.floor(s%60);
      return String(m).padStart(2,'0')+':'+String(sec).padStart(2,'0');
    };

    button?.addEventListener('click',()=>{
      if(current && current!==audio){
        current.pause();
        const old=current.closest('[data-audio-track]');
        old?.classList.remove('playing');
        const oldBtn=old?.querySelector('[data-audio-button]');
        if(oldBtn)oldBtn.textContent='▶';
      }

      if(audio.paused){
        audio.play();
        current=audio;
        track.classList.add('playing');
        button.textContent='❚❚';
      }else{
        audio.pause();
        track.classList.remove('playing');
        button.textContent='▶';
      }
    });

    audio.addEventListener('loadedmetadata',()=>{
      if(time)time.textContent=fmt(audio.duration);
    });

    audio.addEventListener('timeupdate',()=>{
      if(fill && audio.duration){
        fill.style.width=((audio.currentTime/audio.duration)*100)+'%';
      }
      if(time)time.textContent=fmt(audio.currentTime)+' / '+fmt(audio.duration);
    });

    audio.addEventListener('ended',()=>{
      track.classList.remove('playing');
      if(button)button.textContent='▶';
      if(fill)fill.style.width='0%';
      const idx=tracks.indexOf(track);
      const next=tracks[idx+1];
      next?.querySelector('[data-audio-button]')?.click();
    });

    bar?.addEventListener('click',e=>{
      if(!audio.duration)return;
      const r=bar.getBoundingClientRect();
      audio.currentTime=((e.clientX-r.left)/r.width)*audio.duration;
    });
  });
}


function initModelViewers(){
  document.querySelectorAll('[data-model-viewer]').forEach(el=>{
    const url=el.dataset.modelUrl;
    if(!url)return;

    const scene=new THREE.Scene();
    const camera=new THREE.PerspectiveCamera(45,el.clientWidth/Math.max(el.clientHeight,1),0.01,1000);
    camera.position.set(2.8,1.8,3.8);

    const renderer=new THREE.WebGLRenderer({antialias:true,alpha:true});
    renderer.setPixelRatio(Math.min(devicePixelRatio,1.7));
    renderer.setSize(el.clientWidth,el.clientHeight);
    renderer.outputColorSpace=THREE.SRGBColorSpace;
    renderer.toneMapping=THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure=1.1;
    el.appendChild(renderer.domElement);

    const hemi=new THREE.HemisphereLight(0xffffff,0x222233,2.2);
    scene.add(hemi);
    const key=new THREE.DirectionalLight(0xffffff,2.6);
    key.position.set(4,6,5);
    scene.add(key);
    const rim=new THREE.DirectionalLight(0x8a5cff,1.4);
    rim.position.set(-4,2,-4);
    scene.add(rim);

    const controls=new OrbitControls(camera,renderer.domElement);
    controls.enableDamping=true;
    controls.dampingFactor=.06;
    controls.autoRotate=true;
    controls.autoRotateSpeed=1.1;

    const loader=new GLTFLoader();
    loader.setMeshoptDecoder(MeshoptDecoder);

    const loading=el.querySelector('.model-loading');
    if(loading){
      loading.innerHTML='<div><strong>Загрузка 3D-модели…</strong><div class="small mt-2 model-load-url"></div></div>';
      const urlEl=loading.querySelector('.model-load-url');
      if(urlEl)urlEl.textContent=url;
    }

    loader.load(url,gltf=>{
      const model=gltf.scene;
      scene.add(model);

      const box=new THREE.Box3().setFromObject(model);
      const size=box.getSize(new THREE.Vector3());
      const center=box.getCenter(new THREE.Vector3());
      model.position.sub(center);

      const maxDim=Math.max(size.x,size.y,size.z)||1;
      const distance=maxDim*2.2;
      camera.position.set(distance*.8,distance*.45,distance);
      camera.near=Math.max(distance/1000,.01);
      camera.far=distance*20;
      camera.updateProjectionMatrix();
      controls.target.set(0,0,0);
      controls.update();

      el.classList.add('loaded');
      const loading=el.querySelector('.model-loading');
      if(loading)loading.remove();
    },progress=>{
      const loading=el.querySelector('.model-loading');
      if(loading && progress.total){
        const pct=Math.round((progress.loaded/progress.total)*100);
        const strong=loading.querySelector('strong');
        if(strong)strong.textContent='Загрузка 3D-модели… '+pct+'%';
      }
    },err=>{
      console.error('GLTF load error',err);
      const loading=el.querySelector('.model-loading');
      if(loading){
        const msg=err?.message || String(err || 'Неизвестная ошибка');
        loading.innerHTML='<div class="model-error"><strong>Не удалось загрузить 3D-модель</strong><div class="small mt-2"></div><a class="btn btn-sm btn-ghost mt-3" target="_blank" rel="noopener">Открыть файл</a></div>';
        const text=loading.querySelector('.small');
        if(text)text.textContent=msg;
        const link=loading.querySelector('a');
        if(link)link.href=url;
      }
    });

    const ro=new ResizeObserver(()=>{
      const w=el.clientWidth,h=el.clientHeight;
      camera.aspect=w/Math.max(h,1);
      camera.updateProjectionMatrix();
      renderer.setSize(w,h);
    });
    ro.observe(el);

    function draw(){
      requestAnimationFrame(draw);
      controls.update();
      renderer.render(scene,camera);
    }
    draw();
  });
}


function initMediaPagination(){
  document.querySelectorAll('[data-paginated-list]').forEach(list=>{
    const items=[...list.querySelectorAll('[data-page-item]')];
    const pageSize=Math.max(1,Number(list.dataset.pageSize||6));
    const pager=list.parentElement?.querySelector('[data-pagination]');
    if(items.length<=pageSize || !pager)return;

    const pages=Math.ceil(items.length/pageSize);
    let current=1;

    const render=()=>{
      items.forEach((item,i)=>{
        item.hidden=!(i>=(current-1)*pageSize && i<current*pageSize);
      });

      pager.innerHTML='';
      const prev=document.createElement('button');
      prev.type='button';
      prev.className='media-page-btn media-page-arrow';
      prev.innerHTML='<span>←</span><small>НАЗАД</small>';
      prev.disabled=current===1;
      prev.addEventListener('click',()=>{ if(current>1){current--;render();list.scrollIntoView({behavior:'smooth',block:'start'});} });
      pager.appendChild(prev);

      for(let p=1;p<=pages;p++){
        const b=document.createElement('button');
        b.type='button';
        b.className='media-page-btn '+(p===current?'active':'');
        b.textContent=String(p).padStart(2,'0');
        b.addEventListener('click',()=>{ current=p;render();list.scrollIntoView({behavior:'smooth',block:'start'}); });
        pager.appendChild(b);
      }

      const next=document.createElement('button');
      next.type='button';
      next.className='media-page-btn media-page-arrow';
      next.innerHTML='<small>ВПЕРЁД</small><span>→</span>';
      next.disabled=current===pages;
      next.addEventListener('click',()=>{ if(current<pages){current++;render();list.scrollIntoView({behavior:'smooth',block:'start'});} });
      pager.appendChild(next);
    };

    render();
  });
}

function initMediaSliders(){
  document.querySelectorAll('[data-media-slider]').forEach(slider=>{
    const track=slider.querySelector('[data-slider-track]');
    const prev=slider.querySelector('[data-slider-prev]');
    const next=slider.querySelector('[data-slider-next]');
    const counter=slider.querySelector('[data-slider-counter]');
    const fullscreen=slider.querySelector('[data-slider-fullscreen]');
    const slides=[...track?.querySelectorAll('.media-slider-slide')||[]];
    if(!track)return;

    const step=()=>Math.max(track.clientWidth*.92,280);
    prev?.addEventListener('click',()=>track.scrollBy({left:-step(),behavior:'smooth'}));
    next?.addEventListener('click',()=>track.scrollBy({left:step(),behavior:'smooth'}));

    const refresh=()=>{
      if(prev)prev.disabled=track.scrollLeft<=4;
      if(next)next.disabled=track.scrollLeft+track.clientWidth>=track.scrollWidth-4;

      if(counter && slides.length){
        const center=track.scrollLeft+track.clientWidth/2;
        let active=0;
        let best=Infinity;
        slides.forEach((slide,i)=>{
          const pos=slide.offsetLeft+slide.offsetWidth/2;
          const d=Math.abs(pos-center);
          if(d<best){best=d;active=i;}
        });
        counter.textContent=String(active+1).padStart(2,'0')+' / '+String(slides.length).padStart(2,'0');
      }
    };

    fullscreen?.addEventListener('click',()=>{
      if(!slides.length)return;
      const center=track.scrollLeft+track.clientWidth/2;
      let active=slides[0],best=Infinity;
      slides.forEach(slide=>{
        const pos=slide.offsetLeft+slide.offsetWidth/2;
        const d=Math.abs(pos-center);
        if(d<best){best=d;active=slide;}
      });
      const target=active.querySelector('.pano-shell,.model-viewer-local,.model-card') || active;
      if(document.fullscreenElement){
        document.exitFullscreen?.();
      }else{
        target.requestFullscreen?.();
      }
    });

    track.addEventListener('scroll',refresh,{passive:true});
    new ResizeObserver(refresh).observe(track);
    refresh();
  });
}


function initPhotoLightbox(){
  const box=document.querySelector('[data-photo-lightbox]');
  if(!box)return;

  const photos=[...document.querySelectorAll('[data-lightbox-photo]')];
  if(!photos.length)return;

  const image=box.querySelector('[data-lightbox-image]');
  const title=box.querySelector('[data-lightbox-title]');
  const counter=box.querySelector('[data-lightbox-counter]');
  const close=box.querySelector('[data-lightbox-close]');
  const prev=box.querySelector('[data-lightbox-prev]');
  const next=box.querySelector('[data-lightbox-next]');
  let index=0;

  const render=()=>{
    const item=photos[index];
    image.src=item.dataset.src||'';
    image.alt=item.dataset.title||'';
    if(title)title.textContent=item.dataset.title||'';
    if(counter)counter.textContent=String(index+1).padStart(2,'0')+' / '+String(photos.length).padStart(2,'0');
  };

  const open=i=>{
    index=i;
    render();
    box.classList.add('open');
    box.setAttribute('aria-hidden','false');
    document.body.classList.add('lightbox-open');
  };

  const hide=()=>{
    box.classList.remove('open');
    box.setAttribute('aria-hidden','true');
    document.body.classList.remove('lightbox-open');
  };

  photos.forEach((item,i)=>item.addEventListener('click',()=>open(i)));
  close?.addEventListener('click',hide);
  prev?.addEventListener('click',()=>{index=(index-1+photos.length)%photos.length;render();});
  next?.addEventListener('click',()=>{index=(index+1)%photos.length;render();});

  box.addEventListener('click',e=>{if(e.target===box)hide();});
  document.addEventListener('keydown',e=>{
    if(!box.classList.contains('open'))return;
    if(e.key==='Escape')hide();
    if(e.key==='ArrowLeft'){index=(index-1+photos.length)%photos.length;render();}
    if(e.key==='ArrowRight'){index=(index+1)%photos.length;render();}
  });
}


function initScrollTitles(){
  const titles=[...document.querySelectorAll('.section-head h2,.scroll-title,.studio-title')];
  if(!titles.length)return;

  const update=()=>{
    const vh=window.innerHeight||1;
    titles.forEach(title=>{
      const r=title.getBoundingClientRect();
      const p=Math.max(-1,Math.min(1,(r.top+r.height/2-vh/2)/vh));
      title.style.setProperty('--scroll-shift',(p*-18).toFixed(1)+'px');
      title.style.setProperty('--scroll-skew',(p*1.4).toFixed(2)+'deg');
    });
  };

  update();
  window.addEventListener('scroll',update,{passive:true});
  window.addEventListener('resize',update);
}


function initAccessibility(){
  const buttons=[...document.querySelectorAll('[data-accessibility-toggle]')];
  if(!buttons.length)return;

  const apply=enabled=>{
    document.documentElement.classList.toggle('accessibility-mode',enabled);
    buttons.forEach(button=>{
      button.setAttribute('aria-pressed',enabled?'true':'false');
      const small=button.querySelector('small');
      if(small)small.textContent=enabled?'Обычная версия':'Версия для слабовидящих';
    });
  };

  let enabled=false;
  try{enabled=localStorage.getItem('accessibilityMode')==='1'}catch{}
  apply(enabled);

  buttons.forEach(button=>button.addEventListener('click',()=>{
    enabled=!document.documentElement.classList.contains('accessibility-mode');
    try{localStorage.setItem('accessibilityMode',enabled?'1':'0')}catch{}
    apply(enabled);
  }));
}


function initMobileMoreMenu(){
  const sheet=document.querySelector('[data-mobile-more-sheet]');
  if(!sheet)return;

  const openButtons=[...document.querySelectorAll('[data-mobile-more-open]')];
  const closeButtons=[...sheet.querySelectorAll('[data-mobile-more-close]')];

  const open=()=>{
    sheet.classList.add('open');
    sheet.setAttribute('aria-hidden','false');
    document.body.classList.add('mobile-menu-open');
  };

  const close=()=>{
    sheet.classList.remove('open');
    sheet.setAttribute('aria-hidden','true');
    document.body.classList.remove('mobile-menu-open');
  };

  openButtons.forEach(btn=>btn.addEventListener('click',open));
  closeButtons.forEach(btn=>btn.addEventListener('click',close));
  sheet.querySelectorAll('a').forEach(a=>a.addEventListener('click',close));
  document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
}


function initPWA(){
  if('serviceWorker' in navigator){
    window.addEventListener('load',()=>{
      navigator.serviceWorker.register('/sw.js').catch(err=>console.error('SW registration failed',err));
    });
  }

  let deferredPrompt=null;
  const buttons=[...document.querySelectorAll('[data-pwa-install]')];

  const setVisible=visible=>{
    buttons.forEach(button=>{
      button.classList.toggle('d-none',!visible);
    });
  };

  const isStandalone=window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone===true;
  if(isStandalone){
    setVisible(false);
    return;
  }

  window.addEventListener('beforeinstallprompt',event=>{
    event.preventDefault();
    deferredPrompt=event;
    setVisible(true);
  });

  buttons.forEach(button=>button.addEventListener('click',async()=>{
    if(deferredPrompt){
      deferredPrompt.prompt();
      await deferredPrompt.userChoice.catch(()=>null);
      deferredPrompt=null;
      setVisible(false);
      return;
    }

    const isiOS=/iphone|ipad|ipod/i.test(navigator.userAgent);
    if(isiOS){
      alert('Чтобы установить приложение на iPhone/iPad: нажмите «Поделиться» в Safari → «На экран Домой».');
    }else{
      alert('Откройте меню браузера и выберите «Установить приложение» или «Добавить на главный экран».');
    }
  }));

  window.addEventListener('appinstalled',()=>{
    deferredPrompt=null;
    setVisible(false);
  });
}


function initGlobalMusicPlayer(){
  const player=document.querySelector('[data-global-music-player]');
  if(!player)return;

  const audio=player.querySelector('[data-music-audio]');
  const playlistEl=player.querySelector('[data-music-playlist]');
  if(!audio||!playlistEl)return;

  let playlist=[];
  try{playlist=JSON.parse(playlistEl.textContent||'[]')}catch{}
  if(!Array.isArray(playlist)||!playlist.length)return;

  const title=player.querySelector('[data-music-title]');
  const artist=player.querySelector('[data-music-artist]');
  const playBtn=player.querySelector('[data-music-play]');
  const prevBtn=player.querySelector('[data-music-prev]');
  const nextBtn=player.querySelector('[data-music-next]');
  const currentEl=player.querySelector('[data-music-current]');
  const durationEl=player.querySelector('[data-music-duration]');
  const progress=player.querySelector('[data-music-progress]');
  const fill=player.querySelector('[data-music-progress-fill]');
  const volume=player.querySelector('[data-music-volume]');
  const collapse=player.querySelector('[data-music-collapse]');
  const eq=player.querySelector('[data-music-eq]');

  const storageKey='skiMusicPlayerStateV2';
  let state={index:0,time:0,volume:.65,playing:false,collapsed:false,updatedAt:0};
  let playbackWanted=false;
  let leavingPage=false;

  try{
    const savedV2=JSON.parse(localStorage.getItem(storageKey)||'{}');
    const savedV1=JSON.parse(localStorage.getItem('skiMusicPlayerStateV1')||'{}');
    state={...state,...savedV1,...savedV2};
  }catch{}

  if(!playlist[state.index])state.index=0;
  playbackWanted=!!state.playing;

  const fmt=s=>{
    if(!Number.isFinite(s))return '00:00';
    const m=Math.floor(s/60),sec=Math.floor(s%60);
    return String(m).padStart(2,'0')+':'+String(sec).padStart(2,'0');
  };

  const save=()=>{
    state.time=Number.isFinite(audio.currentTime)?audio.currentTime:0;
    state.volume=audio.volume;
    state.playing=playbackWanted;
    state.collapsed=player.classList.contains('collapsed');
    state.updatedAt=Date.now();
    try{
      localStorage.setItem(storageKey,JSON.stringify(state));
      localStorage.setItem('skiMusicPlayerStateV1',JSON.stringify(state));
    }catch{}
  };

  const renderPlaying=()=>{
    const playing=!audio.paused;
    player.classList.toggle('is-playing',playing);
    eq?.classList.toggle('active',playing);
    if(playBtn)playBtn.textContent=playing?'❚❚':'▶';
  };

  const loadTrack=(index,restoreTime=0,autoplay=false)=>{
    state.index=(index+playlist.length)%playlist.length;
    const track=playlist[state.index];
    audio.src=track.url;
    if(title)title.textContent=track.title||'Музыка ШКИ';
    if(artist)artist.textContent=track.artist||'ШКИ Волжск';
    if(currentEl)currentEl.textContent='00:00';
    if(durationEl)durationEl.textContent='00:00';
    if(fill)fill.style.width='0%';

    const restore=()=>{
      if(Number.isFinite(restoreTime)&&restoreTime>0&&restoreTime<audio.duration){
        try{audio.currentTime=restoreTime}catch{}
      }
      audio.removeEventListener('loadedmetadata',restore);
    };
    audio.addEventListener('loadedmetadata',restore);

    if(autoplay){
      audio.play().catch(()=>renderPlaying());
    }
    save();
  };

  audio.volume=Math.max(0,Math.min(1,Number(state.volume)||.65));
  if(volume)volume.value=String(audio.volume);
  player.classList.toggle('collapsed',!!state.collapsed);

  const shouldResume=!!state.playing;
  const savedTime=Number(state.time)||0;
  loadTrack(state.index,savedTime,false);

  if(shouldResume){
    const tryImmediateResume=()=>{
      audio.play().catch(()=>{
        const resumeOnce=()=>{
          if(playbackWanted)audio.play().catch(()=>{});
        };
        document.addEventListener('pointerdown',resumeOnce,{once:true});
        document.addEventListener('keydown',resumeOnce,{once:true});
        document.addEventListener('touchstart',resumeOnce,{once:true,passive:true});
      });
    };

    if(audio.readyState>=1)tryImmediateResume();
    else audio.addEventListener('loadedmetadata',tryImmediateResume,{once:true});
  }

  playBtn?.addEventListener('click',()=>{
    if(audio.paused){
      playbackWanted=true;
      save();
      audio.play().catch(()=>{});
    }else{
      playbackWanted=false;
      save();
      audio.pause();
    }
  });

  prevBtn?.addEventListener('click',()=>{
    playbackWanted=true;
    loadTrack(state.index-1,0,true);
  });
  nextBtn?.addEventListener('click',()=>{
    playbackWanted=true;
    loadTrack(state.index+1,0,true);
  });

  audio.addEventListener('play',()=>{
    playbackWanted=true;
    renderPlaying();
    save();
  });
  audio.addEventListener('pause',()=>{
    if(!leavingPage){
      renderPlaying();
      save();
    }
  });
  audio.addEventListener('ended',()=>{
    playbackWanted=true;
    loadTrack(state.index+1,0,true);
  });

  audio.addEventListener('loadedmetadata',()=>{
    if(durationEl)durationEl.textContent=fmt(audio.duration);
  });

  audio.addEventListener('timeupdate',()=>{
    if(currentEl)currentEl.textContent=fmt(audio.currentTime);
    if(durationEl)durationEl.textContent=fmt(audio.duration);
    if(fill&&audio.duration)fill.style.width=((audio.currentTime/audio.duration)*100)+'%';
    if(Math.floor(audio.currentTime)%3===0)save();
  });

  progress?.addEventListener('click',e=>{
    if(!audio.duration)return;
    const r=progress.getBoundingClientRect();
    audio.currentTime=Math.max(0,Math.min(audio.duration,((e.clientX-r.left)/r.width)*audio.duration));
    save();
  });

  volume?.addEventListener('input',()=>{
    audio.volume=Number(volume.value);
    save();
  });

  collapse?.addEventListener('click',()=>{
    player.classList.toggle('collapsed');
    save();
  });

  window.addEventListener('beforeunload',()=>{
    leavingPage=true;
    save();
  });
  window.addEventListener('pagehide',()=>{
    leavingPage=true;
    save();
  });
  document.addEventListener('visibilitychange',()=>{if(document.hidden)save();});
  renderPlaying();
}


function initAdminSidebar(){
  const sidebar=document.querySelector('[data-admin-sidebar]');
  if(!sidebar)return;

  const toggles=[...document.querySelectorAll('[data-admin-sidebar-toggle]')];
  const close=document.querySelector('[data-admin-sidebar-close]');
  const backdrop=document.querySelector('[data-admin-sidebar-backdrop]');

  const open=()=>{
    sidebar.classList.add('open');
    backdrop?.classList.add('open');
    document.body.classList.add('admin-sidebar-open');
  };

  const hide=()=>{
    sidebar.classList.remove('open');
    backdrop?.classList.remove('open');
    document.body.classList.remove('admin-sidebar-open');
  };

  toggles.forEach(toggle=>toggle.addEventListener('click',open));
  close?.addEventListener('click',hide);
  backdrop?.addEventListener('click',hide);

  sidebar.querySelectorAll('[data-admin-group-toggle]').forEach(button=>{
    button.addEventListener('click',()=>{
      const group=button.closest('.admin-side-group');
      if(!group)return;

      const willOpen=!group.classList.contains('open');

      sidebar.querySelectorAll('.admin-side-group.open').forEach(other=>{
        if(other!==group)other.classList.remove('open');
      });

      group.classList.toggle('open',willOpen);
    });
  });

  sidebar.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{
    if(window.innerWidth<=1100)hide();
  }));

  document.addEventListener('keydown',e=>{
    if(e.key==='Escape')hide();
  });
}


function initNativePickers(){
  const inputs=[...document.querySelectorAll('input[type="date"],input[type="time"],input[type="datetime-local"],input[type="month"]')];
  inputs.forEach(input=>{
    if(input.dataset.nativePickerReady==='1')return;
    input.dataset.nativePickerReady='1';

    input.addEventListener('click',()=>{
      if(typeof input.showPicker==='function'){
        try{ input.showPicker(); }catch{}
      }
    });

    input.addEventListener('keydown',event=>{
      if((event.key==='Enter'||event.key===' ') && typeof input.showPicker==='function'){
        event.preventDefault();
        try{ input.showPicker(); }catch{}
      }
    });
  });
}

function initScheduleMasterSummary(){
  const output=document.querySelector('[data-schedule-master-summary]');
  if(!output)return;

  const form=output.closest('form');
  if(!form)return;

  const from=form.querySelector('[name="date_from"]');
  const to=form.querySelector('[name="date_to"]');
  const weekday=form.querySelector('[name="weekday"]');
  const starts=form.querySelector('[name="starts_at"]');
  const ends=form.querySelector('[name="ends_at"]');

  const dayNames=['Понедельник','Вторник','Среда','Четверг','Пятница','Суббота','Воскресенье'];

  const isoDay=date=>{
    const jsDay=date.getDay();
    return jsDay===0?7:jsDay;
  };

  const update=()=>{
    if(!from?.value || !to?.value || !weekday?.value){
      output.textContent='Выберите даты — здесь появится предварительное количество занятий.';
      return;
    }

    const start=new Date(from.value+'T12:00:00');
    const finish=new Date(to.value+'T12:00:00');
    if(Number.isNaN(start.getTime()) || Number.isNaN(finish.getTime()) || finish<start){
      output.textContent='Проверьте диапазон дат.';
      return;
    }

    const wanted=Number(weekday.value);
    let count=0;
    const cursor=new Date(start);
    while(cursor<=finish && count<1000){
      if(isoDay(cursor)===wanted)count++;
      cursor.setDate(cursor.getDate()+1);
    }

    const time=(starts?.value && ends?.value)?` с ${starts.value} до ${ends.value}`:'';
    output.innerHTML=`Будет создано: <strong>${count}</strong> занятий · ${dayNames[wanted-1] || ''}${time}.`;
  };

  [from,to,weekday,starts,ends].forEach(el=>{
    el?.addEventListener('change',update);
    el?.addEventListener('input',update);
  });

  update();
}

document.addEventListener('DOMContentLoaded',()=>{
  initNativePickers();
  initScheduleMasterSummary();
});
