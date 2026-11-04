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

    for (const width of [1440,1024,768,390,360]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:width<600});
        await navigate('/preview/schools/demo-maplewood/dashboard');
        await check(`document.querySelector('.welcome-banner') && document.querySelectorAll('.stat-card').length===4`,'welcome and summary cards '+width);
        await check(`document.documentElement.scrollWidth<=innerWidth`,'no page overflow '+width);
        await check(`document.querySelectorAll('.app-nav-item svg[data-icon]').length>15`,'SVG navigation icons '+width);
        await shot('redesign-light-'+width);
        await evaluate(`document.querySelector('.theme-toggle').click()`);
        await check(`document.documentElement.classList.contains('dark')`,'dark theme '+width);
        await shot('redesign-dark-'+width);
        await evaluate(`document.querySelector('.theme-toggle').click()`);
        if(width===390){
            await evaluate(`document.querySelector('.mobile-toggle').click()`);
            await check(`document.querySelector('.app-sidebar').classList.contains('is-open')`,'mobile menu opens');
            await evaluate(`document.querySelector('.sidebar-overlay').click()`);
            await check(`!document.querySelector('.app-sidebar').classList.contains('is-open')`,'mobile menu closes');
        }
    }
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    for(const url of ['/preview','/preview/schools/demo-maplewood/students','/preview/schools/demo-maplewood/finance','/preview/settings']) {
        await navigate(url);
        await check(`document.documentElement.scrollWidth<=innerWidth`,'page layout '+url);
        await shot('redesign-'+url.split('/').at(-1));
    }
    await evaluate(`document.querySelector('.theme-toggle').click()`);
    await navigate('/preview');
    await check(`document.documentElement.classList.contains('dark') && localStorage.getItem('schoolspace-theme')==='dark'`,'theme persists across navigation');
    await evaluate(`document.querySelector('.theme-toggle').click()`);
    if(errors.length) throw new Error('Browser errors: '+errors.join('\n'));
    console.log('PASS no browser runtime errors or warnings');
} finally {
    if(socket?.readyState===WebSocket.OPEN){socket.send(JSON.stringify({id:99999,method:'Browser.close'}));socket.close();}
    chrome.kill();
}
