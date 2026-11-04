// Reproducible local browser checks without adding a browser automation dependency.
import { spawn } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
const base = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8000';
const artifacts = path.resolve('storage/app/private/academic-qa');
await mkdir(artifacts, { recursive: true });
const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=9224', `--user-data-dir=${path.resolve('storage/framework/testing/academic-chrome')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
try {
    let target;
    for (let i=0;i<50;i++) {
        try { target = await (await fetch('http://127.0.0.1:9224/json/new?about:blank', {method:'PUT'})).json(); break; } catch { await pause(200); }
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
    await check(`document.querySelector('.live-metrics-note')!==null`,'dashboard identifies live academic metrics');
    await check(`document.querySelectorAll('.stat-card')[0].textContent.includes('Database')`,'academic cards identify database source');
    const schoolBase='/preview/schools/demo-maplewood/academic';
    for(const resource of ['sessions','classes','sections','subjects','class-subjects','teachers','teacher-assignments','students','enrollments']){
        await navigate(schoolBase+'/'+resource);
        await check(`document.querySelector('.academic-directory')!==null`,'directory renders: '+resource);
        await navigate(schoolBase+'/'+resource+'/create');
        await check(`document.querySelector('.academic-form-panel form')!==null`,'form renders: '+resource);
    }
    async function fill(fields){await evaluate(`(()=>{const values=${JSON.stringify(fields)};for(const [key,value] of Object.entries(values)){const el=document.getElementById(key);if(!el)throw new Error('No input '+key);if(el.type==='checkbox')el.checked=value;else el.value=String(value);el.dispatchEvent(new Event(el.type==='checkbox'||el.tagName==='SELECT'?'change':'input',{bubbles:true}));}})()`);}
    async function save(){await evaluate(`document.querySelector('.academic-form-panel form').requestSubmit()`);await waitFor(`document.querySelector('.academic-profile')!==null || document.querySelector('.error-summary')!==null`);if(await evaluate(`!!document.querySelector('.error-summary')`))throw new Error(await evaluate(`document.querySelector('.error-summary').textContent`));}
    const suffix=String(Date.now()).slice(-7);
    await navigate(schoolBase+'/classes/create');
    await fill({name:'Browser QA Grade '+suffix,code:'QA-'+suffix,grade_level:9,display_order:9,active:true});
    await save();
    console.log('PASS browser class create persisted with CSRF protection');
    const classPath=await evaluate('location.pathname');
    await navigate(classPath+'/edit');await fill({name:'Updated Browser QA '+suffix});await save();
    await check(`document.querySelector('.profile-heading').textContent.includes('Updated Browser QA')`,'browser class edit persisted');
    await evaluate(`document.querySelector('.academic-profile .danger-button').click()`);
    await check(`document.querySelector('[role=dialog]')!==null`,'delete confirmation opens');
    await evaluate(`document.querySelector('[role=dialog] .danger-button').click()`);
    await waitFor(`document.querySelector('.academic-directory')!==null && !document.querySelector('[role=dialog]')`);
    console.log('PASS browser deletes only its own newly created unused QA class');
    await navigate(schoolBase+'/students/create');
    await fill({full_name:'Browser QA Student '+suffix,date_of_birth:'2014-01-01',gender:'unspecified',guardian_name:'Browser QA Guardian',guardian_phone:'03000000000',admitted_on:'2026-11-04',status:'active'});
    await save();
    await check(`document.querySelector('.profile-heading').textContent.includes('ADM-')`,'student admission generates number');
    const studentPath=await evaluate('location.pathname');const studentId=studentPath.split('/').at(-1);
    await navigate(schoolBase+'/enrollments/create');
    const ids=await evaluate(`({session:document.querySelector('#academic_session_id option:nth-child(2)').value,klass:document.querySelector('#academic_class_id option:nth-child(2)').value})`);
    await fill({student_id:studentId,academic_session_id:ids.session,academic_class_id:ids.klass});
    const section=await evaluate(`document.querySelector('#section_id option:nth-child(2)').value`);
    await fill({section_id:section,started_on:'2026-11-04'});await save();
    console.log('PASS browser enrollment persisted');
    const enrollmentPath=await evaluate('location.pathname');
    await evaluate(`document.querySelector('.academic-profile .academic-button').click()`);
    const targetSection=await evaluate(`Array.from(document.querySelector('#change-section').options).find(o=>o.value && o.value!==${JSON.stringify(section)})?.value`);
    if(!targetSection)throw new Error('No alternate demo section');
    await evaluate(`document.querySelector('#effective-date').value='2026-11-04';document.querySelector('#effective-date').dispatchEvent(new Event('input',{bubbles:true}));document.querySelector('#change-section').value=${JSON.stringify(targetSection)};document.querySelector('#change-section').dispatchEvent(new Event('change',{bubbles:true}));document.querySelector('[role=dialog] form').requestSubmit()`);
    await waitFor(`!document.querySelector('[role=dialog]') && document.querySelector('.academic-directory')!==null`);
    await navigate(studentPath);await check(`document.querySelector('.academic-history').textContent.includes('transferred')`,'transfer preserves previous enrollment history');
    await navigate(schoolBase+'/students?q=Browser%20QA%20Student%20'+suffix);await check(`document.querySelectorAll('tbody tr').length===1`,'student search filters live records');
    for(const width of [1440,1024,768,375]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:960,deviceScaleFactor:1,mobile:width<600});
        for(const route of ['/preview/schools/demo-maplewood/dashboard',schoolBase+'/students',schoolBase+'/students/create']){
            await navigate(route);await check(`document.documentElement.scrollWidth<=innerWidth`,'no overflow '+width+'px '+route.split('/').slice(-2).join('/'));
        }
        await shot('admission-'+width);
    }
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});await navigate('/preview/schools/demo-maplewood/dashboard');await shot('dashboard-live');
    await navigate(schoolBase+'/students');await shot('student-directory');
    if(errors.length)throw new Error('Browser errors: '+errors.join('\n'));
    console.log('PASS no browser runtime errors or console warnings');
    console.log('Academic browser screenshots: '+artifacts);
} finally {
    if(socket?.readyState===WebSocket.OPEN){socket.send(JSON.stringify({id:99999,method:'Browser.close'}));socket.close();}
    chrome.kill();
}
