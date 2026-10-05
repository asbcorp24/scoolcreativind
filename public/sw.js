const CACHE_NAME='ski-pwa-v3';
const STATIC_ASSETS=[
  '/offline.html',
  '/css/site.css',
  '/js/site.js',
  '/assets/vendor/bootstrap/bootstrap.min.css',
  '/assets/vendor/bootstrap/bootstrap.bundle.min.js',
  '/icons/pwa.svg'
];

const PRIVATE_PREFIXES=[
  '/admin',
  '/cabinet',
  '/study',
  '/login',
  '/register',
  '/my-portfolio',
  '/clips/'
];

function isPrivatePath(pathname){
  return PRIVATE_PREFIXES.some(prefix=>pathname===prefix || pathname.startsWith(prefix));
}

function canCacheResponse(response){
  if(!response || response.status!==200 || response.type!=='basic')return false;
  const cc=(response.headers.get('Cache-Control')||'').toLowerCase();
  return !cc.includes('no-store') && !cc.includes('private');
}

function offlineResponse(){
  return new Response(
    '<!doctype html><meta charset="utf-8"><title>Нет соединения</title><body style="font-family:sans-serif;padding:2rem;background:#071018;color:#fff"><h1>Нет соединения</h1><p>Подключитесь к сети и обновите страницу.</p></body>',
    {status:503,headers:{'Content-Type':'text/html; charset=utf-8'}}
  );
}

async function cachedOffline(){
  return (await caches.match('/offline.html')) || offlineResponse();
}

self.addEventListener('install',event=>{
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then(async cache=>{
      for(const url of STATIC_ASSETS){
        try{
          await cache.add(url);
        }catch(err){
          console.warn('PWA cache skip:',url,err);
        }
      }
    })
  );
});

self.addEventListener('activate',event=>{
  event.waitUntil(
    caches.keys().then(keys=>Promise.all(
      keys.filter(key=>key!==CACHE_NAME).map(key=>caches.delete(key))
    )).then(()=>self.clients.claim())
  );
});

self.addEventListener('fetch',event=>{
  const request=event.request;
  if(request.method!=='GET')return;

  const url=new URL(request.url);
  if(url.origin!==self.location.origin)return;

  if(isPrivatePath(url.pathname)){
    event.respondWith(
      fetch(request).catch(()=>request.mode==='navigate' ? cachedOffline() : new Response('',{status:503}))
    );
    return;
  }

  if(request.mode==='navigate'){
    event.respondWith((async()=>{
      try{
        const response=await fetch(request);
        if(canCacheResponse(response)){
          const cache=await caches.open(CACHE_NAME);
          await cache.put(request,response.clone()).catch(()=>{});
        }
        return response;
      }catch{
        const cached=await caches.match(request);
        return cached || await cachedOffline();
      }
    })());
    return;
  }

  const isCodeAsset=
    request.destination==='script' ||
    request.destination==='style' ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.css');

  if(isCodeAsset){
    event.respondWith((async()=>{
      try{
        const response=await fetch(request);
        if(canCacheResponse(response)){
          const cache=await caches.open(CACHE_NAME);
          await cache.put(request,response.clone()).catch(()=>{});
        }
        return response;
      }catch{
        return (await caches.match(request)) || new Response('',{status:503});
      }
    })());
    return;
  }

  event.respondWith((async()=>{
    const cached=await caches.match(request);
    if(cached)return cached;

    try{
      const response=await fetch(request);
      if(canCacheResponse(response)){
        const cache=await caches.open(CACHE_NAME);
        await cache.put(request,response.clone()).catch(()=>{});
      }
      return response;
    }catch{
      return new Response('',{status:503});
    }
  })());
});
