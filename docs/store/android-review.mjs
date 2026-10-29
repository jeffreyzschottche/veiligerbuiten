// Operates only the disposable VeiligerBuiten review emulator. No production reports are created.
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const adbPath = '/Users/jeffreyzschottche/Library/Android/sdk/platform-tools/adb';
const device = 'emulator-5554';
const pkg = 'nl.veiligerbuiten.app';
const out = resolve('docs/store/review/android');
const adb = (...args) => execFileSync(adbPath, ['-s', device, ...args], { maxBuffer: 24 * 1024 * 1024 });
const [action, ...args] = process.argv.slice(2);

async function cdp(expression) {
  const sockets = adb('shell', 'cat', '/proc/net/unix').toString().match(/@webview_devtools_remote_\d+/g);
  if (!sockets?.length) throw new Error('The app WebView is not running.');
  adb('forward', 'tcp:9223', `localabstract:${sockets[0].slice(1)}`);
  const pages = await fetch('http://127.0.0.1:9223/json').then(r => r.json());
  const target = pages.find(p => p.type === 'page' && p.url.startsWith('https://localhost'));
  if (!target) throw new Error('VeiligerBuiten WebView not found.');
  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((ok, fail) => { ws.addEventListener('open', ok, { once: true }); ws.addEventListener('error', fail, { once: true }); });
  const result = await new Promise((ok, fail) => {
    const timer = setTimeout(() => fail(new Error('WebView timed out')), 20000);
    ws.addEventListener('message', ({ data }) => {
      const r = JSON.parse(data);
      if (r.id !== 1) return;
      clearTimeout(timer);
      if (r.error || r.result?.exceptionDetails) fail(new Error(JSON.stringify(r.error || r.result.exceptionDetails)));
      else ok(r.result?.result?.value);
    });
    ws.send(JSON.stringify({ id: 1, method: 'Runtime.evaluate', params: { expression, awaitPromise: true, returnByValue: true } }));
  });
  ws.close();
  return result;
}

if (action === 'status') {
  console.log(adb('shell', 'getprop', 'sys.boot_completed').toString().trim());
} else if (action === 'stabilize') {
  adb('shell', 'wm', 'size', '720x1600');
  adb('shell', 'wm', 'density', '280');
  for (const setting of ['window_animation_scale','transition_animation_scale','animator_duration_scale']) adb('shell','settings','put','global',setting,'0');
  console.log('Review emulator display: 720x1600, animations disabled');
} else if (action === 'launch') {
  console.log(adb('shell', 'am', 'start', '-n', `${pkg}/.MainActivity`).toString());
} else if (action === 'app-settings') {
  console.log(adb('shell','am','start','-a','android.settings.APPLICATION_DETAILS_SETTINGS','-d',`package:${pkg}`).toString());
} else if (action === 'plugin-status') {
  console.log(await cdp(`(async()=>({background:await Capacitor.Plugins.BackgroundLocation.check(),location:await Capacitor.Plugins.Geolocation.checkPermissions(),notifications:await Capacitor.Plugins.LocalNotifications.checkPermissions()}))()`));
} else if (action === 'billing-check') {
  console.log(await cdp(`(async()=>{
    const cfg=window.__NUXT__.config.public;
    const account=JSON.parse((await Capacitor.Plugins.Preferences.get({key:'vb_account'})).value);
    if(!cfg.revenuecatAndroidKey?.startsWith('goog_'))throw Error('Google SDK key missing');
    await Capacitor.Plugins.Purchases.configure({apiKey:cfg.revenuecatAndroidKey,appUserID:String(account.id)});
    try{const r=await Capacitor.Plugins.Purchases.getOfferings();return {sdkConfigured:true,current:r.current?.identifier,monthly:r.current?.monthly?.product?.identifier,price:r.current?.monthly?.product?.priceString};}
    catch(e){return {sdkConfigured:true,code:e.code,message:e.message};}
  })()`));
} else if (action === 'notification-permission') {
  console.log(await cdp(`Capacitor.Plugins.LocalNotifications.requestPermissions()`));
} else if (action === 'seed-demo') {
  console.log(await cdp(`(async()=>{
    const send=(event,details)=>Capacitor.Plugins.CapacitorBackgroundRunner.dispatchEvent({label:'nl.veiligerbuiten.app.check',event,details});
    const account=JSON.parse((await Capacitor.Plugins.Preferences.get({key:'vb_account'})).value);
    const token=JSON.parse((await Capacitor.Plugins.Preferences.get({key:'vb_auth_token'})).value);
    if(account.email!=='appdemo@mail.com')throw Error('Use only the demo account');
    await send('configure',{});
    await send('configure',{apiBase:window.__NUXT__.config.public.apiBase,token,validUntil:account.subscription.expires_at,locale:'en',interval:15,minLevel:'danger',categories:['unsafe'],quiet:{enabled:false},places:[],strings:{near:'Nearby · {d}',offlineNear:'Saved earlier · {d}'}});
    const result=await send('sync',{at:Date.now(),center:{latitude:52.35,longitude:4.9},items:[{id:990028,title:'DEMO: Road hazard nearby',level:'danger',category:'unsafe',latitude:52.3687,longitude:4.8785,alertRadius:2000,confirmations:1,official:false,expiresAt:new Date(Date.now()+3600000).toISOString(),status:'actief'}],hotspots:[]});
    return result;
  })()`));
} else if (action === 'background-check') {
  console.log(await cdp(`Capacitor.Plugins.BackgroundRunner.dispatchEvent({label:'nl.veiligerbuiten.app.check',event:'checkIn',details:{force:true}})`));
} else if (action === 'capture') {
  if (!/^[a-z0-9-]+$/.test(args[0] || '')) throw new Error('Invalid frame name');
  mkdirSync(resolve(out, 'frames'), { recursive: true });
  const path = resolve(out, 'frames', `${args[0]}.png`);
  writeFileSync(path, adb('exec-out', 'screencap', '-p'));
  console.log(path);
} else if (action === 'ui') {
  adb('shell', 'uiautomator', 'dump', '/sdcard/veiligerbuiten-review-window.xml');
  const xml = adb('shell', 'cat', '/sdcard/veiligerbuiten-review-window.xml').toString();
  console.log([...xml.matchAll(/<node\s+([^>]+)/g)].map(m => Object.fromEntries([...m[1].matchAll(/([\w-]+)="([^"]*)"/g)].map(a=>[a[1],a[2]]))).filter(n=>n.text || n['content-desc']).map(n=>({text:n.text,label:n['content-desc'],bounds:n.bounds,checked:n.checked}))); 
} else if (action === 'native-click') {
  adb('shell', 'uiautomator', 'dump', '/sdcard/veiligerbuiten-review-window.xml');
  const xml = adb('shell', 'cat', '/sdcard/veiligerbuiten-review-window.xml').toString();
  const node = [...xml.matchAll(/<node\s+([^>]+)/g)].map(m=>Object.fromEntries([...m[1].matchAll(/([\w-]+)="([^"]*)"/g)].map(a=>[a[1],a[2]]))).find(n=>n.text===args[0] || n['content-desc']===args[0]);
  const b = node?.bounds?.match(/\d+/g)?.map(Number);
  if (!b) throw new Error('Android control not found: '+args[0]);
  adb('shell','input','tap',String(Math.round((b[0]+b[2])/2)),String(Math.round((b[1]+b[3])/2)));
  console.log(args[0]);
} else if (action === 'tap') {
  if (args.length !== 2 || !args.every(x => /^\d{1,4}$/.test(x))) throw new Error('Invalid tap');
  adb('shell', 'input', 'tap', ...args);
} else if (action === 'key') {
  if (!/^\d{1,3}$/.test(args[0] || '')) throw new Error('Invalid key');
  adb('shell', 'input', 'keyevent', args[0]);
} else if (action === 'location') {
  const [lon, lat] = args.map(Number);
  if (!Number.isFinite(lon) || !Number.isFinite(lat) || Math.abs(lon) > 180 || Math.abs(lat) > 90) throw new Error('Invalid position');
  console.log(adb('emu', 'geo', 'fix', String(lon), String(lat)).toString());
} else if (action === 'web-state') {
  console.log(JSON.stringify(await cdp(`({url:location.href,text:document.body.innerText,inputs:[...document.querySelectorAll('input')].map(el=>({type:el.type,placeholder:el.placeholder,autocomplete:el.autocomplete})),buttons:[...document.querySelectorAll('button')].map(el=>({text:el.innerText,label:el.getAttribute('aria-label'),disabled:el.disabled}))})`), null, 2));
} else if (action === 'layout') {
  console.log(await cdp(`({html:document.querySelector('#__nuxt')?.innerHTML.slice(0,550),body:document.body.children.length,classes:[...document.querySelectorAll('div')].slice(0,12).map(e=>e.className),url:location.href})`));
  console.log(JSON.stringify(await cdp(`({viewport:{w:innerWidth,h:innerHeight},nodes:[...document.querySelectorAll('.screen,.vb-app,.sheet')].map(el=>({tag:el.tagName,class:el.className,rect:el.getBoundingClientRect().toJSON(),css:{top:getComputedStyle(el).top,bottom:getComputedStyle(el).bottom,height:getComputedStyle(el).height,minHeight:getComputedStyle(el).minHeight,tabbar:getComputedStyle(el).getPropertyValue('--tabbar-h')},parents:[...function*(e){for(let p=e.parentElement;p;p=p.parentElement)yield p;}(el)].map(p=>({tag:p.tagName,class:p.className,position:getComputedStyle(p).position,rect:p.getBoundingClientRect().toJSON()}))}))})`),null,2));
  console.log(await cdp(`[...document.querySelectorAll('.sheet,.screen,.sheet-backdrop')].map(el=>({class:el.className,text:el.innerText.slice(0,800),rect:el.getBoundingClientRect().toJSON(),scroll:el.scrollTop,height:el.scrollHeight,css:{position:getComputedStyle(el).position,transform:getComputedStyle(el).transform,zIndex:getComputedStyle(el).zIndex,display:getComputedStyle(el).display},parents:[...function*(e){for(let p=e.parentElement;p;p=p.parentElement)yield p;}(el)].map(p=>({tag:p.tagName,class:p.className,position:getComputedStyle(p).position,transform:getComputedStyle(p).transform}))}))`));
} else if (action === 'web-click') {
  console.log(await cdp(`(()=>{const text=${JSON.stringify(args[0])};const b=[...document.querySelectorAll('button')].find(el=>!el.disabled&&(el.innerText.trim()===text||el.getAttribute('aria-label')===text));if(!b)throw Error('Button not found: '+text);b.click();return text;})()`));
} else if (action === 'web-scroll') {
  console.log(await cdp(`(()=>{const text=${JSON.stringify(args[0])};const b=[...document.querySelectorAll('button')].find(el=>el.innerText.trim()===text||el.getAttribute('aria-label')===text);if(!b)throw Error('Button not found');b.scrollIntoView({block:'center'});return text;})()`));
} else if (action === 'login') {
  const email = process.env.VB_REVIEW_EMAIL;
  const password = process.env.VB_REVIEW_PASSWORD;
  if (!email || !password) throw new Error('Set VB_REVIEW_EMAIL and VB_REVIEW_PASSWORD');
  console.log(await cdp(`(()=>{const values={email:${JSON.stringify(email)},password:${JSON.stringify(password)}};for(const type of ['email','password']){const el=document.querySelector('input[type="'+type+'"]');if(!el)throw Error('Login form missing');el.value=values[type];el.dispatchEvent(new Event('input',{bubbles:true}));}document.querySelector('form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));return 'Login submitted';})()`));
} else if (action === 'jobs') {
  const lines = adb('shell', 'dumpsys', 'jobscheduler').toString().split('\n');
  const selected = new Set();
  lines.forEach((l, i) => { if (l.includes(pkg)) for (let j = Math.max(0, i - 2); j < Math.min(lines.length, i + 10); j++) selected.add(j); });
  console.log([...selected].sort((a,b)=>a-b).map(i=>lines[i]).join('\n'));
} else if (action === 'runner-state') {
  const xml=adb('shell','run-as',pkg,'cat',`shared_prefs/${pkg}.check.xml`).toString();
  const decode=s=>s.replace(/&quot;/g,'"').replace(/&apos;/g,"'").replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&amp;/g,'&');
  const state=Object.fromEntries([...xml.matchAll(/<string name="([^"]+)">([\s\S]*?)<\/string>/g)].map(m=>{let v=decode(m[2]);try{v=JSON.parse(v);}catch{}return [m[1],v];}));
  console.log(JSON.stringify({config:state.config?{tokenPresent:!!state.config.token,apiBase:state.config.apiBase,validUntil:state.config.validUntil,minLevel:state.config.minLevel,categories:state.config.categories}:null,lastPosition:state.lastPosition,lastCheck:state.lastCheck,notified:state.notified,cache:state.cache?{at:state.cache.at,center:state.cache.center,count:state.cache.items?.length,items:state.cache.items?.filter(i=>i.id===990028)}:null},null,2));
} else if (action === 'run-job') {
  if (!/^\d+$/.test(args[0] || '')) throw new Error('Invalid job id');
  console.log(adb('shell', 'cmd', 'jobscheduler', 'run', '-f', pkg, args[0]).toString());
} else {
  throw new Error('Unknown review command');
}
