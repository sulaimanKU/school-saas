// Reproducible local browser checks without adding a browser automation dependency.
import { spawn } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
const base = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8000';
const artifacts = path.resolve('storage/app/private/dashboard-qa');
await mkdir(artifacts, { recursive: true });
const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=9223', `--user-data-dir=${path.resolve('storage/framework/testing/dashboard-chrome')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
try {
    let target;
    for (let i=0;i<50;i++) {
        try { target = await (await fetch('http://127.0.0.1:9223/json/new?about:blank', {method:'PUT'})).json(); break; } catch { await pause(200); }
    }
    if (!target) throw new Error('Chrome debugging endpoint did not start');
    socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
    let next=0; const pending=new Map(); const errors=[];
    socket.addEventListener('message', event=>{
        const value=JSON.parse(event.data);
        if(value.id){const handler=pending.get(value.id);pending.delete(value.id);value.error?handler.reject(new Error(JSON.stringify(value.error))):handler.resolve(value.result);}
        if(value.method==='Runtime.exceptionThrown') errors.push(value.params.exceptionDetails.text+': '+value.params.exceptionDetails.exception?.description);
        if(value.method==='Runtime.consoleAPICalled' && ['error','warning'].includes(value.params.type)) errors.push(value.params.args.map(a=>a.value ?? a.description).join(' '));
    });
    function send(method,params={}) {const id=++next;return new Promise((resolve,reject)=>{pending.set(id,{resolve,reject});socket.send(JSON.stringify({id,method,params}));});}
    async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(JSON.stringify(r.exceptionDetails));return r.result.value;}
    async function waitFor(expression){for(let i=0;i<60;i++){if(await evaluate(expression))return;await pause(100);}throw new Error('Condition timed out: '+expression);}
    async function navigate(url){await send('Page.navigate',{url:base+url});await waitFor(`document.querySelector('.dashboard-app') && !document.querySelector('[aria-busy="true"]')`);await pause(200);}
    async function shot(name){const {data}=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});await writeFile(path.join(artifacts,name+'.png'),Buffer.from(data,'base64'));}
    async function check(condition,label){if(!await evaluate(condition))throw new Error('Failed: '+label);console.log('PASS '+label);}
    await send('Runtime.enable');await send('Page.enable');
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1050,deviceScaleFactor:1,mobile:false});
    await navigate('/preview/schools/demo-maplewood/dashboard');
    await check(`document.querySelectorAll('.stat-card').length===4 && document.querySelectorAll('.dashboard-card').length===9`,'dashboard cards render');
    await check(`document.documentElement.scrollWidth<=innerWidth`,'desktop has no horizontal overflow');
    await shot('desktop');
    await evaluate(`document.querySelector('.global-search input').value='Crescent';document.querySelector('.global-search input').dispatchEvent(new Event('input',{bubbles:true}));document.querySelector('.global-search input').focus()`);
    await waitFor(`document.querySelectorAll('.search-results a').length===1`);
    await check(`document.querySelector('.search-results a').textContent.includes('Crescent')`,'local search returns matching school');
    await evaluate(`document.querySelector('.search-results a').click()`);
    await waitFor(`location.pathname.includes('demo-crescent') && document.querySelector('.stat-card strong')?.textContent==='1,420'`);
    console.log('PASS search navigation changes selected school data');
    await evaluate(`const s=document.querySelector('#school-switcher');s.value='demo-oakridge';s.dispatchEvent(new Event('change',{bubbles:true}))`);
    await waitFor(`location.pathname.includes('demo-oakridge') && document.querySelector('.stat-card strong')?.textContent==='280'`);
    console.log('PASS school selector updates dashboard');
    await evaluate(`document.querySelector('.compact-select').value='3';document.querySelector('.compact-select').dispatchEvent(new Event('change',{bubbles:true}))`);
    await check(`document.querySelector('.finance-chart svg').getAttribute('aria-label').includes('Sep to Oct to Nov')`,'finance chart period updates');
    await evaluate(`document.querySelector('.notification-button').click()`);
    await check(`document.querySelectorAll('.notifications-popover a').length===3`,'sample notifications open');
    await evaluate(`document.querySelector('[aria-label="Close notifications"]').click();document.querySelector('.theme-toggle').click()`);
    await check(`document.documentElement.classList.contains('dark')`,'dark theme toggle works');
    await shot('dark');
    await evaluate(`document.querySelector('.theme-toggle').click()`);
    await navigate('/preview/schools/demo-maplewood/dashboard');
    const links=await evaluate(`Array.from(new Set(Array.from(document.querySelectorAll('a[href^="/preview"]')).map(a=>new URL(a.href).pathname)))`);
    for(const href of links){const result=await fetch(base+href);if(result.status!==200)throw new Error(href+' returned '+result.status);}
    console.log('PASS all rendered navigation links return HTTP 200 ('+links.length+')');
    await navigate('/preview');await check(`document.querySelector('.growth-panel')!==null && document.querySelector('.school-stats')===null`,'platform dashboard remains separate');
    await navigate('/preview/settings');await check(`document.querySelector('.appearance')!==null`,'system settings render');
    await evaluate(`document.querySelector('.appearance button').click()`);await check(`document.documentElement.classList.contains('dark')`,'settings theme control works');await evaluate(`document.querySelector('.appearance button').click()`);
    await navigate('/preview/schools/demo-maplewood/classes');await check(`document.querySelector('.module-placeholder').textContent.includes('placeholder')`,'new modules are labeled placeholders');
    for(const width of [1024,768,390,360]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
        await navigate('/preview/schools/demo-maplewood/dashboard');
        await check(`document.documentElement.scrollWidth<=innerWidth`,'no horizontal overflow at '+width+'px');
        if(width===390){await shot('mobile');await evaluate(`document.querySelector('.mobile-toggle').click()`);await check(`document.querySelector('.app-sidebar').classList.contains('is-open')`,'mobile menu opens');await evaluate(`document.querySelector('.sidebar-overlay').click()`);await check(`!document.querySelector('.app-sidebar').classList.contains('is-open')`,'mobile menu closes');}
    }
    if(errors.length)throw new Error('Browser errors: '+errors.join('\n'));
    console.log('PASS no browser runtime errors or console warnings');
    console.log('Screenshots: '+artifacts);
} finally {
    if(socket?.readyState===WebSocket.OPEN){socket.send(JSON.stringify({id:99999,method:'Browser.close'}));socket.close();}
    chrome.kill();
}
