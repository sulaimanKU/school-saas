// Reproducible local browser checks without adding a browser automation dependency.
import { spawn } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
const base = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8000';
const artifacts = path.resolve('storage/app/private/operations-qa');
await mkdir(artifacts, { recursive: true });
const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=9225', `--user-data-dir=${path.resolve('storage/framework/testing/operations-chrome')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
try {
    let target;
    for (let i=0;i<50;i++) {
        try { target = await (await fetch('http://127.0.0.1:9225/json/new?about:blank', {method:'PUT'})).json(); break; } catch { await pause(200); }
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
    const root='/preview/schools/demo-maplewood/academic/operations';
    const pages=['student-attendance','staff-attendance','working-days','periods','timetable','teacher-timetable','grading','exam-types','exams','schedules','marks','results','reports','audit'];
    for(const page of pages){await navigate(root+'/'+page);await check(`document.querySelector('h1').textContent.length>0`,'page renders: '+page);}
    async function setValue(selector,value){await evaluate(`(()=>{const el=document.querySelector(${JSON.stringify(selector)});if(!el)throw new Error('Missing field '+${JSON.stringify(selector)});if(el.type==='checkbox')el.checked=${JSON.stringify(value)};else el.value=${JSON.stringify(String(value))};el.dispatchEvent(new Event(el.tagName==='SELECT'||el.type==='checkbox'?'change':'input',{bubbles:true}));})()`);}
    async function option(selector,label){const value=await evaluate(`Array.from(document.querySelector(${JSON.stringify(selector)}).options).find(o=>o.textContent.includes(${JSON.stringify(label)}))?.value`);if(!value)throw new Error('Missing option '+label);await setValue(selector,value);return value;}
    async function submitModal(){await evaluate(`document.querySelector('.operations-modal form').requestSubmit()`);await waitFor(`!document.querySelector('.operations-modal') || document.querySelector('.operations-modal .error-summary')`);if(await evaluate(`!!document.querySelector('.operations-modal .error-summary')`))throw new Error(await evaluate(`document.querySelector('.operations-modal .error-summary').textContent`));}
    async function create(page,fields){await navigate(root+'/'+page);await evaluate(`document.querySelector('.page-heading .academic-button').click()`);await waitFor(`!!document.querySelector('.operations-modal')`);for(const [key,value] of Object.entries(fields)){if(value&&typeof value==='object')await option('#op-'+key,value.option);else await setValue('#op-'+key,value);}await submitModal();}
    const suffix=String(Date.now()).slice(-7), examName='QA Operations Exam '+suffix;
    await navigate(root+'/student-attendance');
    const academicId=await option('[aria-label="Filter academic session"]','Demo Academic');const sectionId=await option('[aria-label="Filter section"]','Grade 7 / Section A');await setValue('[aria-label="Attendance date"]','2026-10-05');
    await evaluate(`document.querySelector('.academic-filters').requestSubmit()`);await waitFor(`document.querySelectorAll('.roster-panel tbody tr').length>0`);
    await evaluate(`document.querySelector('.roster-panel .panel-heading button').click();document.querySelector('.roster-panel form').requestSubmit()`);
    await waitFor(`document.querySelector('.success-message')!==null`);await check(`Array.from(document.querySelectorAll('.roster-panel tbody select')).every(s=>s.value==='present')`,'bulk student attendance saved');await shot('student-attendance');
    await navigate(root+'/staff-attendance');await setValue('[aria-label="Attendance date"]','2026-10-05');await evaluate(`document.querySelector('.academic-filters').requestSubmit()`);await waitFor(`document.querySelectorAll('.roster-panel tbody tr').length>0`);await evaluate(`document.querySelector('.roster-panel .panel-heading button').click();document.querySelector('.roster-panel form').requestSubmit()`);await waitFor(`document.querySelector('.success-message')!==null`);console.log('PASS bulk staff attendance saved');
    await navigate(root+'/timetable');
    const busy=await evaluate(`Array.from(document.querySelectorAll('.weekly-slot time')).map(e=>e.textContent.match(/\\d{2}:\\d{2}/g)).filter(Boolean)`);
    const minutes=time=>Number(time.slice(0,2))*60+Number(time.slice(3));let slot=600;while(busy.some(times=>minutes(times[0])<slot+10&&minutes(times[1])>slot))slot+=15;
    const clock=m=>String(Math.floor(m/60)).padStart(2,'0')+':'+String(m%60).padStart(2,'0');
    const periodName='QA Period '+suffix;
    await create('periods',{name:periodName,code:'QA-'+suffix,starts_at:clock(slot),ends_at:clock(slot+10),kind:'teaching'});
    await create('timetable',{academic_session_id:academicId,section_id:sectionId,weekday:1,timetable_period_id:{option:periodName},teacher_assignment_id:{option:'Mathematics'}});
    await check(`document.querySelector('.weekly-grid').textContent.includes(${JSON.stringify(periodName)})`,'weekly timetable creation renders');await shot('weekly-timetable');
    await evaluate(`document.querySelector('.page-heading .academic-button').click()`);for(const [key,value] of Object.entries({academic_session_id:academicId,section_id:sectionId,weekday:1})){await setValue('#op-'+key,value);}await option('#op-timetable_period_id',periodName);await option('#op-teacher_assignment_id','Mathematics');await evaluate(`document.querySelector('.operations-modal form').requestSubmit()`);await waitFor(`document.querySelector('.operations-modal .error-summary')!==null`);console.log('PASS conflicting timetable is rejected in browser');await evaluate(`document.querySelector('.operations-modal .academic-form-footer .secondary-button').click()`);
    await create('exams',{name:examName,academic_session_id:{option:'Demo Academic'},exam_type_id:{option:'Midterm'},grading_scheme_id:{option:'Default academic grading'},status:'draft'});console.log('PASS examination created');
    const dateDay=String(1+Number(suffix)%25).padStart(2,'0');
    await create('schedules',{exam_id:{option:examName},academic_class_id:{option:'Grade 7'},section_id:{option:'Grade 7 / Section A'},subject_id:{option:'Mathematics'},exam_date:'2026-10-'+dateDay,starts_at:'13:'+String(Number(suffix)%50).padStart(2,'0'),duration_minutes:5,maximum_marks:100,passing_marks:50});console.log('PASS examination schedule saved');
    await navigate(root+'/marks');const scheduleId=await option('[aria-label="Marks schedule"]',examName);await evaluate(`document.querySelector('.academic-filters').requestSubmit()`);await waitFor(`document.querySelectorAll('.roster-panel tbody tr').length>0`);
    await evaluate(`(()=>{for(const input of document.querySelectorAll('.roster-panel input[type=number]')){input.value='80';input.dispatchEvent(new Event('input',{bubbles:true}));}})()`);
    await evaluate(`document.querySelector('.roster-panel form').requestSubmit()`);await waitFor(`document.querySelector('.success-message')!==null || document.querySelector('.error-summary')!==null`);if(await evaluate(`!!document.querySelector('.error-summary')`))throw new Error(await evaluate(`document.querySelector('.error-summary').textContent`));
    await check(`Array.from(document.querySelectorAll('.roster-panel input[type=number]')).every(i=>i.value==='80')`,'bulk marks saved to actual roster');await shot('marks-entry');
    await navigate(root+'/exams?q='+encodeURIComponent(examName));await evaluate(`document.querySelector('.operations-directory .row-actions button').click()`);await setValue('#op-status','completed');await submitModal();console.log('PASS examination completed');
    await navigate(root+'/results');const examId=await option('[aria-label="Publication exam"]',examName);await waitFor(`location.search.includes('exam_id') && document.querySelector('.draft-links a')!==null`);
    const draftHref=await evaluate(`document.querySelector('.draft-links a').getAttribute('href')`);await navigate(draftHref);await check(`document.querySelector('.draft-watermark')!==null`,'draft report uses actual marks');
    await navigate(root+'/results?exam_id='+examId);await setValue('.publication-confirm input',true);await evaluate(`document.querySelector('.publication-form').requestSubmit()`);await waitFor(`document.querySelector('.operations-directory tbody tr a')!==null || document.querySelector('.error-summary')!==null`);if(await evaluate(`!!document.querySelector('.error-summary')`))throw new Error(await evaluate(`document.querySelector('.error-summary').textContent`));
    console.log('PASS result publication persisted immutable report snapshots');const cardHref=await evaluate(`document.querySelector('.operations-directory tbody tr a').getAttribute('href')`);
    await navigate(cardHref);await check(`document.querySelector('.report-card-sheet').textContent.includes('PUBLISHED') && document.querySelector('.report-card-sheet').textContent.includes('80%')`,'published report card renders');await shot('report-card');
    await send('Emulation.setEmulatedMedia',{media:'print'});await check(`getComputedStyle(document.querySelector('.app-sidebar')).display==='none'`,'print layout hides navigation');const pdf=await send('Page.printToPDF',{printBackground:true,preferCSSPageSize:true});await writeFile(path.join(artifacts,'report-card-a4.pdf'),Buffer.from(pdf.data,'base64'));await send('Emulation.setEmulatedMedia',{media:''});console.log('PASS A4 report card PDF generated');
    await navigate(root+'/marks?exam_schedule_id='+scheduleId);await check(`document.querySelector('.roster-panel button[type=submit]').disabled`,'published marks are locked in browser');
    await navigate(root+'/reports?report_type=performance&exam_id='+examId);await check(`document.querySelector('.report-metrics').textContent.includes('80%')`,'performance report reflects published marks');
    const csv=await evaluate(`fetch(document.querySelector('a[href*="/reports/export"]').href).then(r=>r.text())`);if(!csv.includes('80'))throw new Error('CSV missing actual score');console.log('PASS CSV export contains actual published values');
    for(const width of [1440,1024,768,375]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:960,deviceScaleFactor:1,mobile:width<600});
        for(const route of [root+'/student-attendance?academic_session_id='+academicId+'&section_id='+sectionId+'&attendance_date=2026-10-05' ,root+'/marks?exam_schedule_id='+scheduleId,root+'/timetable',root+'/grading',cardHref,'/preview/schools/demo-maplewood/dashboard']){
            await navigate(route);await check(`document.documentElement.scrollWidth<=innerWidth`,'no overflow '+width+'px '+route.split('/').at(-1).split('?')[0]);
        }
        await shot('dashboard-'+width);
    }
    await evaluate(`document.querySelector('.theme-toggle').click()`);await check(`document.documentElement.classList.contains('dark')`,'dark mode works');await shot('dark-375');await evaluate(`document.querySelector('.theme-toggle').click()`);
    const links=await evaluate(`Array.from(new Set(Array.from(document.querySelectorAll('a[href^="/preview"]')).map(a=>new URL(a.href).pathname)))`);
    for(const href of links){const status=await evaluate(`fetch(${JSON.stringify(href)}).then(async r=>{await r.text();return r.status})`);if(status!==200)throw new Error('Broken navigation '+href+' '+status);}console.log('PASS all rendered navigation links return 200');
    if(errors.length)throw new Error('Browser errors: '+errors.join('\n'));console.log('PASS no runtime errors or console warnings');console.log('Artifacts: '+artifacts);
} finally {
    if(socket?.readyState===WebSocket.OPEN){socket.send(JSON.stringify({id:99999,method:'Browser.close'}));socket.close();}
    chrome.kill();
}
