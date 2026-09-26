import * as THREE from '/assets/vendor/three/three.module.min.js';

const root=document.getElementById('designClip');
const canvas=document.getElementById('designClipCanvas');
if(!root||!canvas) throw new Error('Design clip root missing');

const audio=root.querySelector('[data-clip-audio]');
const startBtn=root.querySelector('[data-clip-start]');
const startOverlay=root.querySelector('[data-clip-start-overlay]');
const playBtn=root.querySelector('[data-clip-play]');
const muteBtn=root.querySelector('[data-clip-mute]');
const seek=root.querySelector('[data-clip-seek]');
const seekFill=root.querySelector('[data-clip-seek-fill]');
const currentEl=root.querySelector('[data-clip-current]');
const durationEl=root.querySelector('[data-clip-duration]');
const lyricEl=root.querySelector('[data-clip-lyric]');
const wordEl=root.querySelector('[data-clip-word]');
const modeEl=root.querySelector('[data-clip-mode]');
const audioStateEl=root.querySelector('[data-clip-audio-state]');
const fullscreenBtn=root.querySelector('[data-clip-fullscreen]');

const scene=new THREE.Scene();
scene.fog=new THREE.FogExp2(0x05070d,.035);
const camera=new THREE.PerspectiveCamera(55,innerWidth/innerHeight,.1,150);
camera.position.set(0,1.8,12);

const renderer=new THREE.WebGLRenderer({canvas,antialias:innerWidth>900,alpha:true,powerPreference:'high-performance'});
renderer.setSize(innerWidth,innerHeight);
renderer.setPixelRatio(Math.min(devicePixelRatio,innerWidth<900?1.25:1.8));
renderer.outputColorSpace=THREE.SRGBColorSpace;
renderer.toneMapping=THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure=1.1;

const world=new THREE.Group();
scene.add(world);

const ambient=new THREE.AmbientLight(0x7c8fff,.9);
scene.add(ambient);
const key=new THREE.PointLight(0x5edcff,16,40,2);
key.position.set(4,6,8);
scene.add(key);
const rim=new THREE.PointLight(0xff6fe8,11,35,2);
rim.position.set(-6,-2,4);
scene.add(rim);

const grid=new THREE.GridHelper(34,34,0x2b77ff,0x162138);
grid.position.y=-3.5;
grid.material.transparent=true;
grid.material.opacity=.22;
world.add(grid);

const coreMat=new THREE.MeshStandardMaterial({
 color:0x9faeff,metalness:.78,roughness:.22,
 emissive:0x1b2a66,emissiveIntensity:.9
});
const core=new THREE.Mesh(new THREE.IcosahedronGeometry(2.15,4),coreMat);
world.add(core);

const shell=new THREE.Mesh(
 new THREE.TorusKnotGeometry(3.15,.055,260,24,2,5),
 new THREE.MeshBasicMaterial({color:0x59ddff,transparent:true,opacity:.6})
);
world.add(shell);

const ringGroup=new THREE.Group();
for(let i=0;i<5;i++){
 const ring=new THREE.Mesh(
   new THREE.TorusGeometry(3.2+i*.42,.018,8,160),
   new THREE.MeshBasicMaterial({color:i%2?0xff7fe5:0x6ce7ff,transparent:true,opacity:.22})
 );
 ring.rotation.set(Math.random()*1.5,Math.random()*1.5,Math.random()*1.5);
 ringGroup.add(ring);
}
world.add(ringGroup);

const linePoints=[];
for(let i=0;i<160;i++){
 const x=(i/159-.5)*14;
 linePoints.push(new THREE.Vector3(x,Math.sin(i*.16)*.12,0));
}
const lineGeo=new THREE.BufferGeometry().setFromPoints(linePoints);
const lineMat=new THREE.LineBasicMaterial({color:0xffffff,transparent:true,opacity:.7});
const designLine=new THREE.Line(lineGeo,lineMat);
designLine.position.z=1.3;
world.add(designLine);

const panels=new THREE.Group();
for(let i=0;i<18;i++){
 const g=new THREE.PlaneGeometry(.7+Math.random()*1.6,.18+Math.random()*.8);
 const m=new THREE.MeshBasicMaterial({
   color:i%3===0?0xff73db:(i%3===1?0x61ddff:0x786cff),
   transparent:true,opacity:.10+Math.random()*.12,side:THREE.DoubleSide
 });
 const p=new THREE.Mesh(g,m);
 p.position.set((Math.random()-.5)*16,(Math.random()-.5)*9,(Math.random()-.5)*8-2);
 p.rotation.set(Math.random()*2,Math.random()*2,Math.random()*2);
 p.userData.speed=.001+Math.random()*.003;
 panels.add(p);
}
world.add(panels);

const particleCount=innerWidth<800?800:1600;
const pos=new Float32Array(particleCount*3);
for(let i=0;i<particleCount;i++){
 pos[i*3]=(Math.random()-.5)*28;
 pos[i*3+1]=(Math.random()-.5)*18;
 pos[i*3+2]=(Math.random()-.5)*24-4;
}
const pGeo=new THREE.BufferGeometry();
pGeo.setAttribute('position',new THREE.BufferAttribute(pos,3));
const particles=new THREE.Points(pGeo,new THREE.PointsMaterial({color:0xa9dfff,size:.028,transparent:true,opacity:.6}));
world.add(particles);

let analyser=null,dataArray=null,audioCtx=null,source=null;
function initAudioAnalysis(){
 if(!audio||analyser)return;
 audioCtx=new (window.AudioContext||window.webkitAudioContext)();
 analyser=audioCtx.createAnalyser();
 analyser.fftSize=256;
 analyser.smoothingTimeConstant=.82;
 dataArray=new Uint8Array(analyser.frequencyBinCount);
 source=audioCtx.createMediaElementSource(audio);
 source.connect(analyser);
 analyser.connect(audioCtx.destination);
}

const cues=[
 {s:0,e:17,a:'Пустое',b:'пространство.',w:'SPACE',m:'EMPTY SPACE'},
 {s:17,e:31,a:'Одна',b:'линия.',w:'LINE',m:'LINE'},
 {s:31,e:45,a:'Один',b:'цвет.',w:'COLOR',m:'COLOR'},
 {s:45,e:59,a:'Одна',b:'мысль.',w:'IDEA',m:'IDEA'},
 {s:59,e:80,a:'Я ищу форму',b:'для того, что существует внутри.',w:'FORM',m:'FORM SEARCH'},
 {s:80,e:97,a:'Убираю лишнее.',b:'Оставляю главное.',w:'REDUCE',m:'REDUCTION'},
 {s:97,e:111,a:'Цвет становится',b:'настроением.',w:'COLOR / FEELING',m:'MOOD'},
 {s:111,e:122,a:'Линия —',b:'движением.',w:'MOTION',m:'MOVEMENT'},
 {s:122,e:132,a:'Буква —',b:'голосом.',w:'TYPE / VOICE',m:'TYPOGRAPHY'},
 {s:132,e:141,a:'Идея',b:'обретает форму.',w:'IDENTITY',m:'IDENTITY'},
 {s:141,e:148,a:'Создай образ.',b:'Создай язык.',w:'CREATE',m:'CREATE'},
 {s:148,e:153,a:'Создай то,',b:'что будут узнавать.',w:'RECOGNIZE',m:'BRAND'},
 {s:153,e:999,a:'Дизайн начинается',b:'с чувства.',w:'DESIGN',m:'FEELING'}
];
let cueIndex=-1;
function setCue(t){
 const idx=Math.max(0,cues.findIndex(c=>t>=c.s&&t<c.e));
 const safe=idx<0?cues.length-1:idx;
 if(safe===cueIndex)return;
 cueIndex=safe;
 const c=cues[safe];
 if(lyricEl){
   lyricEl.innerHTML='<span>'+c.a+'</span><strong>'+c.b+'</strong>';
   lyricEl.animate([{opacity:.15,filter:'blur(10px)',transform:'translateY(16px)'},{opacity:1,filter:'blur(0)',transform:'translateY(0)'}],{duration:700,easing:'cubic-bezier(.2,.8,.2,1)'});
 }
 if(wordEl)wordEl.textContent=c.w;
 if(modeEl)modeEl.textContent=c.m;
}

function fmt(s){
 if(!Number.isFinite(s))return'00:00';
 return String(Math.floor(s/60)).padStart(2,'0')+':'+String(Math.floor(s%60)).padStart(2,'0');
}

function updateAudioUi(){
 if(!audio)return;
 const t=audio.currentTime||0,d=audio.duration||154.824;
 if(currentEl)currentEl.textContent=fmt(t);
 if(durationEl)durationEl.textContent=fmt(d);
 if(seekFill)seekFill.style.width=(d?(t/d)*100:0)+'%';
 if(playBtn)playBtn.textContent=audio.paused?'▶':'❚❚';
 if(audioStateEl)audioStateEl.textContent=audio.paused?'PAUSED':'LIVE';
 setCue(t);
}

async function startClip(){
 if(!audio)return;
 initAudioAnalysis();
 if(audioCtx?.state==='suspended')await audioCtx.resume();
 try{await audio.play();}catch{}
 startOverlay?.classList.add('hidden');
 updateAudioUi();
}
startBtn?.addEventListener('click',startClip);
playBtn?.addEventListener('click',async()=>{
 if(!audio)return;
 initAudioAnalysis();
 if(audioCtx?.state==='suspended')await audioCtx.resume();
 if(audio.paused)audio.play();else audio.pause();
});
muteBtn?.addEventListener('click',()=>{if(audio){audio.muted=!audio.muted;muteBtn.textContent=audio.muted?'MUTE':'VOL';}});
seek?.addEventListener('click',e=>{
 if(!audio?.duration)return;
 const r=seek.getBoundingClientRect();
 audio.currentTime=Math.max(0,Math.min(audio.duration,((e.clientX-r.left)/r.width)*audio.duration));
});
fullscreenBtn?.addEventListener('click',()=>{
 if(!document.fullscreenElement)root.requestFullscreen?.();else document.exitFullscreen?.();
});
audio?.addEventListener('timeupdate',updateAudioUi);
audio?.addEventListener('loadedmetadata',updateAudioUi);
audio?.addEventListener('play',updateAudioUi);
audio?.addEventListener('pause',updateAudioUi);

let mx=0,my=0;
addEventListener('pointermove',e=>{
 mx=(e.clientX/innerWidth-.5);
 my=(e.clientY/innerHeight-.5);
});

const clock=new THREE.Clock();
function animate(){
 requestAnimationFrame(animate);
 const elapsed=clock.getElapsedTime();
 let low=0,mid=0,high=0;
 if(analyser&&dataArray){
   analyser.getByteFrequencyData(dataArray);
   const avg=(a,b)=>{let s=0;for(let i=a;i<b;i++)s+=dataArray[i]||0;return s/Math.max(1,b-a)/255};
   low=avg(0,10);mid=avg(10,38);high=avg(38,90);
 }

 const t=audio?.currentTime||0;
 const progress=Math.min(1,t/154.824);
 const bassEra=Math.max(0,Math.min(1,(t-75)/35));

 core.rotation.x+=.0018+mid*.006;
 core.rotation.y+=.0024+high*.008;
 const pulse=1+low*.20+bassEra*.03*Math.sin(elapsed*3.5);
 core.scale.setScalar(pulse);
 coreMat.emissiveIntensity=.65+low*2.2+bassEra*.8;

 shell.rotation.x=elapsed*.08;
 shell.rotation.y=elapsed*.12;
 shell.scale.setScalar(.92+bassEra*.12+mid*.05);

 ringGroup.rotation.x=elapsed*.025;
 ringGroup.rotation.z=-elapsed*.035;
 ringGroup.children.forEach((r,i)=>{
   r.rotation.x+=.0005*(i+1);
   r.material.opacity=.08+bassEra*.18+high*.16;
 });

 panels.children.forEach((p,i)=>{
   p.rotation.x+=p.userData.speed*(1+mid*4);
   p.rotation.y+=p.userData.speed*.7;
   p.position.y+=Math.sin(elapsed*.25+i)*.0007;
   p.material.opacity=.04+bassEra*.12+high*.14;
 });

 particles.rotation.y=elapsed*.006;
 particles.rotation.x=Math.sin(elapsed*.1)*.03;
 particles.material.opacity=.25+high*.55;

 grid.material.opacity=.08+bassEra*.24;
 grid.position.z=(progress*8)%2;

 designLine.rotation.z=Math.sin(elapsed*.2)*.03;
 designLine.scale.y=1+mid*2.3;
 lineMat.opacity=.25+high*.65;

 key.intensity=9+low*28;
 rim.intensity=6+mid*22;
 key.color.setHSL(.53+.08*Math.sin(progress*Math.PI),.85,.65);
 rim.color.setHSL(.86-.12*progress,.78,.65);

 camera.position.x+=(mx*1.1-camera.position.x)*.02;
 camera.position.y+=(1.8-my*.8-camera.position.y)*.02;
 camera.position.z=12-bassEra*1.8-mid*.45;
 camera.lookAt(0,0,0);

 renderer.render(scene,camera);
}
animate();

addEventListener('resize',()=>{
 camera.aspect=innerWidth/innerHeight;
 camera.updateProjectionMatrix();
 renderer.setSize(innerWidth,innerHeight);
 renderer.setPixelRatio(Math.min(devicePixelRatio,innerWidth<900?1.25:1.8));
});
