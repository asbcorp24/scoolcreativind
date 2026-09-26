const CACHE_NAME='ski-pwa-v2';
const STATIC_ASSETS=[
  '/',
  '/offline.html',
  '/css/site.css',
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
    event.respondWith(fetch(request));
    return;
  }

  if(request.mode==='navigate'){
    event.respondWith(
      fetch(request)
        .then(response=>{
          if(canCacheResponse(response)){
            const copy=response.clone();
            caches.open(CACHE_NAME).then(cache=>cache.put(request,copy)).catch(()=>{});
          }
          return response;
        })
        .catch(async()=>{
          const cached=await caches.match(request);
          return cached || caches.match('/offline.html');
        })
    );
    return;
  }

  event.respondWith(
    caches.match(request).then(cached=>{
      const network=fetch(request).then(response=>{
        if(canCacheResponse(response)){
          const copy=response.clone();
          caches.open(CACHE_NAME).then(cache=>cache.put(request,copy)).catch(()=>{});
        }
        return response;
      }).catch(()=>cached);
      return cached || network;
    })
  );
});
