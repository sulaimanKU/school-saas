// Reproducible local browser checks without adding a browser automation dependency.
import { spawn } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
const base = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8014';
const artifacts = path.resolve('storage/app/private/finance-qa');
await mkdir(artifacts, { recursive: true });
const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=9227', `--user-data-dir=${path.resolve('storage/framework/testing/finance-chrome')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
try {
    let target;
    for (let i=0;i<50;i++) {
        try { target = await (await fetch('http://127.0.0.1:9227/json/new?about:blank', {method:'PUT'})).json(); break; } catch { await pause(200); }
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
    const root='/preview/schools/demo-maplewood/finance';
    for(const page of ['dashboard','categories','structures','assignments','generate','invoices','payments','discounts','late-fees','refunds','expenses','expense-categories','defaulters','statements','reports','audit']){await navigate(root+'/'+page);await check(`document.querySelector('h1').textContent.length>0`,'page renders: '+page);}
    async function setValue(selector,value){await evaluate(`(()=>{const el=document.querySelector(${JSON.stringify(selector)});if(!el)throw new Error('Missing '+${JSON.stringify(selector)});if(el.type==='checkbox')el.checked=${JSON.stringify(value)};else el.value=${JSON.stringify(String(value))};el.dispatchEvent(new Event(el.tagName==='SELECT'||el.type==='checkbox'?'change':'input',{bubbles:true}));})()`);}
    async function option(selector,label){const value=await evaluate(`Array.from(document.querySelector(${JSON.stringify(selector)}).options).find(o=>o.textContent.includes(${JSON.stringify(label)}))?.value`);if(!value)throw new Error('Missing option '+label);await setValue(selector,value);return value;}
    async function submit(){await evaluate(`document.querySelector('.finance-form form').requestSubmit()`);await waitFor(`!document.querySelector('.finance-form')||!!document.querySelector('.finance-form .error-summary')`);if(await evaluate(`!!document.querySelector('.finance-form .error-summary')`))throw new Error(await evaluate(`document.querySelector('.finance-form .error-summary').textContent`));}
    async function open(page){await navigate(root+'/'+page);await evaluate(`document.querySelector('.page-heading .academic-button').click()`);await waitFor(`!!document.querySelector('.finance-form')`);}
    const suffix=String(Date.now()).slice(-7), name='QA Finance Tuition '+suffix;
    await open('categories');await setValue('#fin-name',name);await submit();await check(`document.body.textContent.includes(${JSON.stringify(name)})`,'category created with browser CSRF');
    await open('structures');await setValue('#fin-name',name);const session=await option('#fin-academic_session_id','Demo Academic');await option('#fin-academic_class_id','Grade 7');await option('#fin-fee_category_id',name);await setValue('#fin-amount','1000.00');await setValue('#fin-effective_from','2026-08-01');await setValue('#fin-effective_to','2027-07-31');await submit();
    await open('generate');await setValue('#fin-academic_session_id',session);await option('#fin-academic_class_id','Grade 7');await setValue('#fin-period','2026-11-01');await setValue('#fin-issued_on','2026-11-01');await setValue('#fin-due_on','2026-11-10');await submit();await check(`document.querySelector('tbody tr')?.textContent.includes('INV-')`,'automatic fee assignment and invoice generation');await shot('invoices');
    await open('payments');const student=await evaluate(`document.querySelector('#fin-student_id option[value]:not([value=""])').value`);await setValue('#fin-student_id',student);await setValue('#fin-amount','400.00');await setValue('#fin-paid_on','2026-11-04');await evaluate(`Array.from(document.querySelectorAll('.finance-allocations button')).find(b=>b.textContent.includes('Add allocation')).click()`);await waitFor(`!!document.querySelector('.finance-allocation select')`);const inv=await evaluate(`document.querySelector('.finance-allocation select option[value]:not([value=""])').value`);await setValue('.finance-allocation select',inv);await setValue('.finance-allocation input','400.00');await submit();await waitFor(`document.querySelector('tbody')?.textContent.includes('400')`);await check(`document.querySelector('tbody').textContent.includes('400')`,'manual payment and allocation posted');
    const receipt=await evaluate(`Array.from(document.querySelectorAll('tbody a')).find(a=>a.textContent.includes('Receipt')).getAttribute('href')`);await navigate(receipt);await check(`document.querySelector('.finance-document').textContent.includes('400.00')`,'historical receipt renders');await shot('receipt');const pdf=await send('Page.printToPDF',{printBackground:true,preferCSSPageSize:true});await writeFile(path.join(artifacts,'receipt-a4.pdf'),Buffer.from(pdf.data,'base64'));
    for(const page of ['dashboard','payments','reports','structures']){await navigate(root+'/'+page);if(page==='structures'){await evaluate(`document.querySelector('.page-heading .academic-button').click()`);await waitFor(`!!document.querySelector('.finance-form')`);}for(const width of [375,768,1024,1440]){await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<768});await pause(100);await check(`document.documentElement.scrollWidth<=innerWidth+1`,page+' no overflow '+width);await shot(page+'-'+width);}}
    await navigate(root+'/reports');await setValue('#report-type','payments');await setValue('#filter-from','2026-11-01');await evaluate(`document.querySelector('.finance-filters').requestSubmit()`);await waitFor(`location.search.includes('report_type=payments')&&document.querySelector('tbody')?.textContent.includes('Manually recorded')`);await check(`document.querySelector('tbody').textContent.includes('400.00')`,'report filters submit and return recorded payments');
    await navigate('/preview/schools/demo-maplewood/dashboard');await check(`!!document.querySelector('.finance-live')`,'school dashboard uses live finance');await shot('school-dashboard');
    await evaluate(`document.documentElement.classList.add('dark')`);await send('Emulation.setDeviceMetricsOverride',{width:375,height:1000,deviceScaleFactor:1,mobile:true});await check(`document.documentElement.scrollWidth<=innerWidth+1`,'dark mobile dashboard no overflow');await shot('dark-mobile');
    if(errors.length)throw new Error('Browser errors: '+errors.join('\n'));console.log('PASS no runtime errors or console warnings');console.log('Artifacts: '+artifacts);
} finally {if(socket?.readyState===WebSocket.OPEN){socket.send(JSON.stringify({id:99999,method:'Browser.close'}));socket.close();}chrome.kill();}
